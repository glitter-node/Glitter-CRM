# Tag Data Quality Gate

이 문서는 `glitter-crm` 0.5.0 Tag 데이터 계층과 Contact 연결의 검증 기준을
정의한다. 현재 모듈 버전은 1.3.3이며 Tag API 검증은
`docs/testing/tag-api-quality-gate.md`로 분리한다. Tag 관리자 UI는 이 범위에
포함되지 않는다.

## 1. Schema 계약

`crm_tags`는 bigint `id`를 내부 PK로 사용하고 unique `uuid`를 공개
식별자로 사용한다. `name`은 255자, `slug`는 unique 191자이며
`description`은 nullable text, `color`는 nullable `#RRGGBB`, `deleted_at`은
soft delete 용도다. 테이블 prefix는 connection 설정에 맡긴다.

`crm_contact_tag`는 unsigned bigint `contact_id`, `tag_id`, timestamps를
갖는다. `unique(contact_id, tag_id)`가 중복 연결을 차단하고 두 FK는 각각
`crm_contacts.id`, `crm_tags.id`를 참조해 물리 삭제 시 cascade한다.

## 2. UUID와 slug 정책

- Tag의 `getKeyName()`은 `id`, `uniqueIds()`는 `['uuid']`, route key는
  `uuid`다.
- Service의 Contact–Tag public relation contract는 Contact UUID와 Tag UUID만
  받는다.
- slug는 Unicode 문자와 숫자를 보존하고 나머지 연속 문자를 `-`로 바꾼 뒤
  소문자화한다. 따라서 `중요 고객`은 `중요-고객`이 된다.
- slug는 soft-deleted Tag까지 포함해 예약된다. restore API가 없는 현재에도
  분류 식별자의 재사용으로 인한 의미 변경을 막는다.
- DB unique가 최종 경합 방어선이고 Service가 저장 전에 중복을 검사한다.

## 3. Color와 soft delete 정책

color는 CSS class나 임의 스타일 문자열이 아니라 대문자 `#RRGGBB`만
저장한다. 빈 문자열은 null이다.

Tag 삭제는 soft delete다. pivot은 soft delete 시 보존되어 데이터 관계 이력을
유지하며 기본 Eloquent 관계에서는 삭제 Tag가 제외된다. 물리 삭제가 수행되는
module `--delete-data` rollback 또는 향후 별도 정책에서는 FK cascade가 pivot을
정리한다. restore와 force-delete application 기능은 제공하지 않는다.

## 4. Contact 연결 계약

- `attachToContact(contactUuid, tagUuid)`는 중복 없이 연결한다.
- `detachFromContact(contactUuid, tagUuid)`는 해당 연결만 제거한다.
- `syncForContact(contactUuid, tagUuids)`는 중복 UUID를 제거하고 관계를 정확히
  동기화한다.
- 빈 배열은 전체 연결 해제다.
- null은 method 계약에 포함되지 않으며 기존 값 유지의 암시적 의미로 사용하지
  않는다.
- UUID 형식 오류나 존재하지 않는 Tag가 있으면 transaction 안에서 동기화를
  시작하지 않는다.

## 5. 정적 검사

```bash
find modules/_bundled/glitter-crm -name '*.php' -print0 \
  | xargs -0 -n1 /usr/local/bin/php83 -l

/usr/local/bin/php83 /usr/local/bin/composer validate \
  --working-dir=modules/_bundled/glitter-crm \
  --no-check-publish

git diff --check
```

추가로 migration table/FK/index 선언, model reflection, Provider binding,
backend ko/en translation parity, version parity, JSON/YAML parse 및 Tag
Controller/Request/Resource/layout/route 부재를 검사한다.

## 6. 격리 DB 테스트

`.env.testing`과 production과 다른 전용 DB가 준비된 후에만 실행한다.

```bash
/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Unit/TagStructureTest.php

/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Feature/TagDataLayerTest.php

/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests
```

검증 대상은 migration 생성, unique/FK/cascade, UUID 생성, Unicode slug,
중복 slug, color, repository 검색·정렬·soft delete와 UUID 기반
attach/detach/sync다.

## 7. Lifecycle 검증

production이 아닌 격리 복제본에서만 다음 순서로 수행한다.

```bash
/usr/local/bin/php83 artisan module:install glitter-crm \
  --vendor-mode=bundled --env=testing
/usr/local/bin/php83 artisan module:activate glitter-crm --env=testing
/usr/local/bin/php83 artisan module:deactivate glitter-crm --env=testing
/usr/local/bin/php83 artisan module:uninstall glitter-crm \
  --force --delete-data --env=testing
```

rollback은 파일명의 역순으로 `crm_contact_tag`, `crm_tags`,
`crm_contacts`를 제거해야 한다. 각 단계에서 production DB와 active source를
사용하지 않았는지 다시 확인한다.

## 8. Release blocker

다음 중 하나라도 미검증 또는 실패하면 Tag 데이터 기반의 공개 배포를 중단한다.

- migration up/down과 FK cascade
- slug unique의 실제 DB collation 동작
- PHPUnit Tag 및 전체 CRM suite
- install/activate/deactivate/uninstall
- bundled와 active output 일치
- Contact 0.4.1 quality gate 회귀 검사

현재 `.env.testing`이 없으므로 DB와 lifecycle 검증은 통과한 것으로 간주하지
않는다.
