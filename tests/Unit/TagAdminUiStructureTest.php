<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use Modules\Glitter\Crm\Module;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario tag-admin-ui
 */
class TagAdminUiStructureTest extends ModuleTestCase
{
    public function test_tag_menu_and_typed_routes_are_read_protected_and_static_first(): void
    {
        $menu = (new Module)->getAdminMenus()[0];
        $routes = $this->readJson('resources/routes/admin.json')['routes'];
        $paths = array_column($routes, 'path');

        $this->assertSame('glitter-crm-tags', $menu['children'][1]['slug']);
        $this->assertSame('/admin/crm/tags', $menu['children'][1]['url']);
        $this->assertSame('glitter-crm.tags.read', $menu['children'][1]['permission']);
        $this->assertSame([
            '*/admin/crm/tags',
            '*/admin/crm/tags/create',
            '*/admin/crm/tags/:id/edit',
        ], array_slice($paths, 3));
    }

    public function test_tag_layouts_use_uuid_api_abilities_and_supported_components(): void
    {
        $index = file_get_contents($this->moduleBasePath().'/resources/layouts/admin/admin_crm_tag_index.json');
        $form = file_get_contents($this->moduleBasePath().'/resources/layouts/admin/admin_crm_tag_form.json');

        $this->assertStringContainsString('"idField": "uuid"', $index);
        $this->assertStringContainsString('tags?.data?.abilities?.can_create', $index);
        $this->assertStringContainsString('abilities.can_update', $index);
        $this->assertStringContainsString('abilities.can_delete', $index);
        $this->assertStringContainsString('/api/modules/glitter-crm/admin/tags/{{_local.deleteUuid}}', $index);
        $this->assertStringContainsString('"dataSourceId": "tags"', $index);
        $this->assertStringContainsString('/api/modules/glitter-crm/admin/tags/{{route?.id}}', $form);
        $this->assertStringContainsString('tag?.data?.abilities?.can_update', $form);
        $this->assertStringContainsString('tag?.data?.abilities?.can_delete', $form);
        $this->assertStringContainsString('"name": "Textarea"', $form);
        $this->assertStringNotContainsString('/restore', $index.$form);
        $this->assertStringNotContainsString('/force-delete', $index.$form);
    }

    public function test_delete_refetches_before_modal_context_is_closed(): void
    {
        $index = file_get_contents($this->moduleBasePath().'/resources/layouts/admin/admin_crm_tag_index.json');
        $refetch = strpos($index, '"handler": "refetchDataSource"');
        $close = strpos($index, '"handler": "closeModal"', $refetch);

        $this->assertNotFalse($refetch);
        $this->assertNotFalse($close);
        $this->assertLessThan($close, $refetch);
    }

    public function test_frontend_translations_have_parity_and_no_tag_assets_exist(): void
    {
        $ko = $this->readJson('resources/lang/ko.json');
        $en = $this->readJson('resources/lang/en.json');

        $this->assertSame($this->leafKeys($ko['admin']['tags']), $this->leafKeys($en['admin']['tags']));
        $this->assertFileDoesNotExist($this->moduleBasePath().'/resources/js/tag.ts');
        $this->assertFileDoesNotExist($this->moduleBasePath().'/resources/css/tag.css');
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
            $keys = is_array($value)
                ? [...$keys, ...$this->leafKeys($value, $path)]
                : [...$keys, $path];
        }

        sort($keys);

        return $keys;
    }
}
