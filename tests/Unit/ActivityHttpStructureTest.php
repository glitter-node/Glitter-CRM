<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use App\Http\Controllers\Api\Base\AdminBaseController;
use App\Http\Resources\BaseApiCollection;
use App\Http\Resources\BaseApiResource;
use Modules\Glitter\Crm\Http\Controllers\Admin\ActivityController;
use Modules\Glitter\Crm\Http\Resources\ActivityCollection;
use Modules\Glitter\Crm\Http\Resources\ActivityResource;
use Modules\Glitter\Crm\Repositories\Contracts\ActivityRepositoryInterface;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

class ActivityHttpStructureTest extends ModuleTestCase
{
    public function test_activity_http_layer_files_exist_without_activity_ui(): void
    {
        foreach ([
            'src/Http/Controllers/Admin/ActivityController.php',
            'src/Http/Requests/IndexActivityRequest.php',
            'src/Http/Requests/IndexContactActivityRequest.php',
            'src/Http/Requests/StoreActivityRequest.php',
            'src/Http/Requests/UpdateActivityRequest.php',
            'src/Http/Resources/ActivityResource.php',
            'src/Http/Resources/ActivityCollection.php',
        ] as $path) {
            $this->assertFileExists($this->moduleBasePath().'/'.$path);
        }

        foreach ([
            'resources/layouts/admin/admin_crm_activity_index.json',
            'resources/layouts/admin/admin_crm_activity_form.json',
        ] as $path) {
            $this->assertFileDoesNotExist($this->moduleBasePath().'/'.$path);
        }
    }

    public function test_http_classes_follow_project_base_contracts(): void
    {
        $this->assertTrue(is_subclass_of(ActivityController::class, AdminBaseController::class));
        $this->assertTrue(is_subclass_of(ActivityResource::class, BaseApiResource::class));
        $this->assertTrue(is_subclass_of(ActivityCollection::class, BaseApiCollection::class));
    }

    public function test_route_surface_has_only_crud_and_nested_contact_get(): void
    {
        $routes = file_get_contents($this->moduleBasePath().'/src/routes/api.php');

        $this->assertStringContainsString("Route::prefix('admin/activities')", $routes);
        $this->assertStringContainsString(
            "Route::get('/{contact}/activities', [ActivityController::class, 'contactIndex'])",
            $routes,
        );
        $this->assertSame(3, substr_count($routes, "whereUuid('activity')"));
        $this->assertSame(2, substr_count($routes, "whereUuid('contact')") - 3);
        $this->assertStringNotContainsString('Route::patch', $routes);
        $this->assertStringNotContainsString('restore', $routes);
        $this->assertStringNotContainsString('force-delete', $routes);
        $this->assertStringNotContainsString(
            "Route::post('/{contact}/activities'",
            $routes,
        );
    }

    public function test_activity_endpoint_permissions_are_separated(): void
    {
        $routes = file_get_contents($this->moduleBasePath().'/src/routes/api.php');

        foreach (['read', 'create', 'update', 'delete'] as $action) {
            $this->assertStringContainsString(
                "permission:admin,glitter-crm.activities.{$action}",
                $routes,
            );
        }
        $this->assertStringContainsString(
            "middleware(['auth:sanctum', 'admin', 'throttle:600,1'])",
            $routes,
        );
    }

    public function test_request_and_repository_sort_allowlists_have_one_source(): void
    {
        $this->assertSame([
            'occurred_at',
            'created_at',
            'updated_at',
            'type',
        ], ActivityRepositoryInterface::SORTABLE_COLUMNS);

        $request = file_get_contents(
            $this->moduleBasePath().'/src/Http/Requests/IndexActivityRequest.php',
        );
        $this->assertStringContainsString(
            'ActivityRepositoryInterface::SORTABLE_COLUMNS',
            $request,
        );
    }

    public function test_creator_and_internal_fields_are_not_request_inputs_or_resource_outputs(): void
    {
        $store = file_get_contents(
            $this->moduleBasePath().'/src/Http/Requests/StoreActivityRequest.php',
        );
        $update = file_get_contents(
            $this->moduleBasePath().'/src/Http/Requests/UpdateActivityRequest.php',
        );
        $resource = file_get_contents(
            $this->moduleBasePath().'/src/Http/Resources/ActivityResource.php',
        );

        foreach (["'created_by'", "'created_by_uuid'", "'id'", "'uuid'"] as $forbidden) {
            $this->assertStringNotContainsString($forbidden.' =>', $store);
            $this->assertStringNotContainsString($forbidden.' =>', $update);
        }
        $this->assertStringNotContainsString("'id' =>", $resource);
        $this->assertStringNotContainsString("'deleted_at' =>", $resource);
        $this->assertStringNotContainsString("'contact_id' =>", $resource);
        $this->assertStringNotContainsString("'created_by' =>", $resource);
    }
}
