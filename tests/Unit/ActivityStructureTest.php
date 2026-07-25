<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Glitter\Crm\Enums\ActivityType;
use Modules\Glitter\Crm\Models\Activity;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Repositories\ActivityRepository;
use Modules\Glitter\Crm\Repositories\Contracts\ActivityRepositoryInterface;
use Modules\Glitter\Crm\Services\ActivityService;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

class ActivityStructureTest extends ModuleTestCase
{
    public function test_activity_data_layer_files_exist_without_activity_ui_additions(): void
    {
        foreach ([
            'database/migrations/2026_07_25_000004_create_crm_activities_table.php',
            'src/Enums/ActivityType.php',
            'src/Models/Activity.php',
            'src/Repositories/Contracts/ActivityRepositoryInterface.php',
            'src/Repositories/ActivityRepository.php',
            'src/Services/ActivityService.php',
        ] as $path) {
            $this->assertFileExists($this->moduleBasePath().'/'.$path);
        }

        $this->assertFileDoesNotExist(
            $this->moduleBasePath().'/resources/layouts/admin/admin_crm_activity_index.json',
        );
    }

    public function test_activity_type_contract_is_complete(): void
    {
        $this->assertSame([
            'note',
            'call',
            'email',
            'meeting',
            'status_change',
            'system',
        ], ActivityType::values());
    }

    public function test_activity_keeps_bigint_primary_key_and_uuid_as_public_identifier(): void
    {
        $activity = new Activity;

        $this->assertSame('crm_activities', $activity->getTable());
        $this->assertSame('id', $activity->getKeyName());
        $this->assertTrue($activity->getIncrementing());
        $this->assertSame('int', $activity->getKeyType());
        $this->assertSame(['uuid'], $activity->uniqueIds());
        $this->assertSame('uuid', $activity->getRouteKeyName());
        $this->assertContains(HasUuids::class, class_uses_recursive(Activity::class));
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Activity::class));
    }

    public function test_activity_relations_and_contact_relation_are_defined_without_default_order(): void
    {
        $activity = new Activity;
        $activities = (new Contact)->activities();

        $this->assertInstanceOf(BelongsTo::class, $activity->contact());
        $this->assertInstanceOf(BelongsTo::class, $activity->creator());
        $this->assertInstanceOf(HasMany::class, $activities);
        $this->assertSame([], $activities->getQuery()->getQuery()->orders ?? []);
    }

    public function test_provider_binds_activity_repository(): void
    {
        $this->assertInstanceOf(
            ActivityRepository::class,
            $this->app->make(ActivityRepositoryInterface::class),
        );
        $this->assertInstanceOf(ActivityService::class, $this->app->make(ActivityService::class));
    }

    public function test_activity_migration_rolls_back_before_contact(): void
    {
        $files = glob($this->moduleBasePath().'/database/migrations/*.php');
        rsort($files);

        $names = array_map('basename', $files);
        $this->assertLessThan(
            array_search('2026_07_25_000001_create_crm_contacts_table.php', $names, true),
            array_search('2026_07_25_000004_create_crm_activities_table.php', $names, true),
        );
        $this->assertSame(
            '2026_07_25_000001_create_crm_contacts_table.php',
            basename($files[array_key_last($files)]),
        );
    }

    public function test_activity_backend_translations_have_ko_en_parity(): void
    {
        $ko = require $this->moduleBasePath().'/src/lang/ko/module.php';
        $en = require $this->moduleBasePath().'/src/lang/en/module.php';

        $this->assertSame(
            array_keys($ko['enums']['activity_type']),
            array_keys($en['enums']['activity_type']),
        );
        $this->assertSame(
            array_keys($ko['validation']['activity']),
            array_keys($en['validation']['activity']),
        );
    }
}
