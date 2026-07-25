<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use Modules\Glitter\Crm\Tests\ModuleTestCase;

class ContactAdminNextTaskBindingTest extends ModuleTestCase
{
    public function test_contact_list_renders_server_next_task_at_with_standard_empty_and_datetime_rules(): void
    {
        $layout = $this->readJson('resources/layouts/admin/admin_crm_contact_index.json');
        $grid = collect($layout['slots']['content'][0]['children'])
            ->firstWhere('id', 'contacts_grid');
        $column = collect($grid['props']['columns'])
            ->firstWhere('field', 'next_task_at');

        $this->assertNotNull($column);
        $this->assertFalse($column['sortable']);
        $this->assertSame(
            "{{row.next_task_at | datetime('YYYY-MM-DD HH:mm')}}",
            $column['cellChildren'][0]['text'],
        );
    }

    public function test_task_mutations_refetch_contact_without_writing_next_task_at(): void
    {
        $layout = file_get_contents(
            $this->moduleBasePath().'/resources/layouts/admin/admin_crm_contact_form.json',
        );

        $this->assertGreaterThanOrEqual(
            2,
            substr_count(
                $layout,
                '"handler": "refetchDataSource", "params": {"dataSourceId": "contact"}',
            ),
        );

        $bodyStart = strpos($layout, '"body": "{{({name:');
        $bodyEnd = strpos($layout, '"onSuccess"', $bodyStart);

        $this->assertNotFalse($bodyStart);
        $this->assertNotFalse($bodyEnd);
        $this->assertStringNotContainsString(
            'next_task_at',
            substr($layout, $bodyStart, $bodyEnd - $bodyStart),
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
}
