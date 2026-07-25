<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use App\Http\Controllers\Api\Base\AdminBaseController;
use App\Http\Resources\BaseApiCollection;
use App\Http\Resources\BaseApiResource;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Glitter\Crm\Http\Controllers\Admin\TagController;
use Modules\Glitter\Crm\Http\Requests\IndexTagRequest;
use Modules\Glitter\Crm\Http\Requests\StoreTagRequest;
use Modules\Glitter\Crm\Http\Requests\UpdateTagRequest;
use Modules\Glitter\Crm\Http\Resources\TagCollection;
use Modules\Glitter\Crm\Http\Resources\TagResource;
use Modules\Glitter\Crm\Module;
use Modules\Glitter\Crm\Repositories\Contracts\TagRepositoryInterface;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario tag-admin-api
 */
class TagHttpStructureTest extends ModuleTestCase
{
    public function test_http_classes_follow_contact_api_base_classes(): void
    {
        $this->assertTrue(is_subclass_of(TagController::class, AdminBaseController::class));
        $this->assertTrue(is_subclass_of(TagResource::class, BaseApiResource::class));
        $this->assertTrue(is_subclass_of(TagCollection::class, BaseApiCollection::class));

        foreach ([IndexTagRequest::class, StoreTagRequest::class, UpdateTagRequest::class] as $request) {
            $this->assertTrue(is_subclass_of($request, FormRequest::class));
        }
    }

    public function test_route_source_has_tag_crud_permissions_and_uuid_constraint_only(): void
    {
        $source = file_get_contents($this->moduleBasePath().'/src/routes/api.php');

        $this->assertStringContainsString("prefix('admin/tags')", $source);
        $this->assertStringContainsString('permission:admin,glitter-crm.tags.read', $source);
        $this->assertStringContainsString('permission:admin,glitter-crm.tags.create', $source);
        $this->assertStringContainsString('permission:admin,glitter-crm.tags.update', $source);
        $this->assertStringContainsString('permission:admin,glitter-crm.tags.delete', $source);
        $this->assertStringContainsString("whereUuid('tag')", $source);
        $this->assertStringNotContainsString('Route::patch', $source);

        foreach (['restore', 'force-delete', 'attach', 'detach', 'sync', 'contacts/{contact}/tags'] as $path) {
            $this->assertStringNotContainsString($path, $source);
        }
    }

    public function test_request_sort_allowlist_is_repository_single_source_of_truth(): void
    {
        $request = file_get_contents($this->moduleBasePath().'/src/Http/Requests/IndexTagRequest.php');

        $this->assertStringContainsString('TagRepositoryInterface::SORTABLE_COLUMNS', $request);
        $this->assertSame([
            'name',
            'slug',
            'created_at',
            'updated_at',
        ], TagRepositoryInterface::SORTABLE_COLUMNS);
    }

    public function test_resource_exposes_only_public_tag_fields_and_expected_abilities(): void
    {
        $resource = file_get_contents($this->moduleBasePath().'/src/Http/Resources/TagResource.php');
        $collection = file_get_contents($this->moduleBasePath().'/src/Http/Resources/TagCollection.php');

        foreach (['uuid', 'name', 'slug', 'description', 'color'] as $field) {
            $this->assertStringContainsString("'{$field}' =>", $resource);
        }
        foreach (['id', 'deleted_at', 'contact_id', 'tag_id'] as $field) {
            $this->assertStringNotContainsString("'{$field}' =>", $resource);
        }

        $this->assertStringContainsString("'can_update' => 'glitter-crm.tags.update'", $resource);
        $this->assertStringContainsString("'can_delete' => 'glitter-crm.tags.delete'", $resource);
        $this->assertStringContainsString("'can_create' => 'glitter-crm.tags.create'", $collection);
    }

    public function test_tag_ui_uses_existing_layout_engine_without_new_runtime_assets(): void
    {
        $menu = (new Module)->getAdminMenus();
        $children = $menu[0]['children'] ?? [];

        $this->assertSame(['glitter-crm-contacts', 'glitter-crm-tags'], array_column($children, 'slug'));
        $this->assertFileExists($this->moduleBasePath().'/resources/layouts/admin/admin_crm_tag_index.json');
        $this->assertFileExists($this->moduleBasePath().'/resources/layouts/admin/admin_crm_tag_form.json');
        $this->assertFileDoesNotExist($this->moduleBasePath().'/resources/js/tag.ts');
        $this->assertFileDoesNotExist($this->moduleBasePath().'/resources/css/tag.css');
    }
}
