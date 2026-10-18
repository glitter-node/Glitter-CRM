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

describe('glitter-crm Contact Activity timeline layout', () => {
  it('uses the nested Contact GET only in edit mode and adds no Activity page', () => {
    const source = layout.data_sources.find((item) => item.id === 'activities') as any;
    expect(source?.endpoint).toBe('/api/modules/glitter-crm/admin/contacts/{{route?.id}}/activities');
    expect(source?.method).toBe('GET');
    expect(source?.if).toBe('{{!!route?.id}}');
    expect(routes.routes.map((route) => route.path)).not.toContain('*/admin/crm/activities');
  });

  it('binds the collection envelope, filters, pagination and abilities', () => {
    expect(text).toContain('activities?.data?.data');
    expect(text).toContain('activities?.data?.meta?.current_page');
    expect(text).toContain('activities?.data?.abilities?.can_create');
    expect(text).toContain('activity.abilities?.can_update');
    expect(text).toContain('activity.abilities?.can_delete');
    expect(text).toContain('"occurred_from"');
    expect(text).toContain('"occurred_to"');
    expect(text).toContain('"activityPage":1');
  });

  it('uses UUID endpoints and safe Activity payloads', () => {
    expect(text).toContain('/api/modules/glitter-crm/admin/activities/');
    expect(text).toContain('contact_uuid:route.id');
    expect(text).toContain("new Date(_local.activityForm.occurred_at).toISOString()");
    expect(text).not.toContain('creator_uuid:');
    expect(text).not.toContain('created_by:');
    expect(text).not.toContain('metadata:_local.activityForm');
    expect(text).not.toContain('contact_id:');
  });

  it('keeps system Activity read-only and preserves plain text rendering', () => {
    const formModal = (layout.modals as any[]).find((modal) => modal.id === 'activity_form_modal');
    expect(JSON.stringify(formModal)).not.toContain('"value":"system"');
    expect(text).toContain("activity.type === 'system'");
    expect(text).toContain('whitespace-pre-wrap');
    expect(text).not.toContain('v-html');
    expect(text).not.toContain('dangerouslySetInnerHTML');
  });

  it('keeps Activity translations in parity and renders through createLayoutTest', async () => {
    expect(leafKeys(ko.admin.activities)).toEqual(leafKeys(en.admin.activities));
    const test = createLayoutTest({
      layout_name: layout.layout_name,
      slots: { content: layout.slots.content },
    } as any);
    await expect(test.render()).resolves.toBeDefined();
    test.cleanup();
  });
});
