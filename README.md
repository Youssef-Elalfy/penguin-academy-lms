# Penguin Academy — final M6 handoff

A Laravel 13 application with persistent MySQL data, Fortify registration/login/logout, three server-controlled roles, Bootstrap dashboards, administrator-managed teacher accounts, transactional course, lesson, and enrollment management, idempotent lesson completion, persisted progress, and role-scoped learning reports.

M6 completes the original application with verified authenticated browser journeys, security/contract corrections and professional handoff. No service has been deployed. The previous managed-browser limitation is resolved using local Playwright/Chromium and disposable accounts provisioned through supported application flows.

Read [MILESTONE_6_REPORT.md](MILESTONE_6_REPORT.md), [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md), [SECURITY_AUDIT.md](SECURITY_AUDIT.md), [BROWSER_TEST_RESULTS.md](BROWSER_TEST_RESULTS.md), [PERFORMANCE_REPORT.md](PERFORMANCE_REPORT.md), and [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md). Architecture, schema and design remain documented in [ARCHITECTURE.md](ARCHITECTURE.md) and [app/DESIGN.md](app/DESIGN.md). Earlier milestone reports remain historical evidence.

## Verified baseline

| Component | Version |
| --- | --- |
| PHP | 8.3.6; Ubuntu revision 8.3.6-0ubuntu0.24.04.11 |
| Laravel / Composer | 13.38.0 / 2.10.3 |
| MySQL Community | 8.4.13 |
| Fortify / Sanctum | 1.41.0 / 4.3.3 |
| Bootstrap / Vite | 5.3.8 / 8.3.4 |
| PHPUnit | 12.5.40 |
| Node.js / npm | 24.19.0 / 11.9.0 |

No application dependency versions changed in M2–M6. M6 adds separately pinned development-only QA tools under tools/qa. Lockfiles and checksummed runtime manifests preserve the baseline.

## Existing initialized workspace

From this directory:

```bash
source .runtime/env.sh
python3 tools/with-database.py -- bash tools/verify-m6.sh
```

Do not initialize the databases again. This command applies pending migrations to both databases and runs the complete test suite. The wrapper starts/stops the local database around the command.

All 313 original M1–M5 tests remain unchanged. Most tests use transactions; eight concurrency cases use independent processes, commit isolated test fixtures, and clean only their own records. The PHPUnit bootstrap rejects the development database, the wrong test database user, and cached configuration.

## Fresh extraction: Ubuntu 24.04 AMD64

Prerequisites: Python 3, dpkg-deb, CA certificates, Node.js 22.12+ (24.19.0 verified), npm, and network access to official package sources.

```bash
python3 tools/restore-runtime.py
source .runtime/env.sh
python3 tools/database.py init
python3 tools/database.py stop
python3 tools/configure-environment.py
cd app
composer install --no-interaction --prefer-dist
npm ci
npm run build
cd ..
python3 tools/with-database.py -- bash tools/verify-m6.sh
```

Keep database initialization and stopping in the same terminal execution in command-isolated environments. Initialization refuses to overwrite existing state. Credentials and independent application keys are generated into private local files.

The optional restoration script extracts SHA256-verified official packages locally. It only supports Ubuntu 24.04 AMD64 and is development infrastructure.

On other systems, install PHP 8.3+, Composer 2, Node.js 22.12+, and MySQL 8.4 normally. Provision databases `penguin_academy` and `penguin_academy_test`, with users `penguin_dev` and `penguin_test`, each restricted to its own database. Use InnoDB, utf8mb4, and UTC. Copy `app/.env.example` and `app/.env.testing.example` to their private environment files, configure connection settings, then run inside app/:

```bash
composer install --no-interaction --prefer-dist
php artisan key:generate
php artisan key:generate --env=testing
npm ci
npm run build
php artisan migrate --no-interaction
php artisan migrate --env=testing --no-interaction
php vendor/bin/phpunit --testdox
```

Never use production databases for testing. Clear cached configuration before tests.

## Secure administrator provisioning

There is no seeded administrator, default password, or public administrator registration. From the project directory in an interactive terminal:

```bash
source .runtime/env.sh
python3 tools/with-database.py -- php app/artisan academy:admin
```

Enter the name, email, hidden password, and hidden confirmation. Passwords require at least 12 characters, uppercase, lowercase, and a number, within 72 UTF-8 bytes. The command creates a new administrator and refuses to promote or overwrite an existing account. It hashes the password and never prints it.

Automation may supply `--name`, `--email`, and `--password-stdin`. The password must arrive as one line on standard input from a protected source, not as a command argument. Do not put secrets in source files, logs, or command history.

## Run locally

```bash
source .runtime/env.sh
python3 tools/with-database.py -- php app/artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Open http://127.0.0.1:8000 on that computer. Run one database wrapper/server at a time. The root package.json and preview.py adapt to the managed internal preview; they are not deployment configuration.

## Account flows

- Students register at /register. Privileged fields are rejected; the server always assigns Student.
- All roles sign in at /login. /dashboard redirects to the matching workspace.
- Administrators manage teachers at /admin/teachers: search, create, edit, reset temporary passwords, deactivate, reactivate.
- Teacher-account writes require the administrator's current password. Teachers must replace temporary passwords before workspace access.
- Active users change their own passwords at /account/password. Other sessions and tokens are revoked.
- Sign-out is a CSRF-protected POST.

Administrators share temporary teacher passwords privately; no password email is sent. Email verification, emailed recovery, MFA, and passkeys are not enabled in this release.

## Security and data

Development/test credentials are separate. Development sessions use encrypted MySQL storage. Tests normally use array sessions; a dedicated integration test exercises encrypted MySQL storage. Cookies are HttpOnly and SameSite=Lax. Secure cookies default on in production; local HTTP development permits non-Secure cookies.

Keep APP_DEBUG=false outside controlled debugging. Configure HTTPS before any separately approved production deployment. Production service accounts, backups, monitoring, and deployment infrastructure are outside this local milestone.

Course deletion is soft by default. Hard deletion cascades course-owned records. Referenced teachers/students cannot be hard-deleted. MySQL rejects duplicate enrollments/completions and cross-course completion references. Course, lesson, and enrollment writes now use scoped policies, validation, and database transactions. Completion/undo also recheck eligibility under locks and preserve server-owned course/enrollment/lesson relationships.

## Course, lesson, and enrollment flows

- Admin: /courses provides searchable, paginated management, teacher assignment, publication, and course archive actions. /admin/enrollments provides the academy enrollment overview.
- Teacher: /courses shows assigned courses. Creating a course assigns it to the signed-in teacher. Course edits, publication, lessons, ordering, and rosters are limited to assigned courses.
- Student: /courses shows published courses. Course details support self-enrollment and withdrawal. /my-courses shows own active and withdrawn enrollments, including unavailable archived-course history.
- Lesson readers require an active enrollment and published course/lesson for students. Staff manage lessons from course details.
- Staff enrollment uses active Student accounts only. Assignment requires an active Teacher. All new/re-enrollments require a published course.
- Re-enrollment restores the same enrollment row and preserves learning history. Reordering detects stale changes with HTTP 409; reload the order page before retrying.
- Course archive remains administrator-only. Lesson archive is available to administrators and assigned teachers. There is no restore or permanent-delete interface.

All course-owned writes use transactions and locks. Nested routes reject cross-course lessons/enrollments. Draft and archived content is unavailable to students. Lesson content is escaped plain text; rich text and media are not implemented.

## Learning and progress flows

- Students use Mark complete / Undo completion in the lesson reader. Repeated requests preserve one completion row or no row, respectively. Subsequent page requests calculate progress from persisted MySQL records.
- Progress is completed eligible lessons / eligible lessons × 100, rounded to two decimals. Published, non-archived lessons are eligible. No eligible lessons means 0%, not a completed course.
- Continue learning opens the first incomplete eligible lesson in the current order. Fully completed courses offer review instead.
- Withdrawal preserves history but blocks learning/completion changes. Re-enrollment restores access to the same records. Unpublishing or archiving lessons excludes them from current progress; publishing new lessons can lower progress.
- Student dashboard and My courses show personal progress. Course details show completion indicators and retained progress after withdrawal.
- Admin and assigned teachers open Progress report from a course or dashboard. Lists support active/withdrawn/all filters, student search, pagination, and per-student eligible-lesson details.
- Staff summary cards cover current enrollments in non-archived courses, including draft courses and inactive student accounts with an unwithdrawn enrollment. The separate active-student metric counts distinct active Student accounts. Student dashboards cover own active enrollments in currently published courses only.
- Average progress gives each enrollment equal weight (zero-lesson enrollments contribute 0%). Completion rate is the share of current enrollments with at least one eligible lesson and all eligible lessons completed. These are current curriculum metrics, not certificates or immutable achievement records.

## Versioned REST API

See [app/docs/API.md](app/docs/API.md) for all 24 operations and [app/docs/openapi.json](app/docs/openapi.json) for OpenAPI 3.1.1. Token issuance uses POST /api/v1/tokens with credentials in the JSON body, a finite ability list, and a mandatory expiry (default 60 minutes, maximum 24 hours). API requests require a Bearer header; browser cookies are not accepted. Policies always enforce current role, account state, course ownership and enrollment. Token metadata/revocation is restricted to its owner.

API course/lesson archival, enrollment withdrawal/re-enrollment, completion and progress reuse existing Actions/Services. Paginated catalogs and reports use scoped queries and SQL aggregates. No API writes accept client-controlled roles or completion ownership. All responses are marked no-store, and resources whitelist fields.

## Verification and delivery

Final actual results: **328 PHPUnit tests / 2,216 assertions, zero failures/errors/skips**; **33 real Chromium scenarios**; **78 recorded exact-width checks**; **24 axe scans with zero final violations**; and **24 API operations verified through 126 real HTTP responses** in 15 groups. All 313 baseline tests remain, with 15 targeted regressions added. These evidence types are reported separately, not summed into one test count.

Migrations/status, database integrity/isolation, Composer validation/platform requirements, production asset build and Blade compilation passed. Both dedicated database users were denied reads of the other database. The original development core records were empty and remain unchanged. No default account was left behind; provision your own administrator securely.

For the Linux x64 QA environment, prepare the additional tools and run each suite separately:

```bash
npm ci --prefix tools/qa --ignore-scripts --no-fund --no-audit
node tools/qa/prepare-browser.mjs
python3 tools/with-database.py -- node tools/qa/browser.mjs
python3 tools/with-database.py -- node tools/qa/api-contract.mjs
python3 tools/with-database.py -- node tools/qa/performance.mjs
```

Run only one database wrapper at a time. Browser/HTTP runners read the private test environment but run normal web CSRF/session middleware; all fixtures are disposable, uniquely named and scoped to the test database. The API suite waits for an actual one-minute token to expire. `tools/verify-m6.sh` records the full final checks under evidence/m6/final and establishes a private preservation snapshot only if none exists.

The source archive includes complete application files, tools, historical/final evidence, lockfiles, compiled assets and documentation. It excludes secrets, private database contents, runtime binaries/caches/logs, vendor and node_modules. Separate PNGs in evidence/m6/browser are genuine screenshots of synthetic test records.

M6 corrects API creation serialization, credential byte-limit consistency, response security headers and a small set of accessibility/layout defects. HSTS requires production HTTPS; CSP is enforced except during local Vite hot development. Production operational readiness still requires separately approved TLS/proxy/web-server setup, patched infrastructure, backups, monitoring and recovery decisions. Other browser engines/real devices, formal assistive-technology certification and production-scale capacity remain unverified. The administrator-report workload limits are measured in PERFORMANCE_REPORT.md.

M6 is complete for local source handoff. No production deployment is authorized or claimed.
