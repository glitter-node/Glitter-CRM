<?php

namespace Modules\Glitter\Crm\Tests\Feature;

use App\Extension\ModuleManager;
use Modules\Glitter\Crm\Module;
use Modules\Glitter\Crm\Providers\CrmServiceProvider;
use Modules\Glitter\Crm\Repositories\ContactRepository;
use Modules\Glitter\Crm\Repositories\Contracts\ContactRepositoryInterface;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario module-lifecycle
 */
class ModuleSkeletonTest extends ModuleTestCase
{
    public function test_manifest_and_module_metadata_are_consistent(): void
    {
        $manifest = json_decode(
            file_get_contents($this->moduleBasePath().'/module.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $module = new Module;

        $this->assertSame('glitter-crm', $manifest['identifier']);
        $this->assertSame('glitter-crm', $module->getIdentifier());
        $this->assertSame('1.3.3', $manifest['version']);
        $this->assertSame('1.3.3', $module->getVersion());
        $this->assertSame('Glitter.kr', $manifest['vendor']);
        $this->assertSame('Glitter.kr', $module->getVendor());
        $this->assertSame('Glitter.kr', $module->getMetadata()['author']);
        $this->assertSame('>=7.0.5', $module->getRequiredCoreVersion());
        $this->assertSame(['modules' => [], 'plugins' => []], $module->getDependencies());
        $this->assertArrayNotHasKey('author', $manifest);
    }

    public function test_service_provider_loads_contact_repository_binding(): void
    {
        $provider = $this->app->getProvider(CrmServiceProvider::class);

        $this->assertInstanceOf(CrmServiceProvider::class, $provider);
        $this->assertInstanceOf(
            ContactRepository::class,
            $this->app->make(ContactRepositoryInterface::class),
        );
    }

    public function test_permissions_follow_module_identifier_and_action_conventions(): void
    {
        $module = new Module;
        $categories = $module->getPermissions()['categories'];
        $identifiers = [];

        foreach ($categories as $category) {
            foreach ($category['permissions'] as $permission) {
                $identifiers[] = 'glitter-crm.'.$category['identifier'].'.'.$permission['action'];
                $this->assertSame('admin', $permission['type']);
                $this->assertSame(['admin'], $permission['roles']);
            }
        }

        $this->assertSame([
            'glitter-crm.dashboard.read',
            'glitter-crm.contacts.read',
            'glitter-crm.contacts.create',
            'glitter-crm.contacts.update',
            'glitter-crm.contacts.delete',
            'glitter-crm.contacts.export',
            'glitter-crm.tags.read',
            'glitter-crm.tags.create',
            'glitter-crm.tags.update',
            'glitter-crm.tags.delete',
            'glitter-crm.activities.read',
            'glitter-crm.activities.create',
            'glitter-crm.activities.update',
            'glitter-crm.activities.delete',
            'glitter-crm.tasks.read',
            'glitter-crm.tasks.create',
            'glitter-crm.tasks.update',
            'glitter-crm.tasks.delete',
            'glitter-crm.tasks.assign',
            'glitter-crm.leads.read',
            'glitter-crm.leads.create',
            'glitter-crm.leads.update',
            'glitter-crm.leads.delete',
            'glitter-crm.settings.read',
            'glitter-crm.settings.update',
        ], $identifiers);
    }

    public function test_admin_menu_exposes_contact_and_tag_interfaces(): void
    {
        $menu = (new Module)->getAdminMenus()[0];

        $this->assertSame('glitter-crm', $menu['slug']);
        $this->assertNull($menu['url']);
        $this->assertTrue($menu['is_active']);
        $this->assertSame('fas fa-users', $menu['icon']);
        $this->assertCount(2, $menu['children']);
        $this->assertSame('glitter-crm-contacts', $menu['children'][0]['slug']);
        $this->assertSame('/admin/crm/contacts', $menu['children'][0]['url']);
        $this->assertSame('glitter-crm.contacts.read', $menu['children'][0]['permission']);
        $this->assertSame('glitter-crm-tags', $menu['children'][1]['slug']);
        $this->assertSame('/admin/crm/tags', $menu['children'][1]['url']);
        $this->assertSame('glitter-crm.tags.read', $menu['children'][1]['permission']);
    }

    public function test_backend_and_frontend_translations_are_complete_for_ko_and_en(): void
    {
        $module = new Module;
        $permissions = $module->getPermissions();

        foreach (['ko', 'en'] as $locale) {
            $backend = require $this->moduleBasePath()."/src/lang/{$locale}/module.php";
            $frontend = json_decode(
                file_get_contents($this->moduleBasePath()."/resources/lang/{$locale}.json"),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            $this->assertSame('CRM', $backend['name']);
            $this->assertSame($backend['name'], $frontend['module']['name']);
            $this->assertSame($backend['description'], $frontend['module']['description']);
            $this->assertSame(['contact', 'lead', 'activity', 'task', 'tag'], array_keys($backend['entities']));
            $this->assertSame(array_keys($backend['entities']), array_keys($frontend['entities']));
            $this->assertNotEmpty($permissions['name'][$locale]);
            $this->assertNotEmpty($permissions['description'][$locale]);
        }
    }

    public function test_module_manager_discovers_bundled_manifest(): void
    {
        $manager = $this->app->make(ModuleManager::class);
        $manager->loadModules();

        $bundled = $manager->getBundledModules();

        $this->assertArrayHasKey('glitter-crm', $bundled);
        $this->assertSame('1.3.3', $bundled['glitter-crm']['version']);
        $this->assertSame('Glitter.kr', $bundled['glitter-crm']['vendor']);
    }

    public function test_current_module_contains_activity_api_without_activity_ui(): void
    {
        $this->assertFileExists($this->moduleBasePath().'/src/routes/api.php');
        $this->assertFileExists($this->moduleBasePath().'/src/Http/Controllers/Admin/ContactController.php');
        $this->assertFileExists($this->moduleBasePath().'/resources/routes/admin.json');
        $this->assertFileExists($this->moduleBasePath().'/resources/layouts/admin/admin_crm_contact_index.json');
        $this->assertFileExists($this->moduleBasePath().'/resources/layouts/admin/admin_crm_contact_form.json');

        $this->assertFileExists($this->moduleBasePath().'/src/Models/Tag.php');
        $this->assertFileExists($this->moduleBasePath().'/src/Models/Activity.php');
        $this->assertFileExists(
            $this->moduleBasePath().'/src/Http/Controllers/Admin/ActivityController.php',
        );
        $this->assertFileDoesNotExist(
            $this->moduleBasePath().'/resources/layouts/admin/admin_crm_activity_index.json',
        );

        $this->assertFileExists($this->moduleBasePath().'/src/Models/Task.php');
        $this->assertFileDoesNotExist($this->moduleBasePath().'/src/Models/Lead.php');
        foreach (['Lead'] as $class) {
            $this->assertFileDoesNotExist($this->moduleBasePath()."/src/Http/Controllers/Admin/{$class}Controller.php");
        }
        $this->assertFileExists($this->moduleBasePath().'/src/Http/Controllers/Admin/TaskController.php');
        $this->assertFileDoesNotExist(
            $this->moduleBasePath().'/resources/layouts/admin/admin_crm_task_index.json',
        );
    }
}
