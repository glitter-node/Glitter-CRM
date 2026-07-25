# Tag 관리자 UI

## 화면과 권한

| 화면 | URL | 필요 권한 |
|---|---|---|
| Tag 목록 | `/admin/crm/tags` | `glitter-crm.tags.read` |
| Tag 등록 | `/admin/crm/tags/create` | `glitter-crm.tags.create` |
| Tag 조회·수정 | `/admin/crm/tags/{uuid}/edit` | 조회는 `tags.read`, 저장은 `tags.update` |

CRM 메뉴는 Contact 다음에 Tag를 표시한다. API middleware가 최종 보안 경계이며
목록과 폼은 Resource/Collection abilities로 버튼과 읽기 전용 상태를 제어한다.

## 목록

Tag API의 `search`, `sort`, `direction`, `page`, `per_page`를 사용한다. 이름,
slug, 색상, 수정일과 작업을 표시하고 내부 ID 및 UUID는 기본 컬럼에서 숨긴다.
색상은 서버에서 검증된 `#RRGGBB`만 작은 swatch의 `background-color`에 바인딩하며
값도 함께 표시한다.

삭제는 UUID DELETE 성공 후 `tags` data source를 refetch하고 modal을 닫는다.
Tag는 soft delete되며 restore와 force delete UI는 없다.

## 등록과 수정

공유 layout에서 name, slug, description, color를 편집한다. slug가 비어 있으면
name에서 Unicode를 보존해 생성하며 삭제 Tag의 slug도 재사용할 수 없다. color는
새 picker 없이 일반 text input으로 `#RRGGBB`를 받는다.

수정 ability가 없으면 기존 데이터를 읽기 전용으로 표시하며 저장 버튼을 숨긴다.
422 오류는 name, slug, description, color별 오류 상태에 연결한다.

## 제외 범위

- Tag 상세 전용 페이지와 Tag별 Contact 목록
- Contact 목록 Tag 필터
- bulk tagging, import/export
- restore/force delete
- 신규 JS handler, component 또는 CSS
