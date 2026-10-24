# Penguin Academy API v1 — implemented M5 contract

The machine-readable contract is [openapi.json](openapi.json). It documents all 24 implemented HTTP operations. URLs are relative to the same Laravel application. The Blade/Fortify routes remain available.

## Authentication

Use `Authorization: Bearer <token>` and `Accept: application/json`. Session cookies do not authenticate API requests. An active account with no outstanding mandatory password change is required. Students register through the existing web registration; administrators are provisioned with the existing interactive command; teachers are managed by administrators. There is no API account-provisioning backdoor.

`POST /api/v1/tokens` accepts JSON `email`, `password`, `name`, `abilities`, and optional `expires_in_minutes`. Send credentials only in the request body. Email is normalized. Unknown fields and URL parameters are rejected. Existing passwords are checked with Laravel Hash; unsuccessful credential checks have a minimum 200 ms timebox. This is a minimum duration, not a claim of constant-time network behavior.

A token expires after 60 minutes by default, with a maximum of 1440 minutes. At most ten unexpired tokens younger than 24 hours may exist per user. Issuance locks the user row. The plaintext token is returned once; only its SHA-256 hash is stored. Token listing never exposes either hash or plaintext. Legacy tokens without an expiry cannot authenticate this API.

| Role | Issuable abilities |
| --- | --- |
| All three roles | courses:read, lessons:read, enrollments:read, enrollments:write, progress:read, tokens:read, tokens:revoke |
| Student additionally | progress:write |
| Admin and Teacher additionally | courses:write, lessons:write, reports:read |

Request an explicit nonempty array; wildcards and duplicate/unknown abilities are rejected. Abilities constrain tokens and never grant additional role/ownership rights. Account status and role are reloaded from MySQL on each request; policies and transactional Actions recheck relevant permissions. Admin has no proxy lesson-completion ability. Reassignment removes the previous teacher's access immediately. The existing password-change and teacher-account security workflows revoke tokens where previously configured.

`DELETE /api/v1/tokens/current` signs the API client out by revoking its current token. A browser logout only ends its web session; revoke API tokens explicitly. `DELETE /api/v1/tokens/{token}` is restricted to the owner.

## Implemented operation inventory

| Method | Path under /api/v1 | Ability | Authorized scope |
| --- | --- | --- | --- |
| POST | /tokens | Credentials | Active account; password change completed |
| GET | /tokens | tokens:read | Own token metadata |
| DELETE | /tokens/current | tokens:revoke | Current token |
| DELETE | /tokens/{token} | tokens:revoke | Own token |
| GET | /courses | courses:read | Admin all; Teacher assigned; Student published |
| POST | /courses | courses:write | Admin; Teacher assigned to self on creation |
| GET | /courses/{course} | courses:read | Course view policy |
| PATCH | /courses/{course} | courses:write | Admin; assigned Teacher |
| DELETE | /courses/{course} | courses:write | Admin, soft archival |
| PUT | /courses/{course}/publication | courses:write | Admin; assigned Teacher |
| GET | /courses/{course}/lessons | lessons:read | Admin; assigned Teacher; actively enrolled Student |
| POST | /courses/{course}/lessons | lessons:write | Admin; assigned Teacher |
| GET | /courses/{course}/lessons/{lesson} | lessons:read | Lesson view policy |
| PATCH | /courses/{course}/lessons/{lesson} | lessons:write | Admin; assigned Teacher |
| DELETE | /courses/{course}/lessons/{lesson} | lessons:write | Admin; assigned Teacher; soft archival |
| PUT | /courses/{course}/lessons/{lesson}/publication | lessons:write | Admin; assigned Teacher |
| PUT | /courses/{course}/lessons/order | lessons:write | Admin; assigned Teacher; optimistic version |
| GET | /enrollments | enrollments:read | Admin all; Teacher assigned; Student own |
| POST | /enrollments | enrollments:write | Self-enrollment or authorized staff |
| DELETE | /enrollments/{enrollment} | enrollments:write | Own or authorized staff withdrawal |
| GET | /enrollments/{enrollment}/progress | progress:read | Own or authorized staff |
| PUT | /enrollments/{enrollment}/lessons/{lesson}/completion | progress:write | Own active Student enrollment |
| DELETE | /enrollments/{enrollment}/lessons/{lesson}/completion | progress:write | Own active Student enrollment |
| GET | /reports/overview | reports:read | Admin academy; Teacher assigned courses |

Course POST creates a draft. PATCH supports partial changes, including administrator-only teacher assignment. An assigned teacher must be active and have the Teacher role. Separate publication endpoints preserve existing web behavior. Lesson POST allocates the position under a course lock. Reordering requires all non-archived IDs exactly once and the latest `lesson_order_version`; stale order returns 409. Nested lesson bindings reject cross-course IDs.

Student enrollment omits `student_id`; staff must provide an active Student ID. First enrollment returns 201, duplicate/re-enrollment 200 with the same enrollment ID. Withdrawal returns 204, is idempotent and retains learning history. Archived courses cannot be enrolled in or accessed through these APIs.

Completion PUT and DELETE accept no fields and return a progress resource. Enrollment, student and course identities are never taken from submitted completion data. Both operations are idempotent. Withdrawal, unpublication or archival blocks completion changes. Progress reads retain historical eligibility calculations for an owner's withdrawn/unpublished course, but do not grant lesson access; archived courses are denied.

## Pagination and filtering

Collections return `data`, `links`, and `meta`. Default `per_page=20`; range 1–100. `page` is 1–1000000. Links contain only validated query parameters.

- Courses: `q` (title/summary, max 120 characters), `status=published|draft`, `sort=newest|title`, and admin-only `teacher_id`. Students cannot request drafts.
- Lessons: `status=published|draft`; students see only published lessons. Position ordering is stable; list entries omit content.
- Enrollments: `status=active|withdrawn|all` (default active), `course_id`, and staff-only `student_id`. Filters only narrow the authorized scope.
- Reports and tokens: pagination only.

Progress uses distinct persisted completions of published, non-deleted lessons in the enrollment's course, divided by current eligible lessons. Zero eligible lessons gives 0%; maximum 100%. New published lessons may reduce progress.

Report summaries exclude withdrawals and archived courses, but include draft courses and active enrollments of disabled users. `active_students` separately counts distinct active Student accounts in the selected enrollments. Admin account counts are academy-wide. Mean progress is unweighted across active enrollments, including zero-lesson enrollments.

## Request/response examples

These examples contain invented display data and no credentials or tokens.

Create a draft (authorized staff token in the header):

```http
POST /api/v1/courses
Content-Type: application/json
Accept: application/json

{"title":"Design foundations","slug":"design-foundations","summary":"Start with the fundamentals."}
```

Mark a lesson complete for the signed-in student:

```http
PUT /api/v1/enrollments/1/lessons/2/completion
Accept: application/json
```

Example progress response:

```json
{"data":{"completed_lessons":2,"eligible_lessons":3,"percent":66.67,"is_complete":false,"state":"In progress","next_lesson_id":3}}
```

Validation failure (422):

```json
{"message":"The given data was invalid.","code":"validation_failed","errors":{"title":["The title field is required."]}}
```

An enrollment list can be requested with `GET /api/v1/enrollments?status=active&per_page=20&page=1`. Its `meta.total` counts only authorized, filtered rows. Follow `links.next` until null.

## Status and security behavior

- 200 read/update/idempotent existing enrollment; 201 new course/lesson/enrollment/token; 204 successful archive, withdrawal or token revocation.
- 401 absent/invalid/expired/revoked bearer token, disabled account, invalid issuance credentials.
- 403 missing ability, policy denial, or mandatory password change.
- 404 absent/archived bound resource, cross-course lesson, or token not owned.
- 405 unsupported method; 409 stale reorder version; 422 validation/business-rule error; 429 throttled; 500 generic unexpected error.
- API errors use `message` and `code`, plus `errors` for field validation. Exception traces, SQL and private framework fields are not returned, even when local debug is enabled.
- Responses use `Cache-Control: no-store, private`. Secret fields are never in serialized account/token resources. Course teacher details expose only ID and name.
- Limits: token issuance 5/minute per normalized email+IP and 30/minute per IP; authenticated requests 120/minute per user; writes additionally share 60/minute per user with the existing web academy-write limiter. Failed attempts count. Rate-limit responses retain Retry-After.
- Use HTTPS outside local development. Keep bearer tokens in secure client memory/storage, never query parameters, process arguments, screenshots or logs. Configure reverse proxies to redact Authorization and never log authentication request bodies. Production proxy/TLS configuration remains an M6/deployment task; no deployment occurred.

## Verification boundaries

The PHPUnit suite uses real hashed Sanctum tokens and the dedicated MySQL test database, not Sanctum::actingAs. It verifies endpoint/ability coverage, data scopes, transaction-backed writes, expiration/revocation, error contracts, route/OpenAPI parity, field protection, and constant query counts for representative pages. The existing web tests remain unchanged. Browser sign-in still requires secure account provisioning and user handoff; HTTP feature tests are not browser end-to-end tests.
