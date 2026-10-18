# Task 데이터 계층 quality gate

## 1. 범위와 현재 상태

`glitter-crm` 1.3.3의 Task 데이터 계층은 Contact에 귀속되는 후속 작업만
다룬다. 관리자 UI, 메뉴, 알림, scheduler, queue, Observer 및 Activity
자동 생성은 이 범위에 포함하지 않는다. Task API 검증은 별도
`task-api-quality-gate.md`가 담당한다. 현재 production 환경에서는 migration,
PHPUnit 및 module lifecycle을 실행하지 않았다.

## 2. Schema 계약

- 논리 테이블은 `crm_tasks`이며 DB connection prefix를 migration에
  하드코딩하지 않는다.
- 내부 식별자는 auto-increment unsigned bigint `id`, 공개 식별자는 unique
  `uuid`다.
- `contact_id`는 `crm_contacts.id`를 참조하고 물리 삭제 시 cascade한다.
- nullable `assigned_to`는 `users.id`를 참조하고 User 삭제 시 null이 된다.
- `status`, `priority`, `due_at`, `completed_at`, nullable JSON `metadata`,
  timestamps와 soft delete를 사용한다.
- `next_task_at`은 별도 선행 migration으로 `crm_contacts`에 추가한다. 역순
  rollback은 `crm_tasks`를 먼저 제거한 뒤 이 컬럼을 제거한다.

## 3. Enum과 상태 불변 조건

상태는 `pending`, `in_progress`, `completed`, `cancelled`, 우선순위는
`low`, `normal`, `high`, `urgent`다. 기본값은 각각 `pending`, `normal`이다.
`completed` 진입 시 Service가 현재 UTC를 `completed_at`에 기록한다. 이미 완료된
Task의 일반 수정은 기존 시각을 보존하고, 완료 상태에서 벗어나면 null로 만든다.
외부 입력의 `completed_at`은 persistence payload에 포함하지 않는다.

## 4. UUID, assignee와 datetime

Task, Contact와 User의 외부 Service 계약은 UUID만 사용한다. Task의 Contact는
생성 후 이동할 수 없다. `assigned_to_uuid` 키가 없으면 기존 담당자를 유지하고,
null이면 해제하며, UUID면 내부 bigint FK로 변환한다.

`due_at`은 null, `CarbonInterface`, 또는 offset/Z가 포함된 ISO 8601만 받으며
UTC로 저장한다. 과거 기한을 허용한다. timezone 없는 문자열은 거부한다.

## 5. Metadata와 soft delete

metadata는 null, 빈 배열 또는 연관 배열만 허용한다. 중첩 값은 허용하지만
비어 있지 않은 list 배열과 JSON 직렬화 불가능 값은 거부한다. Task 삭제는 soft
delete이며 restore와 force-delete 계약은 없다.

## 6. Repository 계약

검색은 `title`, `description` OR 절을 별도 괄호로 그룹화한다. Contact UUID,
assignee UUID, status, priority, due range와 `overdue=true`를 필터링한다.
overdue는 현재 UTC보다 due_at이 이르고 상태가 pending/in_progress인 Task다.
정렬 allowlist는 `due_at`, `created_at`, `updated_at`, `status`, `priority`,
`title`, `completed_at`이다. DB별 null 정렬 차이를 피하기 위해 기본 정렬은
`created_at DESC, id DESC`다.

## 7. Contact next_task_at

`next_task_at`은 삭제되지 않은 pending/in_progress Task 중 null이 아닌
`due_at`의 전체 MIN이다. 과거 overdue 기한도 포함한다. Task create/update/
delete와 같은 transaction에서 매번 재계산하며 대상이 없으면 null이다.
Contact Request, ContactService 및 Contact UI는 이 필드를 직접 쓰지 않는다.

## 8. 정적 검증

production DB 접근 없이 다음을 실행한다.

```bash
find modules/_bundled/glitter-crm -name '*.php' -print0 \
  | xargs -0 -n1 /usr/local/bin/php83 -l

/usr/local/bin/php83 /usr/local/bin/composer validate \
  --working-dir=modules/_bundled/glitter-crm \
  --no-check-publish

git diff --check
```

추가로 JSON/YAML parse, backend ko/en recursive parity, enum/UUID reflection,
migration FK/index 선언, Provider binding, 1.3.3 version parity 및 Task
UI/Observer/queue 부재를 확인한다.

## 9. 격리 DB와 lifecycle 검증

production과 다른 `.env.testing` DB가 준비된 환경에서만 실행한다.

```bash
/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Unit/TaskStructureTest.php

/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Feature/TaskDataLayerTest.php
```

그 후 별도 복제본에서 APP_ENV와 DB 이름을 확인하고 module discovery → install
→ migration/FK/index 확인 → activate → PHPUnit → deactivate → uninstall
순으로 검증한다. 실제 명령은 현재 `docs/extension/module-commands.md`를
사용하며 production에서는 실행하지 않는다.

## 10. Release blocker

다음 중 하나라도 미통과면 1.3.3 runtime 공개 배포를 중단한다.

- migration up/down 및 Task PHPUnit
- Contact/User FK의 cascade/nullOnDelete 실제 동작
- status/completed_at 불변 조건과 UTC 저장
- 모든 Task 변경 후 `next_task_at` 전체 재계산 및 transaction rollback
- install/activate/deactivate/uninstall
- bundled source와 active output 일치
- Task UI/알림/Observer가 데이터 기준선에 섞이지 않았다는 검사

현재 DB 및 lifecycle 검증은 환경 미구성으로 미실행이며 성공으로 간주하지 않는다.
