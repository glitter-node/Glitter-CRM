# Phase 6 integration release quality gate

검증일: 2026-07-25  
대상 버전: 1.3.3  
판정: **runtime release pending**

## Environment and database safety

- 격리 프로젝트: `/volume1/Gnuboard/7/Gnuboard7Official-phase6-0XHSdd`
- production 프로젝트: `/volume1/Gnuboard/7/Gnuboard7Official`
- production DB 식별자: `Gnuboard7Official`
- 계획한 MariaDB 테스트 DB: `glitter_crm_phase6_testing_20260725`
- disposable SQLite: 격리 프로젝트의 `database/glitter_crm_phase6_testing.sqlite`
- PHP 8.3.27: `/usr/local/bin/php83`
- Composer 2.9.7: `/usr/local/bin/php83 /usr/local/bin/composer`
- Node 20.19.5, npm 10.8.2, x86_64

MariaDB 계정에는 별도 DB 생성 권한이 없었고 보이는 테스트 DB도 없었다. 따라서
MariaDB migration, Feature/API, permission, lifecycle 검증은 실행하지 않았다.

초기 SQLite 시도에서 별도 PHPUnit XML의 환경값보다 production `.env`가 우선되어
production DB 이름으로 연결되었다. CRM 테이블이 존재하지 않아 모든 write 쿼리는
`table not found`로 실패했으며 성공한 write는 없었다. 즉시 중단한 뒤 모든 DB 환경값을
프로세스 환경으로 고정했다. 이후 SQLite 실행은 `Connection: sqlite`와 격리 파일 경로를
오류 로그에서 확인했다. 프로젝트의 `.env.testing` 존재 가드는 Laravel이 실제로 그
파일을 로드하는지 보장하지 않으므로 별도 release blocker다.

## Verification matrix

| 영역 | 결과 | 근거/제한 |
|---|---|---|
| Composer validate | 통과 | schema warning만 존재(license, version field) |
| PHP syntax | 통과 | 모듈 전체 PHP 파일 |
| Pint | 통과 | 테스트 helper 포맷 수정 후 모듈 전체 `--test` |
| JSON/YAML | 통과 | 전체 resource JSON 및 13개 scenario |
| ko/en parity | 통과 | frontend key parity |
| version parity | 통과 | module/composer 1.3.3 |
| PHPUnit Unit | 통과 | 62 tests, 381 assertions |
| PHPUnit Feature | 미실행 | MariaDB 테스트 DB 없음; SQLite는 core board migration 비호환 |
| CRM migration up/down/reinstall | 통과 | standalone SQLite, prefix `g7_`, 6개 migration, final clean |
| MariaDB schema assertion | 미실행 | 안전한 MariaDB 테스트 DB 없음 |
| Contact/Tag/Activity/Task data runtime | 미실행 | Feature DB blocker |
| transaction/aggregate runtime | 미실행 | Feature DB blocker |
| route collection runtime | 미실행 | 활성 DB/lifecycle blocker |
| permission/API smoke | 미실행 | Feature DB blocker |
| scoped Vitest | 통과 | 4 files, 22 tests |
| static build | 통과 | 격리 복제본 `npm run build` |
| manifest/chunk existence | 통과 | 새 manifest와 실제 JS/CSS chunk 확인 |
| module lifecycle | 미실행 | 안전한 MariaDB 테스트 DB 없음 |
| bundled/active comparison | 미실행 | lifecycle 미실행 |
| browser UI smoke | 미실행 | active module/runtime 없음 |
| create-mode/error isolation | 미실행 | 구조 테스트만 통과, browser smoke 없음 |
| datetime/metadata/N+1/performance | 미실행 | runtime DB/UI blocker |
| production debug route exposure | 미실행 | production route collection 실행 금지 |
| `git diff --check` | 통과 | 검증 시작 시 통과; 최종 재검증 필요 |

## Static and frontend details

- Vite, esbuild, Rollup 실행 파일의 executable bit와 실제 버전 실행을 확인했다.
- `synoacltool -get node_modules` 결과는 `It's Linux mode`였으며 Synology ACL 상속
  구조는 존재하지 않았다.
- `npm ci`는 lock file을 변경하지 않았다.
- root static build 전 manifest보다 CRM bundled resource가 최신이었고, build 후
  manifest가 갱신되었다. CRM layout은 API로 제공되므로 root manifest에 개별 layout
  asset key가 생기는 구조는 아니다.
- Contact/Tag/Activity/Task layout Vitest는 모두 통과했다.

## Defects found and fixed

테스트 코드 결함만 수정했다.

1. 네 개 UI 구조 테스트의 private `json()` helper가 Laravel TestCase의 public
   `json()`과 충돌하던 PHP fatal을 `readJson()`으로 이름 변경했다.
2. Contact Vitest가 layout component `id`와 빈 state title을 payload/internal ID 및
   visible text로 오인하던 assertion 범위를 수정했다.
3. Contact/Tag Unit 테스트의 과거 phase assertion을 현재 Activity/Task/Tag UI 계약에
   맞게 갱신했다.
4. `ModuleTestCase`를 Pint 형식에 맞췄다.

설치 차단 결함 수정 버전은 1.3.3이며 CHANGELOG에 migration autoload 수정 근거를 기록한다.

## Known blockers and release decision

- 전용 MariaDB DB와 해당 DB에만 권한을 가진 계정이 필요하다.
- `.env.testing`을 실제 Laravel 환경 파일로 강제 로드하거나, bootstrap에서 runtime
  connection/database를 assertion하는 안전장치가 필요하다. 이는 core 범위이므로 이번
  모듈 작업에서는 수정하지 않았다.
- MariaDB Feature/API/permission/lifecycle, active output, browser UI, datetime,
  metadata preservation, N+1 및 performance smoke가 남아 있다.

핵심 runtime 항목이 미실행이므로 최종 판정은 **runtime release pending**이다.
