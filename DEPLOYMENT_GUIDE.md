# Penguin Academy — deployment preparation and operations

This is a future deployment runbook, not evidence that a production deployment occurred. No hosting, domain, certificate, external mail account, production database or paid service was configured during M6. Complete the environment-specific prerequisites and obtain separate deployment approval before using production commands.

## Architecture and supported installation

The application lives in `app/`. Laravel 13 serves Blade/Bootstrap pages using Fortify web sessions and `/api/v1` using Sanctum bearer tokens. Both controller families call the same Academy Actions, policies, query objects and progress service. MySQL is the persistent system of record. Assets are built locally by Vite; runtime Node is not required to serve built pages. See `ARCHITECTURE.md`, `app/DESIGN.md`, and `app/docs/API.md`.

Verified versions are PHP 8.3.6 (Ubuntu package revision recorded in the runtime manifest), Laravel 13.38.0, MySQL Community 8.4.13, Composer 2.10.3, Node 24.19.0, npm 11.9.0, Bootstrap 5.3.8 and PHPUnit 12.5.40. For deployment use maintained, patched PHP 8.3+ and MySQL 8.4 installations; a framework/package audit does not establish OS patch completeness. `composer check-platform-reqs` is the authoritative extension check. Required extensions include PDO MySQL, mbstring, XML/DOM, ctype, fileinfo, curl and OpenSSL; the development runtime also supplies intl, zip and bcmath.

Install from the reviewed lockfiles, not `composer update` or an unlocked npm install. Keep a release identifier and checksums. The ZIP contains source, tests, migrations, lockfiles, documentation and built assets. It intentionally excludes credentials, database contents, dependencies and runtime binaries.

For a fresh local environment, use the complete instructions in `README.md`. The optional `tools/restore-runtime.py` restores checksummed Ubuntu 24.04 AMD64 development tools. It is not production server configuration. Never run `tools/database.py init` on an existing database; it is only for a new local installation and refuses existing state.

For production packaging after separate approval, from `app/`:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer check-platform-reqs --no-dev
npm ci
npm run build
```

These are documented future commands, not a claim they were executed against production. Run tests and browser acceptance in a separate staging/test environment before promoting the release. Production should not install the isolated `tools/qa` browser dependencies.

## Environment and access prerequisites

Use a secret manager or a private environment file outside the publicly served document root. Do not put values in source control, tickets, screenshots or shell history. Restrict filesystem permissions and separate the deploy user from the web worker where possible.

| Setting | Required decision / value |
| --- | --- |
| APP_ENV | `production` |
| APP_DEBUG | `false` |
| APP_URL | The separately approved HTTPS origin |
| APP_KEY | A new unique key for a new installation; preserve the existing key on upgrades |
| DB_CONNECTION | `mysql` |
| DB_HOST / DB_PORT | Private database endpoint; firewall/restrict access |
| DB_DATABASE / DB_USERNAME / DB_PASSWORD | Dedicated production database and least-privileged runtime principal |
| DB_CHARSET / DB_COLLATION | `utf8mb4` / `utf8mb4_unicode_ci` |
| SESSION_DRIVER / SESSION_ENCRYPT | `database` / `true` |
| SESSION_SECURE_COOKIE / SESSION_HTTP_ONLY | Explicitly `true` / `true` |
| SESSION_SAME_SITE | `lax`; reassess only for a separately approved cross-site use case |
| SESSION_DOMAIN | Host-only by default; no broad parent-domain cookie without a reason |
| SESSION_LIFETIME | 120 minutes by default; choose the organization's policy |
| BCRYPT_ROUNDS | 12 by default; benchmark on the actual server, never copy QA's value 4 |
| CACHE_STORE | `database` is the verified default |
| CACHE_PREFIX / SESSION_COOKIE | Unique per environment, particularly on shared infrastructure |
| QUEUE_CONNECTION | `database`; current LMS workflows are synchronous |
| LOG_LEVEL | Prefer `info` or appropriate operational severity, with rotation and restricted retention |
| MAIL_MAILER | `log` is the current local default, not a production email delivery service |

Separate development, test, staging and production databases, application keys and credentials. Automated tests must use `penguin_academy_test` with `penguin_test`; the test bootstrap rejects other database names/users and cached configuration. Browser/HTTP QA runners load private test connection settings but use normal web middleware with `APP_ENV=local`, so CSRF protection remains enabled. These runners are local QA utilities, not production entry points.

Keep UTC for PHP/application/database timestamps. Use InnoDB and utf8mb4 for all tables. Use a privileged migration principal only for approved schema changes; constrain normal application privileges. The local restoration service users are development conveniences, not a production privilege design.

## Web server, TLS and headers

Serve only `app/public/`. Never expose the repository root, `.env*`, `.runtime`, `storage`, `tools`, `vendor`, tests or QA evidence through the web server. Reject unexpected Host values and disable directory listing. Route requests to `public/index.php`; allow PHP execution only for the intended front controller. Keep writable access limited to `storage/` and `bootstrap/cache/`.

Use an approved, supported Nginx/Apache + PHP-FPM setup with TLS, HTTP-to-HTTPS redirects, appropriate body/time limits and updated OS packages. Do not use `php artisan serve` in production. Configure trusted reverse proxies explicitly for the actual topology so client IPs, rate limiting, secure URLs and HTTPS detection cannot be spoofed. Do not trust arbitrary forwarded headers. Test the origin and proxy paths before launch.

The application emits no-store, nosniff, DENY, Referrer-Policy and Permissions-Policy. Its CSP restricts scripts/connections/assets to the same origin, blocks objects and framing, and restricts form destinations. Inline CSS remains permitted for Bootstrap styling; inline scripts are not permitted. Local Vite hot development omits CSP while a hot file is present; production never takes this local-only exception. Ensure `public/hot` is absent from a production release.

HSTS (`max-age=31536000`) is emitted only for HTTPS requests in the production environment. TLS termination must be recognized correctly or HSTS must be configured at the trusted web server. No includeSubDomains/preload claim is made. The local browser suite used HTTP and did not validate a real production TLS/proxy setup.

## First installation and migration procedure

1. Verify the target environment and database identity privately. Obtain a backup or confirm this is a genuinely new empty database.
2. Install the reviewed release and its dependencies. Configure environment values and filesystem permissions.
3. Generate an application key only for a new installation with no existing encrypted data: `php artisan key:generate`. Never rotate an existing key as a routine deployment step.
4. Inspect pending migrations with `php artisan migrate:status` and review their compatibility with the running release.
5. Under the approved change window, run `php artisan migrate --force`. Do not run `migrate:fresh`, `migrate:refresh`, `db:wipe`, seeders with privileged users, or unreviewed down migrations.
6. Build/cache the release: `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`. Confirm route caching is supported in the intended release; M6 explicitly verifies routes and view compilation but does not stand up a cached production runtime.
7. Reload PHP-FPM/opcache using the operator's approved service process. Restart queue workers if workers are used.
8. Run approved staging/production smoke checks: health, login/logout, each role, a safe synthetic learning journey, API authorization, logs and database integrity. Confirm the deployed asset manifest matches the release.

M6 makes no schema changes; the five existing migrations remain intact. Course/lesson archival and enrollment withdrawal preserve historical records. Direct physical deletion follows existing foreign-key cascades and must never be used as an archival substitute.

## Administrator and teacher provisioning

There are no default administrator credentials or public privilege-granting routes. From the protected application console:

```bash
php artisan academy:admin
```

The command prompts for name/email and hidden password/confirmation, creates a new Admin, and refuses to overwrite or promote an existing account. Use at least 12 characters with uppercase, lowercase and a number, within 72 UTF-8 bytes. For automation, `--password-stdin` reads a secret from protected standard input; never pass a password as an argument or paste it into a logged command.

An administrator signs in through Fortify, creates teachers through the protected Teachers page and confirms their current password for writes. Deliver temporary passwords through an approved private channel. Teachers must change the temporary password before workspace/API access. Do not use test factories, database session injection or public utility scripts to provision production administrators.

Students self-register and receive Student role only. Account password changes revoke other sessions/tokens. Teacher deactivation revokes that teacher's sessions/tokens. Public password reset email, verified email, MFA/passkeys and student-account administration are not implemented; establish an explicitly approved support/recovery procedure before opening a public service. Administrator recovery requires an authorized operator using supported privileged provisioning and a separately reviewed account-recovery process, not a temporary login bypass.

## Sessions, caches, tokens and jobs

Encrypted database sessions and database-backed rate-limit/cache storage are the verified defaults. Budget for cache/session table traffic. Current LMS operations do not dispatch background jobs; the standard queue tables exist. If future jobs are introduced, configure supervised workers, retry/dead-letter handling and alerts before relying on them. No production queue service was configured in M6.

Sanctum tokens are hashed in MySQL, issued with explicit abilities and expiry (default 60 minutes, maximum 1,440), and capped at ten active tokens per account. Current role/account/ownership checks apply to every request. Revocation endpoints are owner-scoped. Schedule the official `sanctum:prune-expired` maintenance command through the future environment's approved scheduler if needed; expiry enforcement does not depend on pruning. No scheduled task was installed during M6.

Do not log Authorization headers, passwords, session payloads or token-issuance bodies. Never put bearer tokens in URLs. Use authenticated TLS clients and private token storage. OpenAPI examples contain placeholders only.

## Backups and restore

Define required recovery-point/recovery-time objectives before deployment. Back up the database, necessary uploaded/application storage if introduced, and the corresponding encrypted secret/key configuration separately. Encrypt backups, restrict access, use retention rules and keep an off-host copy on an approved service. Do not store private backups in the source archive.

For InnoDB logical backups, an operator may use `mysqldump --single-transaction --quick` with a protected `--defaults-extra-file` and a correctly scoped backup user. Do not place database passwords on the command line. Additional flags for GTID, routines/events, tablespaces and binary-log coordinates depend on the actual server privileges/topology. Avoid concurrent DDL during the dump. A dump command alone is not proof of a recoverable backup.

Periodically restore into a separate isolated recovery database, validate counts/foreign keys, check encrypted-data readability with the matching key, and exercise authentication/learning workflows. Never restore over the active database as a routine test. Production backup/restore drills were not executed here; there is no approved production environment.

## Secret rotation and incident handling

- Database credentials: provision the replacement credential securely, apply least privilege, switch configuration, rebuild config cache, verify connections, then revoke the previous credential. Do not print either value.
- API tokens: issue a narrowly scoped replacement, update the authorized client privately, revoke the old token, and verify 401 rejection of the old token. Revoke sessions/tokens promptly for compromised/deactivated accounts.
- Application key: plan separately because sessions and any encrypted data depend on it. Laravel supports previous decryption keys through `APP_PREVIOUS_KEYS`; retain old keys only for the approved transition period, then retire them after re-encryption/session invalidation and recovery verification. Generating a new key without a plan can make encrypted data unreadable.
- User passwords: use supported account change/reset workflows, revoke old sessions/tokens, and verify current-role authorization. Never install an authentication bypass for recovery.

Keep an incident owner, audit timeline and containment procedure. The application does not implement a full tamper-resistant activity/audit-log subsystem; define that requirement before a compliance-sensitive rollout.

## Monitoring, errors and maintenance

Monitor HTTP error rates/latency, login/token throttling, database connections and slow queries, disk usage, backup success, session/cache growth, expired-token cleanup, and PHP worker health. `/up` checks Laravel bootstrap; it is not an end-to-end database or learning readiness check. Add external, non-destructive authenticated smoke checks only through an approved monitoring process.

Use structured logs and correlation IDs at the trusted infrastructure layer if required. Redact sensitive request fields before aggregation. Keep `APP_DEBUG=false`; API errors are normalized without SQL/stack disclosure. Set operational log rotation/retention and alert ownership. Do not expose diagnostics or exception pages publicly.

Dependency maintenance requires reviewing updated lockfiles, audits, full regression tests, API schema checks and browser acceptance before release. OS/PHP/MySQL patching and real multi-device/cross-browser validation remain operational responsibilities. See `PERFORMANCE_REPORT.md` for measured local workload limits; no production capacity guarantee is implied.

## Rollback

Retain the prior immutable release, its built assets and a recoverable database/key backup. Prefer backward-compatible additive migrations and rolling back application code while preserving compatible data. After switching release directories, reload PHP/opcache and verify health, login, APIs and stored learning records. Do not automatically run `migrate:rollback` on production data. If schema/data restoration is unavoidable, stop writes, obtain explicit operational approval, restore to a reviewed recovery point and reconcile writes that occurred afterward. Document downtime and lost-write risk in the change plan.

## References

- Laravel deployment: https://laravel.com/docs/13.x/deployment
- Laravel encryption/key rotation: https://laravel.com/docs/13.x/encryption
- Laravel Sanctum: https://laravel.com/docs/13.x/sanctum
- MySQL 8.4 mysqldump: https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html
