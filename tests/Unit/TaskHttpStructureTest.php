<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use App\Http\Controllers\Api\Base\AdminBaseController;
use App\Http\Resources\BaseApiCollection;
use App\Http\Resources\BaseApiResource;
use Modules\Glitter\Crm\Http\Controllers\Admin\TaskController;
use Modules\Glitter\Crm\Http\Resources\TaskCollection;
use Modules\Glitter\Crm\Http\Resources\TaskResource;
use Modules\Glitter\Crm\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

class TaskHttpStructureTest extends ModuleTestCase
{
    public function test_contact_resource_exposes_server_managed_next_task_at(): void
    {
        $resource = file_get_contents(
            $this->moduleBasePath().'/src/Http/Resources/ContactResource.php',
        );

        $this->assertIsString($resource);
        $this->assertStringContainsString(
            "'next_task_at' => \$this->next_task_at?->utc()->toISOString()",
            $resource,
        );
    }

    public function test_task_http_files_exist_without_ui_or_runtime_side_effects(): void
    {
        foreach ([
            'src/Http/Controllers/Admin/TaskController.php',
            'src/Http/Requests/IndexTaskRequest.php',
            'src/Http/Requests/IndexContactTaskRequest.php',
            'src/Http/Requests/StoreTaskRequest.php',
            'src/Http/Requests/UpdateTaskRequest.php',
            'src/Http/Resources/TaskResource.php',
            'src/Http/Resources/TaskCollection.php',
        ] as $path) {
            $this->assertFileExists($this->moduleBasePath().'/'.$path);
        }

        foreach ([
            'resources/layouts/admin/admin_crm_task_index.json',
            'src/Observers/TaskObserver.php',
            'src/Events/TaskCompleted.php',
            'src/Listeners/CreateTaskActivity.php',
            'src/Jobs/SendTaskReminder.php',
        ] as $path) {
            $this->assertFileDoesNotExist($this->moduleBasePath().'/'.$path);
        }
    }

    public function test_http_classes_use_project_base_contracts(): void
    {
        $this->assertTrue(is_subclass_of(TaskController::class, AdminBaseController::class));
        $this->assertTrue(is_subclass_of(TaskResource::class, BaseApiResource::class));
        $this->assertTrue(is_subclass_of(TaskCollection::class, BaseApiCollection::class));
    }

    public function test_route_surface_is_crud_plus_contact_nested_get_only(): void
    {
        $routes = file_get_contents($this->moduleBasePath().'/src/routes/api.php');

        $this->assertStringContainsString("Route::prefix('admin/tasks')", $routes);
        $this->assertStringContainsString(
            "Route::get('/{contact}/tasks', [TaskController::class, 'contactIndex'])",
            $routes,
        );
        $this->assertSame(3, substr_count($routes, "whereUuid('task')"));
        foreach (['read', 'create', 'update', 'delete'] as $action) {
            $this->assertStringContainsString(
                "permission:admin,glitter-crm.tasks.{$action}",
                $routes,
            );
        }

        foreach ([
            'Route::patch',
            "Route::post('/{contact}/tasks'",
            '/complete',
            '/reopen',
            '/cancel',
            '/assign',
            'restore',
            'force-delete',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $routes);
        }
    }

    public function test_request_sort_allowlist_and_enum_values_are_single_source(): void
    {
        $request = file_get_contents(
            $this->moduleBasePath().'/src/Http/Requests/IndexTaskRequest.php',
        );
        $this->assertStringContainsString(
            'TaskRepositoryInterface::SORTABLE_COLUMNS',
            $request,
        );
        $this->assertStringContainsString('Rule::enum(TaskStatus::class)', $request);
        $this->assertStringContainsString('Rule::enum(TaskPriority::class)', $request);
        $this->assertSame([
            'due_at',
            'created_at',
            'updated_at',
            'status',
            'priority',
            'title',
            'completed_at',
        ], TaskRepositoryInterface::SORTABLE_COLUMNS);
    }

    public function test_internal_and_system_managed_fields_are_not_http_inputs_or_outputs(): void
    {
        $store = file_get_contents($this->moduleBasePath().'/src/Http/Requests/StoreTaskRequest.php');
        $update = file_get_contents($this->moduleBasePath().'/src/Http/Requests/UpdateTaskRequest.php');
        $resource = file_get_contents($this->moduleBasePath().'/src/Http/Resources/TaskResource.php');

        foreach ([
            "'id'",
            "'uuid'",
            "'contact_id'",
            "'assigned_to'",
            "'completed_at'",
            "'next_task_at'",
            "'deleted_at'",
        ] as $field) {
            $this->assertStringNotContainsString($field.' =>', $store);
            $this->assertStringNotContainsString($field.' =>', $update);
        }
        foreach (["'id' =>", "'contact_id' =>", "'assigned_to' =>", "'deleted_at' =>"] as $field) {
            $this->assertStringNotContainsString($field, $resource);
        }
    }
}
