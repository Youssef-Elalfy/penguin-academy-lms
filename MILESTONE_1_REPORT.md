# Penguin Academy — Milestone 1 report

Report date: 21 October 2026. Scope: runtime, dependency compatibility, Laravel setup, and a working MySQL connection. No deployment was performed.

**Result:** the M1 foundation is implemented and verified through real MySQL integration tests and a frontend build. The application is not yet a functional LMS. Browser inspection is incomplete. Work stops at the M1 review gate.

## Environment and compatibility

The environment is Ubuntu 24.04.3 LTS AMD64. Shell execution worked, but PHP, Composer, and MySQL were initially missing globally. Node.js, npm, Git, and Python were available. Package downloads worked.

System package installation failed on required UID/group operations (`setgroups` / `seteuid`); the actual output is in `evidence/apt-update.log`. Official Ubuntu and MySQL packages were instead downloaded, SHA256-verified, and extracted into the dedicated local runtime. PHP is available after `source .runtime/env.sh`; global `php` remains absent in a fresh shell.

| Component | Verified version |
| --- | --- |
| PHP | 8.3.6; Ubuntu revision 8.3.6-0ubuntu0.24.04.11 |
| Composer | 2.10.3 |
| Laravel skeleton / framework | 13.11.0 / 13.38.0 |
| MySQL Community | 8.4.13 |
| Node.js / npm / Git | 24.19.0 / 11.9.0 / 2.51.1 |
| Fortify / Sanctum | 1.41.0 / 4.3.3 |
| Bootstrap / Popper | 5.3.8 / 2.11.8 |
| Vite / Laravel Vite plugin | 8.3.4 / 3.2.0 |
| PHPUnit | 12.5.40 |
| Playwright | 1.64.1 package; browser tests not executed |

Laravel 13's PHP requirement was checked against official release documentation and resolved Composer constraints. Actual platform requirements passed. Stable releases and lockfiles were used.

Primary references consulted:

- https://laravel.com/docs/13.x/releases
- https://laravel.com/docs/13.x/installation
- https://dev.mysql.com/doc/refman/8.4/en/linux-installation-debian.html
- Exact official download URLs/checksums: `evidence/runtime-packages.json` and `evidence/composer-download.json`.

## Implemented

- A dedicated Laravel project with Eloquent connected to MySQL.
- Isolated runtime restoration and private environment provisioning tools.
- Development database `penguin_academy` and test database `penguin_academy_test`, with separate scoped users and random passwords.
- UTC connections, utf8mb4 encoding, and InnoDB tables.
- Standard users/password-reset/session, cache, and queue migrations applied to both databases.
- Independent application keys and private environment files excluded from delivery.
- Blade foundation page with locally bundled Bootstrap/JavaScript and Vite production assets.
- Seven meaningful foundation/integration tests.
- Architecture, initial design-system documentation, and setup instructions.

MySQL uses loopback TCP port 33067 because Unix-domain sockets are prohibited here. The helper disables the unused X listener, local infile, and server file import/export. The command wrapper runs MySQL and its client command in the same execution session to handle network namespace isolation.

## Actual command evidence

Executed from the project directory:

```bash
source .runtime/env.sh
python3 tools/with-database.py -- bash tools/verify-m1.sh
```

Selected actual output from `evidence/m1-verification.log`:

```text
Private MySQL connection verified.
PHP 8.3.6 (cli) (built: Sep  2 2026 12:56:02) (NTS)
Composer version 2.10.3 2026-08-27 13:34:23
Laravel Framework 13.38.0

0001_01_01_000000_create_users_table .. [1] Ran
0001_01_01_000001_create_cache_table .. [1] Ran
0001_01_01_000002_create_jobs_table .. [1] Ran

PHPUnit 12.5.40 by Sebastian Bergmann and contributors.
OK (7 tests, 16 assertions)
```

The full log records successful migrations for both databases. JUnit output reports **7 tests, 16 assertions, 0 failures, 0 errors, and 0 skipped tests**.

| Test | Result |
| --- | --- |
| Foundation page renders and references built assets | Passed |
| Health endpoint responds | Passed |
| MySQL 8.4, test DB identity, UTC, utf8mb4, InnoDB | Passed |
| Record survives database reconnection | Passed |
| Failed transaction rolls back its write | Passed |
| Test account cannot read development data | Passed |
| Duplicate email rejected by database uniqueness | Passed |

HTTP assertions are Laravel feature-test requests, not browser tests. The persistence test checks database reconnection; it does not claim machine restart or disaster-recovery verification.

Actual `evidence/mysql-status.log` output:

```text
version  timezone  charset  engine
8.4.13   +00:00    utf8mb4  InnoDB
Database (penguin_academy%)
penguin_academy
penguin_academy_test
```

Actual `npm run build` output in `evidence/frontend-build.log`:

```text
vite v8.3.4 building client environment for production...
✓ 61 modules transformed.
public/build/manifest.json              0.33 kB │ gzip:  0.16 kB
public/build/assets/app-Z6bcoaqa.css  230.76 kB │ gzip: 31.13 kB
public/build/assets/app-DDatstDC.js    80.12 kB │ gzip: 23.89 kB
✓ built in 223ms
```

Additional completed checks:

- Composer strict validation: `./composer.json is valid`.
- Composer platform check: all requirements succeeded.
- Pint formatting applied; output retained in `evidence/pint.log`.
- Composer audit: no reported advisories or abandoned packages.
- npm audit: zero reported vulnerabilities at all severities.

These audits are point-in-time dependency checks, not a complete application security audit. npm emitted an environment `http-proxy` warning; installation and build succeeded.

## Failures, resolutions, and limitations

| Issue | Resolution / status |
| --- | --- |
| Global package-manager UID/group restrictions | Resolved by verified local package extraction |
| Unix socket restriction | Resolved with loopback TCP and empty socket setting |
| Initial MySQL default file-directory error | Resolved with `secure-file-priv=NULL`; original attempt retained |
| Execution-session network isolation | Resolved with database command wrapper |
| Preview private MySQL library lookup | Resolved by adding MySQL private library directory |
| Browser operation stalled before inspection | Unresolved verification limitation |

The managed preview eventually reported a running server, but the browser operation stalled and was aborted before page inspection. The preview was stopped afterward. No screenshot, visual/mobile pass, accessibility audit, or Playwright run is claimed. There is no deployed service or public URL.

## Outside this milestone

Login, registration, role assignment, domain policies, LMS Form Requests, course/lesson/enrollment tables and workflows, progress, dashboards, and `/api/v1` endpoints remain unimplemented. Fortify and Sanctum installation does not count as implemented authentication or token controls. Fortify discovery is deferred until M2 configuration.

The full requested functional/security test matrix belongs to later milestones. These seven tests cover the foundation only. No production-readiness claim is made.

## Handoff and approval

The source delivery contains application files, lockfiles, verified frontend assets, runtime restoration tools, documentation, and evidence. It excludes secrets, application keys, database contents, runtime binaries, and dependency directories.

The next step after M1 approval is M2: domain schema, Fortify authentication, and server-side role authorization. No M2 implementation or deployment was started.
