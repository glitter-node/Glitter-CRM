<?php

namespace Modules\Glitter\Crm\Tests\Feature;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Models\Tag;
use Modules\Glitter\Crm\Repositories\Contracts\TagRepositoryInterface;
use Modules\Glitter\Crm\Services\TagService;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario tag-data-layer
 */
class TagDataLayerTest extends ModuleTestCase
{
    use RefreshDatabase;

    public function test_tag_schema_and_pivot_indexes_exist(): void
    {
        $this->assertTrue(Schema::hasTable('crm_tags'));
        $this->assertTrue(Schema::hasTable('crm_contact_tag'));
        $this->assertTrue(Schema::hasColumns('crm_tags', [
            'id',
            'uuid',
            'name',
            'slug',
            'description',
            'color',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $tagIndexes = collect(Schema::getIndexes('crm_tags'))->keyBy('name');
        $pivotIndexes = collect(Schema::getIndexes('crm_contact_tag'))->keyBy('name');

        $this->assertTrue((bool) $tagIndexes->first(fn (array $index) => $index['unique'] && $index['columns'] === ['uuid']));
        $this->assertTrue((bool) $tagIndexes->first(fn (array $index) => $index['unique'] && $index['columns'] === ['slug']));
        $this->assertTrue((bool) $pivotIndexes->first(
            fn (array $index) => $index['unique'] && $index['columns'] === ['contact_id', 'tag_id'],
        ));

        $foreignKeys = collect(Schema::getForeignKeys('crm_contact_tag'));
        $this->assertTrue((bool) $foreignKeys->first(
            fn (array $foreign) => $foreign['columns'] === ['contact_id']
                && $foreign['foreign_table'] === 'crm_contacts'
                && strtolower($foreign['on_delete']) === 'cascade',
        ));
        $this->assertTrue((bool) $foreignKeys->first(
            fn (array $foreign) => $foreign['columns'] === ['tag_id']
                && $foreign['foreign_table'] === 'crm_tags'
                && strtolower($foreign['on_delete']) === 'cascade',
        ));
    }

    public function test_service_creates_multilingual_slug_and_normalizes_optional_values(): void
    {
        $tag = $this->service()->create([
            'name' => '  중요 고객  ',
            'description' => '   ',
            'color' => '#a1b2c3',
        ]);

        $this->assertNotNull($tag->uuid);
        $this->assertSame('중요 고객', $tag->name);
        $this->assertSame('중요-고객', $tag->slug);
        $this->assertNull($tag->description);
        $this->assertSame('#A1B2C3', $tag->color);
    }

    public function test_duplicate_slug_is_rejected_even_when_original_tag_is_soft_deleted(): void
    {
        $tag = $this->service()->create(['name' => 'VIP']);
        $this->service()->delete($tag);

        $this->expectException(ValidationException::class);
        $this->service()->create(['name' => '다른 이름', 'slug' => 'vip']);
    }

    public function test_repository_search_sort_update_and_soft_delete(): void
    {
        $repository = $this->app->make(TagRepositoryInterface::class);
        $first = $repository->create([
            'name' => 'Alpha',
            'slug' => 'alpha',
            'description' => '중요 고객',
        ]);
        $repository->create(['name' => 'Beta', 'slug' => 'beta']);

        $result = $repository->paginate([
            'keyword' => '중요',
            'sort' => 'name',
            'direction' => 'desc',
        ], 10);
        $this->assertSame(1, $result->total());
        $this->assertSame($first->uuid, $result->items()[0]->uuid);

        $updated = $this->service()->update($first, [
            'name' => ' Alpha Updated ',
            'slug' => 'ALPHA UPDATED',
        ]);
        $this->assertSame('Alpha Updated', $updated->name);
        $this->assertSame('alpha-updated', $updated->slug);

        $this->assertTrue($this->service()->delete($updated));
        $this->assertNull($repository->findByUuid($updated->uuid));
        $this->assertNotNull(Tag::withTrashed()->where('uuid', $updated->uuid)->first());
    }

    public function test_contact_tag_attach_detach_and_sync_use_public_uuids(): void
    {
        $contact = Contact::create(['name' => '태그 고객']);
        $first = $this->service()->create(['name' => 'VIP']);
        $second = $this->service()->create(['name' => '재구매']);

        $attached = $this->service()->attachToContact($contact->uuid, $first->uuid);
        $this->service()->attachToContact($contact->uuid, $first->uuid);
        $this->assertSame([$first->uuid], $attached->fresh()->tags->pluck('uuid')->all());
        $this->assertSame(1, DB::table('crm_contact_tag')->count());

        $synced = $this->service()->syncForContact(
            $contact->uuid,
            [$second->uuid, $second->uuid],
        );
        $this->assertSame([$second->uuid], $synced->tags->pluck('uuid')->all());

        $detached = $this->service()->detachFromContact($contact->uuid, $second->uuid);
        $this->assertCount(0, $detached->tags);

        $this->service()->syncForContact($contact->uuid, [$first->uuid, $second->uuid]);
        $cleared = $this->service()->syncForContact($contact->uuid, []);
        $this->assertCount(0, $cleared->tags);
    }

    public function test_pivot_unique_constraint_and_cascade_delete(): void
    {
        $contact = Contact::create(['name' => '관계 고객']);
        $tag = $this->service()->create(['name' => '관계 태그']);

        DB::table('crm_contact_tag')->insert([
            'contact_id' => $contact->id,
            'tag_id' => $tag->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::table('crm_contact_tag')->insert([
                'contact_id' => $contact->id,
                'tag_id' => $tag->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('중복 Contact-Tag pivot가 허용되었습니다.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('crm_contact_tag')->count());
        }

        DB::table('crm_contacts')->where('id', $contact->id)->delete();
        $this->assertDatabaseMissing('crm_contact_tag', ['tag_id' => $tag->id]);
    }

    public function test_tag_soft_delete_preserves_pivot_but_hides_tag_from_contact_relation(): void
    {
        $contact = Contact::create(['name' => '보존 고객']);
        $tag = $this->service()->create(['name' => '보존 태그']);
        $this->service()->attachToContact($contact->uuid, $tag->uuid);

        $this->service()->delete($tag);

        $this->assertDatabaseHas('crm_contact_tag', [
            'contact_id' => $contact->id,
            'tag_id' => $tag->id,
        ]);
        $this->assertCount(0, $contact->fresh()->tags);
    }

    public function test_numeric_ids_are_not_accepted_by_public_relation_contract(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->attachToContact('1', '2');
    }

    public function test_invalid_values_and_unknown_tag_uuid_are_rejected_without_syncing(): void
    {
        $contact = Contact::create(['name' => '검증 고객']);

        foreach ([
            ['name' => '   '],
            ['name' => '색상', 'color' => 'red'],
            ['name' => '슬러그', 'slug' => '---'],
            ['name' => '설명', 'description' => ['invalid']],
        ] as $data) {
            try {
                $this->service()->create($data);
                $this->fail('유효하지 않은 Tag 입력이 허용되었습니다.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->expectException(ModelNotFoundException::class);
        $this->service()->syncForContact(
            $contact->uuid,
            ['00000000-0000-4000-8000-000000000000'],
        );
    }

    private function service(): TagService
    {
        return $this->app->make(TagService::class);
    }
}
