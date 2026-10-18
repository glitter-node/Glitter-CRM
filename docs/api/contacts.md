# Contact 관리자 API

## 공통 계약

- Base URL: `/api/modules/glitter-crm/admin/contacts`
- 인증: Sanctum Bearer token 및 관리자
- 공개 Contact 식별자: UUID
- `user_id`, `assigned_user_id`: 코어 User UUID 또는 `null`
- 응답에서 Contact 내부 bigint `id`와 `deleted_at`은 노출하지 않는다.
- email, phone, mobile 중복을 허용한다.
- DELETE는 soft delete이며 restore/force-delete API는 제공하지 않는다.
- `tag_uuids`는 활성 Tag UUID 배열이며 Contact 저장과 같은 transaction에서 관계를 동기화한다.

성공 응답은 `success`, `message`, `data` envelope를 사용한다. validation 실패는
HTTP 422와 `errors` 필드, 없는 UUID는 HTTP 404를 반환한다.

## 목록

```text
GET /api/modules/glitter-crm/admin/contacts
Permission: glitter-crm.contacts.read
Route name: api.modules.glitter-crm.admin.contacts.index
```

Query parameter:

| 필드 | 규칙 |
| --- | --- |
| `keyword` | nullable string, max 100; name/company/email/phone/mobile 부분 검색 |
| `status` | `active`, `inactive`, `archived` |
| `user_id` | 존재하는 User UUID |
| `assigned_user_id` | 존재하는 User UUID |
| `sort` | `name`, `company`, `status`, `created_at`, `updated_at`, `last_activity_at`, `next_action_at` |
| `direction` | `asc`, `desc` |
| `per_page` | integer 1..100, 기본 20 |
| `page` | integer, min 1 |

응답 예시:

```json
{
  "success": true,
  "message": "고객 정보를 조회했습니다.",
  "data": {
    "data": [
      {
        "uuid": "018f4f73-6471-7d78-8b3f-0d7334aa2001",
        "user_id": null,
        "assigned_user_id": null,
        "status": "active",
        "name": "홍길동",
        "company": "Glitter.kr",
        "job_title": null,
        "email": "customer@example.com",
        "phone": null,
        "mobile": "010-0000-0000",
        "locale": "ko",
        "timezone": "Asia/Seoul",
        "source": "manual",
        "last_activity_at": null,
        "next_action_at": null,
        "next_task_at": null,
        "metadata": null,
        "user": null,
        "assigned_user": null,
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
      "can_create": true,
      "can_update": true,
      "can_delete": true
    }
  }
}
```

## 생성

```text
POST /api/modules/glitter-crm/admin/contacts
Permission: glitter-crm.contacts.create
Route name: api.modules.glitter-crm.admin.contacts.store
```

Body:

| 필드 | 규칙 |
| --- | --- |
| `name` | required string, max 255, trim 후 빈 값 불가 |
| `user_id`, `assigned_user_id` | nullable User UUID |
| `status` | nullable ContactStatus; 누락 시 `active` |
| `company`, `job_title` | nullable string, max 255 |
| `email` | nullable email, max 255, unique 아님 |
| `phone`, `mobile` | nullable string, max 50, unique 아님 |
| `locale` | nullable string, max 10 |
| `timezone` | nullable IANA timezone, max 50 |
| `source` | nullable string, max 100 |
| `next_action_at` | nullable datetime-local (`Y-m-d\TH:i`); 사이트 기본 타임존으로 해석 |
| `metadata` | nullable array/object |
| `tag_uuids` | nullable UUID 배열, 각 값 distinct; `null` 또는 `[]`은 Tag 없이 생성 |

`last_activity_at`은 Activity 계층이 관리할 내부 필드이므로 관리자 입력을 받지 않는다.
`next_action_at`은 사이트 기본 타임존 기준 UTC로 저장하고 Resource에서는
`Y-m-d\TH:i` 형식으로 반환한다.
성공 시 HTTP 201과 Contact Resource를 반환한다.

응답의 `tags`는 선택된 활성 Tag를 다음 경량 구조로 반환한다.

```json
[
  {"uuid": "...", "name": "중요 고객", "slug": "중요-고객", "color": "#FFAA00"}
]
```

내부 Tag ID, pivot, Tag abilities는 중첩 응답에 포함하지 않는다.

## 상세

```text
GET /api/modules/glitter-crm/admin/contacts/{contact}
Permission: glitter-crm.contacts.read
Route name: api.modules.glitter-crm.admin.contacts.show
```

`{contact}`는 UUID만 허용한다. 내부 bigint나 soft deleted UUID는 HTTP 404다.

## 수정

```text
PUT /api/modules/glitter-crm/admin/contacts/{contact}
Permission: glitter-crm.contacts.update
Route name: api.modules.glitter-crm.admin.contacts.update
```

생성과 같은 공개 필드에 부분 수정(`sometimes`) 규칙을 적용한다. `uuid`, 내부 `id`,
`last_activity_at`, `deleted_at`은 수정 입력이 아니다.

`tag_uuids`가 없으면 기존 관계를 유지한다. `null` 또는 빈 배열은 전체 연결 해제,
UUID 배열은 해당 활성 Tag 집합으로 정확히 동기화한다. 존재하지 않거나 삭제된
Tag가 포함되면 HTTP 422이며 Contact 필드 변경도 rollback된다. Tag 정의를 수정하는
작업이 아니므로 Contact create/update 권한 외에 `tags.update`는 요구하지 않는다.

## 삭제

```text
DELETE /api/modules/glitter-crm/admin/contacts/{contact}
Permission: glitter-crm.contacts.delete
Route name: api.modules.glitter-crm.admin.contacts.destroy
```

성공 시 HTTP 200 성공 envelope를 반환한다. 행은 `deleted_at`과 함께 유지되고 이후
일반 목록·상세·수정·삭제 대상에서 제외된다.

## 오류

```json
{
  "success": false,
  "message": "입력값이 올바르지 않습니다.",
  "errors": {
    "status": ["선택한 상태 값이 올바르지 않습니다."]
  }
}
```

- 401: 인증되지 않음
- 403: 관리자 또는 endpoint 권한 없음
- 404: UUID에 해당하는 활성 Contact 없음
- 422: query/body validation 실패
