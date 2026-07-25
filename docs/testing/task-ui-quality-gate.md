# Task UI quality gate

`glitter-crm` 1.3.3의 Task UI는 Contact 수정 화면 내부 기능이다. 이 버전은
소스 기능 버전이며 격리 runtime 검증 완료 전 공개 배포할 수 없다.

## 정적 계약

- edit에서만 nested GET 실행, create에서는 Task 요청 없음
- 독립 Task route/menu/layout 없음
- `data.data`, `data.meta`, `data.abilities`와 UUID endpoint 사용
- 검색, 상태, 우선순위, User UUID 담당자, UTC 기한 범위, true-only overdue
- API `is_overdue`, pagination과 collection/item abilities 사용
- POST만 `contact_uuid` 포함; `completed_at`·`metadata` input/payload 없음
- write 성공 후 Task와 Contact refetch, section별 오류 격리
- ko/en parity 및 translation binding
- 신규 component, CSS, runtime handler 없음

## 격리 검증과 runtime smoke

별도 testing DB와 `APP_ENV=testing`을 확인한 뒤 CRM PHPUnit, layout Vitest,
정적 build, module lifecycle과 bundled/active output 비교를 수행한다.
Contact create 요청 차단, edit loading/empty/error, 권한 격리, badge,
nullable 담당자·기한, 검색·필터·pagination, 네 상태와 우선순위 CRUD,
overdue, validation, 중복 제출, soft delete, `next_task_at` 재조회 및
401/403/404/422/500/network 오류를 확인한다.

위 검증 중 하나라도 미통과면 1.3.3 공개 배포를 중단한다. production DB,
active module 및 `public/build`는 직접 수정하지 않는다.
