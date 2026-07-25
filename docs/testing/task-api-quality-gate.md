# Task API quality gate

## 범위

`glitter-crm` 1.3.3의 Task 관리자 CRUD 및 Contact nested GET을 검증한다.
Task UI, 메뉴, 알림, reminder, queue, Observer 및 action endpoint는 포함하지
않는다.

## 정적 gate

- CRUD 5개와 Contact nested GET 1개만 존재
- `auth:sanctum`, `admin`, throttle 및 endpoint별 permission
- Task/Contact UUID constraint
- Request와 Repository sort allowlist 일치
- TaskStatus/TaskPriority enum SSoT
- timezone-aware due range, metadata object와 `overdue=true|1`
- Resource 내부 ID 비노출, 경량 Contact/assignee, abilities
- Repository와 Resource의 strict `< now` overdue 조건 일치
- PATCH, nested POST, action, restore, force-delete 부재
- Task UI/frontend/알림/Observer/Event/Listener/queue 부재
- backend ko/en parity와 1.3.3 version parity

## 격리 Feature test

production과 다른 `.env.testing` DB에서만 실행한다.

```bash
/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Unit/TaskHttpStructureTest.php

/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Feature/TaskAdminApiTest.php
```

인증·권한, CRUD, nested 목록, pagination/filter/sort, enum·UUID·날짜·metadata
validation, completed_at 상태 전이, overdue 경계, soft delete와 next_task_at
transaction을 검증한다.

## Runtime smoke

격리 환경에서 module install/activate 후 실제 route:list, permission별 요청,
UUID 404/422, 목록 envelope, 관계 N+1, soft delete 및 next_task_at을 확인한다.
이후 deactivate/uninstall과 migration rollback을 검증한다.

## Release blocker

PHPUnit, migration up/down, lifecycle, actual route collection, transaction rollback,
bundled/active output 비교 중 하나라도 미통과면 1.3.3 공개 배포를 중단한다.
현재 production 환경에서는 이 runtime 검증을 실행하지 않는다.
