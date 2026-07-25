# Changelog

이 프로젝트의 모든 주요 변경사항을 기록합니다.
형식은 [Keep a Changelog](https://keepachangelog.com/ko/1.1.0/)를 따르며,
[Semantic Versioning](https://semver.org/lang/ko/)을 준수합니다.

## [1.3.3] - 2026-07-25

### Fixed

- Fixed the Contact list `next_task_at` value not rendering in either the
  desktop table or responsive card view.
- Split the formatted value and translated empty state into pipe-safe
  `cellChildren` nodes shared by both DataGrid renderers.

## [1.3.2] - 2026-07-25

### Fixed

- Bound the existing Contact list next-action display slot to the server-managed
  `next_task_at` aggregate.
- Reused the administrator `datetime` pipe and existing empty-value translation,
  while preserving `next_action_at` as the separate manager-editable Contact field.
- Kept the existing Task mutation success chain that refetches both Task and
  Contact data so the aggregate display does not calculate or retain stale values.

## [1.3.1] - 2026-07-25

### Fixed

- Fixed bundled installation failure caused by the Task migration referencing
  module enum classes before extension autoload registration.
- Updated the unapplied Task migration in place to use the existing persistent
  scalar defaults (`pending`, `normal`). Version 1.3.0 could not complete an
  official bundled installation, so deployed databases did not record that
  sixth migration.
- Exposed the server-managed Contact `next_task_at` aggregate in Contact API
  resources so Task changes are observable after the documented Contact refetch.

## [1.3.0] - 2026-07-25

### Added

- Contact 수정 화면에 격리된 Task section과 생성·수정·삭제 modal
- Task 상태·우선순위·담당자·기한·overdue 표시와 검색·필터·pagination
- UUID 및 abilities 기반 Task 작업과 변경 후 Contact `next_task_at` 재조회
- 한국어·영어 frontend 번역, layout/구조 테스트, scenario와 관리자 quality gate 문서

## [1.2.0] - 2026-07-25

### Added

- UUID route와 endpoint별 권한을 적용한 Task 관리자 CRUD API
- Contact UUID별 Task 관리자 목록 API
- 상태·우선순위·담당자·timezone-aware 기한 및 metadata validation
- overdue 조회와 계산 필드를 제공하는 Task Resource/Collection
- 상태 전이에 따른 `completed_at` 및 Contact `next_task_at` 계약의 HTTP 통합
- Task API 테스트, scenario, 문서와 quality gate

## [1.1.0] - 2026-07-25

### Added

- UUID 공개 식별자와 soft delete를 사용하는 CRM Task schema와 model
- Task 상태·우선순위 enum 및 Contact·nullable 담당 User 관계
- 검색·필터·overdue 조회를 지원하는 Task Repository와 Service
- Task 상태에 따른 `completed_at` 불변 조건과 UTC 기한 처리
- Task 변경 transaction 내 Contact `next_task_at` 재계산
- Task 데이터 계층 테스트, scenario, 설계 문서와 quality gate

## [1.0.0] - 2026-07-25

### Added

- Contact 수정 화면의 Activity 타임라인
- Activity 검색·유형·기간 필터와 pagination
- Activity 생성·수정·soft-delete modal 및 abilities 기반 동작
- system Activity 읽기 전용, creator와 로컬 datetime 표시
- 한국어·영어 frontend 번역과 layout/구조 테스트, scenario 및 문서

## [0.9.0] - 2026-07-25

### Added

- UUID route와 endpoint별 권한을 적용한 Activity 관리자 CRUD API
- Contact UUID별 Activity 관리자 목록 API
- 인증 관리자 기반 creator 기록과 timezone-aware ISO 8601 validation
- 경량 Contact/creator 관계와 abilities를 제공하는 Activity Resource/Collection
- 관리자 system type 생성 차단 및 기존 system Activity 수정·삭제 보호
- Activity API 테스트, scenario, API 문서와 quality gate

## [0.8.0] - 2026-07-25

### Added

- UUID 공개 식별자와 soft delete를 사용하는 CRM Activity schema와 model
- Contact–Activity 및 nullable creator 관계와 Activity type enum
- 기간·유형·작성자·키워드 조회를 지원하는 Activity Repository와 Service
- Activity 변경 transaction 내 Contact `last_activity_at` 재계산
- Activity 데이터 계층 테스트, scenario, 설계 문서와 quality gate

## [0.7.0] - 2026-07-25

### Added

- Tag 관리자 목록과 생성·수정 공유 layout 및 CRM Tag 메뉴
- abilities 기반 Tag 생성·수정·삭제 UI와 soft-delete 확인 흐름
- Contact 폼의 UUID 기반 다중 Tag 선택
- Contact API `tag_uuids` 원자적 동기화와 경량 `tags` 응답
- Tag UI 및 Contact–Tag 연동 테스트, scenario, 문서와 quality gate

## [0.6.0] - 2026-07-25

### Added

- endpoint별 권한과 UUID route 제약을 적용한 Tag 관리자 CRUD API
- Tag 목록·생성·수정 FormRequest와 API Resource/Collection
- slug 영구 예약 및 색상 형식 validation의 HTTP 422 응답
- Tag 관리자 API 테스트, scenario, API 문서와 격리 quality gate

## [0.5.0] - 2026-07-25

### Added

- UUID 공개 식별자와 soft delete를 사용하는 CRM Tag 데이터 모델
- Contact–Tag timestamp pivot 관계와 중복 방지·cascade 외래키
- Tag Repository 및 Service 기반 CRUD·검색·UUID 관계 동기화
- Tag 데이터 계층 테스트와 격리 quality gate

## [0.4.1] - 2026-07-25

### Added

- Contact 정적 quality gate와 격리된 PHPUnit·Vitest·lifecycle 검증 절차

### Fixed

- `next_action_at`을 프로젝트 표준 사이트 타임존의 `datetime-local` 형식으로 왕복하도록 수정
- Contact Service의 일반 write 경로에서 시스템 관리 필드 `last_activity_at`을 제외
- Contact 삭제 후 목록 재조회 핸들러의 파라미터와 모달 처리 순서를 지원 계약에 맞게 수정
- Phase 2-C 이후 실제 layout 구조와 불일치하던 구조 테스트 및 Vitest 실행 설정 보완

## [0.4.0] - 2026-07-25

### Added

- Contact 관리자 목록·등록·조회/수정 화면
- Contact 검색·상태/담당자 필터·정렬·페이지네이션
- 권한 기반 Contact 생성·수정·삭제 동작
- Contact 삭제 확인과 soft-delete API 연동
- 한국어·영어 관리자 번역
- Contact 관리자 UI 구조·레이아웃 테스트와 문서

## [0.3.0] - 2026-07-25

### Added

- Contact 관리자 CRUD API
- Contact 목록·생성·수정 요청 검증
- Contact API Resource와 표준 pagination 응답
- Contact endpoint별 관리자 권한 middleware
- Contact API 문서와 HTTP 계층 테스트

## [0.2.0] - 2026-07-25

### Added

- Contact 데이터베이스 스키마
- Contact 상태 enum과 모델
- Contact Repository contract와 구현체
- Contact Service와 Repository binding
- Contact 데이터 계층 테스트와 시나리오

## [0.1.0] - 2026-07-25

### Added

- 초기 모듈 스켈레톤과 메타데이터
- 공통 모듈 ServiceProvider
- 기능별 관리자 권한 선언
- 향후 관리자 진입점을 위한 비활성 메뉴 등록 구조
- 한국어·영어 번역
- 모듈 lifecycle 및 기본 smoke 테스트
