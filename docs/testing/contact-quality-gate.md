# Contact Quality Gate

현재 모듈 버전은 `1.3.3`이다. Contact 수정 화면의 Task UI는 별도 오류
상태를 사용하고 변경 성공 후 Contact를 재조회한다. `next_task_at`은 Task
도메인만 관리하며 Contact 일반 write와 UI payload에는 포함하지 않는다.

이 문서는 `glitter-crm` Contact 기준선의 정적 검사와 격리 실행 절차를 정의한다.
모든 명령은 프로젝트 복제본과 별도 테스트 DB에서만 실행한다. production
환경에서는 PHPUnit, migration 및 module lifecycle을 실행하지 않는다.

## 1. 현재 검증 상태

- 번들 식별자: `glitter-crm`
- Contact 기능 기준선: `0.4.1`
- 현재 모듈 버전: `1.3.3`
- Contact 수정 화면의 Activity 타임라인은 별도 data source로 격리하며,
  Activity read 403이 Contact 조회·저장을 실패시키지 않아야 한다.
- Activity 쓰기 후 Contact를 다시 조회해 `last_activity_at` 표시를
  서버 재계산 결과와 동기화한다.
- 코어 제약: `>=7.0.5`
- 번들 원본: `modules/_bundled/glitter-crm`
- 설치 산출물: `modules/glitter-crm`
- 현재 저장소 환경: `.env.testing`과 root `node_modules`가 없어 DB·Vitest·build
  검증 미실행

현재 정적으로 확인한 범위는 manifest/composer/version, PHP 문법, migration
선언, UUID 모델 계약, Repository query grouping, Service writable allowlist,
Request 규칙, API route middleware, Resource envelope, typed admin route,
menu/layout 연결, JSON/YAML parse 및 ko/en 번역 parity다.

0.5.0의 Tag 데이터 계층 검증은 `docs/testing/tag-data-quality-gate.md`에서
분리한다. 이 문서의 Contact API/UI runtime 항목은 그대로 유지한다.

0.7.0부터 Contact write는 선택적 `tag_uuids`를 받는다. 키 미전송은 기존 관계
유지, `null`/빈 배열은 전체 해제, UUID 배열은 정확한 sync다. Contact write와
pivot 변경은 같은 transaction이며 Contact Resource는 UUID/name/slug/color만
포함한 `tags`를 반환한다. Tag UI 검증은 `tag-ui-quality-gate.md`로 분리한다.

0.8.0부터 `last_activity_at`은 일반 Contact write 대상이 아니며
`ActivityService`만 관리한다. Activity 생성·수정·soft delete transaction에서
삭제되지 않은 해당 Contact Activity의 `MAX(occurred_at)`으로 재계산하고,
남은 Activity가 없으면 null로 되돌린다. 상세 검증은
`activity-data-quality-gate.md`로 분리한다.

## 2. 완료된 정적 검증

```bash
git status --short
git diff --check

find modules/_bundled/glitter-crm -name '*.php' -print0 \
  | xargs -0 -n1 /usr/local/bin/php83 -l

find modules/_bundled/glitter-crm/resources -name '*.json' -print0 \
  | xargs -0 -n1 jq empty

/usr/local/bin/php83 /usr/local/bin/composer \
  --working-dir=modules/_bundled/glitter-crm \
  validate --no-check-publish

/usr/local/bin/php83 artisan module:list
```

Scenario YAML은 root `vendor/autoload.php`와
`Symfony\Component\Yaml\Yaml::parseFile()`로 parse한다. 이 검사는 DB에
접근하지 않는다.

## 3. 미실행 검증

다음은 환경 미구성으로 아직 통과하지 않았다.

- PHPUnit 및 migration up/down
- install/activate/deactivate/uninstall
- 활성 모듈의 실제 Contact route collection
- Vitest `createLayoutTest()` render
- 정적 frontend build
- 관리자 브라우저 smoke
- 번들 원본과 설치 산출물의 byte-level 비교

미실행 항목은 성공으로 간주하지 않는다.

## 4. 테스트 환경 요구사항

1. production과 분리된 프로젝트 복제본을 사용한다.
2. `.env.testing.example`을 기준으로 사용자가 `.env.testing`을 작성한다.
   production `.env`를 복사하지 않는다.
3. `APP_ENV=testing`과 별도 `APP_KEY`를 설정한다.
4. `DB_WRITE_DATABASE`와 `DB_READ_DATABASE`는 동일한 전용 테스트 DB를
   가리키되 production DB 이름과 달라야 한다.
5. 테스트 DB 사용자는 해당 테스트 DB에 한해
   `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, `DROP`,
   `INDEX`, `REFERENCES` 권한을 가져야 한다.
6. DB 생성과 자격증명 설정은 사용자가 수행한다.

`tests/bootstrap.php`는 `.env.testing` 부재 시 즉시 종료하고 `.env`와
`.env.testing`의 `DB_WRITE_DATABASE`가 같아도 즉시 종료한다. PHPUnit은
이 공통 guard를 우회하지 않는다.

## 5. PHPUnit 준비와 실행

먼저 실제 환경값을 확인한다. 비밀번호는 출력하지 않는다.

```bash
test -f .env.testing
rg '^(APP_ENV|DB_CONNECTION|DB_WRITE_DATABASE|DB_READ_DATABASE)=' .env.testing
rg '^(DB_WRITE_DATABASE)=' .env
```

두 DB 이름이 다르고 `APP_ENV=testing`임을 사람이 확인한 후 실행한다.

```bash
/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Unit

/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Feature/ContactDataLayerTest.php

/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests/Feature/ContactApiTest.php

/usr/local/bin/php83 vendor/bin/phpunit \
  modules/_bundled/glitter-crm/tests
```

`RefreshDatabase`가 테스트 DB를 `migrate:fresh`하므로 공유 DB에는 실행하지
않는다. 정리는 전용 테스트 DB를 폐기하거나 다음 검증 run의
`migrate:fresh`에 맡긴다. production DB나 다른 개발 DB를 cleanup 대상으로
지정하지 않는다.

## 6. Vitest 준비와 실행

root `package-lock.json`이 있으므로 package manager는 npm이다. Vite 7의
lockfile 제약에 따라 Node.js `^20.19.0` 또는 `>=22.12.0`을 사용한다.
현재 저장소 script는 `test:run`, `build`, `build:core-watch`를 제공한다.
AGENTS.md가 언급하는 정확한 이름의 `build:watch` script는 현재
`package.json`에 없으므로 존재하는 script로 대체했다고 가정하지 않는다.

의존성 설치는 사용자가 명시적으로 승인한 격리 환경에서만 실행한다.

```bash
npm ci
node --version
npm --version
ls -la node_modules/.bin/vite node_modules/.bin/vitest
readlink node_modules/.bin/vite
ls -la node_modules/@esbuild/*/bin/esbuild
synoacltool -get node_modules
node_modules/.bin/vite --version
node_modules/@esbuild/linux-x64/bin/esbuild --version
node_modules/rollup/dist/bin/rollup --version
```

CRM scoped test:

```bash
node_modules/.bin/vitest run \
  --config modules/_bundled/glitter-crm/vitest.config.ts
```

전체 frontend test:

```bash
npm run test:run
```

root `vitest.config.ts`의 include에는 module 경로가 없으므로 위 전체 명령이
CRM scoped test를 대체하지 않는다. release 검증에서는 두 명령을 각각
실행한다.

정적 build:

```bash
npm run build
```

CRM은 runtime JS/CSS entry가 없으므로 별도 module bundle은 현재 생성하지
않는다. `npm run dev`, `vite`, `vite dev`는 금지한다. watch가 필요한 실제
frontend 개발 단계에서만 존재하는 `npm run build:core-watch` 또는
`/usr/local/bin/php83 artisan module:build glitter-crm --watch`를 검토한다.

## 7. 격리 lifecycle 검증

아래는 production이 아닌 복제본에서만 실행한다.

```bash
/usr/local/bin/php83 artisan module:list --env=testing
/usr/local/bin/php83 artisan module:install glitter-crm \
  --vendor-mode=bundled --env=testing
/usr/local/bin/php83 artisan module:activate glitter-crm --env=testing
/usr/local/bin/php83 artisan route:list \
  --path=api/modules/glitter-crm --env=testing
```

이후 Contact PHPUnit, CRM Vitest, `npm run build`, 브라우저 smoke를 순서대로
수행한다. JSON layout만 다시 동기화할 때 사용할 실제 명령은 다음과 같지만,
초기 설치 검증에서는 install/activate 흐름을 우선한다.

```bash
/usr/local/bin/php83 artisan module:refresh-layout glitter-crm --env=testing
```

종료 검증:

```bash
/usr/local/bin/php83 artisan module:deactivate glitter-crm --env=testing
/usr/local/bin/php83 artisan module:uninstall glitter-crm \
  --force --env=testing
```

기본 uninstall은 데이터를 보존한다. 데이터 삭제 정책까지 검증하는 별도
일회성 테스트에서는 재설치 후 전용 테스트 DB임을 다시 확인하고
`module:uninstall glitter-crm --force --delete-data --env=testing`을 사용한다.

## 8. Active output 검증

ModuleManager의 active 경로는 `modules/glitter-crm`이다. install/update는
`ExtensionPendingHelper::copyToActive()`를 통해 bundled source를 active
output으로 복사한다. active 경로는 직접 편집하지 않는다.

비교 대상:

- `module.json`, `module.php`, `composer.json`
- `src`, `database/migrations`
- `resources/routes`, `resources/layouts`, `resources/lang`
- `docs`

현재 CRM에는 runtime frontend bundle이 없고 `module.json.assets`는 비어 있다.
테스트 소스도 일반 복사 대상이지만 runtime 실행에는 사용되지 않는다.

격리 install/update 이후 비교:

```bash
diff -qr \
  modules/_bundled/glitter-crm \
  modules/glitter-crm

find modules/_bundled/glitter-crm -type f -print0 \
  | sort -z | xargs -0 sha256sum

find modules/glitter-crm -type f -print0 \
  | sort -z | xargs -0 sha256sum
```

active output에 lifecycle이 생성한 `vendor`, build output 또는 runtime 파일이
있다면 그 경로를 별도로 분류한 뒤 source 파일 비교 결과와 섞지 않는다.

## 9. Runtime smoke checklist

### 메뉴

- [ ] CRM 부모와 고객 자식 메뉴가 표시된다.
- [ ] 고객 메뉴 URL이 `/admin/crm/contacts`다.
- [ ] `glitter-crm.contacts.read`가 없으면 메뉴와 화면 접근이 차단된다.
- [ ] Dashboard, Lead, Activity, Task, Tag 메뉴가 없다.

### 목록

- [ ] 최초 loading overlay와 빈 상태가 정상이다.
- [ ] keyword 검색 대상이 이름·회사·이메일·전화번호·휴대전화다.
- [ ] 상태·담당자 필터 변경 시 page가 1로 초기화된다.
- [ ] 허용 정렬과 pagination metadata가 적용된다.
- [ ] `can_create`, row `can_update`, row `can_delete`가 UI와 일치한다.

### 등록

- [ ] 비회원 Contact와 User UUID 연결 Contact를 각각 생성한다.
- [ ] 담당자는 User UUID로 지정한다.
- [ ] 중복 email·phone·mobile을 허용한다.
- [ ] 공백 name, 잘못된 email/status/timezone/User UUID는 422다.
- [ ] `next_action_at`이 사이트 타임존 기준으로 왕복한다.
- [ ] metadata와 last_activity_at이 UI payload에 없다.
- [ ] next_task_at이 Contact write payload에 없고 Task Service만 갱신한다.
- [ ] Contact 목록은 API `next_task_at`을 `datetime('YYYY-MM-DD HH:mm')`으로
      표시하고 null이면 기존 `contacts.no_value` 번역을 사용한다.
- [ ] Task 생성·수정·삭제 성공 후 기존 Contact data source refetch가 실행된다.

### 수정

- [ ] read-only 사용자는 내용을 보되 저장할 수 없다.
- [ ] update 권한 사용자는 허용 필드만 수정한다.
- [ ] 연결 회원과 담당자를 각각 null로 해제할 수 있다.
- [ ] nullable 문자열을 null로 변경할 수 있다.
- [ ] last_activity_at은 읽기 전용이다.
- [ ] UI가 metadata를 보내지 않아 기존 metadata가 보존된다.

### 삭제와 오류

- [ ] 삭제 confirm 취소 시 요청하지 않는다.
- [ ] 확인 시 soft delete하고 목록에서 제거한다.
- [ ] 삭제 UUID 재조회는 404다.
- [ ] 401, 403, 404, 422 및 네트워크/500 오류 UI를 확인한다.

User dropdown은 코어 `/api/admin/users/search`를 사용하므로 실행 계정에
`core.users.read`도 필요하다. CRM 권한만 가진 관리자에서 selector가 403이
되는 현재 공용 API 권한 경계는 runtime에서 반드시 확인한다.

## 10. Release blocking 조건

다음 중 하나라도 충족하면 공개 배포를 중단한다.

- PHPUnit, migration up/down 또는 lifecycle 실패
- Contact route/middleware/permission 불일치
- UUID 대신 bigint ID 노출
- API envelope와 layout binding 불일치
- Vitest 또는 static build 실패
- 번역 key 누락이나 visible sentence hardcoding
- active output이 bundled source와 불일치
- runtime smoke 실패
- production/debug/demo 노출 검증 미완료

## 11. Production에서 금지되는 명령

- `module:install`, `module:update`, `module:activate`, `module:deactivate`,
  `module:uninstall`, `module:refresh-layout`
- migration 및 `RefreshDatabase` PHPUnit
- production API write
- `.env`를 `.env.testing`으로 복사하는 작업
- `npm run dev`, `vite dev`

## 12. 다음 단계 진입 조건

정적 계약 검사와 본 문서의 구조 검사가 통과하면 다음 도메인 코드 개발은
진행할 수 있다. 공개 배포는 PHPUnit, migration up/down,
install/activate/deactivate/uninstall, 실제 route collection, Vitest, static
build, active output 비교 및 runtime smoke가 모두 통과한 뒤에만 가능하다.
