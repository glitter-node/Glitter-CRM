# Tag UI Quality Gate

## 계약

- 관리자 route: `/admin/crm/tags`, `/create`, `/{uuid}/edit`
- 메뉴: CRM 자식, `glitter-crm.tags.read`
- layout: `admin_crm_tag_index`, `admin_crm_tag_form`
- abilities: collection `can_create`, item `can_update`, `can_delete`
- 삭제: UUID DELETE → `tags` refetch → modal close
- 색상: 검증된 `#RRGGBB`만 style binding, 신규 CSS 없음
- Contact selector: SearchableDropdown multiple, Tag UUID 배열
- 권한 경계: Tag API 403이면 selector와 `tag_uuids` payload 모두 생략
- Contact write와 pivot sync는 단일 transaction

## 정적 검사

- JSON parse 및 layout translation key 존재
- ko/en frontend parity
- typed create route가 dynamic edit보다 먼저 선언
- menu URL/permission과 실제 layout 일치
- API envelope, UUID, pagination, abilities binding
- visible text의 `$t:` binding
- 신규 runtime handler/component/CSS 부재

## 격리 테스트

```bash
node_modules/.bin/vitest run \
  --config modules/_bundled/glitter-crm/vitest.config.ts

/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Unit/TagAdminUiStructureTest.php

/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Feature/ContactApiTest.php
```

`.env.testing`과 production과 다른 테스트 DB가 준비된 경우에만 PHPUnit을 실행한다.

## Runtime smoke

- Tag 메뉴 권한별 표시
- 목록 loading/empty/error, 검색, 정렬, pagination
- Unicode 자동/명시 slug, 중복과 삭제 slug 예약
- color 입력·표시, read-only 수정, 삭제 확인·갱신
- Contact Tag 검색·다중 선택·기존 값·전체 해제
- Tag read 권한 없는 Contact 저장 시 기존 관계 유지
- 존재하지 않거나 삭제된 Tag에서 Contact write rollback
- 401/403/404/422 및 네트워크 오류

## Release blocker

PHPUnit, Vitest, static build, 격리 lifecycle, active output 비교 및 위 runtime smoke가
모두 통과하기 전에는 현재 모듈 버전 1.3.3을 공개 배포할 수 없다.
