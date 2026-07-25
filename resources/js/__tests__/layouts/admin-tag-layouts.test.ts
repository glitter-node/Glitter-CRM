import { describe, expect, it } from 'vitest';
import { createLayoutTest } from '../../../../../../../resources/js/core/template-engine/__tests__/utils/layoutTestUtils';
import indexLayout from '../../../layouts/admin/admin_crm_tag_index.json';
import formLayout from '../../../layouts/admin/admin_crm_tag_form.json';
import routes from '../../../routes/admin.json';
import ko from '../../../lang/ko.json';
import en from '../../../lang/en.json';

type JsonValue = null | boolean | number | string | JsonValue[] | { [key: string]: JsonValue };

function serialized(value: unknown): string {
  return JSON.stringify(value);
}

function collectText(value: JsonValue, key = ''): string[] {
  if (typeof value === 'string') {
    return ['text', 'title', 'description', 'placeholder', 'label', 'triggerLabel', 'searchPlaceholder', 'noResultsText', 'initialText', 'emptyMessage', 'actionsColumnHeader'].includes(key)
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

describe('glitter-crm Tag 관리자 layout', () => {
  it('typed route는 목록, 정적 create, UUID edit 순서다', () => {
    const tagRoutes = routes.routes.slice(3);
    expect(tagRoutes.map((route) => route.path)).toEqual([
      '*/admin/crm/tags',
      '*/admin/crm/tags/create',
      '*/admin/crm/tags/:id/edit',
    ]);
    expect(tagRoutes.map((route) => route.layout)).toEqual([
      'admin_crm_tag_index',
      'admin_crm_tag_form',
      'admin_crm_tag_form',
    ]);
    expect(tagRoutes[0].meta.permission).toBe('glitter-crm.tags.read');
    expect(tagRoutes[1].meta.permission).toBe('glitter-crm.tags.create');
  });

  it('목록은 Tag envelope, allowlist 정렬, UUID와 abilities를 사용한다', () => {
    const source = indexLayout.data_sources[0];
    const grid = (indexLayout as any).slots.content[0].children.find((child: any) => child.id === 'tags_grid');
    const text = serialized(indexLayout);

    expect(source.endpoint).toBe('/api/modules/glitter-crm/admin/tags');
    expect(source.params).toMatchObject({
      search: '{{query.search}}',
      sort: "{{query.sort || 'name'}}",
      direction: "{{query.direction || 'asc'}}",
    });
    expect(grid?.props.idField).toBe('uuid');
    expect(grid?.props.data).toBe('{{tags?.data?.data}}');
    expect(grid?.props.serverCurrentPage).toContain('tags?.data?.meta?.current_page');
    expect(text).toContain('tags?.data?.abilities?.can_create');
    expect(text).toContain('abilities.can_update');
    expect(text).toContain('abilities.can_delete');
    expect(text).toContain('"page":1');
  });

  it('삭제는 UUID DELETE, refetch, modal close 순서를 따른다', () => {
    const text = serialized(indexLayout);
    const deleteAt = text.indexOf('/api/modules/glitter-crm/admin/tags/{{_local.deleteUuid}}');
    const refetchAt = text.indexOf('"handler":"refetchDataSource"', deleteAt);
    const closeAt = text.indexOf('"handler":"closeModal"', refetchAt);

    expect(deleteAt).toBeGreaterThan(-1);
    expect(refetchAt).toBeGreaterThan(deleteAt);
    expect(closeAt).toBeGreaterThan(refetchAt);
    expect(text).not.toContain('/restore');
    expect(text).not.toContain('/force-delete');
  });

  it('공유 form은 UUID load와 POST/PUT, validation 및 read-only abilities를 사용한다', () => {
    const text = serialized(formLayout);

    expect(formLayout.data_sources[0].endpoint).toBe('/api/modules/glitter-crm/admin/tags/{{route?.id}}');
    expect(text).toContain("'PUT' : 'POST'");
    expect(text).toContain('_local.errors?.name');
    expect(text).toContain('_local.errors?.slug');
    expect(text).toContain('_local.errors?.description');
    expect(text).toContain('_local.errors?.color');
    expect(text).toContain('tag?.data?.abilities?.can_update');
    expect(text).toContain('tag?.data?.abilities?.can_delete');
    expect(text).toContain('"name":"Textarea"');
    expect(text).toContain('"type":"text"');
  });

  it('표시 문자열은 번역 binding이며 ko/en 키가 일치한다', () => {
    for (const text of [...collectText(indexLayout as JsonValue), ...collectText(formLayout as JsonValue)]) {
      expect(text.startsWith('$t:') || text.includes('{{')).toBe(true);
    }
    expect(leafKeys(ko.admin.tags)).toEqual(leafKeys(en.admin.tags));
  });

  it('createLayoutTest로 두 layout의 content tree를 렌더한다', async () => {
    for (const layout of [indexLayout, formLayout]) {
      const test = createLayoutTest({
        layout_name: layout.layout_name,
        slots: { content: layout.slots.content },
      } as any);
      await expect(test.render()).resolves.toBeDefined();
      test.cleanup();
    }
  });
});
