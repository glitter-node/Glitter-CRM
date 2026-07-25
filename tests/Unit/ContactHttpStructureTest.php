<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use App\Http\Controllers\Api\Base\AdminBaseController;
use App\Http\Resources\BaseApiCollection;
use App\Http\Resources\BaseApiResource;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Glitter\Crm\Http\Controllers\Admin\ContactController;
use Modules\Glitter\Crm\Http\Requests\IndexContactRequest;
use Modules\Glitter\Crm\Http\Requests\StoreContactRequest;
use Modules\Glitter\Crm\Http\Requests\UpdateContactRequest;
use Modules\Glitter\Crm\Http\Resources\ContactCollection;
use Modules\Glitter\Crm\Http\Resources\ContactResource;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario contact-admin-api
 */
class ContactHttpStructureTest extends ModuleTestCase
{
    public function test_http_classes_follow_project_base_classes(): void
    {
        $this->assertTrue(is_subclass_of(ContactController::class, AdminBaseController::class));
        $this->assertTrue(is_subclass_of(ContactResource::class, BaseApiResource::class));
        $this->assertTrue(is_subclass_of(ContactCollection::class, BaseApiCollection::class));

        foreach ([IndexContactRequest::class, StoreContactRequest::class, UpdateContactRequest::class] as $request) {
            $this->assertTrue(is_subclass_of($request, FormRequest::class));
        }
    }

    public function test_route_source_has_expected_permissions_without_duplicate_prefix(): void
    {
        $source = file_get_contents($this->moduleBasePath().'/src/routes/api.php');

        $this->assertStringNotContainsString("Route::prefix('api/modules/glitter-crm", $source);
        $this->assertStringContainsString("prefix('admin/contacts')", $source);
        $this->assertStringContainsString('permission:admin,glitter-crm.contacts.read', $source);
        $this->assertStringContainsString('permission:admin,glitter-crm.contacts.create', $source);
        $this->assertStringContainsString('permission:admin,glitter-crm.contacts.update', $source);
        $this->assertStringContainsString('permission:admin,glitter-crm.contacts.delete', $source);
        $this->assertStringContainsString("whereUuid('contact')", $source);
        $this->assertStringNotContainsString('Route::patch', $source);
    }

    public function test_resource_source_does_not_expose_internal_or_deleted_identifiers(): void
    {
        $source = file_get_contents($this->moduleBasePath().'/src/Http/Resources/ContactResource.php');

        $this->assertStringNotContainsString("'id' =>", $source);
        $this->assertStringNotContainsString("'deleted_at' =>", $source);
    }

    public function test_http_contract_uses_project_datetime_local_conversion_and_blocks_last_activity_writes(): void
    {
        $store = file_get_contents($this->moduleBasePath().'/src/Http/Requests/StoreContactRequest.php');
        $update = file_get_contents($this->moduleBasePath().'/src/Http/Requests/UpdateContactRequest.php');
        $resource = file_get_contents($this->moduleBasePath().'/src/Http/Resources/ContactResource.php');
        $service = file_get_contents($this->moduleBasePath().'/src/Services/ContactService.php');

        $this->assertStringContainsString('TimezoneHelper::fromSiteDateTime', $store);
        $this->assertStringContainsString('TimezoneHelper::fromSiteDateTime', $update);
        $this->assertStringContainsString('formatDateTimeLocalStringForSite', $resource);
        $this->assertStringNotContainsString("'last_activity_at',", $service);
    }

    public function test_no_future_lead_or_dashboard_api_routes_are_present(): void
    {
        $routeSource = file_get_contents($this->moduleBasePath().'/src/routes/api.php');
        foreach (['leads', 'dashboard'] as $resource) {
            $this->assertStringNotContainsString("admin/{$resource}", $routeSource);
        }
    }

    public function test_contact_tag_contract_uses_uuid_array_atomic_service_and_public_resource_fields(): void
    {
        $store = file_get_contents($this->moduleBasePath().'/src/Http/Requests/StoreContactRequest.php');
        $update = file_get_contents($this->moduleBasePath().'/src/Http/Requests/UpdateContactRequest.php');
        $service = file_get_contents($this->moduleBasePath().'/src/Services/ContactService.php');
        $repository = file_get_contents($this->moduleBasePath().'/src/Repositories/ContactRepository.php');
        $resource = file_get_contents($this->moduleBasePath().'/src/Http/Resources/ContactResource.php');

        foreach ([$store, $update] as $request) {
            $this->assertStringContainsString("'tag_uuids'", $request);
            $this->assertStringContainsString("'tag_uuids.*'", $request);
            $this->assertStringContainsString("'uuid'", $request);
            $this->assertStringContainsString("'distinct'", $request);
        }
        $this->assertStringContainsString('DB::transaction', $service);
        $this->assertStringContainsString('syncForContact', $service);
        $this->assertStringContainsString("'tags'", $repository);
        $this->assertStringContainsString("'tags' =>", $resource);
        $this->assertStringNotContainsString("'id' => \$tag", $resource);
        $this->assertStringNotContainsString("'pivot'", $resource);
    }
}
