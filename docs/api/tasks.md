# Task 관리자 API

Contact 수정 화면의 Task UI는 Contact nested GET과 전역 CRUD endpoint만
사용한다. 담당자는 User UUID, 기한은 timezone-aware ISO 8601로 전송하며
overdue 표시는 Resource의 `is_overdue`를 기준으로 한다.

## 공통 계약

- Base URL: `/api/modules/glitter-crm/admin/tasks`
- 인증: Sanctum Bearer token 및 관리자
- middleware: `auth:sanctum`, `admin`, `throttle:600,1`
- Task·Contact·User 외부 식별자는 UUID다.
- 내부 bigint FK와 `deleted_at`은 노출하지 않는다.
- DELETE는 soft delete이며 PATCH/action/restore/force-delete는 제공하지 않는다.

## Endpoint와 permission

| Method | Endpoint | Permission |
| --- | --- | --- |
| GET | `/admin/tasks` | `glitter-crm.tasks.read` |
| POST | `/admin/tasks` | `glitter-crm.tasks.create` |
| GET | `/admin/tasks/{task}` | `glitter-crm.tasks.read` |
| PUT | `/admin/tasks/{task}` | `glitter-crm.tasks.update` |
| DELETE | `/admin/tasks/{task}` | `glitter-crm.tasks.delete` |
| GET | `/admin/contacts/{contact}/tasks` | `glitter-crm.tasks.read` |

표의 경로 앞에는 `/api/modules/glitter-crm`이 자동 적용된다. `{task}`와
`{contact}`는 UUID만 허용한다.

## 목록

`search`, `contact_uuid`, `assigned_to_uuid`, `status`, `priority`,
`due_from`, `due_to`, `overdue`, `sort`, `direction`, `per_page`, `page`를
지원한다. 날짜 범위는 timezone offset 또는 Z가 포함된 ISO 8601이며 종료는
시작보다 빠를 수 없다. `overdue`는 `true` 또는 `1`만 허용한다.

정렬 allowlist는 `due_at`, `created_at`, `updated_at`, `status`, `priority`,
`title`, `completed_at`이다. 기본 정렬은 `created_at DESC, id DESC`다.
Contact nested 목록에서는 query `contact_uuid`를 사용하지 않고 route UUID가
유일한 Contact 기준이다.

목록은 표준 `data.data`, `data.meta`, `data.abilities.can_create` envelope를
사용한다.

## 생성과 수정

POST 허용 필드:

| Field | Contract |
| --- | --- |
| `contact_uuid` | required active Contact UUID |
| `assigned_to_uuid` | nullable User UUID |
| `title` | required trimmed string, max 255 |
| `description` | nullable string |
| `status` | pending/in_progress/completed/cancelled |
| `priority` | low/normal/high/urgent |
| `due_at` | nullable timezone-aware ISO 8601 |
| `metadata` | nullable JSON object |

PUT은 Contact를 제외한 같은 필드의 부분 수정을 지원한다. null 담당자는 배정
해제, null due_at은 기한 제거, null metadata는 metadata 제거다. `id`, `uuid`,
내부 FK, `completed_at`, `next_task_at` 등 allowlist 밖 입력은 validated
payload에 포함되지 않는다.

completed 진입 시 서버가 현재 UTC를 `completed_at`에 기록한다. 완료 상태를
유지하는 일반 수정은 시각을 보존하고 완료 상태에서 벗어나면 null로 만든다.

## Resource

Task는 `uuid`, `title`, `description`, status, priority, UTC ISO 8601
`due_at`/`completed_at`, `is_overdue`, metadata, 경량 Contact/assignee,
timestamps와 `can_update`/`can_delete`를 반환한다. 내부 ID는 없다.

`is_overdue`는 due_at이 현재 UTC보다 엄격히 이르고 상태가 pending 또는
in_progress일 때만 true다. `due_at == now`, completed, cancelled, null
due_at은 false다.

## 삭제와 Contact 집계

삭제는 soft delete다. 일반 목록·nested 목록·show에서 제외되고 UUID 재조회는
404다. create/update/delete transaction은 active Task의 `MIN(due_at)`을
Contact `next_task_at`으로 재계산한다.

## 오류

- 401: 인증되지 않음
- 403: 관리자 또는 endpoint permission 없음
- 404: active Contact 또는 Task UUID 없음
- 422: UUID, enum, 담당자, 날짜 범위, overdue, metadata validation 실패

Task 관리자 UI, Contact Task UI, 알림, reminder와 queue는 이 API 범위가 아니다.
