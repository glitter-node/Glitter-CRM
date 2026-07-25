<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MigrationRuntimeDependencyTest extends TestCase
{
    private function moduleBasePath(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array<string>
     */
    private function migrationFiles(): array
    {
        $files = glob($this->moduleBasePath().'/database/migrations/*.php');

        $this->assertIsArray($files);
        sort($files);

        return $files;
    }

    public function test_migrations_do_not_reference_module_runtime_namespaces(): void
    {
        $forbiddenNamespaces = [
            'Modules\\Glitter\\Crm\\Enums',
            'Modules\\Glitter\\Crm\\Models',
            'Modules\\Glitter\\Crm\\Services',
            'Modules\\Glitter\\Crm\\Repositories',
        ];

        foreach ($this->migrationFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);

            foreach ($forbiddenNamespaces as $namespace) {
                $this->assertStringNotContainsString(
                    $namespace,
                    $source,
                    basename($file).' must remain independent from module runtime autoload.',
                );
            }
        }
    }

    public function test_task_migration_uses_persistent_scalar_defaults(): void
    {
        $file = $this->moduleBasePath()
            .'/database/migrations/2026_07_25_000006_create_crm_tasks_table.php';
        $source = file_get_contents($file);

        $this->assertIsString($source);
        $this->assertMatchesRegularExpression(
            "/->string\\('status', 20\\)\\s*->default\\('pending'\\)/",
            $source,
        );
        $this->assertMatchesRegularExpression(
            "/->string\\('priority', 20\\)\\s*->default\\('normal'\\)/",
            $source,
        );
    }
}
