# Penguin Academy — Milestone 4 Implementation & Verification Report

**Date:** 2026-10-22 (UTC)  
**Scope:** Learning Progress, Lesson Completion & Data-Driven Dashboards  
**Status:** Implementation and MySQL/HTTP verification complete. Authenticated browser end-to-end verification remains outstanding for the specific reason below. M5 has not started.

## 1. Outcome and preservation

M4 was implemented in the original `penguin-academy-m1/app` Laravel application. No replacement project, database reset, destructive migration, deployment, paid resource, default account, or M5 API was introduced.

Before editing, 161 application source files matched the previously delivered M3 archive. The full M1–M3 baseline passed: **137 tests / 899 assertions**. The original test files, all five migrations, Composer lockfile, and npm lockfile are unchanged. M1/M2/M3 milestone reports are retained.

The existing development database contained **zero users, courses, lessons, enrollments, and completions** before M4. The same five tables still contain zero rows, with before/after comparisons confirming preservation. Existing authentication/session configuration was retained. Test fixtures were created only in the separate test database and rolled back or cleaned by their owning concurrency test.

No migration or index was necessary for M4. Existing uniqueness constraints, composite foreign keys, soft deletion, and lookup indexes support the new operations.

## 2. Implemented learning workflows

### Completion and undo

The lesson reader now exposes real **Mark complete** and **Undo completion** forms to authorized students. Each operation goes through:

1. Auth/session/password-change middleware and CSRF-protected web routing.
2. `ChangeLessonCompletionRequest`, which resolves the signed-in student's own active enrollment and applies the existing `LessonCompletionPolicy`.
3. `CompletionActions::setCompleted()`, which opens a MySQL transaction, locks the course and affected rows, reloads the active Student account, and rechecks current authorization before writing.

PUT creates the unique enrollment/lesson completion only when absent. Repeated PUTs return the same row without changing its completion or update timestamps. DELETE removes only the signed-in student's completion; repeated DELETEs succeed without extra changes.

The server supplies course, enrollment, lesson, and completion timestamp values. Forged identity, role, timestamp, and progress fields are rejected. Nested binding rejects cross-course lessons. Withdrawn enrollments, unpublished/scheduled/deleted courses, and unpublished/deleted lessons cannot be used to change progress.

Course/lesson archival and withdrawal preserve historical completion rows. Re-enrollment reuses the existing enrollment and restores access to its history. Intentional Undo removes the student's current eligible completion; it is not an append-only audit system.

### Reusable persisted progress

`ProgressService` calculates counts in SQL; `ProgressSummary` supplies display state and percentages:

```text
Progress = completed eligible lessons / eligible lessons × 100
Eligible = published, non-deleted lessons belonging to the selected course
```

- Zero eligible lessons means 0%, never a completed course.
- Percentages are rounded to two decimals and bounded between 0% and 100%.
- The service counts lesson rows with matching completion EXISTS predicates, so duplicate completion rows cannot inflate the count.
- Draft and archived lessons are excluded; their historical completion rows remain.
- Publishing additional lessons may lower progress.
- Withdrawing revokes access and removes Continue learning, while retained progress remains available in own enrollment history.
- Re-enrollment restores access without copying or duplicating learning records.
- Continue learning selects the first incomplete eligible lesson in current lesson order.
- Fully completed courses offer Review course; empty courses offer View course.

All calculations use persisted MySQL data. There are no fabricated statistics or client-supplied percentages.

### Student interface

Added course progress bars, percentages, completed/incomplete lesson indicators, reader completion/undo controls, current learning statistics, and Continue learning links across the student dashboard, course detail, lesson reader, and My courses.

Success/error feedback and busy form behavior reuse the existing UI. The shared native progress element has an accessible label, numeric progress, completed/total text, and a textual state. Responsive layouts, visible focus, semantic tables, and existing reduced-motion behavior are retained.

Subsequent HTTP page requests were verified to show the persisted changes. An actual authenticated browser refresh has **not** been verified.

## 3. Teacher and administrator analytics

`AcademyAnalytics` and `ProgressService` provide aggregated metrics. Staff can open course progress reports and individual student reports.

**Teacher:** assigned courses only, distinct active student accounts, current enrollment counts, total/published lesson counts, average progress, finished enrollment counts/rates, latest eligible completion, and paginated student-level reports.

**Admin:** active teachers/students, current/published/draft courses, current enrollments, completed eligible lessons, finished enrollments, mean progress, course activity summaries, and enrollment/progress reporting.

Reports support active/withdrawn/all enrollment filters, learner name/email search, and pagination. A report's summary cards cover the whole authorized current enrollment scope, independently of list filters.

### Exact metric definitions

| Metric | Definition |
| --- | --- |
| Current enrollment | Enrollment with null withdrawn_at in a non-archived course |
| Active students in staff learning reports | Distinct active Student accounts among that current enrollment scope |
| Eligible lesson | Published, non-deleted lesson belonging to the course |
| Completed lessons | Eligible completed lesson/enrollment assignments in the selected scope |
| Completed enrollment | At least one eligible lesson, with every eligible lesson completed |
| Average progress | Arithmetic mean of per-enrollment progress; each enrollment has equal weight; zero-lesson enrollments contribute 0% |
| Completion rate | Completed enrollments / current enrollments × 100 |
| Latest completion | Latest completion timestamp still eligible in the selected enrollment scope |
| Draft / scheduled course | Current course with null or future published_at |

Staff current enrollment metrics include draft courses and inactive accounts whose enrollments have not been withdrawn; the separately labeled active-student metric excludes inactive accounts. This preserves the original enrollment-count definition. Student dashboard metrics cover only the signed-in student's active enrollments in currently published courses.

These are current-curriculum metrics. Republishing, archiving, undoing, or adding lessons can change them; they are not immutable awards or certificates.

## 4. Architecture, authorization, and performance

New implementation classes:

- `app/Actions/Academy/CompletionActions.php`
- `app/Services/Learning/ProgressService.php`
- `app/Services/Learning/ProgressSummary.php`
- `app/Services/Learning/AcademyAnalytics.php`
- `app/Http/Requests/ChangeLessonCompletionRequest.php`
- `app/Http/Requests/ProgressReportRequest.php`
- `app/Http/Controllers/LessonCompletionController.php`
- `app/Http/Controllers/LearningReportController.php`

Existing dashboard/course/lesson/enrollment controllers now assemble progress view data. Business calculations remain outside Blade. Existing models, relationships, policies, M3 Actions, and account controls are reused.

Course/student reports require `CoursePolicy::update`: Admin or the assigned active Teacher. Scoped route bindings reject foreign enrollments even for Admin. Teacher query scopes apply before aggregation and pagination.

SQL counts/EXISTS and aggregate subqueries avoid loading completion collections. Student/course relationships are eager-loaded; dashboard lists paginate at 8, course reports at 15, and student lesson reports at 20. Tests establish one SQL query for one progress snapshot and a constant report query count, bounded by 12, when growing the visible roster from 1 to 15 learners.

Reused indexes include lesson course/deletion/publication lookup, current-enrollment lookup, unique enrollment/lesson completions, and composite relationship keys. No speculative index or cache was added. Production-scale latency, memory, and load benchmarks were not performed.

All same-course mutations serialize through the existing course row lock and retry transactions up to three times for deadlocks. This favors correctness; high-contention courses may need measured optimization later. The concurrency tests use genuinely separate PHP processes and MySQL connections, not mocked parallel calls.

## 5. Routes and working screens

Four routes were added; all 48 previous routes remain. The complete **52-route** inventory with middleware is in `evidence/m4/routes.json`.

| Method | Path | Route name | Authorization / behavior |
| --- | --- | --- | --- |
| PUT | /courses/{course}/lessons/{lesson}/completion | courses.lessons.completion.store | Own active Student enrollment; idempotent completion |
| DELETE | /courses/{course}/lessons/{lesson}/completion | courses.lessons.completion.destroy | Own active Student enrollment; idempotent undo |
| GET/HEAD | /courses/{course}/progress | courses.progress.index | Admin or assigned Teacher; filtered paginated roster progress |
| GET/HEAD | /courses/{course}/enrollments/{enrollment}/progress | courses.progress.show | Admin or assigned Teacher; scoped individual report |

Completion writes retain the academy-write limiter: 60 requests per authenticated user per minute. Form validation uses redirects/errors for HTML and Laravel 422 responses for JSON requests. Unauthorized access returns 403; deleted or mismatched route models return 404.

Updated working screens: all three dashboards; course detail; lesson reader; student My courses; staff roster; administrator enrollment overview. New screens: course progress report and student progress report. All visible added actions point to implemented routes.

No `/api/v1` endpoints, API token issuance, or M5 functionality were added.

## 6. Actual automated test results

Commands were run with the existing private runtime and isolated MySQL:

```bash
source .runtime/env.sh
python3 tools/with-database.py -- bash -c \
  'cd app && php vendor/bin/phpunit --log-junit ../evidence/m4/phpunit-final.xml --colors=never'
```

Actual final console output:

```text
Private MySQL connection verified.
{"tool":"phpunit","result":"passed","tests":187,"passed":187,"assertions":1268,"duration_ms":7648}
```

JUnit confirms **0 failures, 0 errors, 0 skipped tests**.

| Coverage | Tests | Assertions | Result |
| --- | ---: | ---: | --- |
| Existing M1–M3 suite, unchanged | 137 | 899 | Passed |
| LessonCompletionTest | 25 | 197 | Passed |
| LearningProgressTest | 10 | 33 | Passed |
| LearningReportsTest | 10 | 102 | Passed |
| CompletionConcurrencyTest | 5 | 37 | Passed |
| **Total** | **187** | **1268** | **Passed** |

New coverage includes completion/undo, repeated requests and timestamp stability, duplicate prevention, guest and nonstudent denial, forged fields, cross-course binding, current publication/deletion rules, withdrawal/re-enrollment, curriculum changes, empty courses, precision, Continue learning, dashboard calculations, pagination/filtering, HTML escaping, teacher scope, bounded query counts, throttling, and independent-process races.

Concurrency cases:
1. Two simultaneous completions return one persisted record.
2. Two simultaneous undo requests both succeed and leave no completion.
3. Withdrawal committed before a queued completion causes 403.
4. Withdrawal committed before a queued undo causes 403 and preserves history.
5. Lesson archive committed before a queued undo causes 404 and preserves history.

The original integrity tests still verify actual MySQL rejection of duplicate enrollment/completion pairs, cross-course composite foreign keys, orphan references, and invalid roles. Original authentication, session, authorization, teacher administration, course, lesson, enrollment, and M3 concurrency tests all passed.

### Failures encountered and fixed

The initial dashboard regression run failed all four existing DashboardTest cases: percentage strings such as a value with a percent suffix reached the numeric stat component's `number_format()`, producing TypeError/HTTP 500. The component now accepts separate numeric value, suffix, and decimal-place props. These four original tests pass unchanged in the final suite.

No final test is failing or skipped. Raw final/JUnit output, the passing baseline, focused runs, and a concise initial-failure summary are included. The oversized initial debug HTML/stack log remains local and is excluded from delivery.

## 7. Database migration and preservation evidence

Executed against the existing development and test databases:

```bash
php artisan migrate --no-interaction
php artisan migrate --env=testing --no-interaction
php artisan migrate:status --no-interaction
php artisan migrate:status --env=testing --no-interaction
```

Both migration commands returned:

```text
INFO Nothing to migrate.
```

Both status outputs show:

```text
0001_01_01_000000_create_users_table                         [1] Ran
0001_01_01_000001_create_cache_table                         [1] Ran
0001_01_01_000002_create_jobs_table                          [1] Ran
2026_10_21_001334_create_academy_schema                      [2] Ran
2026_10_21_133758_add_lesson_soft_deletes_and_order_version   [3] Ran
```

Verified databases: `penguin_academy` and `penguin_academy_test`. Both report MySQL **8.4.13**, session time zone **+00:00**, InnoDB, and utf8mb4_unicode_ci for the five core tables.

Integrity checks report zero orphan lessons, wrong-role teacher references, wrong-role/missing enrollment parents, missing/cross-course completion parents, duplicate enrollment pairs, duplicate completion pairs, and duplicate lesson positions in both databases.

The database inspection is intentionally read-only. Because the preserved databases are empty outside tests, invalid-write and populated relationship guarantees are additionally exercised by the MySQL integration tests, rather than inferred from empty-table counts.

Evidence: `database-before.json`, `database-after.json`, `schema-before.json`, `integrity-development.json`, `integrity-test.json`, and both migration/status logs under `evidence/m4`.

## 8. Dependency, build, and style evidence

No package was added or upgraded in M4. The M1–M3 installed baseline remains: PHP 8.3.6, Laravel 13.38.0, MySQL 8.4.13, Composer 2.10.3, Fortify 1.41.0, Sanctum 4.3.3, PHPUnit 12.5.40, Bootstrap 5.3.8, Vite 8.3.4, Node 24.19.0, npm 11.9.0.

```text
composer validate --strict --no-check-publish
./composer.json is valid
```

```text
npm run build
vite v8.3.4 building client environment for production...
✓ 61 modules transformed.
public/build/manifest.json              0.33 kB │ gzip:  0.16 kB
public/build/assets/app-CcLB5p0l.css  244.90 kB │ gzip: 34.71 kB
public/build/assets/app-CBrPiYsV.js    81.17 kB │ gzip: 24.28 kB
✓ built in 292ms
```

npm emitted a nonfatal environment warning: `Unknown env config "http-proxy". This will stop working in the next major version of npm.` The build exited 0.

Blade compilation returned `Blade templates cached successfully.`; compiled views were then cleared.

The requested Pint `--dirty` mode reported `The [--dirty] option is only available when using Git.` This workspace is not a Git checkout. Pint was therefore run on the explicit new/modified PHP file list, exited 0, and fixed method-chain indentation, class-property spacing, and import order. The full suite ran after formatting.

## 9. Real browser evidence and exact limitation

The managed local preview and real Chrome browser worked. The final built application rendered the welcome page. Requests to all three role dashboards redirected guests to the sign-in page.

Real Tab-key navigation proceeded Email → Password → remember-device checkbox → Sign in. At a 1363 × 936 viewport, document width was 1363 and the focused sign-in button had a 3px outline. The previous M2 timeout did not recur. An expired tab produced `Tab not found in browser 1.`; creating a fresh tab in the same browser resolved that tab-lifecycle issue.

**Authenticated verification remains outstanding.** There are zero development accounts, and secure user sign-in/manual signup handoff was not completed. The supported browserAuth flow requires user credential entry and explicitly cannot create accounts. Its guidance prohibits lower-level Playwright credential entry. No credential request was submitted and no browserAuth “unavailable” result is claimed.

Therefore there is no browser success claim for authenticated dashboards, learning/report screens, enrollment/withdrawal, completion/undo, progress after a browser refresh, mobile responsiveness, or authenticated keyboard navigation. These server flows are covered by HTTP/MySQL tests; that is not end-to-end browser verification.

No password was exposed, session injected, or authentication backdoor created. Full observations and executed browser actions are recorded in `evidence/m4/browser-verification.md`.

## 10. Delivery and reproduction

`Penguin-Academy-M4.zip` contains the complete updated application source, tests, lockfiles, compiled frontend assets, migrations/factories/seeders, design/architecture documentation, historical milestone reports, setup/verification tools, and non-secret evidence.

It intentionally excludes private environment files, credentials, database contents, runtime binaries, vendor, node_modules, application logs, and caches. Dependencies are reproducible from the preserved lockfiles; runtime restoration/setup instructions remain in README.md.

The archive is CRC-tested and all entry bytes are compared to their source files before saving. A SHA-256 and byte/file counts are recorded in the adjacent `deliverables/Penguin-Academy-M4.integrity.json` (outside the ZIP to avoid a self-referential hash). Delivery scanning excludes credentials and confirms the actual local app keys/database passwords are absent.

For the already configured workspace:

```bash
source .runtime/env.sh
python3 tools/with-database.py -- bash tools/verify-m4.sh
```

This applies only pending migrations, runs the complete suite, validates Composer, builds assets, compiles Blade, records routes, and inspects both schemas. Fresh results go under `evidence/m4/latest`; recorded milestone evidence is preserved. Run only one database wrapper/preview at a time. Do not reinitialize existing databases.

## 11. Outstanding issues and security observations

- Authenticated browser, mobile, and complete keyboard/accessibility verification remain outstanding as described above.
- Development data is empty by preservation, not a populated demonstration. Provision an administrator through the existing protected console command; there are no supplied passwords.
- Course-level write serialization and aggregate reports are verified at test-fixture scale, not production load.
- Progress uses the current curriculum and is not an immutable completion certificate or audit trail.
- The existing plain-text lesson editor, account/security rules, and archive/withdraw workflows remain unchanged. No restore/permanent-delete HTTP workflow was added.
- The nonfatal npm proxy-configuration warning belongs to the current execution environment.
- No deployment, paid services, production credentials, or M5 APIs were used.

**Stop point:** M4 implementation and server-side verification are delivered. Await explicit approval before M5.
