# Penguin Academy architecture — M6

## Application boundaries

One Laravel 13 application serves Blade/Bootstrap through the web guard. Fortify retains registration/login/logout and password confirmation. Its provider is explicitly registered; automatic discovery remains disabled to avoid double registration.

Controllers assemble views and redirects. Form Requests authorize and validate input. Policies enforce roles and ownership. Academy Actions recheck authorization on locked database records and perform transactional writes. Actor identity always comes from authentication. No role-change endpoint exists.

M2 account provisioning, temporary-password enforcement, session invalidation, teacher management, and dashboard calculations remain in place. Sanctum now supplies the versioned bearer-token API described below. Existing web sessions remain Fortify-authenticated.

## Schema and history

| Table | Integrity rules |
| --- | --- |
| users | Unique email; enum role; active/temporary-password flags; hashed password |
| courses | Nullable teacher FK, RESTRICT; unique slug including archived records; publication timestamp; soft deletion; lesson_order_version |
| lessons | Course FK, CASCADE; unique course/position and id/course pairs; publication flag; soft deletion |
| enrollments | Student FK, RESTRICT; course FK, CASCADE; unique student/course and id/course pairs; enrolled/withdrawn timestamps |
| lesson_completions | Unique enrollment/lesson; composite enrollment/course and lesson/course foreign keys |
| sessions | User FK, SET NULL; encrypted payload |
| personal_access_tokens | Token hash, owner, abilities, expiration index |
| Other foundation tables | Password reset, cache, and queue tables retained |

InnoDB, utf8mb4_unicode_ci, and UTC remain configured. Completion course_id is an intentional integrity field: both composite foreign keys must agree, preventing cross-course references even outside Eloquent.

M3 adds lessons.deleted_at, its course/deletion/publication index, and courses.lesson_order_version through one additive migration. Original migrations are unchanged. No table rebuild or development data reset was performed.

Course and lesson archive actions only set deletion timestamps. They retain enrollments, completion records, and foreign keys. Withdrawals only set withdrawn_at; re-enrollment clears it on the same row and preserves the original enrolled_at. Archived lesson positions remain reserved. No restore or force-delete HTTP endpoint is exposed.

Physical deletion still follows the existing foreign-key cascades; direct administrative SQL is outside these web workflows. Referenced teachers/students cannot be physically deleted. Role eligibility is checked by Form Requests and transactional participant checks, not by a cross-table SQL CHECK constraint.

## Permissions

All actors must be active. Authentication, auth.session, and password.changed middleware protect workspace routes.

| Operation | Admin | Teacher | Student |
| --- | --- | --- | --- |
| Own role dashboard | Yes | Yes | Yes |
| Teacher account management | Current password required | Denied | Denied |
| Create course | Yes; optional eligible teacher | Yes; assigned to self | Denied |
| Read course | All current courses | Assigned only | Published current courses |
| Update/publish course | All current courses | Assigned only | Denied |
| Assign/reassign teacher | Active Teacher only | Denied | Denied |
| Archive course | Yes | Denied, preserving M2 rule | Denied |
| Create/edit/publish/reorder/archive lesson | All current courses | Assigned only | Denied |
| Read lesson | Current course/lesson | Assigned course/lesson | Published course and lesson, active own enrollment |
| View roster | All current courses | Assigned only | Denied |
| Enroll eligible student | Published courses | Assigned published courses | Own account in published courses |
| Withdraw enrollment | All current courses | Assigned courses | Own enrollment, including unpublished courses |
| Academy enrollment overview | Yes | Denied | Denied |
| My courses and enrollment history | Denied | Denied | Own records |
| Complete / undo lesson | Denied | Denied | Accessible lesson, active own enrollment |
| Course / student progress reports | All current courses | Assigned only | Denied |

A new course starts as a draft. Publication requires an assigned active Teacher. Removing a published course's teacher requires unpublishing first. Only administrators can reassign. Teacher creation uses the authenticated teacher ID; submitted teacher IDs are rejected.

Course lists are scoped before pagination. Nested lesson/enrollment routes use scoped bindings; a mismatched parent/child returns 404, including for an administrator. Deleted route models return 404. Students can see a published course's outline before enrollment, but not lesson content.

The staff enrollment selector searches up to 25 active Student accounts across the academy, by name/email, to permit enrollment. That selector is not a roster. Enrollment records themselves remain scoped to the authorized course. No anonymous/student directory endpoint is exposed.

## Transactions and concurrency

Each course-owned mutation locks its course first, then its affected rows. Authorization is re-evaluated under that lock, protecting against course reassignment between request validation and mutation. Participant eligibility is read under a user-row lock. Transactions retry deadlocks up to three attempts.

The unique student/course constraint guarantees one enrollment row, including withdrawal history. Course locking serializes same-course enroll/withdraw actions; repeated active enrollment and repeated withdrawal preserve their existing timestamps.

Lesson append reads the maximum position including archived lessons under the course lock. Reordering requires a complete, unique set of current lesson IDs and the current lesson_order_version. A stale version returns HTTP 409. A malformed/foreign set fails validation.

Reorder first moves current lessons above all occupied positions, then assigns positions in requested order, skipping archived positions. The unique position constraint remains enabled throughout. Version increments on append, archive, and successful reorder. Integer exhaustion is rejected before mutation. Multi-process tests verified duplicate enrollment, concurrent append, and competing reorder behavior.

## Validation and security

Protected role/identity/status/position/timestamp fields are rejected rather than mass-assigned. Actions allowlist course and lesson content. Slugs remain unique across archived courses. Teacher and Student IDs must match the active persisted role; self-enrollment ignores no forged ID—it rejects one.

Web writes use CSRF protection and the existing session controls. Academy mutations are rate-limited to 60 requests per authenticated user per minute. Form validation redirects with field errors for browser forms and returns Laravel 422 errors for JSON requests. Forbidden access returns 403; parent mismatch/deletion returns 404; stale ordering returns 409.

Blade escapes titles, summaries, descriptions, names, and lesson content. Lesson content is plain text with preserved line breaks. SQL values are parameter-bound. No raw HTML editor or file upload surface was added. M5 adds credential-authenticated token issuance with explicit abilities and expiration.

## Account lifecycle and dashboards

Registration assigns Student. Administrator-managed teachers require a password change. Console administrator provisioning refuses to overwrite or promote existing accounts and has no default credentials.

Teacher deactivation, email change, and password reset revoke sessions/tokens. Password changes recheck the current password, revoke other sessions/tokens, rotate remember tokens, and regenerate the current session. Login/registration regenerate sessions; logout invalidates them and rotates CSRF tokens.

Dashboard definitions are preserved: Admin counts active accounts/current courses/current enrollments; Teacher counts assigned courses/current lessons/unique enrolled students; Student counts own active enrollments in published courses and authorized published lesson completions. Soft-deleted lessons now automatically leave current counts while history persists.

M4 adds ProgressService, ProgressSummary, and AcademyAnalytics. Progress uses SQL scalar counts of eligible lessons plus EXISTS against completion rows bound to enrollment, course, and lesson. Counting lesson rows prevents duplicate completion inflation. Aggregated SQL supplies dashboard totals and per-course averages; completion collections are never loaded into memory. Eager-loaded relationships and pagination avoid per-student queries.

CompletionActions serializes same-course writes using the existing course lock. It reloads the current lesson, active Student account, and own active enrollment under locks, then reuses LessonCompletionPolicy. PUT creates a row only if absent and preserves timestamps on repeats; DELETE removes only the authenticated student's matching completion, doing nothing when already absent. Course/lesson publication, soft deletion, and withdrawal are rechecked after waiting on locks. Withdrawal/archive preserve learning history; intentional Undo removes that student's eligible completion.

ProgressSummary clamps counts, calculates a two-decimal percentage, handles the zero-lesson state, and distinguishes completed courses from empty courses. Continue Learning uses a SQL first-incomplete lesson ordered by position/id; it is suppressed after withdrawal or course unpublication/archive. Published curriculum changes immediately affect subsequent progress reads.

Staff reports are gated with CoursePolicy::update, scoped enrollment bindings, and Form Requests. Teacher queries are scoped before aggregation. Staff totals cover active enrollment rows in current courses, including drafts and inactive account rows; active_students separately counts distinct active Student accounts. Student dashboard totals cover own active enrollments in published courses. A zero-lesson enrollment has 0% and is never completed. Mean progress weights enrollments equally; course completion rate counts fully completed nonempty enrollments / current enrollments. Latest completion includes only still-eligible lessons for the selected current enrollment scope.

M4 needs no schema changes: existing eligibility, enrollment-status, and completion-pair indexes and composite foreign keys are reused. All five migrations and both lockfiles remain unchanged. The course lock favors correctness and serializes concurrent writes within one course; large-cohort contention and production-scale query latency have not been benchmarked.

## Final verification boundary

M6 passes 328 PHPUnit tests / 2,216 assertions, retaining all 313 original cases. Real local Chromium verifies authenticated Admin/Teacher/Student journeys, exact 375/768/1440 layouts and keyboard interactions. The independent HTTP client exercises all 24 API operations against MySQL and checks the OpenAPI response schemas. Evidence is separated by method in FINAL_QA_REPORT.md.

M6 makes no schema or application-lockfile changes. Both dedicated database accounts are tested for cross-environment read denial; original core records are unchanged. Production TLS/proxy, backups, monitoring, real-device/cross-engine testing and production capacity remain outside this local verification.

## M5 API boundaries

The stateless api/v1 group uses auth:sanctum with cookie fallback disabled. EnsureApiAccount requires an active account, a persistent token with expires_at, and a completed mandatory password change. Account and ability checks precede route binding. Policies still authorize each resource operation. Issuance is separately rate-limited, verifies the existing password, checks role-compatible explicit abilities, and locks the user row while enforcing ten active tokens. Sanctum hashes token secrets and enforces both per-token expiry and the 24-hour global ceiling.

API controllers adapt HTTP only: CourseActions, LessonActions, EnrollmentActions, CompletionActions, ProgressService and AcademyAnalytics remain the business layer. CourseCatalog is shared with the existing Blade course index. EnrollmentDirectory narrows API listings by current actor role before pagination. API Form Requests reuse existing write/publication/reorder/delete rules, add partial PATCH handling, and reject unknown fields. API Resources serialize explicit fields rather than exposing raw models.

Nested lesson routes use scoped bindings; enrollment-root completion requests independently enforce the enrollment owner and matching course before calling the existing transactional action. Enrollment deletion calls withdrawal; course/lesson deletion calls archival. No schema or lockfile change was needed. Completion concurrency behavior remains covered by the preserved M4 process tests.

ApiErrors provides stable JSON message/code/errors without private exception details, preserving Retry-After and Allow where relevant. SecurityHeaders marks normal responses no-store; error responses also carry no-store. API tokens and web sessions are independent, while existing account security changes continue to revoke tokens.

Measured complete bearer requests for one versus twelve result rows: catalog 6/6 SQL statements, enrollments 7/7, overview reports 11/11. Each includes one Sanctum last_used_at write. These are fixture-scale query-count measurements, not production load or latency benchmarks. Full endpoint contracts and role restrictions are in app/docs/API.md and app/docs/openapi.json.

## M6 corrections and preserved boundaries

Fortify resolves an application LoginRequest with the existing 72-byte password validator. Current-password reauthentication and the confirmation callback use the same bound; exact valid credentials remain accepted. The custom web callback uses a minimum timebox. Shared SecurityHeaders now applies to normal/error responses, including CSP; HSTS is conditional on production HTTPS. None of these checks grants a role or bypasses a policy.

Course creation refreshes the saved model before API serialization so database defaults, especially lesson_order_version, are represented accurately. No action logic is duplicated and no route is replaced. UI changes are limited to names, contrast, headings and readable internally scrolling table columns.

The separate tools/qa utilities are local test programs, not public endpoints. They provision disposable accounts using supported flows, use only the test connection and clean their uniquely named fixtures. Browser authentication uses ordinary forms; the API client obtains actual tokens from the credential-authenticated endpoint. Performance bulk fixtures use factories/direct test inserts inside guarded transactions, distinct from browser account provisioning.

See PERFORMANCE_REPORT.md for the representative 3,000-enrollment benchmark, query templates and existing-index EXPLAIN evidence. No cache/index redesign was justified or introduced. Same-course writes continue to serialize on the course lock, favoring integrity over unrestricted write concurrency.
