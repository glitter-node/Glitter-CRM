<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Models\Tag;
use Modules\Glitter\Crm\Repositories\Contracts\TagRepositoryInterface;
use Modules\Glitter\Crm\Repositories\TagRepository;
use Modules\Glitter\Crm\Services\TagService;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

class TagStructureTest extends ModuleTestCase
{
    public function test_tag_data_layer_and_admin_layout_files_exist(): void
    {
        foreach ([
            'database/migrations/2026_07_25_000002_create_crm_tags_table.php',
            'database/migrations/2026_07_25_000003_create_crm_contact_tag_table.php',
            'src/Models/Tag.php',
            'src/Repositories/Contracts/TagRepositoryInterface.php',
            'src/Repositories/TagRepository.php',
            'src/Services/TagService.php',
        ] as $path) {
            $this->assertFileExists($this->moduleBasePath().'/'.$path);
        }

        $this->assertFileExists(
            $this->moduleBasePath().'/resources/layouts/admin/admin_crm_tag_index.json',
        );
        $this->assertFileExists(
            $this->moduleBasePath().'/resources/layouts/admin/admin_crm_tag_form.json',
        );
    }

    public function test_tag_keeps_bigint_primary_key_and_uuid_as_public_identifier(): void
    {
        $tag = new Tag;

        $this->assertSame('crm_tags', $tag->getTable());
        $this->assertSame('id', $tag->getKeyName());
        $this->assertTrue($tag->getIncrementing());
        $this->assertSame('int', $tag->getKeyType());
        $this->assertSame(['uuid'], $tag->uniqueIds());
        $this->assertSame('uuid', $tag->getRouteKeyName());
        $this->assertContains(HasUuids::class, class_uses_recursive(Tag::class));
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Tag::class));
    }

    public function test_contact_and_tag_define_timestamped_many_to_many_relations(): void
    {
        $contactRelation = (new Contact)->tags();
        $tagRelation = (new Tag)->contacts();

        $this->assertInstanceOf(BelongsToMany::class, $contactRelation);
        $this->assertInstanceOf(BelongsToMany::class, $tagRelation);
        $this->assertSame('crm_contact_tag', $contactRelation->getTable());
        $this->assertSame('crm_contact_tag', $tagRelation->getTable());
        $this->assertTrue($contactRelation->withTimestamps);
        $this->assertTrue($tagRelation->withTimestamps);
    }

    public function test_provider_binds_tag_repository_without_replacing_contact_binding(): void
    {
        $this->assertInstanceOf(
            TagRepository::class,
            $this->app->make(TagRepositoryInterface::class),
        );
        $this->assertInstanceOf(TagService::class, $this->app->make(TagService::class));
    }

    public function test_migration_filenames_make_pivot_roll_back_before_tag_and_contact_tables(): void
    {
        $files = glob($this->moduleBasePath().'/database/migrations/*.php');
        rsort($files);

        $this->assertSame([
            '2026_07_25_000006_create_crm_tasks_table.php',
            '2026_07_25_000005_add_next_task_at_to_crm_contacts_table.php',
            '2026_07_25_000004_create_crm_activities_table.php',
            '2026_07_25_000003_create_crm_contact_tag_table.php',
            '2026_07_25_000002_create_crm_tags_table.php',
            '2026_07_25_000001_create_crm_contacts_table.php',
        ], array_map('basename', $files));
    }

    public function test_backend_tag_validation_translations_have_ko_en_parity(): void
    {
        $ko = require $this->moduleBasePath().'/src/lang/ko/module.php';
        $en = require $this->moduleBasePath().'/src/lang/en/module.php';

        $this->assertSame(
            array_keys($ko['validation']['tag']),
            array_keys($en['validation']['tag']),
        );
    }
}
