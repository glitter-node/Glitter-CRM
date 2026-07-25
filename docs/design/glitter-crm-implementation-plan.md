# glitter-crm 구현 계획

## 공통 실행 원칙

모든 Phase는 Codex CLI로 수행하고 follow-up 질문은 한국어로 한다. Docker, `npm run dev`, Vite dev server, 직접 `php`/`composer` 호출을 사용하지 않는다. PHP는 `/usr/local/bin/php83`, Composer는 `/usr/local/bin/php83 /usr/local/bin/composer`를 사용한다. 소스는 `modules/_bundled/glitter-crm/`만 편집하고 installed/active extension 및 generated assets는 직접 수정하지 않는다. layout visible text는 번역 key만 사용한다. bundled 변경 후 test/build → install/update/activate → active output/cache/route 확인 → `git status`/`git diff` 순서를 지킨다.

각 Phase의 공통 제외 범위는 선택적 예약·이커머스·외부 SaaS 연동, core users table 변경, Controller/Listener의 직접 Model/DB 조작이다.

## Phase 1: 모듈 스켈레톤

- 목표: 설치·활성화·비활성화·제거 가능한 빈 독립 모듈과 metadata contract 확립.
- 포함: `module.json` vendor=`Glitter.kr`, `module.php` metadata author=`Glitter.kr`, `composer.json.authors` name=`Glitter.kr`, composer autoload, `CrmServiceProvider`, empty API route, permissions/menu skeleton, ko/en translations, `CHANGELOG.md`, `LICENSE`, `ModuleTestCase`, scenario/lifecycle test 기반. Frontend route는 실제 화면이 추가되는 Phase 2-C까지 생성하지 않는다.
- 제외: CRM migration/model/controller/layout/JS, 실제 CRUD permission endpoint.
- 생성 예상 파일: file map의 Phase 1 항목.
- 수정 예상 파일: 신규 모듈 외에는 docs API index가 generator에 의해 요구될 때만 생성 블록; active directory 직접 수정 없음.
- 테스트: manifest parse, identifier/namespace/vendor/version, dependency empty, provider boot, permissions/menu identifiers, install/activate/deactivate/uninstall 및 cache invalidation.
- 검증 명령:
  - `jq . modules/_bundled/glitter-crm/module.json`
  - `/usr/local/bin/php83 vendor/bin/phpunit modules/_bundled/glitter-crm/tests`
  - `/usr/local/bin/php83 artisan module:install glitter-crm`
  - `/usr/local/bin/php83 artisan module:activate glitter-crm`
  - `/usr/local/bin/php83 artisan route:list`
  - `/usr/local/bin/php83 artisan module:deactivate glitter-crm`
  - `/usr/local/bin/php83 artisan module:uninstall glitter-crm`
  - `git status --short && git diff -- modules/_bundled/glitter-crm`
- 완료 조건: 다른 extension 없이 lifecycle green, active output 일치, debug route 없음, version files 일치.
- 의존 단계: foundation 문서의 구현 전 결정 1~2.
- 위험: metadata author의 runtime 노출 범위, 잘못된 g7 constraint, uninstall test가 실제 DB를 건드림. 격리 test DB와 unified confirmation을 사용한다.

## Phase 2: Contact 백엔드

- 목표: Contact의 안전한 public UUID CRUD/API 완성.
- 포함: schema 선검사, migration, status/source/type 결정, Enum, Model, RepositoryInterface/Repository, Service transaction, Requests, Resource/Collection, Admin Controller, routes, read/create/update/delete permission, API docs, scenario.
- 제외: UI, tag attach, merge/import/export, Company entity.
- 생성 예상 파일: Contact migration/factory/enum/model/repository/service/requests/resources/controller/tests/docs.
- 수정 예상 파일: `module.php`, provider repository map, `src/routes/api.php`, backend lang, manifest version/CHANGELOG.
- 테스트: migration/up/down/FK/MariaDB, UUID binding, nullable user, non-member create, duplicate email/phone allowed, scope, filters/sorts/pagination, auth/permission/422/404/delete, service rollback/hooks.
- 검증 명령: schema inspection via `/usr/local/bin/php83 artisan` read-only commands; filtered PHPUnit then module suite; `api:docgen --help` 후 module scope; `route:list`; `module:update glitter-crm --source=bundled`; active route/API smoke.
- 완료 조건: Controller→Service→interface chain, Resource envelope, docs and scenario current, no optional dependency.
- 의존 단계: Phase 1; Contact type/retention/duplicate decisions.
- 위험: soft-delete uniqueness, PII exposure, uuid implicit binding, owner/assignee scope.

## Phase 3: Contact 관리자 UI

- 목표: Contact list/create/detail/edit/delete를 static-build runtime에서 제공.
- 포함: typed admin routes JSON, Contact index와 create/edit 공유 form layout, search, status/assignee filter, pagination/sort, modal delete, loading/empty/error/toast, abilities-based buttons, ko/en keys.
- 제외: custom components, dev server, import/export, bulk edit.
- 확정 파일: `resources/routes/admin.json`,
  `admin_crm_contact_index.json`, `admin_crm_contact_form.json`,
  layout tests. built-in component/handler만 사용하므로 runtime JS/CSS 및 package 파일은
  생성하지 않는다.
- 수정 파일: `module.php`, frontend/backend ko/en 번역, manifest/composer/changelog.
- 테스트: `createLayoutTest()`, `mockApi()`, translation-key audit, bindings/actions/components schema, permission visibility and error/loading/empty states.
- 검증 명령: module package scripts 존재 여부 확인; relevant Vitest; assets가 생긴 경우에만 Node executable/ACL 검사와 `/usr/local/bin/php83 artisan module:build glitter-crm`; `module:update`/activate/cache; active layout render.
- 완료 조건: built/active output으로 모든 CRUD state 렌더, visible hardcoded sentence 0.
- 의존 단계: Phase 2.
- 위험: unsupported component props/binding, global state leakage, stale layout cache.

## Phase 4: Tag

- 목표: Contact 전용 Tag CRUD와 attach/detach.
- 포함: tags/pivot migrations, model/repository/service/API, permissions, Contact filter, tag UI, docs/scenario/tests.
- 제외: Lead/Task tags, polymorphic taggable.
- 생성 예상 파일: Tag domain files, pivot migration, Requests/Resources/Controller/layout/tests/docs.
- 수정 예상 파일: Contact relation/repository/resource/form, routes/module/provider/lang/version/changelog.
- 테스트: slug/name normalization decision, pivot composite unique, cascade, permission, filter, concurrency/idempotent attach.
- 검증 명령: Phase 2 backend + Phase 3 layout/build lifecycle commands.
- 완료 조건: Contact tag filtering and safe detach work independently.
- 의존 단계: Phase 3.
- 위험: case/locale uniqueness and future polymorphic migration.

## Phase 5: Activity

- 목표: Contact timeline의 CRM-owned activities CRUD.
- 포함: Contact-required/Lead-nullable schema, type/direction Enums, actor/assignee, occurred/completed dates, CRUD/API/UI timeline representation using only verified components.
- 제외: core activity-log replacement, reservation/order activity types, polymorphic external relations.
- 생성 예상 파일: Activity domain stack, layouts/partials if supported, tests/docs/scenario.
- 수정 예상 파일: Contact detail/resource, provider/routes/module/lang/version/changelog.
- 테스트: type/date rules, Contact delete behavior, actor nullOnDelete, permission, chronological pagination, resource PII.
- 검증 명령: relevant PHPUnit/Vitest, API docgen, module build if applicable, update/activate/cache, route/render smoke.
- 완료 조건: Contact timeline functions without another module.
- 의존 단계: Phase 3; Phase 4 optional only for UI filtering.
- 위험: unbounded timeline queries, system activity authorization, activity-log name collision.

## Phase 6: Task

- 목표: assignable CRM tasks with lifecycle and deadlines.
- 포함: schema, status/priority Enums, CRUD, assignment action, filters, complete transition, UI, permissions, docs/scenario/tests.
- 제외: calendar sync, reminders/queues unless separately approved, external meeting provider.
- 생성 예상 파일: Task domain stack, assignment/status Requests, layouts/tests/docs.
- 수정 예상 파일: Contact detail/dashboard later, provider/routes/module/lang/version/changelog.
- 테스트: state transitions, completed_at consistency, assigned user deletion, due filter/sort/timezone, assign permission.
- 검증 명령: standard backend/UI/lifecycle set.
- 완료 조건: standalone task management and Contact linking.
- 의존 단계: Phase 3.
- 위험: orphan task policy, timezone conversion, assignee scope.

## Phase 7: Lead

- 목표: Contact-linked lead pipeline and state transitions.
- 포함: schema/value currency rules, Lead CRUD, status transition Service, Contact/Activity/Task links, admin list/form/detail, permissions/docs/scenario/tests.
- 제외: ecommerce order conversion, marketing automation, forecast AI.
- 생성 예상 파일: Lead stack, transition Request/Enum/layout/tests/docs.
- 수정 예상 파일: Activity/Task nullable lead relation and UI, provider/routes/module/lang/version/changelog.
- 테스트: transition matrix, closed/lost consistency, decimal/currency validation, scope, aggregate query performance.
- 검증 명령: standard backend/UI/lifecycle set plus migration upgrade test from previous version.
- 완료 조건: pipeline works with CRM-only entities.
- 의존 단계: Phases 5–6.
- 위험: converted semantics, monetary precision, circular resource serialization.

## Phase 8: Dashboard

- 목표: CRM aggregate overview.
- 포함: Contact/Lead/Task counts, recent Activity, permission-gated dashboard API/layout, tested date/status filters.
- 제외: external analytics, custom chart until verified component support.
- 생성 예상 파일: Dashboard RepositoryInterface/Repository or query service, Service, Controller/Resource, Request, layout, tests/docs/scenario.
- 수정 예상 파일: routes/menu/module/provider/lang/version/changelog.
- 테스트: permission, empty DB, aggregate correctness, query count/performance, timezone/date boundaries, layout states.
- 검증 명령: backend/layout/lifecycle set and query inspection.
- 완료 조건: accurate independent stats with no N+1.
- 의존 단계: Phases 2, 5, 6, 7.
- 위험: large-table scans and unsupported visual components.

## Phase 9: API 및 품질 안정화

- 목표: entire public module contract security/performance review.
- 포함: API docs/README, permission matrix, validation, pagination/filter/sort allowlists, error envelope, eager load/index analysis, PII masking/logging, export go/no-go.
- 제외: new integrations/features.
- 생성 예상 파일: missing contract tests, security/performance scenarios; export only after explicit decision.
- 수정 예상 파일: existing Requests/Resources/Repositories/docs/translations/manifest/changelog.
- 테스트: endpoint matrix for unauthenticated/unauthorized/allowed, invalid axes, SQL count, mass assignment, injection-like filter values, PII serialization.
- 검증 명령: full module PHPUnit/Vitest, doc generation/audit, route:list, build/update/cache/active smoke.
- 완료 조건: no undocumented endpoint, permission gap, unbounded list, hardcoded UI sentence.
- 의존 단계: Phases 1–8.
- 위험: export privacy, backwards compatibility, overbroad catch exposing exception messages.

## Phase 10: 배포 준비

- 목표: reproducible public `0.1.0` package readiness.
- 포함: clean install/update from prior test fixture/uninstall both preserve and delete-data, CHANGELOG/license/version sync, build, PHPUnit/Vitest, scenarios, smoke, demo/debug leakage, cache/freshness/ACL checks.
- 제외: tag/release/push (AGENTS.md 금지), production credentials/domain.
- 생성 예상 파일: missing Installation smoke tests/release checklist only.
- 수정 예상 파일: manifest/package/composer locks and changelog only when justified.
- 테스트: fresh install, activate, route/API/UI smoke, deactivate, reactivate, update, uninstall preserve, reinstall, isolated delete-data rollback.
- 검증 명령:
  - `jq '.scripts' package.json` and module package when present
  - native Node executable/ACL inspections required by `AGENTS.md`
  - `/usr/local/bin/php83 artisan module:build glitter-crm` when frontend assets exist
  - `/usr/local/bin/php83 vendor/bin/phpunit modules/_bundled/glitter-crm/tests`
  - `cd modules/_bundled/glitter-crm && npm run test:run` when package exists
  - `/usr/local/bin/php83 /usr/local/bin/composer test-smoke`
  - lifecycle commands, `route:list`, asset/layout/cache freshness checks
  - demo/placeholder/lorem scan, `git status`, scoped `git diff`
- 완료 조건: all checks green; active output equals bundled source; no debug/demo/credential/environment leakage; skipped checks explicitly reported.
- 의존 단계: all prior phases.
- 위험: NAS native binary ACL, stale generated assets, destructive uninstall against non-test DB. Never use real production data for destructive verification.
