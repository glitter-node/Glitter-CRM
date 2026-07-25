<?php

namespace Modules\Glitter\Crm\Tests\Feature;

use App\Enums\ExtensionOwnerType;
use App\Enums\ExtensionStatus;
use App\Extension\ModuleManager;
use App\Models\Menu;
use App\Models\Module as ModuleRecord;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario module-lifecycle
 */
class ModuleLifecycleTest extends ModuleTestCase
{
    use RefreshDatabase;

    private bool $activePathExisted = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activePathExisted = File::isDirectory(base_path('modules/glitter-crm'));
    }

    /**
     * lifecycle 실패 시에도 새 active 출력만 정리합니다.
     */
    protected function tearDown(): void
    {
        if (! $this->activePathExisted) {
            File::deleteDirectory(base_path('modules/glitter-crm'));
        }

        parent::tearDown();
    }

    public function test_install_activate_deactivate_and_uninstall_lifecycle(): void
    {
        if ($this->activePathExisted) {
            $this->markTestSkipped('기존 active glitter-crm 디렉토리를 보호하기 위해 lifecycle 파일 작업을 생략합니다.');
        }

        $identifier = 'glitter-crm';
        $manager = $this->app->make(ModuleManager::class);
        $manager->loadModules();

        $this->artisan('module:install', ['identifier' => $identifier])
            ->assertExitCode(0);

        $this->assertDatabaseHas('modules', [
            'identifier' => $identifier,
            'vendor' => 'Glitter.kr',
            'version' => '1.3.3',
            'status' => ExtensionStatus::Inactive->value,
        ]);
        $this->assertDirectoryExists(base_path('modules/glitter-crm'));
        $this->assertSame(
            hash_file('sha256', $this->moduleBasePath().'/module.json'),
            hash_file('sha256', base_path('modules/glitter-crm/module.json')),
        );
        $this->assertSame(33, Permission::byExtension(ExtensionOwnerType::Module, $identifier)->count());
        $this->assertDatabaseHas('menus', [
            'slug' => $identifier,
            'extension_identifier' => $identifier,
            'is_active' => false,
        ]);

        $this->artisan('module:activate', ['identifier' => $identifier])
            ->assertExitCode(0);
        $this->assertDatabaseHas('modules', [
            'identifier' => $identifier,
            'status' => ExtensionStatus::Active->value,
        ]);

        $this->artisan('module:deactivate', ['identifier' => $identifier])
            ->assertExitCode(0);
        $this->assertDatabaseHas('modules', [
            'identifier' => $identifier,
            'status' => ExtensionStatus::Inactive->value,
        ]);

        $this->artisan('module:uninstall', [
            'identifier' => $identifier,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('modules', ['identifier' => $identifier]);
        $this->assertFalse(ModuleRecord::where('identifier', $identifier)->exists());

        // 기본 제거는 선언을 보존하여 재설치 시 역할 커스터마이징을 복원합니다.
        $this->assertGreaterThan(0, Permission::byExtension(ExtensionOwnerType::Module, $identifier)->count());
        $this->assertGreaterThan(0, Menu::byExtension(ExtensionOwnerType::Module, $identifier)->count());
    }
}
