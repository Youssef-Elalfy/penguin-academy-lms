# Penguin Academy — Milestone 3 implementation and test report

**Delivery date:** 2026-10-22  
**Scope:** Course, Lesson, and Enrollment Management only.  
**Status:** Implemented and verified through MySQL-backed automated tests; public-page browser verification passed in part. Authenticated browser verification remains outstanding.

## 1. Result and preservation

M3 continues the original `penguin-academy-m1/app` Laravel project and the existing `penguin_academy` / `penguin_academy_test` MySQL databases. No replacement project, deployment, paid service, destructive migration, or development data reset was performed.

The final suite passed **137 tests with 899 assertions, zero errors, zero failures, and zero skips**. This includes all **72 existing M1/M2 tests with 348 assertions**, unchanged, and **65 new tests with 551 assertions**. Composer validation, the frontend build, and Blade compilation passed.

A comparison against the previous M2 ZIP verified 31 selected foundation files byte-for-byte unchanged: original tests, all four prior migrations, both dependency lockfiles, existing security configuration, Fortify registration, teacher-account actions, and account/security middleware. See `evidence/m3/m1-m2-preservation.json`.

The development database contained **zero users, courses, lessons, enrollments, and lesson completions at the M3 baseline**. The same tables still contain zero rows. Consequently, no existing populated account/course dataset can be claimed as exercised in development. Tests created real records in the separate test database and cleaned them up; no demo account or hardcoded operational data was added.

Historical M1/M2 reports and evidence are retained as historical records. Their earlier scope/browser limitations are superseded by this report where explicitly stated.

## 2. Runtime and dependencies

| Component | Verified version |
| --- | --- |
| PHP | 8.3.6 |
| Laravel / Composer | 13.38.0 / 2.10.3 |
| MySQL Community | 8.4.13 |
| Fortify / Sanctum | 1.41.0 / 4.3.3 |
| Bootstrap / Vite | 5.3.8 / 8.3.4 |
| PHPUnit | 12.5.40 |
| Node.js / npm | 24.19.0 / 11.9.0 |

M3 changed no dependency versions. The existing private runtime is loaded using `source .runtime/env.sh`; the global shell does not provide PHP on its own. The supplied tools restore this development runtime on Ubuntu 24.04 AMD64 after a fresh extraction.

MySQL processes must run in the same execution context as the commands that use them. `tools/with-database.py` starts the private server, verifies the connection, runs the command, and stops it. This avoids mistaking execution-context isolation for a database configuration failure.

The existing Laravel Boost package was used to generate repository guidance without changing lockfiles. PHP sources were formatted with Pint.

## 3. Review findings resolved first

| Finding | Implemented correction | Verification |
| --- | --- | --- |
| Teachers could not create courses | CoursePolicy permits active Teachers; creation binds the signed-in teacher on the server | ReviewCorrectionsTest and CourseManagementTest |
| Lessons lacked soft deletion | Additive deleted_at column and Eloquent SoftDeletes; policies exclude archived lessons | Migration evidence; history and authorization tests |
| Assigned teachers lacked enrollment management | Course-scoped roster/enroll/withdraw permissions for assigned Teachers | ReviewCorrectionsTest and EnrollmentManagementTest |
| Assigned/enrolled users needed role checks | Form Requests validate active persisted Teacher/Student roles; Actions recheck under row locks | Wrong-role, inactive-account, and forged-ID tests |
| Historical completions could reference an archived lesson | Completion policy safely handles a missing default-scoped lesson; history rows remain | ReviewCorrectionsTest |

These corrections and their additive migration were applied before the new workflow implementation. The final full run contains all 10 review-correction tests, with 29 assertions.

Course soft deletion remains **administrator-only**, preserving the existing M2 permission rule. Assigned teachers can create/edit/publish their courses and manage their lessons/enrollments, but cannot reassign teachers or archive courses.

## 4. Implemented workflows

### Courses

- Create draft courses with title, unique slug, summary, description, and optional administrator-selected teacher.
- Teacher-created courses automatically belong to the signed-in teacher. A teacher-supplied teacher_id is rejected.
- Edit course information; assign/reassign an active Teacher as administrator.
- Publish/unpublish through separate authorized actions. Publication requires an active assigned Teacher. A published course must be unpublished before removing its teacher.
- Archive courses without deleting lessons, enrollments, or learning history.
- Search, filter, sort, and paginate course listings: administrators see current courses, teachers see assigned courses, and students see published current courses.
- Slugs remain unique across archived courses, preventing accidental reuse.

### Lessons

- Create draft lessons inside authorized courses; edit title and escaped plain-text content.
- Publish/unpublish and soft-delete lessons.
- Authorized lesson reader with previous/next navigation.
- Students need an active own enrollment plus a published course and published lesson. Draft, deleted, and cross-course lessons are denied.
- Reordering accepts every current lesson exactly once and uses a course-level version to detect stale forms.
- Course row locks serialize append/delete/reorder operations. Two-phase position updates preserve the unique course/position constraint. Archived positions are retained.
- Conflicting reorder returns **HTTP 409**, with a reload instruction. Foreign, duplicate, or incomplete lesson sets fail validation. Position integer exhaustion is rejected before mutation.

### Enrollments

- Student self-enrollment uses authenticated identity only, in published current courses.
- Administrators and assigned teachers can enroll active Student accounts in published current courses.
- Staff roster search, active/withdrawn filters, persisted counts, and pagination.
- Administrator overview across current courses, with student/status/course filters.
- Student My courses page with active/withdrawn filters and unavailable archived-course history.
- Withdrawal preserves the enrollment row and all completion records. Re-enrollment reactivates that same row and retains the original enrolled_at.
- Duplicate active enrollment and duplicate withdrawal are idempotent. The database unique student/course constraint remains enabled.
- A student can withdraw from an unpublished current course. Archived courses have no mutation route.

### Architecture and UI

Implementation uses `CourseController`, `LessonController`, `EnrollmentController`, dedicated Form Requests, Laravel Policies, and `Actions/Academy` transactional services with Eloquent.

Bootstrap pages reuse the existing green/mint Penguin Academy design system, shared navigation, alerts, form errors, responsive tables, pagination, and dashboard links. New components include course catalog cards, course editors, lesson reader/order forms, roster/overview tables, My courses, and a conflict screen.

Ordering uses native labeled selects, enhanced with up/down controls and an aria-live announcement. Forms include CSRF tokens, busy feedback, and confirmation prompts for archive/withdraw actions. There are no placeholder action buttons. Course and lesson content is escaped, including script-like input.

`app/DESIGN.md` documents the extended typography, spacing, colors, components, interaction states, and verification limits.

## 5. Security and transactional behavior

- All workspace routes retain authentication, session validation, active-account enforcement, and temporary-password-change protection.
- Form Requests authorize each operation. Actions reload and lock the current course and recheck policy authorization before mutation.
- Nested scoped bindings reject mismatched course/lesson or course/enrollment IDs with 404, even for administrators.
- Role, identity, ownership, position, publication timestamp, and lifecycle fields cannot be mass-assigned by callers.
- Teacher assignment and staff enrollment validate the persisted role and active state, including a second check under a participant row lock.
- Academy writes have a per-user limit of 60 requests/minute, alongside the preserved M2 authentication/account throttles.
- Existing password hashing, Fortify session rotation, encrypted database sessions, CSRF protection, safe cookies, and session/token revocation remain covered by regression tests.
- Transactions retry deadlocks up to three attempts. Database uniqueness and foreign keys remain the final integrity boundary.

Web validation redirects with field errors; requests expecting JSON receive Laravel validation responses. There is no new /api/v1 surface in M3.

The enrollment selector lets authorized staff search active students across the academy by name/email, limited to 25 results. This is necessary to add students; it is distinct from a course roster. Teachers cannot read or mutate another course's enrollment records.

## 6. Database migrations and integrity

New additive migration:

`2026_10_21_133758_add_lesson_soft_deletes_and_order_version.php`

It adds:

1. Nullable `lessons.deleted_at`.
2. Index `(course_id, deleted_at, is_published)`.
3. Unsigned bigint `courses.lesson_order_version`, default 0.

No original migration was edited. No migrate:fresh, migrate:refresh, reset, rollback, or development seeding was performed.

Actual migration output excerpts:

```text
Private MySQL connection verified.
2026_10_21_133758_add_lesson_soft_deletes_and_order_version .. 54.02ms DONE
2026_10_21_133758_add_lesson_soft_deletes_and_order_version ... 50.71ms DONE
```

Final status in **both databases**:

```text
0001_01_01_000000_create_users_table                              [1] Ran
0001_01_01_000001_create_cache_table                              [1] Ran
0001_01_01_000002_create_jobs_table                               [1] Ran
2026_10_21_001334_create_academy_schema                           [2] Ran
2026_10_21_133758_add_lesson_soft_deletes_and_order_version        [3] Ran
```

Information-schema inspection confirms both databases use MySQL 8.4.13, session UTC (+00:00), InnoDB, and utf8mb4_unicode_ci for the five core tables. The new columns/index and existing unique/composite foreign-key definitions are present.

Post-test integrity queries returned zero orphan lessons, invalid participant roles, orphan/cross-course completions, duplicate enrollment pairs, duplicate completion pairs, and duplicate lesson positions. Both core datasets were empty after fixture cleanup. Those zero-row inspections are supplemented by the passing populated-record constraint and concurrency tests; they are not a claim of production data validation.

Evidence:
- `review-corrections.log`: original migration execution.
- `migrations-final.log`: final status in both databases.
- `database-before.json` / `database-after.json`: development preservation.
- `database-integrity-development.json` / `database-integrity-test.json`: schema, indexes, foreign keys, counts, violations.
- Private baseline row copies are intentionally excluded from delivery.

## 7. Automated test results

The final executed command was:

```bash
source .runtime/env.sh
python3 tools/with-database.py -- bash -c 'cd app && php vendor/bin/phpunit --testdox --log-junit ../evidence/m3/phpunit-all.xml'
```

Actual final output:

```text
OK (137 tests, 899 assertions)
{"tool":"phpunit","result":"passed","tests":137,"passed":137,"assertions":899,"duration_ms":4988}
```

JUnit records **137 tests, 899 assertions, 0 errors, 0 failures, 0 skipped**, with 4.959204 seconds accumulated test time. The console runner reports 4988 ms; these are different timing measurements of the same successful run.

| Suite | Existing/new | Tests | Assertions | Result |
| --- | --- | ---: | ---: | --- |
| AcademyConcurrencyTest | New M3 | 3 | 27 | Passed |
| AdministratorProvisioningTest | M1/M2 unchanged | 4 | 17 | Passed |
| AuthenticationTest | M1/M2 unchanged | 18 | 111 | Passed |
| AuthorizationTest | M1/M2 unchanged | 9 | 46 | Passed |
| CourseManagementTest | New M3 | 21 | 173 | Passed |
| DashboardTest | M1/M2 unchanged | 4 | 15 | Passed |
| DatabaseIntegrityTest | M1/M2 unchanged | 11 | 27 | Passed |
| EnrollmentManagementTest | New M3 | 18 | 194 | Passed |
| FoundationTest | M1/M2 unchanged | 7 | 16 | Passed |
| LessonManagementTest | New M3 | 13 | 128 | Passed |
| ReviewCorrectionsTest | New M3 | 10 | 29 | Passed |
| SessionSecurityTest | M1/M2 unchanged | 9 | 54 | Passed |
| TeacherManagementTest | M1/M2 unchanged | 10 | 62 | Passed |

Coverage includes authentication/registration/logout, CSRF/session security, administrator provisioning, account lifecycle, role escalation, course ownership, CRUD/publication, forged participant IDs, cross-course routing, soft deletion/history, duplicate submissions, withdrawal/re-enrollment, rosters/filters/pagination, escaping, dashboard regressions, and database constraints.

The three new concurrency tests run **independent PHP processes against MySQL** while coordinating a course-row lock:

- Two simultaneous enrollments produce one row and the same enrollment ID.
- Two simultaneous lesson appends produce distinct positions and increment the course version twice.
- Two reorder requests with the same starting version produce one success and one 409, preserving only the winning order.

These are bounded integration tests, not load or performance certification.

### Initial failure and correction

An intermediate workflow run had **52 tests / 488 assertions and one failure**. A new roster test asserted that a foreign student's email was absent from the entire page. That email legitimately appeared in the eligible-student selector, which searches students academy-wide for enrollment.

The test was corrected to inspect the roster paginator and assert that the other course's **enrollment record** was absent. Application authorization was not relaxed. The targeted enrollment/concurrency run then passed **21 tests / 221 assertions**, followed by the final full pass.

The failed run remains in `workflows-first.log/xml`; the correction's verification is in `enrollment-concurrency.log/xml`. No final test failure or skipped test is concealed.

## 8. Composer, frontend, and template verification

Executed inside app/:

```bash
composer validate --strict
npm run build
php artisan view:cache
php artisan view:clear
php artisan route:list --json
```

Actual Composer output:

```text
./composer.json is valid
```

Actual frontend output:

```text
vite v8.3.4 building client environment for production...
✓ 61 modules transformed.
public/build/manifest.json              0.33 kB │ gzip:  0.16 kB
public/build/assets/app-C8uxtaYG.css  243.57 kB │ gzip: 34.47 kB
public/build/assets/app-CBrPiYsV.js    81.17 kB │ gzip: 24.28 kB
✓ built in 270ms
```

npm also emitted: `Unknown env config "http-proxy". This will stop working in the next major version of npm.` This environment warning did not fail the build. No dependency configuration was changed merely to hide it.

Blade output confirmed templates cached successfully and compiled views cleared successfully. Pint formatted the PHP sources. See `composer-validate.log`, `frontend-build.log`, `blade.log`, and `pint.log`.

## 9. Actual browser verification and exact remaining blocker

The earlier M2 managed-browser timeout **did not recur**. The existing local preview was started through the supported preview supervisor. Chrome opened the actual Laravel welcome page in **2.5402 seconds** without an application change to work around the previous failure.

Using the managed browser's Playwright interface, the following were observed:

- Welcome page and accessibility tree loaded.
- Guest navigation to /courses redirected to /login.
- Sign-in heading and email/password/remember controls rendered; each inspected control had one associated label.
- Desktop sign-in at 1363 × 936 had document width 1363, without horizontal overflow.
- An actual screenshot of the sign-in page was viewed.
- Clicking “Create a student account” opened /register with the registration heading and four expected fields.

**Authenticated course, lesson, and enrollment browser workflows were not run.** The development database had no existing account. The browser's advertised authentication capability states:

> Never enter or submit sign-in credential values through Playwright, vision, or any other model-visible tool.

It requires secure user credential entry through `browserAuth.request`. That handoff was not completed. No account credentials were typed by the agent, and no session injection, test-login backdoor, or mock authentication was introduced.

This is a credential-handoff limitation, **not a new browser timeout or an application error**. The server-side HTTP and MySQL tests passed independently. They must not be described as authenticated browser end-to-end tests.

Mobile behavior, a full keyboard/accessibility walkthrough, and authenticated reorder JavaScript remain browser-unverified. No fabricated screenshot or full Playwright pass is claimed. The preview is stopped at handoff. See `browser-initial.json` and `browser-final.json`.

A future authenticated browser pass should cover administrator assignment/publication, teacher lesson CRUD/order/rosters, student enrollment/reader/withdrawal/re-enrollment, stale-order feedback, and narrow-screen/keyboard navigation using securely entered real local accounts.

## 10. Implemented routes

The complete generated route inventory is `evidence/m3/routes.json`: **48 routes total**, including preserved authentication/dashboard/account/teacher routes. M3 adds **23 route entries** below. GET entries also accept HEAD.

| Method | Path | Route name |
| --- | --- | --- |
| GET | /admin/enrollments | admin.enrollments.index |
| GET | /courses | courses.index |
| POST | /courses | courses.store |
| GET | /courses/create | courses.create |
| GET | /courses/{course} | courses.show |
| PUT / PATCH | /courses/{course} | courses.update |
| DELETE | /courses/{course} | courses.destroy |
| GET | /courses/{course}/edit | courses.edit |
| GET | /courses/{course}/enrollments | courses.enrollments.index |
| POST | /courses/{course}/enrollments | courses.enrollments.store |
| DELETE | /courses/{course}/enrollments/{enrollment} | courses.enrollments.destroy |
| POST | /courses/{course}/lessons | courses.lessons.store |
| GET | /courses/{course}/lessons/create | courses.lessons.create |
| GET | /courses/{course}/lessons/order | courses.lessons.order |
| PUT | /courses/{course}/lessons/order | courses.lessons.reorder |
| GET | /courses/{course}/lessons/{lesson} | courses.lessons.show |
| PUT / PATCH | /courses/{course}/lessons/{lesson} | courses.lessons.update |
| DELETE | /courses/{course}/lessons/{lesson} | courses.lessons.destroy |
| GET | /courses/{course}/lessons/{lesson}/edit | courses.lessons.edit |
| PUT | /courses/{course}/lessons/{lesson}/publication | courses.lessons.publish |
| PUT | /courses/{course}/publication | courses.publish |
| PUT | /courses/{course}/teacher | courses.assign |
| GET | /my-courses | my-courses |

Course resource update routes accept PUT or PATCH. “Delete” course/lesson routes archive records; enrollment DELETE withdraws without physical deletion. All mutations are authenticated, authorized, CSRF-protected web routes. Policy/form-request restrictions apply regardless of whether a button is visible.

## 11. Delivery and reproducibility

The ZIP contains the complete application source, migrations, factories, seeders, tests, controllers/actions/requests/policies, Blade/CSS/JavaScript, compiled Vite assets, lockfiles, environment examples, tooling, and M1–M3 documentation/evidence.

It excludes actual .env files, keys/passwords, database files/dumps, runtime binaries, downloaded installers, vendor/, node_modules/, compiled Blade caches, logs containing runtime session state, and private browser/transfer state. Dependencies are reproducible from lockfiles. Source completeness does not imply bundled third-party dependency directories.

For the existing initialized workspace:

```bash
source .runtime/env.sh
python3 tools/with-database.py -- bash tools/verify-m3.sh
```

The supplied verification script records a fresh run under `evidence/m3/latest`, preserving the evidence of this delivery. It applies pending migrations to the two existing databases, runs the complete suite, validates Composer, builds assets, compiles/clears Blade, and inspects database integrity. The individual commands above were executed for this delivery; the wrapper script was added afterward and syntax-checked, without redundantly rerunning the whole suite.

For a fresh extraction, follow README.md to restore/install the runtime, create private environment files, install locked dependencies, initialize new local databases only if absent, and migrate. Initialization refuses to overwrite existing state. Never reset a populated database to apply M3.

Secure administrator provisioning remains `php app/artisan academy:admin` through the database wrapper. It has hidden password prompts and no default password. Do not place credentials in shell arguments or source files.

## 12. Known limitations and next boundary

- Authenticated browser and mobile/accessibility checks remain outstanding as described above.
- No demonstration or default administrator account is included; the starting development database was empty.
- Lesson content is escaped plain text. Rich text, media uploads, quizzes, and file delivery are not implemented.
- Archive actions preserve history, but no restore/force-delete UI or HTTP route is implemented.
- Staff enrollment search reveals active student names/emails to authorized staff managing a course; a narrower invitation-only directory would require an additional business rule.
- Concurrency was verified for the three documented races, not every possible load pattern.
- Completion/undo workflows and new progress features remain **M4**. Versioned APIs, token issuance/expiration endpoints, and broader frontend/API work remain later milestones. Existing Sanctum/session foundations are preserved.
- Email recovery/verification, MFA, production hosting, operational backups, monitoring, and deployment are not introduced by M3.
- The supplied runtime restoration helper is for local Ubuntu 24.04 AMD64 development; other systems require compatible locally installed services.
- The npm http-proxy warning is non-blocking and remains recorded.

**No M4 implementation or deployment was performed. M4 requires explicit approval.**
