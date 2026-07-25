<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use Modules\Glitter\Crm\Module;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario contact-admin-ui
 */
class ContactAdminUiStructureTest extends ModuleTestCase
{
    public function test_contact_menu_points_to_a_registered_read_protected_layout(): void
    {
        $menu = (new Module)->getAdminMenus()[0];
        $routes = $this->readJson('resources/routes/admin.json');

        $this->assertTrue($menu['is_active']);
        $this->assertCount(2, $menu['children']);
        $this->assertSame('/admin/crm/contacts', $menu['children'][0]['url']);
        $this->assertSame('glitter-crm.contacts.read', $menu['children'][0]['permission']);
        $this->assertContains(
            '*/admin/crm/contacts',
            array_column($routes['routes'], 'path'),
        );
    }

    public function test_routes_and_layouts_use_uuid_and_supported_crm_apis_only(): void
    {
        $routes = $this->readJson('resources/routes/admin.json');
        $paths = array_column($routes['routes'], 'path');
        $index = file_get_contents($this->moduleBasePath().'/resources/layouts/admin/admin_crm_contact_index.json');
        $form = file_get_contents($this->moduleBasePath().'/resources/layouts/admin/admin_crm_contact_form.json');

        $this->assertSame([
            '*/admin/crm/contacts',
            '*/admin/crm/contacts/create',
            '*/admin/crm/contacts/:id/edit',
        ], array_slice($paths, 0, 3));
        $this->assertStringContainsString('"idField": "uuid"', $index);
        $this->assertStringContainsString('/api/modules/glitter-crm/admin/contacts', $index.$form);
        $this->assertStringContainsString('/api/admin/users/search', $index.$form);
        $this->assertStringNotContainsString('/admin/crm/dashboard', $index.$form);

        foreach (['leads', 'export', 'import', 'restore', 'force-delete'] as $feature) {
            $this->assertStringNotContainsString("/{$feature}", $index.$form);
        }
        $this->assertStringContainsString('/api/modules/glitter-crm/admin/tags', $form);
        $this->assertStringContainsString('/api/modules/glitter-crm/admin/contacts/{{route?.id}}/activities', $form);
        $this->assertStringContainsString('/api/modules/glitter-crm/admin/contacts/{{route?.id}}/tasks', $form);
        $this->assertNotContains('*/admin/crm/activities', $paths);
        $this->assertNotContains('*/admin/crm/tasks', $paths);
    }

    public function test_form_payload_excludes_internal_and_system_managed_fields(): void
    {
        $layout = file_get_contents($this->moduleBasePath().'/resources/layouts/admin/admin_crm_contact_form.json');
        $bodyStart = strpos($layout, '"body": "{{({name:');
        $bodyEnd = strpos($layout, '"onSuccess"', $bodyStart);

        $this->assertNotFalse($bodyStart);
        $this->assertNotFalse($bodyEnd);
        $body = substr($layout, $bodyStart, $bodyEnd - $bodyStart);
        $this->assertStringContainsString('next_action_at:', $body);
        $this->assertStringContainsString('tag_uuids:', $body);
        foreach (['"id"', '"uuid"', '"metadata"', '"last_activity_at"', '"deleted_at"'] as $field) {
            $this->assertStringNotContainsString($field, $body);
        }
    }

    public function test_frontend_translation_keys_have_ko_en_parity(): void
    {
        $ko = $this->readJson('resources/lang/ko.json');
        $en = $this->readJson('resources/lang/en.json');

        $this->assertSame(
            $this->leafKeys($ko['admin']['contacts']),
            $this->leafKeys($en['admin']['contacts']),
        );
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        return json_decode(
            file_get_contents($this->moduleBasePath().'/'.$path),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /** @return array<int, string> */
    private function leafKeys(array $values, string $prefix = ''): array
    {
        $keys = [];

        foreach ($values as $key => $value) {
            $path = ltrim($prefix.'.'.$key, '.');
            if (is_array($value)) {
                $keys = [...$keys, ...$this->leafKeys($value, $path)];
            } else {
                $keys[] = $path;
            }
        }

        sort($keys);

        return $keys;
    }
}
