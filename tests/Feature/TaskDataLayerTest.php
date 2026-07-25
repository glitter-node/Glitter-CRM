<?php

namespace Modules\Glitter\Crm\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Glitter\Crm\Enums\TaskPriority;
use Modules\Glitter\Crm\Enums\TaskStatus;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Models\Task;
use Modules\Glitter\Crm\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Glitter\Crm\Services\TaskService;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario task-data-layer
 */
class TaskDataLayerTest extends ModuleTestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_task_schema_and_foreign_keys_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('crm_contacts', 'next_task_at'));
        $this->assertTrue(Schema::hasTable('crm_tasks'));
        $this->assertTrue(Schema::hasColumns('crm_tasks', [
            'id', 'uuid', 'contact_id', 'assigned_to', 'title', 'description',
            'status', 'priority', 'due_at', 'completed_at', 'metadata', 'deleted_at',
        ]));

        $indexes = collect(Schema::getIndexes('crm_tasks'));
        $this->assertTrue((bool) $indexes->first(
            fn (array $index) => $index['unique'] && $index['columns'] === ['uuid'],
        ));
        $this->assertTrue((bool) $indexes->first(
            fn (array $index) => $index['columns'] === ['contact_id', 'status', 'due_at'],
        ));
        $this->assertTrue((bool) $indexes->first(
            fn (array $index) => $index['columns'] === ['assigned_to', 'status', 'due_at'],
        ));

        $foreignKeys = collect(Schema::getForeignKeys('crm_tasks'));
        $this->assertTrue((bool) $foreignKeys->first(
            fn (array $foreign) => $foreign['columns'] === ['contact_id']
                && $foreign['foreign_table'] === 'crm_contacts'
                && strtolower($foreign['on_delete']) === 'cascade',
        ));
        $this->assertTrue((bool) $foreignKeys->first(
            fn (array $foreign) => $foreign['columns'] === ['assigned_to']
                && $foreign['foreign_table'] === 'users'
                && strtolower($foreign['on_delete']) === 'set null',
        ));
    }

    public function test_service_creates_defaults_and_resolves_public_uuids(): void
    {
        $contact = Contact::create(['name' => '태스크 고객']);
        $assignee = User::factory()->create();

        $task = $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'assigned_to_uuid' => $assignee->uuid,
            'title' => '  고객에게 전화하기  ',
            'description' => "  첫 줄\n둘째 줄  ",
            'due_at' => '2026-07-25T18:30:00+09:00',
            'metadata' => ['channel' => 'phone'],
        ]);

        $this->assertNotNull($task->uuid);
        $this->assertSame(TaskStatus::Pending, $task->status);
        $this->assertSame(TaskPriority::Normal, $task->priority);
        $this->assertSame('고객에게 전화하기', $task->title);
        $this->assertSame("첫 줄\n둘째 줄", $task->description);
        $this->assertSame('2026-07-25 09:30:00', $task->due_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($contact->id, $task->contact_id);
        $this->assertSame($assignee->id, $task->assigned_to);
        $this->assertNull($task->completed_at);
        $this->assertSame(['channel' => 'phone'], $task->metadata);
    }

    public function test_completed_at_follows_status_transitions_and_ignores_external_input(): void
    {
        CarbonImmutable::setTestNow('2026-07-25T00:00:00Z');
        $contact = Contact::create(['name' => '상태 고객']);
        $task = $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'title' => '완료 생성',
            'status' => 'completed',
            'completed_at' => '2000-01-01T00:00:00Z',
        ]);
        $this->assertSame('2026-07-25 00:00:00', $task->completed_at?->utc()->format('Y-m-d H:i:s'));

        CarbonImmutable::setTestNow('2026-07-26T00:00:00Z');
        $task = $this->service()->update($task, ['title' => '일반 수정']);
        $this->assertSame('2026-07-25 00:00:00', $task->completed_at?->utc()->format('Y-m-d H:i:s'));

        $task = $this->service()->update($task, ['status' => 'in_progress']);
        $this->assertNull($task->completed_at);

        CarbonImmutable::setTestNow('2026-07-27T00:00:00Z');
        $task = $this->service()->update($task, ['status' => 'completed']);
        $this->assertSame('2026-07-27 00:00:00', $task->completed_at?->utc()->format('Y-m-d H:i:s'));

        $task = $this->service()->update($task, ['status' => 'cancelled']);
        $this->assertNull($task->completed_at);
    }

    public function test_repository_filters_searches_overdue_and_uses_stable_default_order(): void
    {
        CarbonImmutable::setTestNow('2026-07-25T12:00:00Z');
        $contact = Contact::create(['name' => '검색 고객']);
        $other = Contact::create(['name' => '다른 고객']);
        $assignee = User::factory()->create();

        $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'assigned_to_uuid' => $assignee->uuid,
            'title' => 'Alpha overdue',
            'description' => '제안서',
            'priority' => 'urgent',
            'due_at' => '2026-07-25T10:00:00Z',
        ]);
        $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'title' => 'Beta future',
            'due_at' => '2026-07-26T10:00:00Z',
        ]);
        $this->service()->create([
            'contact_uuid' => $other->uuid,
            'title' => 'Alpha other',
            'due_at' => '2026-07-24T10:00:00Z',
        ]);

        $result = $this->service()->paginate([
            'contact_uuid' => $contact->uuid,
            'assigned_to_uuid' => $assignee->uuid,
            'priority' => 'urgent',
            'search' => '제안서',
            'overdue' => true,
        ], 10);
        $this->assertSame(1, $result->total());
        $this->assertSame('Alpha overdue', $result->items()[0]->title);

        $ordered = $this->service()->paginate(['contact_uuid' => $contact->uuid], 10);
        $this->assertSame(
            ['Beta future', 'Alpha overdue'],
            collect($ordered->items())->pluck('title')->all(),
        );
    }

    public function test_update_preserves_missing_values_and_supports_explicit_nulls(): void
    {
        $contact = Contact::create(['name' => '수정 고객']);
        $other = Contact::create(['name' => '이동 금지 고객']);
        $assignee = User::factory()->create();
        $task = $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'assigned_to_uuid' => $assignee->uuid,
            'title' => '수정 전',
            'description' => '설명',
            'due_at' => '2026-07-26T00:00:00Z',
            'metadata' => ['source' => 'manual'],
        ]);

        $updated = $this->service()->update($task, [
            'contact_uuid' => $other->uuid,
            'contact_id' => $other->id,
            'uuid' => '00000000-0000-4000-8000-000000000000',
            'completed_at' => '2000-01-01T00:00:00Z',
            'title' => ' 수정 후 ',
        ]);
        $this->assertSame($contact->id, $updated->contact_id);
        $this->assertSame($task->uuid, $updated->uuid);
        $this->assertSame($assignee->id, $updated->assigned_to);
        $this->assertSame(['source' => 'manual'], $updated->metadata);

        $updated = $this->service()->update($updated, [
            'assigned_to_uuid' => null,
            'description' => '',
            'due_at' => null,
            'metadata' => null,
        ]);
        $this->assertNull($updated->assigned_to);
        $this->assertNull($updated->description);
        $this->assertNull($updated->due_at);
        $this->assertNull($updated->metadata);
    }

    public function test_next_task_at_is_recalculated_for_every_active_task_change(): void
    {
        $contact = Contact::create(['name' => '다음 태스크 고객']);
        $withoutDue = $this->createTask($contact, null, '기한 없음');
        $this->assertNull($contact->fresh()->next_task_at);

        $later = $this->createTask($contact, '2026-07-27T00:00:00Z', '늦은 태스크');
        $this->assertNextTaskAt($contact, '2026-07-27 00:00:00');

        $earlier = $this->createTask($contact, '2026-07-24T00:00:00Z', '기한 초과');
        $this->assertNextTaskAt($contact, '2026-07-24 00:00:00');

        $this->service()->update($earlier, ['status' => 'completed']);
        $this->assertNextTaskAt($contact, '2026-07-27 00:00:00');

        $this->service()->update($earlier, [
            'status' => 'in_progress',
            'due_at' => '2026-07-23T00:00:00Z',
        ]);
        $this->assertNextTaskAt($contact, '2026-07-23 00:00:00');

        $this->service()->update($earlier, ['due_at' => '2026-07-28T00:00:00Z']);
        $this->assertNextTaskAt($contact, '2026-07-27 00:00:00');

        $this->service()->delete($later);
        $this->assertNextTaskAt($contact, '2026-07-28 00:00:00');

        $this->service()->update($earlier, ['status' => 'cancelled']);
        $this->assertNull($contact->fresh()->next_task_at);

        $this->service()->delete($withoutDue);
        $this->assertNull($contact->fresh()->next_task_at);
    }

    public function test_invalid_contract_values_are_rejected_before_persistence(): void
    {
        $contact = Contact::create(['name' => '검증 고객']);

        foreach ([
            ['contact_uuid' => '1', 'title' => '제목'],
            ['contact_uuid' => $contact->uuid, 'title' => '   '],
            ['contact_uuid' => $contact->uuid, 'title' => '제목', 'status' => 'custom'],
            ['contact_uuid' => $contact->uuid, 'title' => '제목', 'priority' => 'critical'],
            ['contact_uuid' => $contact->uuid, 'title' => '제목', 'due_at' => '2026-07-25 00:00:00'],
            ['contact_uuid' => $contact->uuid, 'title' => '제목', 'metadata' => ['list']],
            ['contact_uuid' => $contact->uuid, 'title' => '제목', 'assigned_to_uuid' => '1'],
            [
                'contact_uuid' => $contact->uuid,
                'title' => '제목',
                'assigned_to_uuid' => (string) Str::uuid(),
            ],
        ] as $data) {
            try {
                $this->service()->create($data);
                $this->fail('유효하지 않은 Task 입력이 허용되었습니다.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, Task::count());
    }

    public function test_assignee_delete_sets_null_and_task_delete_is_soft(): void
    {
        $contact = Contact::create(['name' => '삭제 고객']);
        $assignee = User::factory()->create();
        $task = $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'assigned_to_uuid' => $assignee->uuid,
            'title' => '삭제 대상',
        ]);

        $assignee->delete();
        $this->assertNull($task->fresh()->assigned_to);

        $this->assertTrue($this->service()->delete($task->fresh()));
        $this->assertNull($this->repository()->findByUuid($task->uuid));
        $this->assertNotNull(Task::withTrashed()->where('uuid', $task->uuid)->first());
    }

    public function test_physical_contact_delete_cascades_tasks(): void
    {
        $contact = Contact::create(['name' => '물리 삭제 고객']);
        $task = $this->createTask($contact, null, '삭제 대상');

        DB::table('crm_contacts')->where('id', $contact->id)->delete();

        $this->assertDatabaseMissing('crm_tasks', ['id' => $task->id]);
    }

    private function service(): TaskService
    {
        return $this->app->make(TaskService::class);
    }

    private function repository(): TaskRepositoryInterface
    {
        return $this->app->make(TaskRepositoryInterface::class);
    }

    private function createTask(Contact $contact, ?string $dueAt, string $title): Task
    {
        return $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'title' => $title,
            'due_at' => $dueAt,
        ]);
    }

    private function assertNextTaskAt(Contact $contact, string $expected): void
    {
        $this->assertSame(
            $expected,
            $contact->fresh()->next_task_at?->utc()->format('Y-m-d H:i:s'),
        );
    }
}
