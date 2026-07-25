<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Glitter\Crm\Enums\ContactStatus;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Repositories\ContactRepository;
use Modules\Glitter\Crm\Repositories\Contracts\ContactRepositoryInterface;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario contact-data-layer
 */
class ContactStructureTest extends ModuleTestCase
{
    public function test_contact_status_values_and_translation_keys_are_complete(): void
    {
        $this->assertSame(['active', 'inactive', 'archived'], ContactStatus::values());

        foreach (['ko', 'en'] as $locale) {
            $translations = require $this->moduleBasePath()."/src/lang/{$locale}/module.php";

            $this->assertSame(
                ContactStatus::values(),
                array_keys($translations['enums']['contact_status']),
            );
        }
    }

    public function test_contact_model_declares_public_uuid_and_expected_casts(): void
    {
        $contact = new Contact;

        $this->assertSame('crm_contacts', $contact->getTable());
        $this->assertSame('uuid', $contact->getRouteKeyName());
        $this->assertSame(['uuid'], $contact->uniqueIds());
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Contact::class));
        $this->assertSame(ContactStatus::class, $contact->getCasts()['status']);
        $this->assertSame('array', $contact->getCasts()['metadata']);
        $this->assertSame('datetime', $contact->getCasts()['last_activity_at']);
        $this->assertSame('datetime', $contact->getCasts()['next_action_at']);
        $this->assertSame('datetime', $contact->getCasts()['next_task_at']);
    }

    public function test_contact_repository_contract_is_bound_to_implementation(): void
    {
        $this->assertInstanceOf(
            ContactRepository::class,
            $this->app->make(ContactRepositoryInterface::class),
        );
    }

    public function test_contact_baseline_has_current_domains_without_future_http_surfaces(): void
    {
        $this->assertFileExists($this->moduleBasePath().'/src/routes/api.php');
        $this->assertFileExists($this->moduleBasePath().'/src/Http/Controllers/Admin/ContactController.php');
        $this->assertFileExists($this->moduleBasePath().'/resources/routes/admin.json');
        $this->assertFileExists($this->moduleBasePath().'/resources/layouts/admin/admin_crm_contact_index.json');
        $this->assertFileExists($this->moduleBasePath().'/resources/layouts/admin/admin_crm_contact_form.json');

        $this->assertFileExists($this->moduleBasePath().'/src/Models/Activity.php');
        $this->assertFileExists($this->moduleBasePath().'/src/Models/Task.php');
        $this->assertFileDoesNotExist($this->moduleBasePath().'/src/Models/Lead.php');
        $this->assertFileExists($this->moduleBasePath().'/src/Http/Controllers/Admin/TaskController.php');
        $this->assertFileDoesNotExist(
            $this->moduleBasePath().'/resources/layouts/admin/admin_crm_task_index.json',
        );
        $this->assertFileDoesNotExist($this->moduleBasePath().'/src/Http/Controllers/Admin/LeadController.php');
    }
}
