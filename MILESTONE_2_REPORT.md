# Penguin Academy — Milestone 2 implementation and test report

Date: 21 October 2026 (Africa/Cairo). Approved scope: Database, Authentication & Authorization. The existing M1 Laravel application and MySQL development/test databases were used throughout.

**Result: M2 implemented. 72 PHPUnit tests passed with 348 assertions, zero failures, zero errors, and zero skipped tests. Browser inspection remains unverified because the browser tool timed out. No deployment or paid service was used. M3 has not started.**

## 1. Database implementation

Migration `2026_10_21_001334_create_academy_schema` ran successfully on both existing databases, as batch 2. The original three Laravel migrations remain batch 1.

| Entity | Implemented |
| --- | --- |
| Users | Admin/Teacher/Student enum role, active status, forced-password-change flag, unique email |
| Courses | Assigned-teacher FK, unique slug, publication timestamp, soft deletion, indexed visibility/assignment fields |
| Lessons | Course FK, content, ordered position, publication flag, unique position within course |
| Enrollments | Student/course FKs, enrollment/withdrawal timestamps, unique student/course pair |
| Lesson completions | Enrollment/lesson references, completion time, unique enrollment/lesson pair |
| Session data | Existing sessions gain a user FK with SET NULL behavior |
| API-token schema | Sanctum-compatible hashed token storage and expiration index; no issuance API exposed |

Eloquent relationships and factories exist for every domain model. Role-specific UserFactory states support isolated tests. DatabaseSeeder intentionally creates no default account or fake operational data.

Composite foreign keys bind completion enrollment/course and lesson/course pairs to matching parent pairs. Both mismatched-course directions were tested using raw MySQL inserts. This prevents a valid enrollment ID and valid lesson ID from being combined across courses.

Courses use soft deletion by default. Soft deletion preserves related history and hides courses through normal Eloquent scopes/policies. Hard course deletion cascades lessons, enrollments, and completions. Referenced teachers/students are protected by RESTRICT. All domain records use the configured InnoDB engine, utf8mb4, and UTC.

The teacher FK guarantees an existing user. Teacher-role eligibility on future assignment requests belongs to M3; no teacher-assignment write endpoint exists yet.

Actual status output, recorded in `evidence/m2/migrations-and-database.log`:

```text
0001_01_01_000000_create_users_table .. [1] Ran
0001_01_01_000001_create_cache_table .. [1] Ran
0001_01_01_000002_create_jobs_table .. [1] Ran
2026_10_21_001334_create_academy_schema .. [2] Ran

version  timezone  charset  engine
8.4.13   +00:00    utf8mb4  InnoDB
Database (penguin_academy%)
penguin_academy
penguin_academy_test
```

The same log records the migration status for the separate test database.

## 2. Authentication and account security

Fortify is activated through explicit provider registration, with automatic discovery kept disabled to avoid double registration. Its actual controllers handle registration, login, logout, and password confirmation.

Public registration validates name/email/password, normalizes email, hashes passwords, and always assigns Student on the server. Attempts to supply role, account status, verification time, forced-password flags, or identity are rejected. Case-insensitive duplicate email registration is blocked.

Passwords require at least 12 characters with uppercase, lowercase, and a number. A 72-byte maximum prevents silent bcrypt truncation, including multibyte input. Stored passwords and remember tokens are hidden from model serialization.

Login rejects inactive accounts and uses generic invalid-credential responses. Throttling applies at five attempts per normalized email/IP per minute, plus 30 attempts per IP per minute. Registration is limited to six attempts per IP per minute. Password confirmation and sensitive account writes have separate limits.

Fortify regenerates the session after authentication. Logout is POST-only, invalidates the session, and regenerates the CSRF token. Tests verify these behaviors, not merely middleware registration. CSRF rejection was tested with the test-mode bypass explicitly disabled.

Development sessions are encrypted and stored in MySQL. A dedicated integration test verifies the persisted payload is encrypted and decrypts to the expected session data. Cookies are HttpOnly and SameSite=Lax; Secure defaults on for production. Local HTTP development intentionally permits non-Secure cookies.

All active users can change their own password after current-password verification. The check is repeated inside a transaction. Other sessions and tokens are revoked, the remember token rotates, and the current session ID regenerates.

## 3. Authorization and administrator provisioning

Policies cover Users, Courses, Lessons, Enrollments, and LessonCompletions. Dashboard routes enforce the specific role on the server. Students and teachers receive 403 responses when opening administrative routes, and each role is denied the other roles' dashboards.

Course/lesson policies restrict teachers to assigned courses. Student lesson access requires a published lesson, a published non-archived course, and an active enrollment. Completion policies also require ownership and same-course references. These are tested policy foundations; domain write routes are deferred to later milestones.

The `academy:admin` console command provisions administrators with hidden password/confirmation prompts. It rejects weak passwords and refuses to promote or overwrite existing users. Non-interactive use requires explicit identity options and password input through stdin; there is no password argument, printed credential, default password, or public admin-signup endpoint.

No real administrator identity was invented or seeded. The command is implemented and tested; the operator supplies their chosen identity/password during local setup.

## 4. Teacher account management

Administrators can search and paginate teachers, create accounts, update names/emails, reset temporary passwords, and deactivate/reactivate accounts.

Every write uses a Form Request, Policy authorization, and confirmation of the authenticated administrator's current password. Route-bound target IDs are checked against the Teacher role. Replacing a teacher ID with an administrator/student ID is denied. Payload role escalation is rejected.

Teacher creation and administrator password resets require the teacher to choose a new password before dashboard access. Deactivation, email changes, and password resets revoke existing sessions/tokens and rotate remember tokens. Deactivation retains assigned course/history records.

Temporary passwords are shared privately by the administrator; the application does not email or redisplay them.

## 5. Functional dashboards and interface

All three dashboards are distinct Bootstrap/Blade pages backed by persisted database queries:

- Admin: active teachers/students, non-archived courses, current enrollments, and recent account lists.
- Teacher: assigned courses, lesson totals, and distinct enrolled students, with a paginated course list.
- Student: current published-course enrollments, published-lesson completion counts, and an academy-wide published-course count.

Counts are scoped and tested. Withdrawn enrollments, archived courses, unpublished content, and other students' records are excluded where appropriate. Enrollment status is separate from account status; deactivation does not erase enrollment history.

Shared components provide responsive navigation, semantic tables, explicit field labels, validation feedback, busy submit states, empty states, keyboard focus styles, and reduced-motion support. All displayed user-supplied text is escaped. No button claims an unimplemented course/enrollment operation.

Actual asset-build output:

```text
vite v8.3.4 building client environment for production...
✓ 61 modules transformed.
public/build/manifest.json              0.33 kB │ gzip:  0.16 kB
public/build/assets/app-7uvl2oni.css  239.79 kB │ gzip: 33.64 kB
public/build/assets/app-B2XyD8x1.js    80.48 kB │ gzip: 24.05 kB
✓ built in 1.05s
```

Blade compilation also succeeded: `INFO Blade templates cached successfully.` The compiled view cache was then cleared for normal development.

## 6. Actual automated results

Executed against the dedicated MySQL test database:

```bash
source .runtime/env.sh
python3 tools/with-database.py -- bash -c 'cd app && php vendor/bin/phpunit --testdox --log-junit ../evidence/m2/phpunit.xml'
```

Actual output:

```text
Private MySQL connection verified.
PHPUnit 12.5.40 by Sebastian Bergmann and contributors.
Runtime: PHP 8.3.6
Time: 00:06.161, Memory: 60.50 MB
OK (72 tests, 348 assertions)
```

| Suite | Tests | Assertions |
| --- | ---: | ---: |
| Administrator provisioning | 4 | 17 |
| Authentication | 18 | 111 |
| Authorization | 9 | 46 |
| Dashboard calculations | 4 | 15 |
| Database integrity/relationships | 11 | 27 |
| Original M1 foundation | 7 | 16 |
| Session security | 9 | 54 |
| Teacher management | 10 | 62 |
| **Total** | **72** | **348** |

All suites report zero failures, errors, and skips. Data-provider cases are included in these counts. No failing test run occurred in M2. Full output and machine-readable JUnit results are in `evidence/m2/phpunit-first-run.log` and `evidence/m2/phpunit.xml`.

Coverage includes role escalation, ownership, unauthorized routes, duplicate enrollment/completion, both directions of cross-course FK violation, orphan references, ordered relationships, soft deletion/hard-delete cascades, restricted user deletion, incorrect passwords, throttling, session rotation/invalidation, encrypted session persistence, remember-me cookies, password-change enforcement, token/session revocation, escaped output, and actual dashboard calculations.

Additional checks:

```text
composer validate --strict
./composer.json is valid

Laravel Pint
{"tool":"pint","result":"passed"}

Blade
INFO Blade templates cached successfully.
```

No dependency version changed; M1's lockfiles and checksummed runtime baseline are retained. This is a functional/security regression suite, not a penetration test or production-readiness certification.

## 7. Browser attempt and limitations

The managed preview successfully reported a running local server. Chrome inventory was available. Opening the internal preview tab then failed with:

```text
js execution timed out; kernel reset, rerun your request
```

A 15-second timeout was requested, but the tool returned after approximately 1,047 seconds. No DOM, screenshot, or page interaction was obtained. The preview was stopped, and local runtime paths were restored afterward.

Therefore browser execution, visual/mobile behavior, keyboard walkthroughs, and Playwright tests are **unverified**. PHPUnit HTTP requests and Blade compilation must not be described as browser verification. The attempt is recorded in `evidence/m2/browser-attempt.json`.

## 8. Handoff and scope boundary

The updated archive contains source, migrations, factories, policies, Form Requests, Fortify integration, dashboards, tests, lockfiles, built assets, documentation, and evidence. It excludes private environment files, application keys, database contents, runtime binaries, and installed dependency directories. README contains restoration/startup and secure administrator-provisioning instructions.

Email verification, emailed password recovery, MFA/passkeys, course/lesson CRUD, teacher assignment, enrollment/withdrawal operations, completion/undo operations, and versioned APIs are not presented as implemented. The relevant core schema/policies are ready for the next approved milestone.

No production credentials, purchases, public deployment, or M3 work was used. M2 is ready for review; M3 requires approval.
