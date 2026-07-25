<?php

namespace Modules\Glitter\Crm;

use App\Extension\AbstractModule;

/**
 * CRM 공용 모듈.
 */
class Module extends AbstractModule
{
    /**
     * 모듈 표시 메타데이터.
     *
     * @return array<string, string>
     */
    public function getMetadata(): array
    {
        return [
            'author' => 'Glitter.kr',
        ];
    }

    /**
     * 모듈 전용 역할은 정의하지 않습니다.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRoles(): array
    {
        return [];
    }

    /**
     * CRM 관리자 권한 트리.
     *
     * @return array<string, mixed>
     */
    public function getPermissions(): array
    {
        return [
            'name' => $this->localized('permissions.module.name'),
            'description' => $this->localized('permissions.module.description'),
            'categories' => [
                $this->permissionCategory('dashboard', null, [
                    $this->permission('dashboard', 'read'),
                ]),
                $this->permissionCategory('contacts', 'contact', [
                    $this->permission('contacts', 'read'),
                    $this->permission('contacts', 'create'),
                    $this->permission('contacts', 'update'),
                    $this->permission('contacts', 'delete'),
                    $this->permission('contacts', 'export'),
                ]),
                $this->permissionCategory('tags', 'tag', [
                    $this->permission('tags', 'read'),
                    $this->permission('tags', 'create'),
                    $this->permission('tags', 'update'),
                    $this->permission('tags', 'delete'),
                ]),
                $this->permissionCategory('activities', 'activity', [
                    $this->permission('activities', 'read'),
                    $this->permission('activities', 'create'),
                    $this->permission('activities', 'update'),
                    $this->permission('activities', 'delete'),
                ]),
                $this->permissionCategory('tasks', 'task', [
                    $this->permission('tasks', 'read'),
                    $this->permission('tasks', 'create'),
                    $this->permission('tasks', 'update'),
                    $this->permission('tasks', 'delete'),
                    $this->permission('tasks', 'assign'),
                ]),
                $this->permissionCategory('leads', 'lead', [
                    $this->permission('leads', 'read'),
                    $this->permission('leads', 'create'),
                    $this->permission('leads', 'update'),
                    $this->permission('leads', 'delete'),
                ]),
                $this->permissionCategory('settings', null, [
                    $this->permission('settings', 'read'),
                    $this->permission('settings', 'update'),
                ]),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getAdminMenus(): array
    {
        return [
            [
                'name' => $this->localized('menu.admin'),
                'slug' => 'glitter-crm',
                'url' => null,
                'icon' => 'fas fa-users',
                'order' => 35,
                'is_active' => true,
                'children' => [
                    [
                        'name' => $this->localized('menu.contacts'),
                        'slug' => 'glitter-crm-contacts',
                        'url' => '/admin/crm/contacts',
                        'icon' => 'fas fa-users',
                        'order' => 1,
                        'permission' => 'glitter-crm.contacts.read',
                        'is_active' => true,
                    ],
                    [
                        'name' => $this->localized('menu.tags'),
                        'slug' => 'glitter-crm-tags',
                        'url' => '/admin/crm/tags',
                        'icon' => 'fas fa-tag',
                        'order' => 2,
                        'permission' => 'glitter-crm.tags.read',
                        'is_active' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * 권한 카테고리 선언을 구성합니다.
     *
     * @param  array<int, array<string, mixed>>  $permissions
     * @return array<string, mixed>
     */
    private function permissionCategory(
        string $identifier,
        ?string $resourceRouteKey,
        array $permissions,
    ): array {
        return [
            'identifier' => $identifier,
            'resource_route_key' => $resourceRouteKey,
            'owner_key' => null,
            'name' => $this->localized("permissions.{$identifier}.name"),
            'description' => $this->localized("permissions.{$identifier}.description"),
            'permissions' => $permissions,
        ];
    }

    /**
     * 개별 관리자 권한 선언을 구성합니다.
     *
     * @return array<string, mixed>
     */
    private function permission(string $category, string $action): array
    {
        return [
            'action' => $action,
            'name' => $this->localized("permissions.{$category}.actions.{$action}.name"),
            'description' => $this->localized("permissions.{$category}.actions.{$action}.description"),
            'type' => 'admin',
            'roles' => ['admin'],
        ];
    }

    /**
     * backend 언어 파일에서 ko/en 값을 읽습니다.
     *
     * @return array{ko: string, en: string}
     */
    private function localized(string $key): array
    {
        return [
            'ko' => $this->translationValue('ko', $key),
            'en' => $this->translationValue('en', $key),
        ];
    }

    /**
     * dot notation 번역 값을 반환합니다.
     */
    private function translationValue(string $locale, string $key): string
    {
        $translations = require __DIR__."/src/lang/{$locale}/module.php";
        $value = data_get($translations, $key);

        if (! is_string($value)) {
            throw new \RuntimeException("Missing glitter-crm translation: {$locale}.{$key}");
        }

        return $value;
    }
}
