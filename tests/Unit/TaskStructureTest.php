<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Glitter\Crm\Enums\TaskPriority;
use Modules\Glitter\Crm\Enums\TaskStatus;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Models\Task;
use Modules\Glitter\Crm\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Glitter\Crm\Repositories\TaskRepository;
use Modules\Glitter\Crm\Services\TaskService;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario task-data-layer
 */
class TaskStructureTest extends ModuleTestCase
{
    public function test_task_data_layer_files_exist_without_task_ui_or_side_effects(): void
    {
        foreach ([
            'database/migrations/2026_07_25_000005_add_next_task_at_to_crm_contacts_table.php',
            'database/migrations/2026_07_25_000006_create_crm_tasks_table.php',
            'src/Enums/TaskStatus.php',
            'src/Enums/TaskPriority.php',
            'src/Models/Task.php',
            'src/Repositories/Contracts/TaskRepositoryInterface.php',
            'src/Repositories/TaskRepository.php',
            'src/Services/TaskService.php',
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

        $adminRoutes = file_get_contents($this->moduleBasePath().'/resources/routes/admin.json');
        $this->assertStringNotContainsString('/admin/crm/tasks', $adminRoutes);
    }

    public function test_task_enum_contracts_and_translations_are_complete(): void
    {
        $this->assertSame(
            ['pending', 'in_progress', 'completed', 'cancelled'],
            TaskStatus::values(),
        );
        $this->assertSame(['pending', 'in_progress'], TaskStatus::activeValues());
        $this->assertSame(
            ['low', 'normal', 'high', 'urgent'],
            TaskPriority::values(),
        );

        $ko = require $this->moduleBasePath().'/src/lang/ko/module.php';
        $en = require $this->moduleBasePath().'/src/lang/en/module.php';
        foreach (['task_status', 'task_priority'] as $enum) {
            $this->assertSame(array_keys($ko['enums'][$enum]), array_keys($en['enums'][$enum]));
        }
        $this->assertSame(
            array_keys($ko['validation']['task']),
            array_keys($en['validation']['task']),
        );
    }

    public function test_task_keeps_bigint_primary_key_and_uuid_public_identifier(): void
    {
        $task = new Task;

        $this->assertSame('crm_tasks', $task->getTable());
        $this->assertSame('id', $task->getKeyName());
        $this->assertTrue($task->getIncrementing());
        $this->assertSame('int', $task->getKeyType());
        $this->assertSame(['uuid'], $task->uniqueIds());
        $this->assertSame('uuid', $task->getRouteKeyName());
        $this->assertContains(HasUuids::class, class_uses_recursive(Task::class));
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Task::class));
    }

    public function test_task_casts_and_relations_match_the_domain_contract(): void
    {
        $task = new Task;
        $tasks = (new Contact)->tasks();

        $this->assertSame(TaskStatus::class, $task->getCasts()['status']);
        $this->assertSame(TaskPriority::class, $task->getCasts()['priority']);
        $this->assertSame('datetime', $task->getCasts()['due_at']);
        $this->assertSame('datetime', $task->getCasts()['completed_at']);
        $this->assertSame('array', $task->getCasts()['metadata']);
        $this->assertInstanceOf(BelongsTo::class, $task->contact());
        $this->assertInstanceOf(BelongsTo::class, $task->assignee());
        $this->assertInstanceOf(HasMany::class, $tasks);
        $this->assertSame([], $tasks->getQuery()->getQuery()->orders ?? []);
    }

    public function test_provider_binds_task_repository_and_service(): void
    {
        $this->assertInstanceOf(
            TaskRepository::class,
            $this->app->make(TaskRepositoryInterface::class),
        );
        $this->assertInstanceOf(TaskService::class, $this->app->make(TaskService::class));
    }

    public function test_migration_order_drops_tasks_before_next_task_column_and_contact(): void
    {
        $files = array_map(
            'basename',
            glob($this->moduleBasePath().'/database/migrations/*.php'),
        );
        rsort($files);

        $this->assertSame('2026_07_25_000006_create_crm_tasks_table.php', $files[0]);
        $this->assertSame(
            '2026_07_25_000005_add_next_task_at_to_crm_contacts_table.php',
            $files[1],
        );
        $this->assertSame('2026_07_25_000001_create_crm_contacts_table.php', $files[array_key_last($files)]);
    }

    public function test_contact_write_layers_do_not_accept_next_task_at(): void
    {
        foreach ([
            'src/Services/ContactService.php',
            'src/Http/Requests/StoreContactRequest.php',
            'src/Http/Requests/UpdateContactRequest.php',
        ] as $path) {
            $contents = file_get_contents($this->moduleBasePath().'/'.$path);
            $this->assertStringNotContainsString("'next_task_at'", $contents);
        }
    }
}
