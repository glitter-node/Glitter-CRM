<?php

namespace Modules\Glitter\Crm\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Glitter\Crm\Enums\ActivityType;
use Modules\Glitter\Crm\Models\Activity;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Repositories\Contracts\ActivityRepositoryInterface;
use Modules\Glitter\Crm\Services\ActivityService;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario activity-data-layer
 */
class ActivityDataLayerTest extends ModuleTestCase
{
    use RefreshDatabase;

    public function test_activity_schema_and_foreign_keys_exist(): void
    {
        $this->assertTrue(Schema::hasTable('crm_activities'));
        $this->assertTrue(Schema::hasColumns('crm_activities', [
            'id', 'uuid', 'contact_id', 'type', 'subject', 'body',
            'occurred_at', 'created_by', 'metadata', 'deleted_at',
        ]));

        $indexes = collect(Schema::getIndexes('crm_activities'));
        $this->assertTrue((bool) $indexes->first(
            fn (array $index) => $index['unique'] && $index['columns'] === ['uuid'],
        ));
        $this->assertTrue((bool) $indexes->first(
            fn (array $index) => $index['columns'] === ['contact_id', 'occurred_at'],
        ));

        $foreignKeys = collect(Schema::getForeignKeys('crm_activities'));
        $this->assertTrue((bool) $foreignKeys->first(
            fn (array $foreign) => $foreign['columns'] === ['contact_id']
                && $foreign['foreign_table'] === 'crm_contacts'
                && strtolower($foreign['on_delete']) === 'cascade',
        ));
        $this->assertTrue((bool) $foreignKeys->first(
            fn (array $foreign) => $foreign['columns'] === ['created_by']
                && $foreign['foreign_table'] === 'users'
                && strtolower($foreign['on_delete']) === 'set null',
        ));
    }

    public function test_service_creates_activity_from_public_uuids_and_normalizes_utc(): void
    {
        $contact = Contact::create(['name' => '활동 고객']);
        $creator = User::factory()->create();

        $activity = $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'type' => ' CALL ',
            'subject' => '  상담 전화  ',
            'body' => "  첫 줄\n둘째 줄  ",
            'occurred_at' => '2026-07-25T18:30:00+09:00',
            'metadata' => ['channel' => 'phone'],
        ], $creator->uuid);

        $this->assertNotNull($activity->uuid);
        $this->assertSame(ActivityType::Call, $activity->type);
        $this->assertSame('상담 전화', $activity->subject);
        $this->assertSame("첫 줄\n둘째 줄", $activity->body);
        $this->assertSame('2026-07-25 09:30:00', $activity->occurred_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($contact->id, $activity->contact_id);
        $this->assertSame($creator->id, $activity->created_by);
        $this->assertSame(['channel' => 'phone'], $activity->metadata);
    }

    public function test_system_activity_allows_null_creator_and_creator_deletion_sets_null(): void
    {
        $contact = Contact::create(['name' => '시스템 고객']);
        $system = $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'type' => ActivityType::System,
            'body' => '시스템 기록',
            'occurred_at' => CarbonImmutable::parse('2026-07-25T00:00:00Z'),
        ]);
        $this->assertNull($system->created_by);

        $creator = User::factory()->create();
        $activity = $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'type' => 'note',
            'body' => '작성자 기록',
            'occurred_at' => '2026-07-25T01:00:00Z',
        ], $creator->uuid);
        $creator->delete();

        $this->assertNull($activity->fresh()->created_by);
    }

    public function test_repository_filters_groups_keyword_and_uses_stable_latest_order(): void
    {
        $contact = Contact::create(['name' => '검색 고객']);
        $other = Contact::create(['name' => '다른 고객']);
        $repository = $this->app->make(ActivityRepositoryInterface::class);

        foreach ([
            [$contact, 'note', 'Alpha', '2026-07-25T01:00:00Z'],
            [$contact, 'call', 'Beta', '2026-07-25T02:00:00Z'],
            [$other, 'note', 'Alpha', '2026-07-25T03:00:00Z'],
        ] as [$owner, $type, $subject, $time]) {
            $repository->create([
                'contact_id' => $owner->id,
                'type' => $type,
                'subject' => $subject,
                'occurred_at' => $time,
            ]);
        }

        $result = $repository->paginate([
            'contact_uuid' => $contact->uuid,
            'type' => 'note',
            'keyword' => 'Alpha',
        ], 10);
        $this->assertSame(1, $result->total());

        $ordered = $repository->paginate(['contact_uuid' => $contact->uuid], 10);
        $this->assertSame(['Beta', 'Alpha'], collect($ordered->items())->pluck('subject')->all());
    }

    public function test_update_cannot_move_contact_or_creator_and_delete_is_soft(): void
    {
        $contact = Contact::create(['name' => '수정 고객']);
        $other = Contact::create(['name' => '이동 금지 고객']);
        $creator = User::factory()->create();
        $activity = $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'type' => 'note',
            'body' => '수정 전',
            'occurred_at' => '2026-07-25T00:00:00Z',
        ], $creator->uuid);

        $updated = $this->service()->update($activity, [
            'contact_uuid' => $other->uuid,
            'created_by_uuid' => null,
            'uuid' => '00000000-0000-4000-8000-000000000000',
            'body' => ' 수정 후 ',
            'metadata' => null,
        ]);

        $this->assertSame($contact->id, $updated->contact_id);
        $this->assertSame($creator->id, $updated->created_by);
        $this->assertSame($activity->uuid, $updated->uuid);
        $this->assertSame('수정 후', $updated->body);

        $this->assertTrue($this->service()->delete($updated));
        $this->assertNull($this->app->make(ActivityRepositoryInterface::class)->findByUuid($updated->uuid));
        $this->assertNotNull(Activity::withTrashed()->where('uuid', $updated->uuid)->first());
    }

    public function test_last_activity_at_is_recalculated_after_create_update_and_delete(): void
    {
        $contact = Contact::create(['name' => '최근 활동 고객']);
        $older = $this->createActivity($contact, '2026-07-25T01:00:00Z', '과거');
        $this->assertContactLastActivity($contact, '2026-07-25 01:00:00');

        $newest = $this->createActivity($contact, '2026-07-25T03:00:00Z', '최신');
        $this->createActivity($contact, '2026-07-25T02:00:00Z', '중간');
        $this->assertContactLastActivity($contact, '2026-07-25 03:00:00');

        $this->service()->update($newest, ['occurred_at' => '2026-07-25T00:00:00Z']);
        $this->assertContactLastActivity($contact, '2026-07-25 02:00:00');

        $this->service()->update($older, ['occurred_at' => '2026-07-25T04:00:00Z']);
        $this->assertContactLastActivity($contact, '2026-07-25 04:00:00');

        $this->service()->delete($older);
        $this->assertContactLastActivity($contact, '2026-07-25 02:00:00');

        foreach (Activity::query()->where('contact_id', $contact->id)->get() as $activity) {
            $this->service()->delete($activity);
        }
        $this->assertNull($contact->fresh()->last_activity_at);
    }

    public function test_invalid_contract_values_are_rejected_before_persistence(): void
    {
        $contact = Contact::create(['name' => '검증 고객']);

        foreach ([
            ['contact_uuid' => '1', 'type' => 'note', 'body' => '내용', 'occurred_at' => '2026-07-25T00:00:00Z'],
            ['contact_uuid' => $contact->uuid, 'type' => 'custom', 'body' => '내용', 'occurred_at' => '2026-07-25T00:00:00Z'],
            ['contact_uuid' => $contact->uuid, 'type' => 'note', 'occurred_at' => '2026-07-25T00:00:00Z'],
            ['contact_uuid' => $contact->uuid, 'type' => 'note', 'body' => '내용', 'occurred_at' => '2026-07-25 00:00:00'],
            ['contact_uuid' => $contact->uuid, 'type' => 'note', 'body' => '내용', 'occurred_at' => '2026-07-25T00:00:00Z', 'metadata' => ['list']],
        ] as $data) {
            try {
                $this->service()->create($data);
                $this->fail('유효하지 않은 Activity 입력이 허용되었습니다.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, Activity::count());
    }

    public function test_unknown_contact_and_creator_public_uuids_are_rejected(): void
    {
        $unknownUuid = '00000000-0000-4000-8000-000000000000';

        try {
            $this->service()->create([
                'contact_uuid' => $unknownUuid,
                'type' => 'note',
                'body' => '내용',
                'occurred_at' => '2026-07-25T00:00:00Z',
            ]);
            $this->fail('존재하지 않는 Contact UUID가 허용되었습니다.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $contact = Contact::create(['name' => '작성자 검증 고객']);
        $this->expectException(ValidationException::class);
        $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'type' => 'note',
            'body' => '내용',
            'occurred_at' => '2026-07-25T00:00:00Z',
        ], $unknownUuid);
    }

    public function test_physical_contact_delete_cascades_activity(): void
    {
        $contact = Contact::create(['name' => '물리 삭제 고객']);
        $activity = $this->createActivity($contact, '2026-07-25T00:00:00Z', '삭제 대상');

        DB::table('crm_contacts')->where('id', $contact->id)->delete();

        $this->assertDatabaseMissing('crm_activities', ['id' => $activity->id]);
    }

    private function service(): ActivityService
    {
        return $this->app->make(ActivityService::class);
    }

    private function createActivity(Contact $contact, string $occurredAt, string $subject): Activity
    {
        return $this->service()->create([
            'contact_uuid' => $contact->uuid,
            'type' => 'note',
            'subject' => $subject,
            'occurred_at' => $occurredAt,
        ]);
    }

    private function assertContactLastActivity(Contact $contact, string $expected): void
    {
        $this->assertSame(
            $expected,
            $contact->fresh()->last_activity_at?->utc()->format('Y-m-d H:i:s'),
        );
    }
}
