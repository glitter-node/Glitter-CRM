# Contact 관리자 UI

저장된 Contact 수정 화면에는 Activity 타임라인이 포함된다. 생성 화면에서는
Contact UUID가 없으므로 Activity를 요청하지 않는다. 상세 계약은
`docs/admin/activities.md`를 참고한다.

저장된 Contact 수정 화면에는 Contact별 Task section도 포함된다. 검색,
상태·우선순위·담당자·기한·overdue 필터, pagination과 UUID 기반 CRUD를
지원한다. 생성 화면에서는 Task를 요청하지 않으며 Task 권한 오류는 Contact
폼과 격리된다. Task 변경 후 Contact를 재조회해 `next_task_at`을 반영한다.

Contact 목록의 기존 “다음 조치” 표시 슬롯은 Contact API의 서버 관리
`next_task_at`을 사용한다. 값은 관리자 표준 `datetime` pipe로 표시하고,
desktop table과 responsive card가 동일한 pipe-safe `cellChildren`를 공유한다.
null이면 기존 `contacts.no_value` 번역을 사용한다. 관리자 입력
`next_action_at`은 별도 Contact 필드로 유지되며 Task aggregate 계산에
사용하지 않는다.

## 화면과 권한

| 화면 | URL | 필요 권한 |
|---|---|---|
| 고객 목록 | `/admin/crm/contacts` | `glitter-crm.contacts.read` |
| 고객 등록 | `/admin/crm/contacts/create` | `glitter-crm.contacts.create` |
| 고객 조회·수정 | `/admin/crm/contacts/{uuid}/edit` | 조회는 `glitter-crm.contacts.read`, 저장은 `glitter-crm.contacts.update` |

메뉴에는 CRM 아래 고객과 태그 항목이 노출된다. API middleware가 최종 권한 경계이며,
레이아웃은 API Resource의 `abilities`로 생성·수정·삭제 동작을 제어한다.

## 목록

목록은 기존 `GET /api/modules/glitter-crm/admin/contacts`를 사용한다. 이름·회사·
이메일·전화번호 통합 검색, 상태 필터, 담당자 필터, 허용 목록 기반 정렬 및
서버 pagination을 제공한다. 담당자 검색은 코어
`GET /api/admin/users/search`를 재사용하며 User UUID를 전달한다.
이 공용 검색 API는 `core.users.read` 권한도 요구하므로 User selector를 사용하는
관리자 역할에는 CRM 권한과 함께 해당 코어 권한이 필요하다.

## 등록과 수정

폼은 이름, 상태, 회사, 직책, 이메일, 전화번호, 휴대전화, 연결 회원, 담당자,
언어, 시간대, 유입 경로, 다음 조치 일시 및 다중 태그를 다룬다. 연결 회원과 담당자는
내부 bigint ID가 아닌 User UUID를 사용한다. 등록 기본 상태는 `active`다.
다음 조치 일시는 프로젝트 공용 `TimezoneHelper` 규칙에 따라 사이트 기본
타임존의 `datetime-local` 값으로 표시되고 UTC로 저장된다.

수정 URL과 Contact API는 공개 UUID를 사용한다. API의 422 field error는 해당
필드 아래에 표시하며, 저장 중 중복 제출을 막는다.

태그 선택은 기존 `SearchableDropdown`의 multiple 모드와 Tag 검색 API를 사용하고
Tag UUID 배열만 전송한다. `glitter-crm.tags.read` 권한이 없거나 Tag API가 403을
반환하면 selector와 `tag_uuids` payload를 함께 생략하므로 기존 관계가 유지된다.
Contact create/update 권한은 관계 동기화를 허용하지만 Tag 정의 변경 권한을
부여하지 않는다.

## 삭제

삭제 확인 후 기존 DELETE endpoint를 호출한다. Contact 모델의 SoftDeletes
정책에 따라 일반 목록과 조회에서 제외되며, 복원이나 영구 삭제 UI는 제공하지
않는다.

## 의도적으로 제외한 항목

- `metadata` raw 편집
- `last_activity_at` 직접 수정
- restore와 force delete
- import, export, bulk action
- Contact 목록 Tag 필터와 Tag별 Contact 목록
- Activity, Task, Lead 및 Dashboard
- 예약, 이커머스 또는 외부 SaaS 연동
