# glitter-crm 기반 조사

> 상태: 구현 전 설계 조사
> 대상 identifier: `glitter-crm`
> 제품 표기: CRM / Vendor·Author: Glitter.kr
> 목표 버전: `0.1.0`
> 작성일: 2026-07-25

## 1. 조사 범위

이 문서는 기능 코드를 만들지 않고 `glitter-crm`의 번들 구조, 데이터 계층, 관리자 API/UI, 권한, 다국어, 테스트 및 배포 수명주기를 조사한다. 조사 우선순위는 `AGENTS.md` → `README.md` → `docs/**` → 번들 구현이다. 예약, 이커머스, 외부 캘린더·회의·마케팅 서비스는 필수 의존성에서 제외한다.

## 2. 참조한 문서와 코드

주요 규정:

- `AGENTS.md`: `_bundled` SSoT, PHP 8.3 경로, 정적 빌드, i18n layout, lifecycle, DB·ACL·검증·보고 규칙.
- `README.md`: 저장소 기능 및 명령 개요.
- `docs/extension/module-basics.md`: identifier/namespace, `AbstractModule`, 자동 탐지 구조.
- `docs/extension/module-commands.md`, `extension-update-system.md`: install/activate/update/deactivate/uninstall 및 캐시 처리.
- `docs/extension/module-routing.md`, `module-layouts.md`, `module-assets.md`, `module-i18n.md`: API, layouts, assets, 번역.
- `docs/extension/permissions.md`, `menus.md`: 3단계 권한 선언과 메뉴 역할 연결.
- `docs/extension/changelog-rules.md`: SemVer, Keep a Changelog, 릴리스 smoke.
- `docs/backend/{controllers,service-repository,dto,enum,validation,api-resources,response-helper,routing,api-documentation,activity-log}.md`.
- `docs/frontend/{layout-json,data-binding,data-sources,actions,modal-usage,layout-testing}.md`.
- `docs/testing-guide.md`: `ModuleTestCase`, `_bundled` 테스트, scenario YAML, smoke suite.

비교 구현:

- 최소 샘플: `modules/_bundled/gnuboard7-hello_module/`.
- CRUD/API/UI 기준: `modules/_bundled/sirsoft-page/`.
- 권한·필터·관리 UI 기준: `modules/_bundled/sirsoft-board/`.
- 대규모 독립 API·assets·복합 UI 기준: `modules/_bundled/sirsoft-ecommerce/`.
- 공통 기반: `app/Extension/AbstractModule.php`, `BaseModuleServiceProvider.php`, `ModuleManager.php`, `app/Http/Resources/BaseApiResource.php`, `BaseApiCollection.php`, `app/Helpers/PermissionHelper.php`.

## 3. 번들 모듈 기본 구조

소스 위치는 `modules/_bundled/glitter-crm/`로 확정한다. 설치된 `modules/glitter-crm/`은 배포 출력이므로 직접 수정하지 않는다. 확인된 최소 구조는 다음과 같다.

```text
modules/_bundled/glitter-crm/
├── module.json
├── module.php
├── composer.json
├── CHANGELOG.md
├── database/{migrations,factories,seeders}/
├── docs/api/
├── resources/
│   ├── lang/{ko.json,en.json}
│   ├── layouts/admin/
│   └── routes.json
├── src/
│   ├── Providers/CrmServiceProvider.php
│   ├── routes/api.php
│   ├── lang/{ko,en}/
│   ├── Enums/
│   ├── Models/
│   ├── Repositories/Contracts/
│   ├── Repositories/
│   ├── Services/
│   └── Http/{Controllers,Requests,Resources}/
└── tests/{Feature,Unit,scenarios}/
```

`package.json`, `vite.config.ts`, `vitest.config.ts`, `resources/js`, `resources/css`, `dist/`, `editor-spec.json`은 custom handlers/assets가 실제로 필요해지는 경우에만 추가한다. JSON layout만으로 구현 가능하면 Phase 1에서 만들지 않는다. 근거는 hello 모듈이 package 없이 CRUD layout을 제공하고, ecommerce만 manifest assets와 IIFE 빌드를 선언하는 차이다.

`composer.json`은 기존 모듈 모두가 사용하며 PSR-4에 `Modules\Glitter\Crm\`, Database Seeders/Factories, Upgrades, tests를 등록한다. 모듈 class는 `Modules\Glitter\Crm\Module`, provider는 `Modules\Glitter\Crm\Providers\CrmServiceProvider`가 규칙에 맞는다.

별도 install/update/uninstall PHP 파일은 기본 구조가 아니다. `AbstractModule`의 자동 migration 탐지와 `ModuleManager` lifecycle을 사용하며, 버전 간 변환이 필요할 때만 `upgrades/`의 `AbstractUpgradeStep`을 추가한다.

## 4. manifest 규칙

실제 파일명은 `module.json`이다. 비교한 네 manifest의 공통 필드는 `identifier`, `vendor`, 다국어 `name`, `version`, `license`, 다국어 `description`, `g7_version`, `dependencies.modules`, `dependencies.plugins`이다. `assets`, `loading`, `github_url`, `github_changelog_url`, `hidden`은 선택이다.

초기 확정값:

- `identifier`: `glitter-crm`
- `name`: `{ "ko": "CRM", "en": "CRM" }`
- `version`: `0.1.0`
- `license`: 공개 라이선스 결정 후 기록 — **구현 전 결정 필요**
- `description`: ko/en 제공
- `dependencies`: modules/plugins 모두 빈 객체
- `hidden`: 사용하지 않음(공용 모듈)
- `g7_version`: 현재 저장소의 실제 최소 API 요구사항을 skeleton 테스트로 확인한 뒤 확정 — **구현 전 결정 필요**

`AbstractModule::getVendor()`는 `module.json.vendor`를 “사람이 읽는 벤더/개발자명”으로 반환하며 별도 형식 제한이 확인되지 않았다. 기존 bundled module 값은 소문자(`sirsoft`, `gnuboard7`)지만 이는 관례이지 validator 제약으로 확인되지 않았다. 따라서 요구사항을 보존해 manifest `vendor`는 `Glitter.kr`로 계획한다. identifier에서 추론되는 namespace/prefix는 계속 `glitter`/`Modules\Glitter\Crm`이다.

`module.json`의 `author` 필드는 비교 모듈과 `AbstractModule` 파서에서 확인되지 않았다. Author는 `module.php::getMetadata()['author']`와 `composer.json.authors[].name`에 `Glitter.kr`로 기록한다. 두 형식은 `docs/extension/module-basics.md`의 module metadata/composer 예시에 근거한다. 지원되지 않는 manifest `author` 필드는 추가하지 않는다.

버전 SSoT는 `module.json`이다. package/composer 파일을 추가하면 비교 모듈처럼 모두 `0.1.0`으로 동기화한다. 행동 변경 시 manifest 버전과 `CHANGELOG.md`를 함께 검토한다.

## 5. 계층 구조

확정 흐름:

```text
Route → Controller → FormRequest → Service → RepositoryInterface → Repository → Model
                                  ↓
                              hooks/events
Controller → API Resource/Collection → Base API envelope
```

- Controller는 `AdminBaseController`를 상속하고 Service만 주입한다. `sirsoft-page/.../PageController.php`.
- FormRequest는 `authorize(): true`; 실제 권한은 route의 `permission:admin,...` middleware가 담당한다.
- Service는 비즈니스 규칙, transaction, scope access 및 검증된 hook 발행 위치다. `PageService`.
- Repository는 Eloquent 조회, eager load, filter/sort/pagination을 소유한다. interface는 `src/Repositories/Contracts`.
- Provider는 `BaseModuleServiceProvider`와 `$repositories` map으로 interface를 bind한다. `PageServiceProvider`.
- Resource/Collection은 각각 `BaseApiResource`, `BaseApiCollection`을 상속한다.
- 상태·유형·우선순위·방향은 PHP backed Enum을 사용한다(`docs/backend/enum.md`).
- DTO는 여러 계층에서 데이터가 변형되거나 타입 경계가 복잡할 때 도입한다. 단순 CRUD 입력은 기존 Page처럼 validated array가 허용되므로 Phase 2에서 Contact filter DTO 필요성을 결정한다.
- Listener에서 Model 직접 조작하지 않는다. listener가 필요하면 Service 또는 RepositoryInterface를 주입한다.
- 존재하지 않는 CRM hook 이름은 현재 문서에서 제안하지 않는다. hook contract는 각 Phase의 scenario/test와 함께 명시 후 추가한다.

공통 `BaseModel`은 비교한 번들 모델에서 사용되지 않고 `Illuminate\Database\Eloquent\Model`을 직접 상속한다. fillable + `casts(): array`, 관계 메서드, 필요한 scope를 명시한다. API 직렬화는 Model `toArray()`가 아니라 Resource가 소유한다.

## 6. 데이터베이스 규칙

저장소에는 단일 전역 prefix 설정 대신 도메인별 실제 테이블명이 쓰인다(`pages`, `boards`, `ecommerce_*`, 샘플의 `gnuboard7_hello_module_memos`). 충돌 방지와 공용 배포를 위해 CRM은 `crm_` prefix를 사용한다: `crm_contacts`, `crm_leads`, `crm_activities`, `crm_tasks`, `crm_tags`, `crm_contact_tag`.

확인된 규칙:

- 내부 PK: `$table->id()`/`bigIncrements`의 unsigned bigint.
- 외부 식별자: hello sample의 `string('uuid', 36)->unique()` + Model `HasUuids::uniqueIds()`. CRM은 이 hybrid를 우선 채택한다.
- 코어 `users.id`: unsigned bigint. nullable user FK는 explicit unsigned bigint/foreignId nullable + `nullOnDelete()`.
- 소유 자식은 `cascadeOnDelete`, 선택 연결은 `nullOnDelete`, 참조 보존이 필요한 업무 데이터는 서비스 사전 차단 또는 restrict를 사례별 결정한다.
- 모든 컬럼과 boolean/enum 값에 한국어 comment, MySQL일 때 table comment, MariaDB 호환 SQL을 사용한다.
- 상태 저장은 문자열 + backed Enum cast를 권장한다. DB native enum 사용 여부는 **구현 전 실제 schema 사례 재검증 필요**.
- metadata는 nullable JSON/`text` + array cast 중 MariaDB 호환성과 기존 migration 관례를 먼저 확인한다.
- timestamps는 `$table->timestamps()`, nullable business date는 timestamp/datetime 사례에 맞춘다.
- `down()`은 `Schema::hasTable()` guard와 역순 drop으로 완전 rollback한다.
- index는 실제 query를 근거로 만든다. 명시 이름은 긴 이름/MariaDB 제한이 예상될 때 사용한다.

Contact 권고:

- `id` bigint PK + `uuid` unique public key.
- `user_id` nullable, `assigned_user_id` nullable, 둘 다 `users.id`에 `nullOnDelete`.
- email/phone은 unique로 만들지 않는다. 가족·공용 번호·중복 import와 soft-delete unique 충돌을 허용하기 위해서다.
- `normalized_email`, `normalized_phone`는 중복 탐지 요구를 확정한 뒤 추가한다. 초기에는 검색 성능 측정과 개인정보 정책 없이 추측해 넣지 않는다.
- soft delete는 Contact/Lead/Activity/Task에 우선 적용하고 Tag는 hard delete 가능성을 검토한다. 페이지 모듈이 후속 migration에서 soft delete를 제거한 사례가 있으므로 삭제·복구·retention 정책을 먼저 테스트로 확정한다.

모듈 uninstall은 기본적으로 데이터 보존, `--delete-data`일 때 migration rollback과 storage 삭제가 수행된다(`module-commands.md`). CRM 계획도 이 정책을 따른다.

## 7. 관리자 API 규칙

`ModuleRouteServiceProvider`가 `/api/modules/{identifier}`와 route name `api.modules.{identifier}.`를 자동 부여한다. 따라서 제시된 `/admin/api/crm/...`를 쓰지 않는다.

권장 URI:

```text
GET|POST       /api/modules/glitter-crm/admin/contacts
GET|PUT|DELETE /api/modules/glitter-crm/admin/contacts/{contact}
GET|POST       /api/modules/glitter-crm/admin/leads
GET|PUT|DELETE /api/modules/glitter-crm/admin/leads/{lead}
GET|POST       /api/modules/glitter-crm/admin/activities
GET|PUT|DELETE /api/modules/glitter-crm/admin/activities/{activity}
GET|POST       /api/modules/glitter-crm/admin/tasks
GET|PUT|DELETE /api/modules/glitter-crm/admin/tasks/{task}
GET|POST       /api/modules/glitter-crm/admin/tags
PUT|DELETE     /api/modules/glitter-crm/admin/tags/{tag}
```

route name은 `api.modules.glitter-crm.admin.contacts.index` 형태다. group은 `prefix('admin/contacts')`, `middleware(['auth:sanctum', 'throttle:600,1'])`, action별 `permission:admin,glitter-crm.contacts.read` 등을 사용한다. throttle 값은 기존 Page 사례이며 CRM에서 그대로 쓸지는 **후속 단계에서 현재 runtime 정책 검증 필요**.

UUID 공개 route binding은 hello sample의 UUID 외부 식별자를 기반으로 하되, 모델의 `getRouteKeyName(): 'uuid'`와 implicit binding 적용 여부를 Phase 2 테스트로 확정한다. 코어 User는 이미 uuid route key를 사용하지만 CRM FK는 내부 bigint를 유지한다.

목록 FormRequest는 allowlist 방식으로 `page`, `per_page(1..100)`, search, filter fields/operators, sort fields/direction을 검증한다. Repository가 권한 scope, eager loading, 검색, filter, sorting, pagination을 적용한다. bulk action은 전용 FormRequest와 PATCH endpoint로 만들고 단순 CRUD에 미리 포함하지 않는다.

응답은 Controller의 `success`, `successWithResource`, `error`, `notFound`와 번역 key를 사용한다. 목록은 `BaseApiCollection`의 `data` 및 `meta` envelope를 유지한다. 422 validation envelope는 Laravel/FormRequest와 공통 exception handler에 맡긴다. 삭제 성공은 Page 사례처럼 200 success envelope이며 204로 추측 변경하지 않는다.

각 endpoint는 `docs/api/{domain}.md`와 `docs/api/README.md`가 필요하고 `/usr/local/bin/php83 artisan api:docgen --scope=module:glitter-crm`의 실제 option 형식은 실행 전 `api:docgen --help`로 확인한다.

## 8. 관리자 UI 규칙

관리자 frontend 라우트 선언은 typed route인 `resources/routes/admin.json`, 화면은
`resources/layouts/admin/*.json`, `extends: "_admin_base"`를 사용한다.
Phase 2-C에서 `/admin/crm/contacts`, `/admin/crm/contacts/create`,
`/admin/crm/contacts/:id/edit`를 확정했으며 마지막 `:id` 값은 Contact UUID다.
조회 전용 상태와 수정은 공유 form layout에서 Resource ability로 분기한다.

실제 Page/Board layout에서 확인한 구성만 계획한다.

- 기본: `Div`, `Button`, `A`, `Input`, `Label`, `Textarea`, `Span`, `P`, `Icon`.
- composite: `PageHeader`, `DataGrid`, `Modal`, `Select`, `Alert`, `Badge`, `SearchBar`, `StatusBadge`, `TabNavigationScroll`, `HtmlEditor`, `MultilingualInput`, `FileUploader`.
- handler: `apiCall`, `navigate`, `setState`, `sequence`, `switch`, `openModal`, `closeModal`, `refetchDataSource`, `toast`, `showErrorPage`.
- binding: `{{...}}`, `route.id`, `query.*`, `_local`, `_global`, `_computed`, data source id, `$args[0]`.
- 구조: root `permissions`, `state`, `init_actions`, `data_sources`, `transition_overlay`, `modals`, `slots`; 조건은 component `if`, 반복은 문서화된 `iteration`.
- DataGrid는 server-side pagination/sort/selection/action 사례가 있으며 `onPageChange`, `onSortChange`, `onSelectionChange`, `onRowAction`은 `event` + `$args[0]`을 사용한다.
- loading은 `transition_overlay.wait_for`, empty는 DataGrid `emptyMessage`, API error는 `_dataSourceErrors`/`errorHandling` + `showErrorPage`, mutation 결과는 toast, 삭제는 Modal 확인 패턴이다.
- 권한 표시는 root `permissions`로 화면 진입을 제한하고, row/resource `abilities.can_update/can_delete`를 Resource에서 내려 버튼 `if`에 사용한다.

Contact 목록은 검색, status/assignee/tag filter, server pagination/sort를 제공한다. form/detail/edit/delete는 각각 독립 layout으로 검증한다. Activity timeline과 dashboard에 적합한 컴포넌트는 구현 시 `sirsoft-admin_basic/components.json` 및 실제 production layout에서 다시 확인한다. 확인 전 `Timeline`, chart 등의 사용을 확정하지 않는다.

모든 visible sentence, modal 문구, empty/loading/error/toast 문구는 `$t:glitter-crm...` 또는 공통 번역 key만 사용한다. 동적 count/name은 `$t:key|count={{...}}` 같은 translation parameter로 넣는다.

## 9. 권한 규칙

권한은 `module.php::getPermissions()`의 category/action 3단계 선언이다. 최종 identifier는 `{module}.{category}.{action}`이다. 기존 CRUD action은 `read/create/update/delete`이므로 후보의 `view`를 `read`로 바꾼다.

권장 초기 권한:

```text
glitter-crm.dashboard.read
glitter-crm.contacts.read|create|update|delete|export
glitter-crm.leads.read|create|update|delete
glitter-crm.activities.read|create|update|delete
glitter-crm.tasks.read|create|update|delete|assign
glitter-crm.tags.read|create|update|delete
glitter-crm.settings.read|update
```

`tags.manage`, `settings.manage` 같은 합성 action보다 기존 CRUD action을 사용한다. export/assign의 실제 action 지원은 문자열 자체는 가능하지만 UI/API 사례 및 scope 의미는 **구현 전 결정 필요**다. bulk는 별도 권한 사례가 명확하지 않아 대상 update/delete 권한을 재사용하는 것을 우선 검토한다.

각 category에 `resource_route_key`와 owner scope용 `owner_key`가 필요하다. CRM 담당자 scope를 `assigned_user_id`로 둘지, 생성자를 별도로 둘지는 **구현 전 결정 필요**다. 무리하게 owner scope를 담당자 의미로 재사용하지 않는다. 권한 type은 `admin`, roles는 최소 `admin`; `manager` 기본 부여 범위는 제품 정책 결정 후 선언한다.

메뉴 `permission`은 landing page의 read 권한과 일치시킨다. 설치 시 권한·role 연결·menu가 자동 생성되고, menu visibility는 `role_menus`와 active extension 상태를 함께 따른다.

## 10. 다국어 규칙

- backend: `src/lang/ko/*.php`, `src/lang/en/*.php`; `__('glitter-crm::messages...')`.
- frontend: `resources/lang/ko.json`, `en.json`; JSON 내부에 identifier wrapper를 넣지 않으며 runtime이 자동 병합한다.
- layout: `$t:glitter-crm.admin...`; 동적 parameter는 번역 parameter 문법.
- menu/permission/manifest: ko/en 객체.
- validation: FormRequest `messages()`/`attributes()`가 `glitter-crm::validation...`을 사용한다.
- enum: `src/lang/{ko,en}/enums.php`; Enum label은 번역 key 기반.
- 최소 제공 범위는 모든 사용자 표시 문자열의 ko/en 완전 대응이다.

## 11. 테스트 규칙

모든 module test는 `tests/ModuleTestCase.php` 또는 이를 확장한 `FeatureTestCase`를 상속한다. core + module migrations, provider, route, base roles가 자동 설정된다. 다른 선택 확장을 `$requiredExtensions`에 넣지 않아 독립성을 검증한다.

필수 계획:

- Feature: install/activate/deactivate/uninstall(`--delete-data` 포함 별도), migration rollback, permission/menu, API auth/403/CRUD/filter/sort/pagination/bulk.
- Unit: Enum, Model casts/relations/scopes, Repository, Service transaction/exceptions/hooks.
- layout: 각 JSON의 `createLayoutTest()` 렌더, `mockApi()`, loading/empty/error/permission/action.
- scenario: `tests/scenarios/<feature>.yaml`에 axes, expected outcomes, side effects, test file mapping.
- smoke: `tests/Feature/Installation/` 추가 여부는 기존 bundled modules에서 **현재 저장소에서 일관된 사례 확인 필요**.

정책에 맞춘 명령 후보:

```bash
/usr/local/bin/php83 vendor/bin/phpunit modules/_bundled/glitter-crm/tests
/usr/local/bin/php83 vendor/bin/phpunit --filter=ContactControllerTest modules/_bundled/glitter-crm/tests
cd modules/_bundled/glitter-crm && npm run test:run
/usr/local/bin/php83 artisan module:install glitter-crm
/usr/local/bin/php83 artisan module:activate glitter-crm
/usr/local/bin/php83 artisan module:update glitter-crm --source=bundled
/usr/local/bin/php83 artisan module:deactivate glitter-crm
/usr/local/bin/php83 artisan module:uninstall glitter-crm
```

`composer test-smoke` 문서 명령은 환경 규칙상 직접 실행하지 않고 `/usr/local/bin/php83 /usr/local/bin/composer test-smoke`로 변환한다. 실제 scripts 존재를 `composer.json`에서 먼저 검증한다. frontend package 생성 후 `build`, `test`, `test:run`을 확인하며 `build:watch`가 없으면 존재한다고 기록하지 않는다. Vite dev server는 사용하지 않는다.

## 12. 버전 및 배포 규칙

- 최초 버전은 `0.1.0`.
- `module.json`, composer/package/package-lock이 존재하면 버전 동기화. Vendor와 Author 표기는 각 지원 필드에서 `Glitter.kr`로 유지한다.
- `CHANGELOG.md`: Keep a Changelog, `## [0.1.0] - YYYY-MM-DD`, Added.
- core constraint는 실제 사용 API가 도입된 최소 G7 버전으로 정한다.
- 개발은 `_bundled`만 수정한다.
- frontend가 있으면 native Vite/esbuild/rollup executable bit, ACL inheritance, 실제 실행을 먼저 검증하고 `/usr/local/bin/php83 artisan module:build glitter-crm`을 사용한다.
- install 최초 반영 또는 `module:update glitter-crm --source=bundled`로 active output 반영 후 `module:activate`.
- `module:cache-clear glitter-crm`, route list, active layouts/assets, manifest freshness를 확인한다.
- 제거 기본은 데이터 보존; 데이터 삭제 검증은 격리 test DB에서 `--delete-data`.

## 13. CRM 초기 데이터 모델 평가

### Contact

개인과 조직을 하나의 Contact에 섞으면 company 문자열만으로 조직 관계·복수 담당자를 표현하기 어렵다. 0.1.0은 개인 중심 Contact + optional company 문자열로 범위를 제한하고 `type`은 `person|organization`을 미리 확정하지 않는다. 조직 entity 요구가 생기면 별도 Company를 후속 migration으로 추가한다.

권고 필드: bigint id, uuid, nullable user_id, status, name, company/job_title, email/phone/mobile, locale/timezone/source, nullable assigned_user_id, activity dates, metadata, timestamps, soft delete. `type`은 의미 확정 전 제외 후보. email/phone unique 금지. user/assignee delete는 null.

### Lead

Contact는 필수 연결을 우선한다. 금액은 decimal precision/scale을 ecommerce migration 사례와 통화 정책 확인 후 확정한다. status는 `new/contacted/qualified/converted/lost`; converted 시 Contact가 이미 있으므로 “전환 결과” 의미를 명확히 해야 한다. closed_at/lost_reason consistency는 Service가 담당한다.

### Activity

CRM 활동 기록과 코어 activity log는 목적이 다르므로 별도 `crm_activities`로 둔다. `related_type/id` polymorphic는 외부 모듈 결합과 무결성 저하를 가져오므로 0.1.0에서 제외하고 Contact + nullable Lead만 지원한다. `system` type은 누가 생성할 수 있는지 정책 결정이 필요하다. actor/assignee는 nullable + nullOnDelete.

### Task

Phase 5-A에서는 Task를 Contact에 필수 귀속하고 Lead 연결은 만들지 않는다.
담당 User는 nullable `assigned_to` FK이며 User 삭제 시 null 처리한다. 상태는
`pending/in_progress/completed/cancelled`, 우선순위는
`low/normal/high/urgent` backed enum이다. 기한은 UTC로 저장하고 완료 시각은
Service가 상태 전이에 맞춰 관리한다.

### Tag

0.5.0에서는 Contact 전용 `crm_contact_tag`만 사용한다. Tag는 bigint `id`와
보조 unique `uuid`, soft delete를 사용한다. `slug`는 Unicode 문자·숫자를
보존하는 소문자 하이픈 정규화 결과를 191자로 제한하고 soft-deleted Tag까지
포함해 unique하게 유지한다. `color`는 nullable `#RRGGBB`만 저장한다.
pivot는 timestamps, `unique(contact_id, tag_id)`, 양쪽 cascade FK를 사용한다.
Service의 attach/detach/sync 외부 계약은 Contact와 Tag UUID만 받으며 빈 sync
배열은 전체 연결 해제다.

## 14. 독립 모듈 경계

허용 의존은 G7 core의 User, auth, permission/menu, layout engine, hook infrastructure, API base/resource, DB와 module lifecycle뿐이다. `dependencies.modules/plugins`는 빈 객체다. CRM migration·provider·route·menu·tests는 다른 extension class/table/config/identifier를 참조하지 않는다. 담당자는 core User, Contact는 독립 entity이며 users table을 수정하지 않는다.

## 15. 선택적 연동을 위한 향후 확장 지점

후속 연동은 CRM Service public contract 또는 문서화된 hook/event contract를 통해 adapter가 수행한다. CRM core schema에 예약/주문/Zoom 식별자를 넣지 않는다. 외부 reference가 필요해지면 별도 integration-owned mapping table을 권장한다. Activity type extension은 closed Enum과 충돌하므로 등록형 catalog 또는 integration event projection 중 하나를 별도 설계한다. **현재 존재하는 hook으로 확인된 것은 아니며, 후속 단계에서 contract를 정의하고 테스트해야 한다.**

## 16. 확인되지 않은 사항

- `module.json`의 `author` field: **현재 저장소에서 확인되지 않음**.
- manifest vendor에 별도 형식 제한은 **현재 저장소에서 확인되지 않음**; `AbstractModule`은 사람이 읽는 값으로 설명하고 기존 값은 lowercase 관례다.
- 정확한 최소 `g7_version`: **구현 전 결정 필요**.
- CRM 라이선스와 public repository URLs: **구현 전 결정 필요**.
- Contact 개인/조직 범위, retention/restore, duplicate merge 정책: **구현 전 결정 필요**.
- 담당자 기반 permission scope와 `owner_key` 의미: **후속 단계에서 검증 필요**.
- export 파일 형식/개인정보 마스킹/감사 정책: **구현 전 결정 필요**.
- package 없이 layout test만 수행하는 표준 module Vitest 구성: **후속 단계에서 검증 필요**.
- version synchronization 전용 명령: **현재 저장소에서 확인되지 않음**; test/manifest audit를 추가 검토.
- uninstall 데이터 보존의 CRM 개인정보 고지: **구현 전 결정 필요**.

## 17. 구현 전 결정 사항

1. manifest vendor와 metadata/composer author를 `Glitter.kr`로 두고 namespace/prefix만 `glitter`로 유지하는 방식 승인.
2. license와 `g7_version`.
3. Contact를 0.1.0에서 개인 중심으로 제한할지.
4. soft delete/retention/복구 및 중복 merge 정책.
5. manager 기본 권한과 assignee scope.
6. Task의 Contact/Lead 없는 독립 task 허용 여부.
7. export를 0.1.0에 포함할지와 개인정보 보호.

## Phase 2-A 구현 확정 사항 (2026-07-25)

- 논리 테이블명은 `crm_contacts`다. 코어/번들 migration이 `users`, `pages`, `ecommerce_*`처럼 논리명을 선언하고 DB connection prefix를 migration에 하드코딩하지 않는 관례를 따른다.
- 내부 키는 bigint `id`, 공개 식별자는 `HasUuids`가 생성하는 unique `uuid`이며 route key도 `uuid`다. 근거: `gnuboard7-hello_module/src/Models/Memo.php`와 해당 migration.
- 개인 고객만 다루는 현재 범위에서 단일 값 `type` 및 `ContactType`은 제외했다. 유입 경로도 값이 확정되지 않아 nullable `source` 문자열만 두고 `ContactSource` enum은 만들지 않았다.
- 상태는 `active`, `inactive`, `archived` backed enum으로 확정했다. archived는 업무상 보관 상태이고 `deleted_at`은 오삭제 복구와 향후 관계 보존을 위한 삭제 lifecycle이므로 soft delete와 역할이 다르다.
- `user_id`, `assigned_user_id`는 nullable unsigned bigint FK이며 `users.id` 삭제 시 `nullOnDelete`한다. 어느 컬럼에도 unique 제약을 두지 않는다.
- email/phone/mobile은 중복을 허용한다. email은 trim 후 표시값의 대소문자를 보존하며 별도 정규화 컬럼은 중복 병합 정책이 정해질 때 재검토한다.
- Repository contract는 `paginate`, `findByUuid`, `findByUuidOrFail`, `create`, `update`, `delete`로 확정했다. Service가 write transaction과 최소 불변 조건(name/status/metadata)을 담당하고, 사용자 존재 검증은 Phase 2-B FormRequest에서 담당한다.
- 데이터 기반 추가는 공개 기능 추가이므로 manifest/composer/changelog 버전을 `0.2.0`으로 동기화했다. HTTP API와 관리자 UI는 포함하지 않는다.

## Phase 2-B 구현 확정 사항 (2026-07-25)

- Contact 관리자 API는 `src/routes/api.php`에서 `admin/contacts`만 선언한다. `ModuleRouteServiceProvider`가 최종 URL `/api/modules/glitter-crm/admin/contacts`와 name prefix `api.modules.glitter-crm.`을 적용한다.
- route group은 `auth:sanctum`, `admin`, `throttle:600,1`을 사용하고 각 endpoint에 `glitter-crm.contacts.{read|create|update|delete}` middleware를 분리한다.
- 단건 식별자는 `{contact}` UUID 문자열이며 Controller는 Eloquent나 Repository를 직접 조회하지 않고 `ContactService::findByUuid()`를 호출한다.
- 사용자 참조 API 값은 코어 User UUID다. FormRequest가 `Rule::exists(User::class, 'uuid')`로 검증하고 Service가 내부 bigint FK로 변환한다.
- 목록 기본 `per_page`는 20, 최대 100이다. 응답은 `BaseApiCollection` 기반의 `data.data`, `data.meta`, `data.abilities` 구조다.
- `last_activity_at`은 Activity Service 전용 내부 갱신 필드라 API 입력에서 제외하고, `next_action_at`만 관리자 입력을 허용한다.
- DELETE는 HTTP 200 성공 envelope와 soft delete를 사용한다. restore/force-delete endpoint는 없다.
- Contact 관리자 API 추가 시 manifest/composer/changelog 버전을 `0.3.0`으로 동기화했다.
- Phase 2-C에서 검증된 모듈 frontend route 규칙(`resources/routes/admin.json`)과
  관리자 layout 규칙(`resources/layouts/admin`)을 적용해
  `/admin/crm/contacts`, `/admin/crm/contacts/create`,
  `/admin/crm/contacts/:id/edit`를 확정했다. CRM 최상위 메뉴는 URL 없는
  부모로 유지하고 `glitter-crm.contacts.read` 권한의 고객 자식 메뉴만
  활성화했다.
- Contact UI는 API Resource/Collection의 `abilities`, 코어
  `/api/admin/users/search` User UUID 선택, 표준 pagination envelope를
  재사용한다. UI 기능 추가에 따라 버전은 `0.4.0`으로 동기화했다.
- Phase 2-D 정적 감사에서 `next_action_at`의 사용자 표시 문자열 단순 치환이
  사이트 타임존 저장 계약과 불일치함을 확인했다. Request는
  `TimezoneHelper::fromSiteDateTime()`, Resource는
  `formatDateTimeLocalStringForSite()`를 사용하도록 고정하고 일반 Contact
  Service write allowlist에서 `last_activity_at`을 제거했다. 제품 코드 수정에
  따라 기준 버전은 `0.4.1`이다.
- Phase 3-A에서 `crm_tags`, `crm_contact_tag`, Tag Model/Repository/Service와
  Contact 양방향 관계를 추가했다. Tag API와 UI는 포함하지 않으며 모듈 버전은
  `0.5.0`이다.
- Phase 3-B에서 `/api/modules/glitter-crm/admin/tags` UUID CRUD API,
  endpoint별 `glitter-crm.tags.*` 권한, Request/Resource/Collection을 추가했다.
  Contact–Tag 관계 API와 Tag 관리자 UI는 포함하지 않으며 모듈 버전은 `0.6.0`이다.
- Phase 3-C에서 Tag 목록/공유 폼 layout과 CRM Tag 메뉴를 추가했다. Contact
  create/update는 선택적 `tag_uuids`를 받아 Contact write와 같은 transaction에서
  활성 Tag 관계를 sync하며 Resource는 경량 `tags` 배열을 반환한다. 별도 관계
  endpoint와 신규 frontend runtime asset은 추가하지 않았고 버전은 `0.7.0`이다.
- Phase 4-A에서 `crm_activities`, `ActivityType`, Activity
  Model/Repository/Service와 Contact `activities` 관계를 추가했다. Activity는
  Contact에 직접 귀속되고 작성자는 nullable `users.id` FK다. 생성·수정·soft
  delete와 같은 transaction에서 삭제되지 않은 Activity의
  `MAX(occurred_at)`을 Contact `last_activity_at`으로 재계산한다. API/UI,
  Observer와 자동 상태 기록은 포함하지 않으며 버전은 `0.8.0`이다.
- Phase 4-B에서 `/api/modules/glitter-crm/admin/activities` UUID CRUD와
  Contact UUID 하위 GET 목록을 추가했다. creator는 인증 User UUID로
  기록하고 관리자 입력에서 system type을 차단하며, 기존 system Activity는
  read-only와 false abilities로 보호한다. UI·timeline은 포함하지 않고
  버전은 `0.9.0`이다.
- Phase 4-C에서 Activity는 독립 관리자 route/menu 없이 Contact 수정 layout
  내부 타임라인으로 제공한다. Contact nested GET과 전역 UUID CRUD API를
  재사용하고, `datetime` pipe 및 브라우저 `Date` ISO 변환으로 로컬 표시와
  timezone-aware 요청을 처리한다. system Activity는 읽기 전용이며 신규
  component/CSS/runtime handler 없이 버전 `1.0.0`으로 동기화한다.
- Phase 5-A에서 `crm_tasks`, `TaskStatus`, `TaskPriority`, Task
  Model/Repository/Service 및 Contact `tasks` 관계를 추가했다. 담당자는 nullable
  `users.id` FK이고 공개 Service 계약은 User UUID를 사용한다. 모든 Task
  create/update/soft-delete transaction에서 삭제되지 않은
  pending/in_progress Task의 `MIN(due_at)`을 Contact `next_task_at`으로
  재계산한다. API/UI/알림/Observer는 포함하지 않으며 버전은 `1.1.0`이다.
- Phase 5-B에서 `/api/modules/glitter-crm/admin/tasks` UUID CRUD와 Contact
  UUID 하위 GET 목록을 추가했다. Request는 enum SSoT, User UUID, timezone-aware
  due range, metadata object와 `overdue=true|1`을 검증한다. Resource는 경량
  Contact/assignee, strict overdue 계산과 abilities를 반환한다. UI·알림·action
  endpoint는 포함하지 않으며 현재 패치 버전은 `1.3.3`이다.

Phase 5-C에서 Task는 독립 메뉴 없이 Contact 수정 layout의 section으로
통합되었다. Contact nested 목록과 전역 CRUD API를 사용하며 User UUID 담당자,
timezone-aware 기한, Resource overdue, abilities 및 pagination 계약을 따른다.

Phase 6-B4에서 Contact 목록의 기존 “다음 조치” 표시 슬롯을 서버 관리
`next_task_at` aggregate에 연결했다. 관리자 입력 `next_action_at`은 독립
Contact 필드로 유지한다. 표시는 기존 `datetime` pipe와 `contacts.no_value`
번역을 사용하며 Task mutation의 기존 Contact refetch 체인을 재사용한다.
8. custom JS/assets 없이 JSON layouts로 시작할지.

이 결정 전에도 skeleton과 Contact의 기본 CRUD 계획은 진행할 수 있으나 schema/permission/public contract를 되돌리는 결정은 Phase 1 이전에 확정해야 한다.
