# glitter-crm API

- [Contact 관리자 API](contacts.md)
- [Tag 관리자 API](tags.md)
- [Activity 관리자 API](activities.md)
- [Task 관리자 API](tasks.md)

현재 공개된 CRM HTTP 표면은 Contact, Tag, Activity 및 Task 관리자 API입니다. Contact–Tag 관계는
Contact write의 `tag_uuids`로만 동기화하며 독립 관계 API는 제공하지 않습니다.
Activity와 Task는 각각 CRUD와 Contact별 GET 목록만 제공하며 Lead API는 아직 제공하지 않습니다.
