<?php

namespace Modules\Glitter\Crm\Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Models\Tag;
use Modules\Glitter\Crm\Services\TagService;
use Modules\Glitter\Crm\Tests\FeatureTestCase;

/**
 * @scenario tag-admin-api
 */
class TagAdminApiTest extends FeatureTestCase
{
    use RefreshDatabase;

    private const BASE_URI = '/api/modules/glitter-crm/admin/tags';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAdminUser([
            'glitter-crm.tags.read',
            'glitter-crm.tags.create',
            'glitter-crm.tags.update',
            'glitter-crm.tags.delete',
        ]);
    }

    public function test_tag_routes_require_authentication(): void
    {
        $this->getJson(self::BASE_URI)->assertUnauthorized();
    }

    public function test_admin_can_create_list_show_update_and_soft_delete_tag_by_uuid(): void
    {
        $created = $this->actingAs($this->admin)->postJson(self::BASE_URI, [
            'name' => '  중요 고객  ',
            'description' => ' 우선 관리 ',
            'color' => '#aabbcc',
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.name', '중요 고객')
            ->assertJsonPath('data.slug', '중요-고객')
            ->assertJsonPath('data.description', '우선 관리')
            ->assertJsonPath('data.color', '#AABBCC')
            ->assertJsonPath('data.abilities.can_update', true)
            ->assertJsonPath('data.abilities.can_delete', true)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.deleted_at');

        $uuid = $created->json('data.uuid');
        $this->assertIsString($uuid);

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'?search=우선&sort=slug&direction=asc&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.uuid', $uuid)
            ->assertJsonPath('data.abilities.can_create', true)
            ->assertJsonMissingPath('data.data.0.id');

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/'.$uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid);

        $this->actingAs($this->admin)
            ->putJson(self::BASE_URI.'/'.$uuid, [
                'name' => '수정 태그',
                'slug' => '수정 식별자',
                'description' => null,
                'color' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', '수정 태그')
            ->assertJsonPath('data.slug', '수정-식별자')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.color', null);

        $this->actingAs($this->admin)
            ->deleteJson(self::BASE_URI.'/'.$uuid)
            ->assertOk();

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/'.$uuid)
            ->assertNotFound();

        $this->assertNotNull(Tag::withTrashed()->where('uuid', $uuid)->value('deleted_at'));
    }

    public function test_index_searches_all_fields_and_rejects_invalid_sorting(): void
    {
        Tag::create(['name' => '이름 검색', 'slug' => 'name-search']);
        Tag::create(['name' => '둘째', 'slug' => 'slug-search']);
        Tag::create(['name' => '셋째', 'slug' => 'third', 'description' => '설명 검색']);

        foreach (['이름', 'slug-search', '설명'] as $search) {
            $this->actingAs($this->admin)
                ->getJson(self::BASE_URI.'?search='.urlencode($search))
                ->assertOk()
                ->assertJsonPath('data.meta.total', 1);
        }

        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'?sort=id&direction=sideways&per_page=101&page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'direction', 'per_page', 'page']);
    }

    public function test_store_rejects_invalid_values_and_ignores_internal_fields(): void
    {
        $this->actingAs($this->admin)
            ->postJson(self::BASE_URI, ['name' => '   ', 'color' => 'red'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'color']);

        $response = $this->actingAs($this->admin)->postJson(self::BASE_URI, [
            'name' => '공개 필드',
            'id' => 999,
            'uuid' => '00000000-0000-4000-8000-000000000000',
            'deleted_at' => now()->toDateTimeString(),
        ]);

        $response->assertCreated()
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.deleted_at');
        $this->assertNotSame('00000000-0000-4000-8000-000000000000', $response->json('data.uuid'));
        $this->assertNotSame(999, Tag::where('uuid', $response->json('data.uuid'))->value('id'));
    }

    public function test_slug_collision_including_soft_deleted_tag_returns_validation_error(): void
    {
        $existing = $this->app->make(TagService::class)->create(['name' => 'VIP']);
        $existing->delete();

        $this->actingAs($this->admin)
            ->postJson(self::BASE_URI, ['name' => '새 VIP', 'slug' => 'vip'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_update_allows_own_slug_but_rejects_another_tag_slug(): void
    {
        $first = $this->app->make(TagService::class)->create(['name' => '첫 태그']);
        $second = $this->app->make(TagService::class)->create(['name' => '둘째 태그']);

        $this->actingAs($this->admin)
            ->putJson(self::BASE_URI.'/'.$first->uuid, ['slug' => $first->slug])
            ->assertOk()
            ->assertJsonPath('data.slug', $first->slug);

        $this->actingAs($this->admin)
            ->putJson(self::BASE_URI.'/'.$first->uuid, ['slug' => $second->slug])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_numeric_invalid_missing_and_soft_deleted_route_keys_are_not_available(): void
    {
        $tag = $this->app->make(TagService::class)->create(['name' => 'UUID 전용']);

        $this->actingAs($this->admin)->getJson(self::BASE_URI.'/'.$tag->id)->assertNotFound();
        $this->actingAs($this->admin)->getJson(self::BASE_URI.'/not-a-uuid')->assertNotFound();
        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI.'/00000000-0000-4000-8000-000000000000')
            ->assertNotFound();

        $tag->delete();
        $this->actingAs($this->admin)->getJson(self::BASE_URI.'/'.$tag->uuid)->assertNotFound();
    }

    public function test_read_only_admin_has_false_item_abilities_and_cannot_write(): void
    {
        $role = Role::create([
            'identifier' => 'crm-tag-read-only',
            'name' => ['ko' => 'CRM 태그 조회', 'en' => 'CRM Tag Read Only'],
        ]);
        $permission = Permission::where('identifier', 'glitter-crm.tags.read')->firstOrFail();
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $tag = $this->app->make(TagService::class)->create(['name' => '조회 전용']);

        $this->actingAs($user)
            ->getJson(self::BASE_URI)
            ->assertOk()
            ->assertJsonPath('data.abilities.can_create', false)
            ->assertJsonPath('data.data.0.abilities.can_update', false)
            ->assertJsonPath('data.data.0.abilities.can_delete', false);

        $this->actingAs($user)->postJson(self::BASE_URI, ['name' => '금지'])->assertForbidden();
        $this->actingAs($user)->putJson(self::BASE_URI.'/'.$tag->uuid, ['name' => '금지'])->assertForbidden();
        $this->actingAs($user)->deleteJson(self::BASE_URI.'/'.$tag->uuid)->assertForbidden();
    }

    public function test_delete_preserves_pivot_excludes_tag_and_reserves_slug(): void
    {
        $contact = Contact::create(['name' => '태그 고객']);
        $tag = $this->app->make(TagService::class)->create(['name' => '연결 태그']);
        $this->app->make(TagService::class)->attachToContact($contact->uuid, $tag->uuid);

        $this->actingAs($this->admin)->deleteJson(self::BASE_URI.'/'.$tag->uuid)->assertOk();

        $this->assertDatabaseHas('crm_contact_tag', [
            'contact_id' => $contact->id,
            'tag_id' => $tag->id,
        ]);
        $this->assertCount(0, $contact->fresh()->tags);
        $this->actingAs($this->admin)
            ->getJson(self::BASE_URI)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
        $this->assertSame(1, DB::table('crm_contact_tag')->count());

        $this->actingAs($this->admin)
            ->postJson(self::BASE_URI, ['name' => '재사용', 'slug' => $tag->slug])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }
}
