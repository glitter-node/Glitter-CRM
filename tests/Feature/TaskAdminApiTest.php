<?php

namespace Modules\Glitter\Crm\Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Models\Task;
use Modules\Glitter\Crm\Services\TaskService;
use Modules\Glitter\Crm\Tests\FeatureTestCase;

/**
 * @scenario task-admin-api
 */
class TaskAdminApiTest extends FeatureTestCase
{
    use RefreshDatabase;

    private const BASE_URI = '/api/modules/glitter-crm/admin/tasks';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAdminUser([
            'glitter-crm.tasks.read',
            'glitter-crm.tasks.create',
            'glitter-crm.tasks.update',
            'glitter-crm.tasks.delete',
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_routes_require_authentication_and_admin_permissions(): void
    {
        $this->getJson(self::BASE_URI)->assertUnauthorized();
        $this->actingAs(User::factory()->create())
            ->getJson(self::BASE_URI)
            ->assertForbidden();
    }

    public function test_read_only_admin_can_read_but_cannot_write_and_abilities_match(): void
    {
        $contact = Contact::create(['name' => '읽기 전용']);
        $task = $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'title' => '읽기 전용 태스크',
        ]);
        $role = Role::create([
            'identifier' => 'crm-task-read-only',
            'name' => ['ko' => 'CRM 태스크 조회', 'en' => 'CRM Task Read Only'],
        ]);
        $permission = Permission::where('identifier', 'glitter-crm.tasks.read')->firstOrFail();
        $role->permissions()->attach($permission->id);
        $reader = User::factory()->create();
        $reader->roles()->attach($role->id);

        $this->actingAs($reader)
            ->getJson(self::BASE_URI)
            ->assertOk()
            ->assertJsonPath('data.abilities.can_create', false)
            ->assertJsonPath('data.data.0.abilities.can_update', false)
            ->assertJsonPath('data.data.0.abilities.can_delete', false);
        $this->actingAs($reader)->getJson(self::BASE_URI.'/'.$task->uuid)->assertOk();
        $this->actingAs($reader)->postJson(self::BASE_URI, [])->assertForbidden();
        $this->actingAs($reader)->putJson(self::BASE_URI.'/'.$task->uuid, [])->assertForbidden();
        $this->actingAs($reader)->deleteJson(self::BASE_URI.'/'.$task->uuid)->assertForbidden();
    }

    public function test_admin_can_create_show_update_and_soft_delete_by_uuid(): void
    {
        CarbonImmutable::setTestNow('2026-07-25T00:00:00Z');
        $contact = Contact::create(['name' => 'Task API 고객', 'company' => 'Glitter']);
        $assignee = User::factory()->create();

        $response = $this->actingAs($this->admin)->postJson(self::BASE_URI, [
            'contact_uuid' => $contact->uuid,
            'assigned_to_uuid' => $assignee->uuid,
            'title' => ' 후속 전화 ',
            'description' => ' 연락하기 ',
            'status' => 'pending',
            'priority' => 'urgent',
            'due_at' => '2026-07-25T18:30:00+09:00',
            'metadata' => ['channel' => 'phone'],
            'completed_at' => '2000-01-01T00:00:00Z',
            'id' => 999,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', '후속 전화')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.contact.uuid', $contact->uuid)
            ->assertJsonPath('data.assignee.uuid', $assignee->uuid)
            ->assertJsonPath('data.abilities.can_update', true)
            ->assertJsonPath('data.abilities.can_delete', true)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.contact.id')
            ->assertJsonMissingPath('data.assignee.id');
        $uuid = $response->json('data.uuid');
        $this->assertNull(Task::where('uuid', $uuid)->value('completed_at'));
        $this->assertSame(
            '2026-07-25 09:30:00',
            $contact->fresh()->next_task_at?->utc()->format('Y-m-d H:i:s'),
        );

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/'.$uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid);
        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/'.Task::where('uuid', $uuid)->value('id'))
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/not-a-uuid')
            ->assertNotFound();

        $this->actingAs($this->admin)->putJson(self::BASE_URI.'/'.$uuid, [
            'status' => 'completed',
            'assigned_to_uuid' => null,
            'due_at' => null,
            'contact_uuid' => Contact::create(['name' => '이동 금지'])->uuid,
            'completed_at' => '2000-01-01T00:00:00Z',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.assignee', null)
            ->assertJsonPath('data.due_at', null);
        $this->assertSame(
            '2026-07-25 00:00:00',
            Task::where('uuid', $uuid)->first()->completed_at?->utc()->format('Y-m-d H:i:s'),
        );
        $this->assertNull($contact->fresh()->next_task_at);

        $this->actingAs($this->admin)->deleteJson(self::BASE_URI.'/'.$uuid)->assertOk();
        $this->actingAs($this->admin)->getJson(self::BASE_URI.'/'.$uuid)->assertNotFound();
        $this->assertNotNull(Task::withTrashed()->where('uuid', $uuid)->value('deleted_at'));
    }

    public function test_index_nested_filters_overdue_and_public_envelope(): void
    {
        CarbonImmutable::setTestNow('2026-07-25T12:00:00Z');
        $contact = Contact::create(['name' => 'Nested']);
        $other = Contact::create(['name' => 'Other']);
        $assignee = User::factory()->create();
        $included = $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'assigned_to_uuid' => $assignee->uuid,
            'title' => '검색 기한 초과',
            'priority' => 'high',
            'due_at' => '2026-07-25T11:00:00Z',
        ]);
        $this->service()->create([
            'contact_uuid' => $other->uuid,
            'title' => '검색 제외',
            'due_at' => '2026-07-25T10:00:00Z',
        ]);

        $query = '?search='.urlencode('검색')
            .'&assigned_to_uuid='.$assignee->uuid
            .'&priority=high&overdue=true&sort=due_at&direction=asc';
        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.$query)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.uuid', $included->uuid)
            ->assertJsonPath('data.data.0.is_overdue', true)
            ->assertJsonPath('data.abilities.can_create', true);

        $nested = '/api/modules/glitter-crm/admin/contacts/'.$contact->uuid.'/tasks';
        $this->actingAs($this->admin)
            ->getJson($nested.'?contact_uuid='.$other->uuid)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.uuid', $included->uuid);

        $this->actingAs($this->admin)
            ->getJson('/api/modules/glitter-crm/admin/contacts/'.$contact->id.'/tasks')
            ->assertNotFound();
        $contact->delete();
        $this->actingAs($this->admin)->getJson($nested)->assertNotFound();
    }

    public function test_validation_rejects_invalid_filters_and_store_payloads_without_side_effects(): void
    {
        $contact = Contact::create(['name' => '검증 고객']);

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'?sort=id&direction=sideways&overdue=false')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'direction', 'overdue']);
        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'?due_from=2026-07-26T00%3A00%3A00Z'
                .'&due_to=2026-07-25T00%3A00%3A00Z')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_to']);

        foreach ([
            ['contact_uuid' => '1', 'title' => '제목', 'field' => 'contact_uuid'],
            ['contact_uuid' => $contact->uuid, 'title' => ' ', 'field' => 'title'],
            ['contact_uuid' => $contact->uuid, 'title' => '제목', 'status' => 'custom', 'field' => 'status'],
            ['contact_uuid' => $contact->uuid, 'title' => '제목', 'priority' => 'critical', 'field' => 'priority'],
            ['contact_uuid' => $contact->uuid, 'title' => '제목', 'due_at' => '2026-07-25 00:00:00', 'field' => 'due_at'],
            ['contact_uuid' => $contact->uuid, 'title' => '제목', 'metadata' => ['list'], 'field' => 'metadata'],
            ['contact_uuid' => $contact->uuid, 'title' => '제목', 'assigned_to_uuid' => '1', 'field' => 'assigned_to_uuid'],
        ] as $payload) {
            $field = $payload['field'];
            unset($payload['field']);
            $this->actingAs($this->admin)
                ->postJson(self::BASE_URI, $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field]);
        }

        $this->assertSame(0, Task::count());
        $this->assertNull($contact->fresh()->next_task_at);
    }

    public function test_unknown_contact_is_404_and_unknown_assignee_is_validation_error(): void
    {
        $contact = Contact::create(['name' => 'UUID 검증']);
        $payload = [
            'contact_uuid' => (string) Str::uuid(),
            'title' => '없는 고객',
        ];
        $this->actingAs($this->admin)
            ->postJson(self::BASE_URI, $payload)
            ->assertNotFound();

        $payload['contact_uuid'] = $contact->uuid;
        $payload['assigned_to_uuid'] = (string) Str::uuid();
        $this->actingAs($this->admin)
            ->postJson(self::BASE_URI, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to_uuid']);
        $this->assertSame(0, Task::count());
        $this->assertNull($contact->fresh()->next_task_at);
    }

    public function test_due_now_is_not_overdue_and_completed_or_cancelled_tasks_are_never_overdue(): void
    {
        CarbonImmutable::setTestNow('2026-07-25T12:00:00Z');
        $contact = Contact::create(['name' => '경계 고객']);
        foreach ([
            ['pending', '2026-07-25T12:00:00Z', false],
            ['pending', '2026-07-25T11:59:59Z', true],
            ['completed', '2026-07-25T11:00:00Z', false],
            ['cancelled', '2026-07-25T11:00:00Z', false],
        ] as [$status, $dueAt, $expected]) {
            $task = $this->service()->create([
                'contact_uuid' => $contact->uuid,
                'title' => $status.$dueAt,
                'status' => $status,
                'due_at' => $dueAt,
            ]);
            $this->actingAs($this->admin)
                ->getJson(self::BASE_URI.'/'.$task->uuid)
                ->assertOk()
                ->assertJsonPath('data.is_overdue', $expected);
        }
    }

    private function service(): TaskService
    {
        return $this->app->make(TaskService::class);
    }
}
