<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use Modules\Glitter\Crm\Module;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario task-admin-ui
 */
class TaskAdminUiStructureTest extends ModuleTestCase
{
    public function test_task_is_embedded_only_in_the_contact_edit_layout(): void
    {
        $layout = $this->layout();
        $routes = $this->readJson('resources/routes/admin.json');
        $paths = array_column($routes['routes'], 'path');
        $menu = (new Module)->getAdminMenus()[0];

        $this->assertStringContainsString('"id": "contact_task_section"', $layout);
        $this->assertStringContainsString('"if": "{{!!route?.id', $layout);
        $this->assertStringContainsString('/admin/contacts/{{route?.id}}/tasks', $layout);
        $this->assertNotContains('*/admin/crm/tasks', $paths);
        $this->assertCount(2, $menu['children']);
        $this->assertFileDoesNotExist($this->moduleBasePath().'/resources/layouts/admin/admin_crm_task_index.json');
        $this->assertFileDoesNotExist($this->moduleBasePath().'/resources/layouts/admin/admin_crm_task_form.json');
    }

    public function test_task_contract_uses_uuid_abilities_and_safe_payloads(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString('/admin/tasks/{{_local.taskDeleteTarget}}', $layout);
        $this->assertStringContainsString('task.abilities?.can_update', $layout);
        $this->assertStringContainsString('task.abilities?.can_delete', $layout);
        $this->assertStringContainsString('tasks?.data?.abilities?.can_create', $layout);
        $this->assertStringContainsString('_local.taskFilters?.overdue === true ? true : null', $layout);
        $this->assertStringContainsString('new Date(_local.taskForm.due_at).toISOString()', $layout);
        $this->assertStringContainsString('task.is_overdue === true', $layout);

        $modalStart = strpos($layout, '"id": "task_form_modal"');
        $modalEnd = strpos($layout, '"id": "delete_task_modal"', $modalStart);
        $modal = substr($layout, $modalStart, $modalEnd - $modalStart);
        foreach (['completed_at', 'metadata', 'contact_id', 'assigned_to"'] as $field) {
            $this->assertStringNotContainsString('"'.$field, $modal);
        }
        $this->assertStringContainsString('contact_uuid:route.id', $modal);
    }

    public function test_task_translation_keys_have_recursive_parity(): void
    {
        $ko = $this->readJson('resources/lang/ko.json');
        $en = $this->readJson('resources/lang/en.json');

        $this->assertSame(
            $this->leafKeys($ko['admin']['tasks']),
            $this->leafKeys($en['admin']['tasks']),
        );
    }

    public function test_task_ui_adds_no_asset_or_runtime_surface(): void
    {
        $this->assertDirectoryDoesNotExist($this->moduleBasePath().'/resources/css');
        $runtimeFiles = array_filter(glob($this->moduleBasePath().'/resources/js/*') ?: [], 'is_file');
        $this->assertSame([], array_values($runtimeFiles));
    }

    private function layout(): string
    {
        return file_get_contents($this->moduleBasePath().'/resources/layouts/admin/admin_crm_contact_form.json');
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        return json_decode(file_get_contents($this->moduleBasePath().'/'.$path), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<int, string> */
    private function leafKeys(array $values, string $prefix = ''): array
    {
        $keys = [];
        foreach ($values as $key => $value) {
            $path = ltrim($prefix.'.'.$key, '.');
            $keys = is_array($value)
                ? [...$keys, ...$this->leafKeys($value, $path)]
                : [...$keys, $path];
        }
        sort($keys);

        return $keys;
    }
}
