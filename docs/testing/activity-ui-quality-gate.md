# Activity UI Quality Gate

## 범위

`glitter-crm` 1.3.3의 Activity UI는 Contact 수정 화면 내부 타임라인만
대상으로 한다. 독립 Activity 메뉴·typed admin route·layout은 없다.
1.3.3은 소스 기능 버전이며 아래 격리 runtime 검증 전에는 공개 배포
완료를 의미하지 않는다.

## 정적 계약

- Contact 생성 모드에서는 Activity data source의 `if` 조건이 false다.
- Contact UUID nested GET, 전역 POST, Activity UUID PUT/DELETE만 사용한다.
- collection은 `data.data`, `data.meta`, `data.abilities`를 사용한다.
- 생성 버튼은 `can_create`, 항목 수정·삭제는 `can_update`,
  `can_delete`에 연결한다.
- system type은 생성 선택지에 없고 server abilities에 의해 읽기 전용이다.
- creator, metadata, Contact 내부 ID와 Activity 내부 ID를 쓰기 payload에
  포함하지 않는다.
- UI 본문은 HTML로 해석하지 않으며 ko/en 번역 키 parity를 유지한다.
- 새 component, CSS, runtime handler를 사용하지 않는다.

## 격리 검증

Node 의존성을 준비한 별도 환경에서 다음을 실행한다.

```bash
node_modules/.bin/vitest run --config modules/_bundled/glitter-crm/vitest.config.ts
npm run build
```

PHP 테스트는 production과 다른 `.env.testing` 및 별도 DB가 확인된
환경에서만 실행한다.

```bash
/usr/local/bin/php83 vendor/bin/phpunit modules/_bundled/glitter-crm/tests
```

이후 모듈 install/update/activate lifecycle, active output 비교, 실제
관리자 runtime smoke를 수행한다. production DB에서는 실행하지 않는다.

## Runtime smoke

- Contact edit에서 loading, empty, 오류와 403 격리를 확인한다.
- Contact create에서 Activity 요청이 발생하지 않는지 확인한다.
- 최신순·동일 시각 안정 정렬, 검색, 6개 type 조회 필터, UTC 기간 필터,
  pagination을 확인한다.
- 5개 관리자 type의 subject-only/body-only 생성, 둘 다 비어 있는 422,
  미래 시각과 인증 creator를 확인한다.
- 수정 시 metadata가 유지되고 `occurred_at` 변경 후
  `last_activity_at`이 갱신되는지 확인한다.
- 삭제 확인, soft delete, 목록 갱신과 `last_activity_at` 갱신을 확인한다.
- system Activity와 creator null 표시, system 쓰기 버튼 부재를 확인한다.
- 401, 403, 404, 422, 500, 네트워크 오류와 중복 제출 차단을 확인한다.

## Release blockers

다음이 모두 통과해야 공개 배포할 수 있다.

1. 격리 PHPUnit 및 migration up/down
2. scoped Vitest
3. 정적 frontend build와 native binary 실행 무결성
4. install/update/activate/deactivate/uninstall lifecycle
5. bundled/active output 일치
6. production-like 인증 runtime smoke
