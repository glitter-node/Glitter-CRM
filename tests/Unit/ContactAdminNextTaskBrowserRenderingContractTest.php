<?php

namespace Modules\Glitter\Crm\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ContactAdminNextTaskBrowserRenderingContractTest extends TestCase
{
    public function test_desktop_and_responsive_renderers_share_a_pipe_safe_next_task_cell_contract(): void
    {
        $layout = json_decode(
            file_get_contents(
                dirname(__DIR__, 2).'/resources/layouts/admin/admin_crm_contact_index.json',
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $grid = collect($layout['slots']['content'][0]['children'])
            ->firstWhere('id', 'contacts_grid');
        $column = collect($grid['props']['columns'])
            ->firstWhere('field', 'next_task_at');

        $this->assertSame('DataGrid', $grid['name']);
        $this->assertArrayNotHasKey(
            'cardRenderer',
            $grid['props'],
            'The default responsive card must consume the same column contract as the desktop table.',
        );
        $this->assertSame('next_task_at', $column['field']);
        $this->assertSame(
            '$t:glitter-crm.admin.contacts.fields.next_action_at',
            $column['header'],
        );
        $this->assertCount(2, $column['cellChildren']);

        [$valueNode, $emptyNode] = $column['cellChildren'];

        $this->assertSame('{{!!row.next_task_at}}', $valueNode['if']);
        $this->assertSame(
            "{{row.next_task_at | datetime('YYYY-MM-DD HH:mm')}}",
            $valueNode['text'],
        );
        $this->assertSame('{{!row.next_task_at}}', $emptyNode['if']);
        $this->assertSame(
            '$t:glitter-crm.admin.contacts.no_value',
            $emptyNode['text'],
        );

        $displayContract = json_encode(
            $column['cellChildren'],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );

        $this->assertStringNotContainsString('next_action_at', $displayContract);
        $this->assertStringNotContainsString('tasks', $displayContract);
    }

    public function test_layout_identifier_and_version_match_the_release_contract(): void
    {
        $layout = json_decode(
            file_get_contents(
                dirname(__DIR__, 2).'/resources/layouts/admin/admin_crm_contact_index.json',
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('admin_crm_contact_index', $layout['layout_name']);
        $this->assertSame('1.3.3', $layout['version']);
    }
}
