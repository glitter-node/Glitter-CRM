<?php

namespace Modules\Glitter\Crm\Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Services\TagService;
use Modules\Glitter\Crm\Tests\FeatureTestCase;

/**
 * @scenario contact-admin-api
 */
class ContactApiTest extends FeatureTestCase
{
    use RefreshDatabase;

    private const BASE_URI = '/api/modules/glitter-crm/admin/contacts';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAdminUser([
            'glitter-crm.contacts.read',
            'glitter-crm.contacts.create',
            'glitter-crm.contacts.update',
            'glitter-crm.contacts.delete',
        ]);
    }

    public function test_contact_routes_require_authentication(): void
    {
        $this->getJson(self::BASE_URI)->assertUnauthorized();
    }

    public function test_admin_can_create_list_show_update_and_soft_delete_contact_by_uuid(): void
    {
        $linkedUser = User::factory()->create();

        $created = $this->actingAs($this->admin)->postJson(self::BASE_URI, [
            'name' => '  API 고객  ',
            'user_id' => $linkedUser->uuid,
            'assigned_user_id' => $linkedUser->uuid,
            'email' => 'Customer@Example.com',
            'status' => 'active',
            'next_action_at' => '2026-08-01 09:00:00',
            'metadata' => ['segment' => 'api'],
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.name', 'API 고객')
            ->assertJsonPath('data.user_id', $linkedUser->uuid)
            ->assertJsonPath('data.assigned_user_id', $linkedUser->uuid)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.deleted_at');

        $uuid = $created->json('data.uuid');
        $this->assertIsString($uuid);

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'?keyword=API&status=active&sort=company&direction=asc&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.uuid', $uuid);

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/'.$uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid);

        $this->actingAs($this->admin)
            ->putJson(self::BASE_URI.'/'.$uuid, [
                'name' => '수정 고객',
                'status' => 'inactive',
                'assigned_user_id' => null,
                'company' => null,
                'metadata' => ['segment' => 'updated'],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', '수정 고객')
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.assigned_user_id', null);

        $this->actingAs($this->admin)
            ->deleteJson(self::BASE_URI.'/'.$uuid)
            ->assertOk();

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/'.$uuid)
            ->assertNotFound();

        $this->assertNotNull(Contact::withTrashed()->where('uuid', $uuid)->value('deleted_at'));
    }

    public function test_create_allows_duplicate_email_phone_and_mobile(): void
    {
        $payload = [
            'email' => 'duplicate@example.com',
            'phone' => '02-0000-0000',
            'mobile' => '010-0000-0000',
        ];

        $this->actingAs($this->admin)
            ->postJson(self::BASE_URI, ['name' => '첫 고객', ...$payload])
            ->assertCreated();
        $this->actingAs($this->admin)
            ->postJson(self::BASE_URI, ['name' => '둘째 고객', ...$payload])
            ->assertCreated();
    }

    public function test_contact_validation_rejects_invalid_input_and_internal_fields(): void
    {
        $response = $this->actingAs($this->admin)->postJson(self::BASE_URI, [
            'name' => '   ',
            'status' => 'unknown',
            'user_id' => '00000000-0000-4000-8000-000000000000',
            'assigned_user_id' => '00000000-0000-4000-8000-000000000001',
            'metadata' => 'invalid',
            'last_activity_at' => '2026-01-01',
            'id' => 999,
            'uuid' => '00000000-0000-4000-8000-000000000002',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'status',
                'user_id',
                'assigned_user_id',
                'metadata',
            ]);
        $this->assertSame(0, Contact::count());
    }

    public function test_list_validation_rejects_invalid_filters(): void
    {
        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'?status=unknown&sort=id&direction=sideways&per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'sort', 'direction', 'per_page']);
    }

    public function test_internal_numeric_id_cannot_be_used_as_contact_route_key(): void
    {
        $contact = Contact::create(['name' => 'UUID 전용']);

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/'.$contact->id)
            ->assertNotFound();
    }

    public function test_read_permission_does_not_grant_create_permission(): void
    {
        $readOnlyRole = Role::create([
            'identifier' => 'crm-read-only',
            'name' => ['ko' => 'CRM 조회', 'en' => 'CRM Read Only'],
        ]);
        $readPermission = Permission::where('identifier', 'glitter-crm.contacts.read')->firstOrFail();
        $readOnlyRole->permissions()->attach($readPermission->id);
        $readOnly = User::factory()->create();
        $readOnly->roles()->attach($readOnlyRole->id);

        $this->actingAs($readOnly)
            ->postJson(self::BASE_URI, ['name' => '권한 없음'])
            ->assertForbidden();
    }

    public function test_soft_deleted_contact_is_excluded_from_list_and_show(): void
    {
        $contact = Contact::create(['name' => '삭제 고객']);
        $contact->delete();

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/'.$contact->uuid)
            ->assertNotFound();
    }

    public function test_contact_create_update_and_clear_sync_tags_by_uuid(): void
    {
        $tagService = $this->app->make(TagService::class);
        $first = $tagService->create(['name' => '첫 태그']);
        $second = $tagService->create(['name' => '둘째 태그']);

        $created = $this->actingAs($this->admin)->postJson(self::BASE_URI, [
            'name' => '태그 고객',
            'tag_uuids' => [$first->uuid, $second->uuid],
        ]);

        $created->assertCreated()
            ->assertJsonCount(2, 'data.tags')
            ->assertJsonFragment(['uuid' => $first->uuid, 'name' => '첫 태그'])
            ->assertJsonFragment(['uuid' => $second->uuid, 'name' => '둘째 태그'])
            ->assertJsonMissingPath('data.tags.0.id')
            ->assertJsonMissingPath('data.tags.0.pivot');

        $uuid = $created->json('data.uuid');
        $this->actingAs($this->admin)
            ->putJson(self::BASE_URI.'/'.$uuid, ['tag_uuids' => [$second->uuid]])
            ->assertOk()
            ->assertJsonCount(1, 'data.tags')
            ->assertJsonPath('data.tags.0.uuid', $second->uuid);

        $this->actingAs($this->admin)
            ->putJson(self::BASE_URI.'/'.$uuid, ['tag_uuids' => null])
            ->assertOk()
            ->assertJsonCount(0, 'data.tags');
    }

    public function test_omitted_tag_uuids_preserve_relationship_and_empty_array_clears_it(): void
    {
        $tag = $this->app->make(TagService::class)->create(['name' => '유지 태그']);
        $contact = Contact::create(['name' => '기존 고객']);
        $contact->tags()->attach($tag->id);

        $this->actingAs($this->admin)
            ->putJson(self::BASE_URI.'/'.$contact->uuid, ['name' => '관계 유지'])
            ->assertOk()
            ->assertJsonCount(1, 'data.tags');
        $this->assertSame([$tag->id], $contact->fresh()->tags->modelKeys());

        $this->actingAs($this->admin)
            ->putJson(self::BASE_URI.'/'.$contact->uuid, ['tag_uuids' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.tags');
    }

    public function test_invalid_or_deleted_tag_rolls_back_contact_write(): void
    {
        $deleted = $this->app->make(TagService::class)->create(['name' => '삭제 태그']);
        $deleted->delete();
        $contact = Contact::create(['name' => '원래 이름']);

        $this->actingAs($this->admin)
            ->putJson(self::BASE_URI.'/'.$contact->uuid, [
                'name' => '저장되면 안 됨',
                'tag_uuids' => [$deleted->uuid],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tag_uuids']);

        $this->assertSame('원래 이름', $contact->fresh()->name);
        $this->assertSame(0, DB::table('crm_contact_tag')->count());
    }

    public function test_tag_uuid_input_rejects_numeric_duplicate_and_missing_values(): void
    {
        $tag = $this->app->make(TagService::class)->create(['name' => '검증 태그']);

        $this->actingAs($this->admin)
            ->postJson(self::BASE_URI, ['name' => '숫자', 'tag_uuids' => [$tag->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tag_uuids.0']);

        $this->actingAs($this->admin)
            ->postJson(self::BASE_URI, ['name' => '중복', 'tag_uuids' => [$tag->uuid, $tag->uuid]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tag_uuids.1']);

        $this->actingAs($this->admin)
            ->postJson(self::BASE_URI, [
                'name' => '없음',
                'tag_uuids' => ['00000000-0000-4000-8000-000000000000'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tag_uuids']);

        $this->assertSame(0, Contact::count());
    }
}
