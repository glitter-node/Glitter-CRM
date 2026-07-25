# Tag 관리자 API

## 공통 계약

- Base URL: `/api/modules/glitter-crm/admin/tags`
- 인증: Sanctum Bearer token 및 관리자
- 공개 Tag 식별자: UUID
- 응답에서 내부 bigint `id`, `deleted_at`, pivot 정보는 노출하지 않는다.
- DELETE는 soft delete이며 restore/force-delete API는 제공하지 않는다.
- slug는 soft-deleted Tag까지 포함해 영구 예약한다.
- Contact–Tag attach/detach/sync HTTP API는 제공하지 않는다.

관리자 화면 계약은 `docs/admin/tags.md`와 분리한다.

성공 응답은 `success`, `message`, `data` envelope를 사용한다. validation 실패는
HTTP 422와 `errors` 필드, 없는 UUID는 HTTP 404를 반환한다.

## 목록

```text
GET /api/modules/glitter-crm/admin/tags
Permission: glitter-crm.tags.read
Route name: api.modules.glitter-crm.admin.tags.index
```

| Query | 규칙 |
| --- | --- |
| `search` | nullable string, max 100; name/slug/description 부분 검색 |
| `sort` | `name`, `slug`, `created_at`, `updated_at` |
| `direction` | `asc`, `desc` |
| `per_page` | integer 1..100, 기본 20 |
| `page` | integer, min 1 |

응답 예시:

```json
{
  "success": true,
  "message": "태그 정보를 조회했습니다.",
  "data": {
    "data": [
      {
        "uuid": "01983d67-1f62-7596-9439-e1fc75547e52",
        "name": "우선 고객",
        "slug": "우선-고객",
        "description": null,
        "color": "#AABBCC",
        "created_at": "2026-07-25 10:00:00",
        "updated_at": "2026-07-25 10:00:00",
        "abilities": {
          "can_update": true,
          "can_delete": true
        }
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 20,
      "total": 1
    },
    "abilities": {
      "can_create": true
    }
  }
}
```

## 생성

```text
POST /api/modules/glitter-crm/admin/tags
Permission: glitter-crm.tags.create
Route name: api.modules.glitter-crm.admin.tags.store
```

| Body | 규칙 |
| --- | --- |
| `name` | required string, max 255, trim 후 빈 값 불가 |
| `slug` | nullable string, max 191; 누락 시 name에서 Unicode 보존 slug 생성 |
| `description` | nullable string; 빈 문자열은 `null` |
| `color` | nullable `#RRGGBB`; 저장 시 대문자 정규화 |

`id`, `uuid`, `deleted_at` 등 허용 목록 밖 입력은 validated payload에서 제외된다.
slug 중복은 soft-deleted 행과의 충돌도 포함해 HTTP 422 `errors.slug`로 반환한다.
성공 시 HTTP 201과 Tag Resource를 반환한다.

## 상세

```text
GET /api/modules/glitter-crm/admin/tags/{tag}
Permission: glitter-crm.tags.read
Route name: api.modules.glitter-crm.admin.tags.show
```

`{tag}`는 UUID만 허용한다. 숫자 내부 ID, 잘못된 UUID, 존재하지 않거나
soft-deleted UUID는 HTTP 404다.

## 수정

```text
PUT /api/modules/glitter-crm/admin/tags/{tag}
Permission: glitter-crm.tags.update
Route name: api.modules.glitter-crm.admin.tags.update
```

생성과 같은 공개 필드에 부분 수정(`sometimes`) 규칙을 적용한다. slug를 명시하지
않고 name을 바꾸면 name에서 slug를 다시 생성하며, slug와 name을 모두 생략하면
기존 slug를 유지한다. `uuid`, 내부 `id`, `deleted_at`은 수정 입력이 아니다.

## 삭제

```text
DELETE /api/modules/glitter-crm/admin/tags/{tag}
Permission: glitter-crm.tags.delete
Route name: api.modules.glitter-crm.admin.tags.destroy
```

성공 시 HTTP 200 성공 envelope를 반환한다. Tag 행과 pivot 관계는 유지되지만
soft-deleted Tag는 일반 목록·상세 및 Contact의 기본 Tag 관계에서 제외된다.
삭제된 Tag의 slug는 계속 예약된다.

## 오류

- 401: 인증되지 않음
- 403: 관리자 또는 endpoint 권한 없음
- 404: UUID에 해당하는 활성 Tag 없음
- 422: query/body validation 또는 slug 중복
