# Activity 관리자 API

이 API의 관리자 UI 소비자는 독립 Activity 페이지가 아니라 Contact 수정
화면의 타임라인이다. UI는 Contact nested GET과 전역 CRUD endpoint를
사용하며 별도 nested POST를 사용하지 않는다.

## 공통 계약

- Base URL: `/api/modules/glitter-crm/admin/activities`
- 인증: Sanctum Bearer token 및 관리자
- 공개 Activity·Contact·User 식별자: UUID
- 공통 middleware: `auth:sanctum`, `admin`, `throttle:600,1`
- 내부 bigint ID, `deleted_at`, FK 값은 응답에 노출하지 않는다.
- DELETE는 soft delete이며 PATCH, restore, force-delete는 제공하지 않는다.
- Activity UI와 timeline은 이 API 범위에 포함되지 않는다.

성공 응답은 `success`, `message`, `data` envelope를 사용한다. validation 실패는
HTTP 422와 `errors`, 없는 UUID는 HTTP 404를 반환한다.

## 권한과 endpoint

| Method | Endpoint | Permission |
| --- | --- | --- |
| GET | `/admin/activities` | `glitter-crm.activities.read` |
| POST | `/admin/activities` | `glitter-crm.activities.create` |
| GET | `/admin/activities/{activity}` | `glitter-crm.activities.read` |
| PUT | `/admin/activities/{activity}` | `glitter-crm.activities.update` |
| DELETE | `/admin/activities/{activity}` | `glitter-crm.activities.delete` |
| GET | `/admin/contacts/{contact}/activities` | `glitter-crm.activities.read` |

표의 endpoint 앞에는 `/api/modules/glitter-crm`이 자동 적용된다.

## 목록

```text
GET /api/modules/glitter-crm/admin/activities
Route name: api.modules.glitter-crm.admin.activities.index
```

| Query | 규칙 |
| --- | --- |
| `search` | nullable string, max 100; subject/body 부분 검색 |
| `contact_uuid` | nullable active Contact UUID |
| `type` | note/call/email/meeting/status_change/system |
| `created_by_uuid` | nullable User UUID |
| `occurred_from` | nullable timezone-aware ISO 8601 |
| `occurred_to` | nullable timezone-aware ISO 8601, from 이상 |
| `sort` | occurred_at/created_at/updated_at/type |
| `direction` | asc/desc |
| `per_page` | integer 1..100, 기본 20 |
| `page` | integer, min 1 |

기본 정렬은 `occurred_at DESC, id DESC`다. 두 번째 내부 정렬 키는 안정적
페이지 순서를 위한 것으로 API에 노출되지 않는다.

응답 예시:

```json
{
  "success": true,
  "message": "활동 정보를 조회했습니다.",
  "data": {
    "data": [
      {
        "uuid": "01983d67-1f62-7596-9439-e1fc75547e52",
        "type": "call",
        "subject": "상담 전화",
        "body": null,
        "occurred_at": "2026-07-25T09:30:00.000000Z",
        "metadata": {"channel": "phone"},
        "contact": {
          "uuid": "01983d67-1f62-7596-9439-e1fc75547e51",
          "name": "고객",
          "company": null
        },
        "creator": {
          "uuid": "01983d67-1f62-7596-9439-e1fc75547e50",
          "name": "관리자",
          "email": "admin@example.test"
        },
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

## Contact별 목록

```text
GET /api/modules/glitter-crm/admin/contacts/{contact}/activities
Route name: api.modules.glitter-crm.admin.contacts.activities.index
```

`{contact}`는 active Contact UUID다. 숫자 ID, 잘못된 UUID, 존재하지 않거나
soft-deleted Contact는 404다. 전체 목록과 같은 query를 사용하지만
`contact_uuid` query는 받지 않고 route Contact가 유일한 Contact filter다.

## 생성

```text
POST /api/modules/glitter-crm/admin/activities
Route name: api.modules.glitter-crm.admin.activities.store
```

| Body | 규칙 |
| --- | --- |
| `contact_uuid` | required active Contact UUID |
| `type` | note/call/email/meeting/status_change, required |
| `subject` | nullable string, max 255 |
| `body` | nullable string |
| `occurred_at` | required timezone-aware ISO 8601 |
| `metadata` | nullable JSON object |

subject 또는 body 중 하나는 trim 이후 반드시 존재해야 한다. metadata는 빈
객체 표현을 허용하지만 비어 있지 않은 JSON list는 거부한다. 미래
`occurred_at`은 허용하고 UTC로 저장·응답한다.

creator는 Request 입력이 아니라 현재 인증 관리자의 User UUID에서 결정한다.
`created_by`, `created_by_uuid`, 내부 ID 등 허용 목록 밖 입력은 validated
payload에서 제외된다. `system` type은 HTTP 422다. 성공 시 HTTP 201이다.

## 상세·수정·삭제

`{activity}`는 UUID만 허용한다. 숫자 ID, 잘못된 UUID, 존재하지 않거나
soft-deleted Activity는 404다.

PUT은 `type`, `subject`, `body`, `occurred_at`, `metadata`의 부분 수정만
허용한다. Contact, creator, UUID는 변경할 수 없다. DELETE는 soft delete 후
HTTP 200 성공 envelope를 반환한다. 수정·삭제 transaction은 Contact
`last_activity_at`을 삭제되지 않은 Activity의 최대 발생 시각으로 재계산한다.

## System Activity

내부 Service는 system Activity를 만들 수 있지만 관리자 POST에서는 생성할 수
없다. 저장된 system Activity는 조회 가능하나 PUT/DELETE는 HTTP 403이다.
Resource의 `can_update`, `can_delete`도 permission과 무관하게 false다.

## 오류

- 401: 인증되지 않음
- 403: 관리자/endpoint 권한 없음 또는 system Activity 변경 시도
- 404: active Contact 또는 Activity UUID 없음
- 422: UUID/type/content/date range/metadata validation 실패
