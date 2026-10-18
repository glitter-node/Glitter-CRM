# Tag API Quality Gate

## 범위와 현재 상태

이 문서는 `glitter-crm` 0.6.0 Tag 관리자 CRUD API 기준선의 격리 검증 기준이다.
현재 모듈 버전은 1.3.3이다. Tag 관리자 UI는
`docs/testing/tag-ui-quality-gate.md`로 분리하며 Contact–Tag 관계 HTTP API는
범위 밖이다.

정적으로 확인할 계약:

- 외부 경로는 `/api/modules/glitter-crm/admin/tags`이며 member parameter는 UUID다.
- 공통 middleware는 `auth:sanctum`, `admin`, `throttle:600,1`이다.
- index/show/create/update/delete는 각각 세분화된 `glitter-crm.tags.*` 권한을 쓴다.
- PATCH, restore, force-delete, bulk, 관계 endpoint는 없다.
- Resource는 내부 bigint `id`, `deleted_at`, pivot 정보를 노출하지 않는다.
- 목록은 `data.data`, `data.meta`, `data.abilities.can_create` 구조를 사용한다.
- item abilities는 `can_update`, `can_delete`다.
- slug 중복은 soft-deleted Tag까지 포함해 HTTP 422 `errors.slug`로 응답한다.

## 정적 검사

```bash
find modules/_bundled/glitter-crm -name '*.php' -print0 \
  | xargs -0 -n1 /usr/local/bin/php83 -l

/usr/local/bin/php83 /usr/local/bin/composer validate \
  --working-dir=modules/_bundled/glitter-crm \
  --no-check-publish

git diff --check
```

추가로 route source, UUID constraint, permission key, Request/Repository sort
allowlist, Resource 노출 필드, backend ko/en key parity와 YAML/JSON parse를 검사한다.

## 격리 PHPUnit

`.env.testing`과 production과 다른 전용 DB가 준비된 경우에만 실행한다.

```bash
/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Unit/TagHttpStructureTest.php

/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Feature/TagAdminApiTest.php
```

Feature suite는 인증·권한, 검색·정렬, validation, UUID 조회, abilities,
soft delete, pivot 보존 및 slug 예약을 검증한다. production `.env`를 복사하거나
production DB에서 실행하지 않는다.

## Lifecycle 및 runtime 검증

별도 testing 복제본에서 다음 순서로 검증한다.

1. `APP_ENV=testing`과 별도 DB 이름을 확인한다.
2. `/usr/local/bin/php83 artisan module:list`로 bundled 1.3.3을 확인한다.
3. 프로젝트의 `module:install glitter-crm`, `module:activate glitter-crm`을 실행한다.
4. migration과 `/usr/local/bin/php83 artisan route:list --path=api/modules/glitter-crm/admin/tags`를 확인한다.
5. Tag PHPUnit과 실제 관리자 Bearer token smoke를 수행한다.
6. `module:deactivate`, `module:uninstall`과 데이터 제거 선택 정책을 검증한다.

명령의 옵션은 실행 전 `/usr/local/bin/php83 artisan help module:<command>`로
현재 코어 계약을 다시 확인한다. production에서는 위 lifecycle을 실행하지 않는다.

## Release blocker

다음이 모두 통과하기 전에는 Tag API를 포함한 공개 배포가 불가능하다.

- 격리 DB migration up/down
- Tag 데이터 및 HTTP PHPUnit
- install/activate/deactivate/uninstall lifecycle
- 활성 모듈 route:list와 권한 middleware
- 인증·권한·422·404 runtime smoke
- soft delete 후 pivot 보존과 slug 예약 확인
- bundled source와 active output 비교
