import { describe, expect, it } from 'vitest';
import { createLayoutTest } from '../../../../../../../resources/js/core/template-engine/__tests__/utils/layoutTestUtils';
import layout from '../../../layouts/admin/admin_crm_contact_form.json';
import routes from '../../../routes/admin.json';
import ko from '../../../lang/ko.json';
import en from '../../../lang/en.json';

const text = JSON.stringify(layout);

function leafKeys(value: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(value).flatMap(([key, child]) => {
    const path = prefix ? `${prefix}.${key}` : key;
    return child && typeof child === 'object' && !Array.isArray(child)
      ? leafKeys(child as Record<string, unknown>, path)
      : [path];
  }).sort();
}

describe('glitter-crm Contact Task layout', () => {
  it('loads Contact tasks only in edit mode without an independent page', () => {
    const source = layout.data_sources.find((item) => item.id === 'tasks') as any;
    expect(source.endpoint).toBe('/api/modules/glitter-crm/admin/contacts/{{route?.id}}/tasks');
    expect(source.method).toBe('GET');
    expect(source.if).toBe('{{!!route?.id}}');
    expect(routes.routes.map((route) => route.path)).not.toContain('*/admin/crm/tasks');
  });

  it('binds collection data, filters, pagination and abilities', () => {
    expect(text).toContain('tasks?.data?.data');
    expect(text).toContain('tasks?.data?.meta?.current_page');
    expect(text).toContain('tasks?.data?.abilities?.can_create');
    expect(text).toContain('task.abilities?.can_update');
    expect(text).toContain('task.abilities?.can_delete');
    expect(text).toContain('_local.taskFilters?.overdue === true ? true : null');
    expect(text).toContain('"taskPage":1');
    for (const field of ['search', 'status', 'priority', 'assigned_to_uuid', 'due_from', 'due_to']) {
      expect(text).toContain(`"${field}"`);
    }
  });

  it('uses UUID endpoints and protects server-managed fields', () => {
    expect(text).toContain('/api/modules/glitter-crm/admin/tasks/');
    expect(text).toContain('contact_uuid:route.id');
    expect(text).toContain('new Date(_local.taskForm.due_at).toISOString()');
    expect(text).not.toContain('metadata:_local.taskForm');
    expect(text).not.toContain('completed_at:_local.taskForm');
    expect(text).not.toContain('contact_id:');
    expect(text).not.toContain('assigned_to:_local.taskForm');
  });

  it('uses Resource overdue state and refetches Task and Contact data', () => {
    expect(text).toContain('task.is_overdue === true');
    expect(text).toContain('"dataSourceId":"tasks"');
    expect(text).toContain('"dataSourceId":"contact"');
    expect(text).toContain('taskReadable');
    expect(text).not.toContain('v-html');
    expect(text).not.toContain('dangerouslySetInnerHTML');
  });

  it('keeps translations in parity and renders through createLayoutTest', async () => {
    expect(leafKeys(ko.admin.tasks)).toEqual(leafKeys(en.admin.tasks));
    const test = createLayoutTest({
      layout_name: layout.layout_name,
      slots: { content: layout.slots.content },
    } as any);
    await expect(test.render()).resolves.toBeDefined();
    test.cleanup();
  });
});
