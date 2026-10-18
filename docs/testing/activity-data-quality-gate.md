# Activity Data Quality Gate

이 문서는 `glitter-crm` 0.8.0 Activity 데이터 계층의 격리 검증 기준이다.
현재 모듈 버전은 1.3.3이며 Activity API 검증은
`activity-api-quality-gate.md`로 분리한다. 관리자 UI와 자동 기록은 이
기준선에 포함되지 않는다.

## 1. Schema 계약

- 논리 테이블은 `crm_activities`이며 connection prefix를 migration에
  하드코딩하지 않는다.
- bigint `id`는 내부 관계용, unique 36자 `uuid`는 공개 식별용이다.
- `contact_id`는 필수 `crm_contacts.id` FK이고 물리 삭제 시 cascade한다.
- `created_by`는 nullable `users.id` FK이고 User 삭제 시 null 처리한다.
- `(contact_id, occurred_at)`, `type`, `occurred_at` 조회 index를 둔다.
- `metadata`는 nullable JSON, 삭제는 `deleted_at` soft delete다.
- migration 번호 `000004`는 reverse rollback에서 Contact보다 먼저 제거된다.

## 2. Type과 콘텐츠 정책

`ActivityType`은 `note`, `call`, `email`, `meeting`, `status_change`, `system`
backed enum이다. DB enum은 사용하지 않는다. 데이터 계층은 system type을
허용하고 creator null을 허용하지만 자동 system Activity는 만들지 않는다.

모든 type은 `subject` 또는 `body` 중 하나가 있어야 한다. subject는 trim 후
nullable 255자, body는 trim 후 nullable text이며 내부 줄바꿈을 보존한다.

## 3. UUID와 관계 정책

Activity는 bigint auto-increment PK를 유지하고 Laravel `HasUuids`의
`uniqueIds()`가 `['uuid']`만 반환한다. route key도 uuid다. Service create는
Contact UUID와 선택적 creator UUID만 외부 계약으로 받고 내부 bigint FK로
변환한다. update에서 Contact, creator, UUID를 변경할 수 없다.

`Contact::activities()`는 정렬 없는 순수 HasMany다. 최신순 안정 정렬은
Repository가 `occurred_at DESC, id DESC`로 적용한다.

## 4. DateTime과 metadata

Service의 `occurred_at`은 `CarbonInterface` 또는 timezone offset/Z가 있는
ISO 8601 문자열만 받으며 UTC로 정규화한다. 미래 발생 시각은 데이터
계층에서 제한하지 않고 후속 HTTP 정책에서 결정한다.

metadata는 null 또는 JSON object에 대응하는 연관 배열이다. 빈 배열은 빈
객체 표현으로 허용하지만 비어 있지 않은 list와 JSON 직렬화 불가능 값은
거부한다. 중첩 값은 허용하며 임의 개인정보 저장소로 사용하지 않는다.

## 5. 최근 활동과 transaction

Activity 생성·수정·soft delete는 Activity persistence와 Contact
`last_activity_at` 갱신을 하나의 `DB::transaction()`에서 수행한다. 갱신값은
soft-deleted Activity를 제외한 `MAX(occurred_at)`이며 남은 Activity가 없으면
null이다. 일반 Contact Service와 HTTP Request는 이 필드를 쓰지 않는다.

## 6. Repository 계약

Repository는 UUID 조회, CRUD, pagination, Contact/creator UUID, type,
occurred 기간, grouped subject/body keyword 검색을 제공한다. 정렬 allowlist는
`occurred_at`, `created_at`, `updated_at`, `type`이며 임의 SQL 컬럼을 받지
않는다. 기본 Eloquent scope로 soft-deleted Activity를 제외한다.

## 7. 정적 검사

production DB에 접근하지 않고 다음을 실행한다.

```bash
find modules/_bundled/glitter-crm -name '*.php' -print0 \
  | xargs -0 -n1 /usr/local/bin/php83 -l

/usr/local/bin/php83 /usr/local/bin/composer validate \
  --working-dir=modules/_bundled/glitter-crm \
  --no-check-publish

git diff --check
```

추가로 JSON/YAML parse, backend ko/en parity, 1.3.3 version parity,
Provider binding, migration FK/index 선언, Activity UI/Observer 부재를
검사한다.

## 8. 격리 DB 테스트

`.env.testing`에 production과 다른 전용 DB가 준비된 경우에만 실행한다.

```bash
/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Unit/ActivityStructureTest.php

/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Feature/ActivityDataLayerTest.php
```

검증 범위는 migration/FK/index, UUID와 cast, 검색·필터·정렬, create/update/
soft delete, creator nullOnDelete, Contact 물리 삭제 cascade, 입력 검증 및
`last_activity_at` 전체 재계산 사례다.

## 9. Lifecycle 검증

production이 아닌 별도 복제본에서 APP_ENV와 DB 이름을 먼저 확인한 뒤
module discovery → install → migration 확인 → activate → PHPUnit →
deactivate → uninstall 순으로 검증한다. 실제 명령은
`docs/extension/module-commands.md`의 현재 명령을 사용하며, uninstall
rollback에서 `crm_activities`가 `crm_contacts`보다 먼저 제거되는지 확인한다.

## 10. Release blocker

다음 중 하나라도 미통과면 현재 모듈 버전 1.3.3 공개 배포를 중단한다.

- 격리 DB migration up/down 및 Activity PHPUnit
- install/activate/deactivate/uninstall
- bigint PK + 보조 UUID 및 FK 동작
- UTC 저장과 creator nullOnDelete
- create/update/delete 후 `last_activity_at` 재계산과 transaction 원자성
- bundled source와 active output 일치
- Activity UI/Observer가 데이터 기준선에 섞이지 않았다는 검사

현재 production 환경에는 `.env.testing`이 없으므로 DB 및 lifecycle 검증은
미실행 상태이며 성공으로 간주하지 않는다.
