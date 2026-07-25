# Contact Task 관리자 UI

Task는 독립 관리자 메뉴나 화면이 아니라 저장된 Contact의 수정 화면
`/admin/crm/contacts/:uuid/edit` 안에서 관리한다. Contact 생성 화면에서는
Contact UUID가 없으므로 Task section과 API 요청이 비활성화된다.

목록은 제목·설명 검색, 상태, 우선순위, 담당 User UUID, 기한 범위,
`overdue=true` 필터와 20개 단위 pagination을 제공한다. overdue 표시는
API의 `is_overdue`를 사용하고, 날짜는 기존 formatter로 로컬 표시한다.

Task 읽기 권한이 없어도 Contact, Tag와 Activity 기능은 정상 동작한다.
생성은 collection `can_create`, 수정·삭제는 item의 `can_update`,
`can_delete`를 사용하며 서버 middleware가 최종 보안 경계다.

폼은 제목, 설명, 상태, 우선순위, 담당자와 기한만 전송한다. 담당자는 기존
`/api/admin/users/search`의 User UUID를 사용한다. `completed_at`,
`metadata`, 내부 bigint ID는 입력하거나 전송하지 않는다. 쓰기 성공 후
Task와 Contact를 재조회해 `next_task_at`을 반영한다. 삭제는 soft delete다.

독립 Task 화면, metadata 편집, 완료 시각 직접 입력, restore, force delete,
알림, recurrence, subtask 및 Activity 자동 생성은 제공하지 않는다.
