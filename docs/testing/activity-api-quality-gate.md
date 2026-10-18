# Activity API Quality Gate

## 범위

이 문서는 `glitter-crm` Activity 관리자 API의 격리 검증 기준이다. 현재 모듈 버전은 1.3.3이다.
Activity 데이터 계층 검증은 `activity-data-quality-gate.md`에 유지하며
관리자 UI와 timeline은 범위 밖이다.

## 정적 계약

- 전체 CRUD 5개와 Contact nested GET 1개만 노출한다.
- URL과 관계 식별자는 UUID이며 내부 bigint ID를 노출하지 않는다.
- middleware는 `auth:sanctum`, `admin`, `throttle:600,1` 및 endpoint별
  `glitter-crm.activities.*` permission이다.
- creator는 인증 User UUID에서 결정하며 Request 입력이 아니다.
- 관리자 생성 type에서 system을 제외한다.
- 저장된 system Activity는 read-only이고 item abilities도 false다.
- Resource는 Contact/creator를 경량 구조로 반환하며 Repository가 eager load한다.
- 목록은 `data.data`, `data.meta`, `data.abilities` envelope다.
- PATCH, nested POST, restore, force-delete는 없다.

## 정적 검사

```bash
find modules/_bundled/glitter-crm -name '*.php' -print0 \
  | xargs -0 -n1 /usr/local/bin/php83 -l

/usr/local/bin/php83 /usr/local/bin/composer validate \
  --working-dir=modules/_bundled/glitter-crm \
  --no-check-publish

git diff --check
```

추가로 route surface/name/UUID constraint, middleware, Request와 Repository
sort allowlist, Resource 내부 ID 부재, creator input 부재, system 보호,
backend ko/en parity, JSON/YAML parse 및 1.3.3 version parity를 검사한다.

## 격리 Feature test

`.env.testing`과 production과 다른 전용 DB가 준비된 경우에만 실행한다.

```bash
/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Unit/ActivityHttpStructureTest.php

/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Feature/ActivityAdminApiTest.php
```

Feature suite는 인증·권한, CRUD, nested 목록, 검색·필터·정렬·pagination,
authenticated creator, ISO 8601/UTC, metadata object, UUID, system 보호,
abilities, soft delete와 `last_activity_at` 재계산을 검증한다.

## Permission matrix

| 작업 | 권한 |
| --- | --- |
| index/show/nested index | activities.read |
| create | activities.create |
| update | activities.update |
| delete | activities.delete |

system Activity의 update/delete는 권한이 있어도 거부한다.

## Lifecycle 및 runtime smoke

별도 testing 복제본에서 APP_ENV와 DB 이름을 확인한 뒤 discovery → install →
activate → migration 확인 → Activity PHPUnit → route:list → Bearer token smoke →
deactivate → uninstall 순으로 검증한다.

Runtime smoke에서 401/403/404/422, 동일 발생 시각 stable order, Contact nested
격리, creator 자동 기록, system read-only, UTC 응답, soft delete 및 최근 활동
재계산을 확인한다. 실제 lifecycle 옵션은 실행 전 해당 Artisan help로 확인한다.

## Release blocker

다음이 모두 통과하기 전에는 현재 버전을 공개 배포할 수 없다.

- migration up/down과 Activity data/API PHPUnit
- install/activate/deactivate/uninstall
- 실제 route:list와 middleware/permission
- authenticated creator와 system 보호 runtime smoke
- transaction 및 `last_activity_at` 재계산
- bundled source와 active output 일치

현재 production 환경에는 격리 DB가 없으므로 PHPUnit과 lifecycle은 미실행
상태이며 성공으로 간주하지 않는다.
