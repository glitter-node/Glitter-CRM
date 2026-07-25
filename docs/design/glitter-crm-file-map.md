# glitter-crm 예상 파일 맵

Phase 5-C는 `admin_crm_contact_form.json`에 Task section과 modal을 내장하며
독립 Task layout은 만들지 않는다. 관련 파일은 `docs/admin/tasks.md`,
`docs/testing/task-ui-quality-gate.md`,
`resources/js/__tests__/layouts/admin-contact-task-layout.test.ts`,
`tests/Unit/TaskAdminUiStructureTest.php`, `tests/scenarios/task-admin-ui.yaml`이다.

> 이 트리는 조사 시점의 실제 bundled module 패턴을 따른 예상안이다. 기능 코드는 아직 생성하지 않는다. `package.json`/JS/assets는 필요성이 확인될 때만 추가한다.

| 파일 경로 (`modules/_bundled/glitter-crm/` 기준) | 역할 | Phase | 참조 구현 | 테스트 대응 |
|---|---|---:|---|---|
| `module.json` | manifest SSoT | 1 | `gnuboard7-hello_module/module.json`, `sirsoft-ecommerce/module.json` | `tests/Feature/Installation/ModuleManifestTest.php` |
| `module.php` | permissions, menus, seeders/listeners 및 Author=`Glitter.kr` metadata 선언 | 1~8 | `sirsoft-page/module.php`, `docs/extension/module-basics.md` | `ModuleDefinitionTest.php`, permission tests |
| `composer.json` | module PSR-4/autoload-dev, Author=`Glitter.kr` | 1 | `sirsoft-page/composer.json`, module basics composer example | autoload/provider test |
| `CHANGELOG.md` | Keep a Changelog/SemVer | 1~10 | `sirsoft-page/CHANGELOG.md` | manifest/version audit |
| `LICENSE` | 공개 라이선스 전문 | 1 | bundled production modules | packaging audit |
| `src/Providers/CrmServiceProvider.php` | repository bindings | 1~8 | `PageServiceProvider.php` | provider binding test |
| `src/routes/api.php` | module admin API routes | 1~8 | `sirsoft-page/src/routes/api.php` | route/permission feature tests |
| `resources/routes/admin.json` | admin template route→layout map | 2-C~8 | `sirsoft-board/resources/routes/admin.json` | route/layout tests |
| `resources/lang/ko.json`, `en.json` | frontend translations | 1~8 | `sirsoft-page/resources/lang/*.json` | i18n key parity/layout audit |
| `src/lang/{ko,en}/messages.php` | API/domain messages | 1~8 | Page module lang | Controller/API tests |
| `src/lang/{ko,en}/validation.php` | validation messages/attributes | 2~7 | Page module validation | 422 tests |
| `src/lang/{ko,en}/enums.php` | Enum labels | 2~7 | Board module enums lang | Enum/resource tests |
| `tests/ModuleTestCase.php` | module migration/provider/route test base | 1 | Page/hello ModuleTestCase | all PHP tests |
| `tests/FeatureTestCase.php` | feature auth/role helpers if needed | 1 | Page/hello FeatureTestCase | Feature tests |
| `tests/scenarios/module-lifecycle.yaml` | lifecycle axes/effects | 1 | `docs/testing-guide.md` | lifecycle tests |
| `docs/api/README.md` | generated API index | 2 | bundled module API README | api doc audit |
| `database/migrations/*_create_crm_contacts_table.php` | Contact schema | 2 | hello UUID migration, Page user FK | migration/Contact tests |
| `src/Enums/ContactStatus.php` | Contact status values | 2 | Board backed Enums | Enum/validation tests |
| `src/Models/Contact.php` | fillable/casts/relations/UUID | 2 | hello Memo, Page | Model test |
| `src/Repositories/Contracts/ContactRepositoryInterface.php` | Contact persistence contract | 2 | Page contract | binding/service tests |
| `src/Repositories/ContactRepository.php` | search/filter/sort/paginate/CRUD | 2 | `PageRepository` | Repository tests |
| `src/Services/ContactService.php` | transaction/domain rules/hooks | 2 | `PageService` | Service tests |
| `src/Http/Requests/IndexContactRequest.php` | query allowlists | 2-B | `PageListRequest` | API filter tests |
| `src/Http/Requests/StoreContactRequest.php`, `UpdateContactRequest.php` | write validation | 2 | Page Requests | API 422 tests |
| `src/Http/Resources/ContactResource.php`, `ContactCollection.php` | API serialization/envelope/abilities | 2 | Page Resources | Resource/API tests |
| `src/Http/Controllers/Admin/ContactController.php` | thin admin adapter | 2 | `PageController` | `ContactControllerTest.php` |
| `docs/api/contacts.md` | Contact contract | 2 | Page API docs | api doc audit |
| `tests/scenarios/contact-data-layer.yaml`, `contact-admin-api.yaml` | Contact data/API scenario matrices | 2-A/2-B | testing guide manifests | Contact test suite |
| `resources/layouts/admin/admin_crm_contact_index.json` | search/filter/grid/pagination | 2-C | `admin_page_list.json` | Contact list layout test |
| `resources/layouts/admin/admin_crm_contact_form.json` | create/read-only detail/edit shared form | 2-C | `admin_page_form.json` | Contact form layout test |
| `database/migrations/2026_07_25_000002_create_crm_tags_table.php` | Tag schema | 3-A | Contact/Hello UUID migrations | `TagDataLayerTest` |
| `database/migrations/2026_07_25_000003_create_crm_contact_tag_table.php` | Contact–Tag pivot | 3-A | ecommerce product/category pivot | `TagDataLayerTest` |
| `src/Models/Tag.php` | Tag entity와 Contact 관계 | 3-A | `Contact`, ecommerce many-to-many models | `TagStructureTest` |
| `src/Repositories/Contracts/TagRepositoryInterface.php`, `src/Repositories/TagRepository.php` | Tag persistence와 relation persistence | 3-A | Contact repository pattern | `TagDataLayerTest` |
| `src/Services/TagService.php` | CRUD 정규화와 UUID attach/detach/sync | 3-A | Contact service pattern | `TagDataLayerTest` |
| `tests/Unit/TagStructureTest.php`, `tests/Feature/TagDataLayerTest.php` | Tag 구조 및 DB 계약 | 3-A | CRM Contact tests | 자체 |
| `tests/scenarios/tag-data-layer.yaml` | Tag 데이터 계층 scenario | 3-A | CRM scenario manifests | `TagDataLayerTest` |
| `docs/testing/tag-data-quality-gate.md` | Tag 격리 검증·release gate | 3-A | Contact quality gate | 정적 문서 검토 |
| `src/Http/Requests/{IndexTag,StoreTag,UpdateTag}Request.php` | Tag CRUD query/body validation | 3-B | Contact Requests | `TagHttpStructureTest`, `TagAdminApiTest` |
| `src/Http/Resources/TagResource.php`, `TagCollection.php` | Tag 공개 직렬화·pagination·abilities | 3-B | Contact Resources | `TagAdminApiTest` |
| `src/Http/Controllers/Admin/TagController.php` | Tag 관리자 API adapter | 3-B | `ContactController` | `TagAdminApiTest` |
| `tests/Unit/TagHttpStructureTest.php`, `tests/Feature/TagAdminApiTest.php` | Tag HTTP 구조 및 격리 Feature 계약 | 3-B | Contact HTTP tests | 자체 |
| `docs/api/tags.md`, `docs/testing/tag-api-quality-gate.md`, `tests/scenarios/tag-admin-api.yaml` | Tag API 문서·검증 gate·scenario | 3-B | Contact API/testing guides | audits/tests |
| `resources/layouts/admin/admin_crm_tag_index.json`, `admin_crm_tag_form.json` | Tag 목록 및 생성·수정 공유 UI | 3-C | Contact list/form, SearchableDropdown | `TagAdminUiStructureTest`, `admin-tag-layouts.test.ts` |
| `resources/js/__tests__/layouts/admin-tag-layouts.test.ts` | Tag layout rendering과 binding 계약 | 3-C | Contact layout test | 자체 |
| `docs/admin/tags.md`, `docs/testing/tag-ui-quality-gate.md`, `tests/scenarios/tag-admin-ui.yaml` | Tag UI 문서·release gate·scenario | 3-C | Contact UI docs/tests | audits/tests |
| `database/migrations/2026_07_25_000004_create_crm_activities_table.php` | Contact 귀속 Activity schema | 4-A | Contact migration, core activity creator FK | `ActivityDataLayerTest` |
| `src/Enums/ActivityType.php` | note/call/email/meeting/status_change/system | 4-A | `ContactStatus` | `ActivityStructureTest` |
| `src/Models/Activity.php` | UUID, casts, Contact/creator 관계 | 4-A | `Contact`, `Tag` | `ActivityStructureTest` |
| `src/Repositories/Contracts/ActivityRepositoryInterface.php`, `src/Repositories/ActivityRepository.php` | 기간·유형·작성자·검색 persistence | 4-A | Contact repository | `ActivityDataLayerTest` |
| `src/Services/ActivityService.php` | UUID 해석, UTC 정규화, transaction과 최근 활동 재계산 | 4-A | Contact/Tag services | `ActivityDataLayerTest` |
| `tests/Unit/ActivityStructureTest.php`, `tests/Feature/ActivityDataLayerTest.php` | Activity 구조·DB 계약 | 4-A | CRM Tag tests | 자체 |
| `tests/scenarios/activity-data-layer.yaml`, `docs/testing/activity-data-quality-gate.md` | Activity scenario와 release gate | 4-A | Tag data gate | Activity tests/audit |
| `src/Http/Requests/{IndexActivity,IndexContactActivity,StoreActivity,UpdateActivity}Request.php` | Activity query/body validation | 4-B | Contact/Tag Requests | `ActivityHttpStructureTest`, `ActivityAdminApiTest` |
| `src/Http/Resources/ActivityResource.php`, `ActivityCollection.php` | Activity 공개 직렬화·pagination·system abilities | 4-B | Contact Resources | `ActivityAdminApiTest` |
| `src/Http/Controllers/Admin/ActivityController.php` | CRUD와 Contact nested GET adapter | 4-B | ContactController | `ActivityAdminApiTest` |
| `docs/api/activities.md`, `docs/testing/activity-api-quality-gate.md`, `tests/scenarios/activity-admin-api.yaml` | Activity API 문서·검증 gate·scenario | 4-B | Tag API docs/gate | audits/tests |
| `resources/layouts/admin/admin_crm_contact_form.json` | Contact 수정 전용 Activity timeline·CRUD modal | 4-C | Contact form, ecommerce activity log | `ActivityAdminUiStructureTest`, `admin-contact-activity-layout.test.ts` |
| `resources/js/__tests__/layouts/admin-contact-activity-layout.test.ts` | Activity binding·권한·payload layout 계약 | 4-C | 기존 Contact layout test | 자체 |
| `tests/Unit/ActivityAdminUiStructureTest.php`, `tests/scenarios/activity-admin-ui.yaml` | Activity UI 정적 계약과 runtime scenario | 4-C | Tag UI tests/scenario | 자체 |
| `docs/admin/activities.md`, `docs/testing/activity-ui-quality-gate.md` | Contact 내 Activity 운영 문서와 release gate | 4-C | Activity API gate | audits/tests |
| `database/migrations/2026_07_25_000005_add_next_task_at_to_crm_contacts_table.php` | Contact Task 집계 시각 | 5-A | Activity 집계 정책 | `TaskDataLayerTest` |
| `database/migrations/2026_07_25_000006_create_crm_tasks_table.php` | Contact 귀속 Task schema | 5-A | Activity migration | `TaskDataLayerTest` |
| `src/Enums/{TaskStatus,TaskPriority}.php` | Task 상태·우선순위 | 5-A | Activity/Contact Enums | `TaskStructureTest` |
| `src/Models/Task.php` | UUID, casts, Contact/assignee 관계 | 5-A | Activity model | `TaskStructureTest` |
| `src/Repositories/Contracts/TaskRepositoryInterface.php`, `src/Repositories/TaskRepository.php` | Task 검색·필터·overdue persistence | 5-A | Activity repository | `TaskDataLayerTest` |
| `src/Services/TaskService.php` | UUID 해석, 상태 전이, UTC와 next_task_at transaction | 5-A | Activity service | `TaskDataLayerTest` |
| `tests/Unit/TaskStructureTest.php`, `tests/Feature/TaskDataLayerTest.php` | Task 구조·DB 계약 | 5-A | Activity tests | 자체 |
| `tests/scenarios/task-data-layer.yaml`, `docs/testing/task-data-quality-gate.md` | Task scenario와 release gate | 5-A | Activity data gate | Task tests/audit |
| `src/Http/Requests/{IndexTask,IndexContactTask,StoreTask,UpdateTask}Request.php` | Task query/body validation | 5-B | Activity Requests | `TaskHttpStructureTest`, `TaskAdminApiTest` |
| `src/Http/Resources/TaskResource.php`, `TaskCollection.php` | Task 공개 직렬화·pagination·abilities | 5-B | Activity Resources | `TaskAdminApiTest` |
| `src/Http/Controllers/Admin/TaskController.php` | CRUD와 Contact nested GET adapter | 5-B | ActivityController | `TaskAdminApiTest` |
| `docs/api/tasks.md`, `docs/testing/task-api-quality-gate.md`, `tests/scenarios/task-admin-api.yaml` | Task API 문서·검증 gate·scenario | 5-B | Activity API docs/gate | audits/tests |
| `resources/layouts/admin/admin_crm_task_{list,form,detail}.json` | 향후 Task UI 후보(현재 미구현) | 후속 | Page/Board layouts | layout tests |
| `database/migrations/*_create_crm_leads_table.php` | Lead schema | 7 | domain migrations | migration tests |
| `database/factories/LeadFactory.php` | Lead fixtures | 7 | module factories | Lead tests |
| `src/Enums/LeadStatus.php` | lead state | 7 | Board Enums | transition tests |
| `src/Models/Lead.php` | lead relations/casts | 7 | module models | Model tests |
| `src/Repositories/Contracts/LeadRepositoryInterface.php`, `src/Repositories/LeadRepository.php` | lead query/persistence | 7 | Page repository | Repository tests |
| `src/Services/LeadService.php` | transitions/value rules | 7 | Page service | Service tests |
| `src/Http/Requests/{LeadList,StoreLead,UpdateLead,ChangeLeadStatus}Request.php` | lead validation | 7 | module Requests | API tests |
| `src/Http/Resources/LeadResource.php`, `LeadCollection.php` | lead serialization | 7 | Base resources | API tests |
| `src/Http/Controllers/Admin/LeadController.php` | lead API adapter | 7 | module Controllers | Controller tests |
| `resources/layouts/admin/admin_crm_lead_{list,form,detail}.json` | Lead UI | 7 | Page layouts | layout tests |
| `docs/api/leads.md`, `tests/scenarios/leads.yaml` | Lead docs/scenarios | 7 | guides | audits/tests |
| `src/Repositories/Contracts/DashboardRepositoryInterface.php`, `src/Repositories/DashboardRepository.php` | aggregate queries | 8 | Board dashboard repository/service patterns | aggregate tests |
| `src/Services/DashboardService.php` | dashboard orchestration | 8 | Board dashboard service | Service tests |
| `src/Http/Requests/DashboardRequest.php` | date/filter validation | 8 | module Requests | API tests |
| `src/Http/Resources/DashboardResource.php` | aggregate serialization | 8 | Base resource | API test |
| `src/Http/Controllers/Admin/DashboardController.php` | dashboard endpoint | 8 | Board dashboard | Controller test |
| `resources/layouts/admin/admin_crm_dashboard.json` | CRM landing overview | 8 | actual Board/ecommerce dashboards | layout test |
| `docs/api/dashboard.md`, `tests/scenarios/dashboard.yaml` | dashboard docs/scenario | 8 | guides | audits/tests |
| `tests/Feature/Installation/*` | release lifecycle/migration smoke if repository convention confirmed | 10 | core/extension Installation suites | smoke command |

## 조건부 frontend asset 트리

아래 파일은 JSON layout의 검증된 built-in handlers/components로 요구사항을 충족하지 못할 때만 만든다.

| 파일 | 역할 | Phase | 참조 | 테스트 |
|---|---|---:|---|---|
| `package.json`, `package-lock.json` | build/test scripts와 version | 최초 필요 Phase | Page/ecommerce packages | version/script audit |
| `vite.config.ts`, `vitest.config.ts` | IIFE/static build와 independent Vitest | 최초 필요 Phase | Page/ecommerce configs | actual build/test |
| `resources/js/index.ts`, `resources/js/handlers/*.ts` | registered CRM handlers | 최초 필요 Phase | ecommerce handlers | handler Unit tests |
| `resources/css/main.css` | CRM-only styles | 최초 필요 Phase | ecommerce CSS | build/snapshot |
| `dist/js/module.iife.js`, `dist/css/module.css` | generated output; 직접 편집 금지 | build | manifest assets outputs | freshness/runtime smoke |
| `editor-spec.json` | editor palette/capabilities가 필요할 때 | 후속 | Board/ecommerce editor spec | editor audit |

Phase 1에서 custom assets가 없다면 `module.json.assets`는 hello sample처럼 empty로 선언하거나 Page처럼 생략할 수 있다. 둘 중 프로젝트의 manifest validation 기대값을 test로 확인하고 하나를 선택한다.
### Phase 2-C 확정 파일

| 파일 경로 | 역할 | 생성 Phase | 참조할 기존 구현 | 테스트 대응 파일 |
|---|---|---|---|---|
| `resources/routes/admin.json` | Contact 관리자 frontend route | Phase 2-C | `sirsoft-board/resources/routes/admin.json` | `ContactAdminUiStructureTest`, `admin-contact-layouts.test.ts` |
| `resources/layouts/admin/admin_crm_contact_index.json` | 검색·필터·정렬·pagination·삭제 목록 | Phase 2-C | `sirsoft-page/admin_page_list.json` | `admin-contact-layouts.test.ts` |
| `resources/layouts/admin/admin_crm_contact_form.json` | Contact 등록 및 UUID 조회·수정 | Phase 2-C | `sirsoft-page/admin_page_form.json` | `admin-contact-layouts.test.ts` |
| `resources/js/__tests__/layouts/admin-contact-layouts.test.ts` | layout 계약 및 render test | Phase 2-C | 코어 `createLayoutTest()` | 자체 |
| `docs/admin/contacts.md` | 관리자 UI 계약 | Phase 2-C | 모듈 관리자 문서 | 구조 테스트 |
| `vitest.config.ts` | CRM layout test를 root Node dependency로 실행하는 scoped 설정 | Phase 2-D | bundled module Vitest configs | `admin-contact-layouts.test.ts` |
| `docs/testing/contact-quality-gate.md` | 격리 PHPUnit/Vitest/lifecycle 및 release gate | Phase 2-D | 테스트·extension lifecycle 문서 | 정적 문서 검토 |
