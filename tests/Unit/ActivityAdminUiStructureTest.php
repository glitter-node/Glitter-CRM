<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use Modules\Glitter\Crm\Module;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario activity-admin-ui
 */
class ActivityAdminUiStructureTest extends ModuleTestCase
{
    public function test_activity_is_embedded_only_in_the_contact_edit_layout(): void
    {
        $layout = $this->layout();
        $routes = $this->readJson('resources/routes/admin.json');
        $paths = array_column($routes['routes'], 'path');
        $menu = (new Module)->getAdminMenus()[0];

        $this->assertStringContainsString('"id": "contact_activity_timeline"', $layout);
        $this->assertStringContainsString('"if": "{{!!route?.id', $layout);
        $this->assertStringContainsString('/admin/contacts/{{route?.id}}/activities', $layout);
        $this->assertNotContains('*/admin/crm/activities', $paths);
        $this->assertCount(2, $menu['children']);
    }

    public function test_activity_http_contract_uses_uuid_abilities_and_no_system_write_option(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString('/admin/activities/{{_local.activityDeleteTarget}}', $layout);
        $this->assertStringContainsString('activity.abilities?.can_update', $layout);
        $this->assertStringContainsString('activity.abilities?.can_delete', $layout);
        $this->assertStringContainsString('activities?.data?.abilities?.can_create', $layout);
        $this->assertStringContainsString('new Date(_local.activityForm.occurred_at).toISOString()', $layout);
        $this->assertStringContainsString("| datetime('YYYY-MM-DD HH:mm')", $layout);

        $modalStart = strpos($layout, '"id": "activity_form_modal"');
        $modalEnd = strpos($layout, '"id": "delete_activity_modal"', $modalStart);
        $modal = substr($layout, $modalStart, $modalEnd - $modalStart);
        $this->assertStringNotContainsString('"value": "system"', $modal);
        foreach (['metadata', 'created_by', 'creator_uuid', 'contact_id'] as $field) {
            $this->assertStringNotContainsString('"'.$field.'"', $modal);
        }
    }

    public function test_activity_translation_keys_have_recursive_parity(): void
    {
        $ko = $this->readJson('resources/lang/ko.json');
        $en = $this->readJson('resources/lang/en.json');

        $this->assertSame(
            $this->leafKeys($ko['admin']['activities']),
            $this->leafKeys($en['admin']['activities']),
        );
    }

    public function test_activity_ui_adds_no_independent_asset_or_runtime_surface(): void
    {
        $this->assertDirectoryDoesNotExist($this->moduleBasePath().'/resources/css');
        $this->assertFileDoesNotExist($this->moduleBasePath().'/resources/layouts/admin/admin_crm_activity_index.json');
        $this->assertFileDoesNotExist($this->moduleBasePath().'/resources/layouts/admin/admin_crm_activity_form.json');

        $runtimeFiles = array_filter(
            glob($this->moduleBasePath().'/resources/js/*') ?: [],
            'is_file',
        );
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
