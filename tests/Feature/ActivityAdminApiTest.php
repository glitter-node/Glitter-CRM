<?php

namespace Modules\Glitter\Crm\Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Glitter\Crm\Enums\ActivityType;
use Modules\Glitter\Crm\Models\Activity;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Services\ActivityService;
use Modules\Glitter\Crm\Tests\FeatureTestCase;

/**
 * @scenario activity-admin-api
 */
class ActivityAdminApiTest extends FeatureTestCase
{
    use RefreshDatabase;

    private const BASE_URI = '/api/modules/glitter-crm/admin/activities';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAdminUser([
            'glitter-crm.activities.read',
            'glitter-crm.activities.create',
            'glitter-crm.activities.update',
            'glitter-crm.activities.delete',
        ]);
    }

    public function test_activity_routes_require_authentication(): void
    {
        $this->getJson(self::BASE_URI)->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson(self::BASE_URI)
            ->assertForbidden();
    }

    public function test_admin_can_create_each_public_type_with_subject_or_body_and_future_time(): void
    {
        $contact = Contact::create(['name' => '유형 고객']);

        foreach (['note', 'call', 'email', 'meeting', 'status_change'] as $index => $type) {
            $payload = [
                'contact_uuid' => $contact->uuid,
                'type' => $type,
                'occurred_at' => '2030-01-01T00:00:00Z',
                'metadata' => [],
            ];
            $payload[$index % 2 === 0 ? 'subject' : 'body'] = $type;

            $this->actingAs($this->admin)
                ->postJson(self::BASE_URI, $payload)
                ->assertCreated()
                ->assertJsonPath('data.type', $type);
        }

        $this->assertSame(5, Activity::count());
    }

    public function test_admin_can_create_list_show_update_and_soft_delete_activity_by_uuid(): void
    {
        $contact = Contact::create(['name' => 'API 고객', 'company' => 'Glitter']);
        $response = $this->actingAs($this->admin)->postJson(self::BASE_URI, [
            'contact_uuid' => $contact->uuid,
            'type' => 'call',
            'subject' => ' 상담 ',
            'occurred_at' => '2026-07-25T18:30:00+09:00',
            'metadata' => ['channel' => 'phone'],
            'created_by_uuid' => '00000000-0000-4000-8000-000000000000',
            'id' => 999,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'call')
            ->assertJsonPath('data.subject', '상담')
            ->assertJsonPath('data.contact.uuid', $contact->uuid)
            ->assertJsonPath('data.creator.uuid', $this->admin->uuid)
            ->assertJsonPath('data.abilities.can_update', true)
            ->assertJsonPath('data.abilities.can_delete', true)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.contact.id')
            ->assertJsonMissingPath('data.creator.id')
            ->assertJsonMissingPath('data.created_by');
        $this->assertStringEndsWith('Z', $response->json('data.occurred_at'));

        $uuid = $response->json('data.uuid');
        $this->assertSame($this->admin->id, Activity::where('uuid', $uuid)->value('created_by'));
        $this->assertSame(
            '2026-07-25 09:30:00',
            $contact->fresh()->last_activity_at?->utc()->format('Y-m-d H:i:s'),
        );

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'?search=상담&type=call&sort=occurred_at&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.uuid', $uuid)
            ->assertJsonPath('data.abilities.can_create', true);

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/'.$uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid);

        $this->actingAs($this->admin)
            ->putJson(self::BASE_URI.'/'.$uuid, [
                'type' => 'meeting',
                'subject' => null,
                'body' => '미팅 완료',
                'occurred_at' => '2026-07-26T00:00:00Z',
                'metadata' => null,
                'contact_uuid' => Contact::create(['name' => '이동 금지'])->uuid,
                'created_by_uuid' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'meeting')
            ->assertJsonPath('data.body', '미팅 완료')
            ->assertJsonPath('data.contact.uuid', $contact->uuid)
            ->assertJsonPath('data.creator.uuid', $this->admin->uuid);

        $this->actingAs($this->admin)->deleteJson(self::BASE_URI.'/'.$uuid)->assertOk();
        $this->actingAs($this->admin)->getJson(self::BASE_URI.'/'.$uuid)->assertNotFound();
        $this->assertNotNull(Activity::withTrashed()->where('uuid', $uuid)->value('deleted_at'));
        $this->assertNull($contact->fresh()->last_activity_at);
    }

    public function test_index_filters_by_contact_creator_type_range_and_stable_order(): void
    {
        $contact = Contact::create(['name' => '필터 고객']);
        $other = Contact::create(['name' => '다른 고객']);

        $first = $this->createActivity($contact, 'note', '검색 본문', '2026-07-25T01:00:00Z');
        $second = $this->createActivity($contact, 'call', '검색 제목', '2026-07-25T01:00:00Z');
        $this->createActivity($other, 'note', '검색 제외', '2026-07-25T02:00:00Z');

        $uri = self::BASE_URI.'?contact_uuid='.$contact->uuid
            .'&created_by_uuid='.$this->admin->uuid
            .'&occurred_from=2026-07-25T00%3A00%3A00Z'
            .'&occurred_to=2026-07-25T01%3A00%3A00Z'
            .'&search='.urlencode('검색');

        $response = $this->actingAs($this->admin)->getJson($uri)->assertOk();
        $response->assertJsonPath('data.meta.total', 2);
        $this->assertSame(
            [$second->uuid, $first->uuid],
            collect($response->json('data.data'))->pluck('uuid')->all(),
        );

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'?sort=id&direction=sideways&per_page=101&page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'direction', 'per_page', 'page']);

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'?occurred_from=2026-07-26T00%3A00%3A00Z'
                .'&occurred_to=2026-07-25T00%3A00%3A00Z')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['occurred_to']);
    }

    public function test_contact_nested_index_uses_route_contact_as_filter(): void
    {
        $contact = Contact::create(['name' => 'Nested 고객']);
        $other = Contact::create(['name' => '제외 고객']);
        $activity = $this->createActivity($contact, 'note', '포함', '2026-07-25T00:00:00Z');
        $this->createActivity($other, 'note', '제외', '2026-07-25T00:00:00Z');

        $uri = '/api/modules/glitter-crm/admin/contacts/'.$contact->uuid.'/activities';
        $this->actingAs($this->admin)
            ->getJson($uri.'?search='.urlencode('포함'))
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.uuid', $activity->uuid);

        $this->actingAs($this->admin)
            ->getJson('/api/modules/glitter-crm/admin/contacts/'.$contact->id.'/activities')
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->getJson('/api/modules/glitter-crm/admin/contacts/not-a-uuid/activities')
            ->assertNotFound();

        $contact->delete();
        $this->actingAs($this->admin)->getJson($uri)->assertNotFound();
    }

    public function test_store_validation_rejects_system_empty_content_invalid_dates_and_list_metadata(): void
    {
        $contact = Contact::create(['name' => '검증 고객']);

        foreach ([
            ['contact_uuid' => $contact->uuid, 'type' => 'system', 'body' => '위장', 'occurred_at' => '2026-07-25T00:00:00Z', 'field' => 'type'],
            ['contact_uuid' => $contact->uuid, 'type' => 'note', 'subject' => ' ', 'body' => ' ', 'occurred_at' => '2026-07-25T00:00:00Z', 'field' => 'body'],
            ['contact_uuid' => $contact->uuid, 'type' => 'note', 'body' => '내용', 'occurred_at' => '2026-07-25 00:00:00', 'field' => 'occurred_at'],
            ['contact_uuid' => $contact->uuid, 'type' => 'note', 'body' => '내용', 'occurred_at' => '2026-07-25T00:00:00Z', 'metadata' => ['list'], 'field' => 'metadata'],
            ['contact_uuid' => '1', 'type' => 'note', 'body' => '내용', 'occurred_at' => '2026-07-25T00:00:00Z', 'field' => 'contact_uuid'],
        ] as $payload) {
            $field = $payload['field'];
            unset($payload['field']);
            $this->actingAs($this->admin)
                ->postJson(self::BASE_URI, $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field]);
        }

        $this->assertSame(0, Activity::count());
        $this->assertNull($contact->fresh()->last_activity_at);
    }

    public function test_store_returns_not_found_for_unknown_or_soft_deleted_contact(): void
    {
        $payload = [
            'contact_uuid' => '00000000-0000-4000-8000-000000000000',
            'type' => 'note',
            'body' => '내용',
            'occurred_at' => '2026-07-25T00:00:00Z',
        ];
        $this->actingAs($this->admin)
            ->postJson(self::BASE_URI, $payload)
            ->assertNotFound();

        $contact = Contact::create(['name' => '삭제 고객']);
        $contact->delete();
        $payload['contact_uuid'] = $contact->uuid;
        $this->actingAs($this->admin)
            ->postJson(self::BASE_URI, $payload)
            ->assertNotFound();
    }

    public function test_system_activity_is_readable_but_not_mutable_and_has_false_abilities(): void
    {
        $contact = Contact::create(['name' => '시스템 고객']);
        $system = $this->app->make(ActivityService::class)->create([
            'contact_uuid' => $contact->uuid,
            'type' => ActivityType::System,
            'body' => '내부 기록',
            'occurred_at' => '2026-07-25T00:00:00Z',
        ]);

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/'.$system->uuid)
            ->assertOk()
            ->assertJsonPath('data.type', 'system')
            ->assertJsonPath('data.creator', null)
            ->assertJsonPath('data.abilities.can_update', false)
            ->assertJsonPath('data.abilities.can_delete', false);

        $this->actingAs($this->admin)
            ->putJson(self::BASE_URI.'/'.$system->uuid, ['body' => '변조'])
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->deleteJson(self::BASE_URI.'/'.$system->uuid)
            ->assertForbidden();

        $this->assertSame('내부 기록', $system->fresh()->body);
        $this->assertNull($system->fresh()->deleted_at);
    }

    public function test_read_only_admin_has_false_abilities_and_cannot_write(): void
    {
        $role = Role::create([
            'identifier' => 'crm-activity-read-only',
            'name' => ['ko' => 'CRM 활동 조회', 'en' => 'CRM Activity Read Only'],
        ]);
        $permission = Permission::where('identifier', 'glitter-crm.activities.read')->firstOrFail();
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $contact = Contact::create(['name' => '조회 권한 고객']);
        $activity = $this->createActivity($contact, 'note', '조회', '2026-07-25T00:00:00Z');

        $this->actingAs($user)
            ->getJson(self::BASE_URI)
            ->assertOk()
            ->assertJsonPath('data.abilities.can_create', false)
            ->assertJsonPath('data.data.0.abilities.can_update', false)
            ->assertJsonPath('data.data.0.abilities.can_delete', false);

        $payload = [
            'contact_uuid' => $contact->uuid,
            'type' => 'note',
            'body' => '금지',
            'occurred_at' => '2026-07-25T00:00:00Z',
        ];
        $this->actingAs($user)->postJson(self::BASE_URI, $payload)->assertForbidden();
        $this->actingAs($user)
            ->putJson(self::BASE_URI.'/'.$activity->uuid, ['body' => '금지'])
            ->assertForbidden();
        $this->actingAs($user)->deleteJson(self::BASE_URI.'/'.$activity->uuid)->assertForbidden();
    }

    public function test_uuid_routes_and_soft_deleted_activity_are_not_available(): void
    {
        $contact = Contact::create(['name' => 'UUID 고객']);
        $activity = $this->createActivity($contact, 'note', 'UUID', '2026-07-25T00:00:00Z');

        $this->actingAs($this->admin)->getJson(self::BASE_URI.'/'.$activity->id)->assertNotFound();
        $this->actingAs($this->admin)->getJson(self::BASE_URI.'/not-a-uuid')->assertNotFound();
        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/00000000-0000-4000-8000-000000000000')
            ->assertNotFound();

        $activity->delete();
        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/'.$activity->uuid)
            ->assertNotFound();
    }

    private function createActivity(
        Contact $contact,
        string $type,
        string $body,
        string $occurredAt,
    ): Activity {
        return $this->app->make(ActivityService::class)->create([
            'contact_uuid' => $contact->uuid,
            'type' => $type,
            'body' => $body,
            'occurred_at' => $occurredAt,
        ], $this->admin->uuid);
    }
}
