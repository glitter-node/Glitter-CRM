import { describe, expect, it } from 'vitest';
import { createLayoutTest } from '../../../../../../../resources/js/core/template-engine/__tests__/utils/layoutTestUtils';
import indexLayout from '../../../layouts/admin/admin_crm_contact_index.json';
import formLayout from '../../../layouts/admin/admin_crm_contact_form.json';
import routes from '../../../routes/admin.json';
import ko from '../../../lang/ko.json';
import en from '../../../lang/en.json';

type JsonValue = null | boolean | number | string | JsonValue[] | { [key: string]: JsonValue };

function serialized(value: unknown): string {
  return JSON.stringify(value);
}

function collectText(value: JsonValue, key = ''): string[] {
  if (typeof value === 'string') {
    return value !== '' && ['text', 'title', 'description', 'placeholder', 'label', 'triggerLabel', 'searchPlaceholder', 'noResultsText', 'initialText', 'emptyMessage', 'actionsColumnHeader'].includes(key)
      ? [value]
      : [];
  }
  if (Array.isArray(value)) return value.flatMap((item) => collectText(item));
  if (value && typeof value === 'object') {
    return Object.entries(value).flatMap(([childKey, child]) => collectText(child, childKey));
  }
  return [];
}

function leafKeys(value: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(value).flatMap(([key, child]) => {
    const path = prefix ? `${prefix}.${key}` : key;
    return child && typeof child === 'object' && !Array.isArray(child)
      ? leafKeys(child as Record<string, unknown>, path)
      : [path];
  }).sort();
}

describe('glitter-crm Contact 관리자 layout', () => {
  it('관리자 route가 실제 두 layout과 UUID 수정 경로를 참조한다', () => {
    expect(routes.routes.slice(0, 3).map((route) => route.path)).toEqual([
      '*/admin/crm/contacts',
      '*/admin/crm/contacts/create',
      '*/admin/crm/contacts/:id/edit',
    ]);
    expect(routes.routes.slice(0, 3).map((route) => route.layout)).toEqual([
      'admin_crm_contact_index',
      'admin_crm_contact_form',
      'admin_crm_contact_form',
    ]);
    expect(routes.routes[0].meta.permission).toBe('glitter-crm.contacts.read');
    expect(routes.routes[1].meta.permission).toBe('glitter-crm.contacts.create');
  });

  it('목록은 기존 API envelope와 필터, 정렬, pagination을 사용한다', () => {
    const source = indexLayout.data_sources[0];
    const grid = (indexLayout as any).slots.content[0].children.find((child: any) => child.id === 'contacts_grid');
    const text = serialized(indexLayout);

    expect(source.endpoint).toBe('/api/modules/glitter-crm/admin/contacts');
    expect(source.params).toMatchObject({
      keyword: '{{query.keyword}}',
      status: '{{query.status}}',
      assigned_user_id: '{{query.assigned_user_id}}',
    });
    expect(grid?.props.idField).toBe('uuid');
    expect(grid?.props.data).toBe('{{contacts?.data?.data}}');
    expect(grid?.props.serverCurrentPage).toContain('contacts?.data?.meta?.current_page');
    const nextTaskColumn = grid?.props.columns.find((column: any) => column.field === 'next_task_at');
    expect(nextTaskColumn?.sortable).toBe(false);
    expect(grid?.props.cardRenderer).toBeUndefined();
    expect(nextTaskColumn?.cellChildren).toHaveLength(2);
    expect(nextTaskColumn?.cellChildren?.[0]?.if).toBe('{{!!row.next_task_at}}');
    expect(nextTaskColumn?.cellChildren?.[0]?.text).toBe(
      "{{row.next_task_at | datetime('YYYY-MM-DD HH:mm')}}",
    );
    expect(nextTaskColumn?.cellChildren?.[1]).toMatchObject({
      if: '{{!row.next_task_at}}',
      text: '$t:glitter-crm.admin.contacts.no_value',
    });
    expect(serialized(nextTaskColumn.cellChildren)).not.toContain('next_action_at');
    expect(indexLayout.version).toBe('1.3.3');
    expect(text).toContain('/api/admin/users/search');
    expect(text).toContain('"page":1');
  });

  it('폼 payload는 metadata, last_activity_at, UUID 및 내부 ID를 보내지 않는다', () => {
    const text = serialized(formLayout);
    expect(text).toContain('next_action_at:');
    expect(text).toContain('tag_uuids:');
    expect(text).toContain("tag_options?.success === true ? {tag_uuids:");
    expect(text).not.toContain('metadata:');
    expect(text).not.toContain('last_activity_at:');
    expect(text).not.toMatch(/\b(?:uuid|id):_local\.form/);
    expect(text).toContain('/api/admin/users/search');
    expect(text).toContain('/api/modules/glitter-crm/admin/tags');
    expect(text).toContain('"multiple":true');
    expect(text).toContain('(response.data?.data?.tags ?? []).map(tag => tag.uuid)');
    expect(text).toContain('(response.data?.data?.data ?? []).map(tag =>');
    expect(text).toContain('contact?.data?.abilities?.can_update');
    expect(text).toContain('contact?.data?.abilities?.can_delete');
  });

  it('삭제는 확인 modal과 DELETE 후 목록 갱신 또는 이동을 사용한다', () => {
    const indexText = serialized(indexLayout);
    const formText = serialized(formLayout);

    expect(indexText).toContain('delete_contact_modal');
    expect(indexText).toContain('"method":"DELETE"');
    expect(indexText).toContain('"handler":"refetchDataSource","params":{"dataSourceId":"contacts"}');
    expect(indexText).not.toContain('"handler":"refetchDataSource","params":{"id":');
    expect(formText).toContain('"method":"DELETE"');
    expect(formText).toContain('/admin/crm/contacts');
  });

  it('표시 문자열은 번역 binding이며 ko/en 키가 일치한다', () => {
    for (const text of [...collectText(indexLayout as JsonValue), ...collectText(formLayout as JsonValue)]) {
      expect(text.startsWith('$t:') || text.includes('{{')).toBe(true);
    }
    expect(leafKeys(ko.admin.contacts)).toEqual(leafKeys(en.admin.contacts));
  });

  it('createLayoutTest로 두 layout의 content tree를 실제 렌더한다', async () => {
    for (const layout of [indexLayout, formLayout]) {
      const testLayout = {
        layout_name: layout.layout_name,
        slots: { content: layout.slots.content },
      };
      const test = createLayoutTest(testLayout as any);
      await expect(test.render()).resolves.toBeDefined();
      test.cleanup();
    }
  });
});
