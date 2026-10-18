# Contact Activity 타임라인

Activity는 독립 관리자 메뉴나 페이지가 아니라 저장된 Contact의 수정 화면
`/admin/crm/contacts/:id/edit` 하단에서 관리한다. Contact 생성 화면에서는
UUID가 아직 없으므로 Activity API를 요청하거나 타임라인을 표시하지 않는다.

## 권한과 API

목록은 `glitter-crm.activities.read`, 생성은
`glitter-crm.activities.create`, 수정은
`glitter-crm.activities.update`, 삭제는
`glitter-crm.activities.delete` 권한을 사용한다. 서버 middleware와 system
Activity 보호가 최종 보안 경계다.

| 동작 | API |
|---|---|
| Contact 타임라인 | `GET /api/modules/glitter-crm/admin/contacts/{contactUuid}/activities` |
| 생성 | `POST /api/modules/glitter-crm/admin/activities` |
| 수정 | `PUT /api/modules/glitter-crm/admin/activities/{activityUuid}` |
| 삭제 | `DELETE /api/modules/glitter-crm/admin/activities/{activityUuid}` |

읽기 권한이 없거나 목록 API가 403을 반환해도 Contact 조회·저장 상태는
영향받지 않는다. 독립 Activity 관리자 route와 메뉴는 제공하지 않는다.

## 화면 계약

- 제목·본문 검색, type, 발생 기간, 페이지를 서버 query로 전달한다.
- 기간의 `datetime-local` 값은 브라우저 로컬 시간으로 해석해 offset이
  포함된 UTC ISO 8601 값으로 전송한다.
- API UTC 시각은 엔진 `datetime` formatter로 브라우저 로컬 시각에 표시한다.
- 생성 가능한 type은 `note`, `call`, `email`, `meeting`,
  `status_change`이며 `system`은 조회 전용이다.
- 작성자가 없으면 system type은 시스템, 그 외 type은 알 수 없는 작성자로
  번역해 표시한다.
- body는 plain text로 출력하고 줄바꿈만 보존한다.
- 생성·수정 payload에 metadata, creator, 내부 ID를 포함하지 않는다.
- 수정 시 metadata는 미전송되어 기존 값이 유지된다.
- 삭제는 soft delete이며 restore와 force delete UI는 없다.

쓰기 성공 후 Activity와 Contact data source를 다시 조회하여 타임라인 및
`last_activity_at`을 서버 상태와 일치시킨다.

