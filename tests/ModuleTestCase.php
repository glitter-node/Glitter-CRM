<?php

namespace Modules\Glitter\Crm\Tests;

use Modules\Glitter\Crm\Module;
use Modules\Glitter\Crm\Providers\CrmServiceProvider;
use Tests\TestCase;

/**
 * CRM 모듈 테스트 베이스.
 */
abstract class ModuleTestCase extends TestCase
{
    /**
     * 테스트 환경에 bundled 모듈 autoload와 Provider를 등록합니다.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->registerModuleAutoload();

        if (! class_exists(Module::class)) {
            require_once $this->moduleBasePath().'/module.php';
        }

        $this->app->register(CrmServiceProvider::class);
    }

    /**
     * 모듈 루트 경로.
     */
    protected function moduleBasePath(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Composer dump 전에도 bundled 소스를 직접 검사할 수 있게 합니다.
     */
    private function registerModuleAutoload(): void
    {
        $basePath = $this->moduleBasePath();

        spl_autoload_register(static function (string $class) use ($basePath): void {
            $prefix = 'Modules\\Glitter\\Crm\\';
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = $basePath.'/src/'.str_replace('\\', '/', $relative).'.php';

            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
