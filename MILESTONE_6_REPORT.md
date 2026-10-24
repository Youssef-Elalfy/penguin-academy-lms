# Penguin Academy — Milestone 6 report

Handoff date: 24 October 2026. M6 completes local quality assurance and professional source handoff inside the original Laravel project. No replacement application, development-data reset, deployment, purchase or unrelated product feature was introduced.

## Outcome

- **328 PHPUnit tests passed, 2,216 assertions; 0 failed, 0 errors, 0 skipped.** The 313-test M5 baseline was rerun before changes and all original cases remain in the final suite.
- **33 real Chromium scenarios passed** for Admin/Teacher/Student, with 78 exact viewport checks at 375/768/1440 and 24 axe scans reporting zero final violations.
- **All 24 API operations verified** through 126 real HTTP responses in 15 passing groups. Structural OpenAPI validation has zero errors/warnings and exactly matches implemented routes.
- Migrations, MySQL integrity/isolation/preservation, Composer/platform checks, frontend production build and Blade compilation passed.
- Dependency audits reported no advisories/vulnerabilities in the checked Composer/application npm/QA npm dependency sets.

Detailed evidence types are kept separate in `FINAL_QA_REPORT.md`. Genuine browser screenshots and raw results are under `evidence/m6/browser/`. Production operational prerequisites remain unverified; no unconditional production-readiness claim is made.

## Minimal implemented corrections

| Area | Change | Why / verification |
| --- | --- | --- |
| API course creation | Refresh the persisted course before Resource serialization | External schema validation found null lesson_order_version instead of stored integer 0; two new admin/teacher regressions reproduce and verify the correction |
| Fortify credential validation | Custom Fortify-compatible LoginRequest; consistent 72-byte checks on login/current-password/confirmation | Prevent acceptance of extra bytes beyond bcrypt's password limit while preserving valid credentials |
| Authentication timing | Apply a 200 ms minimum timebox to the custom web callback | Reduce the absent-user early-return difference; not a constant-time guarantee |
| Response hardening | Reuse headers on success/errors, add CSP and conditional production HTTPS HSTS | Verified 200/404/401/419 behavior and production/local configuration regressions |
| Accessible names/contrast | Correct report link name; darken eyebrow and avatar text | Fix actual axe findings and targeted contrast review |
| Heading structure | Authentication form h1 in main; course empty-state h2 | Preserve a meaningful main heading at all tested widths |
| Report tables | Minimum column widths with internal focusable scrolling | Fix narrow word fragments seen in real screenshots; verify keyboard scrolling without document overflow |
| QA tooling/documentation | Secure disposable accounts, local browser setup, OpenAPI-driven HTTP client, performance fixtures and final runbook | Resolve the previous authenticated-browser limitation and make evidence reproducible |

No business-service architecture, database migration, permission policy or application dependency lockfile was replaced. The original M1–M5 reports/evidence remain as history. All original tests/migrations plus application lockfiles (35 protected files) remain byte-identical.

## Actual command evidence

The complete logs are included; selected exact output:

```text
{"tool":"phpunit","result":"passed","tests":328,"passed":328,"assertions":2216,"duration_ms":15400}
```

```text
./composer.json is valid
```

```text
npm warn Unknown env config "http-proxy". This will stop working in the next major version of npm.

> build
> vite build

vite v8.3.4 building client environment for production...
transforming...
✓ 15 modules transformed.
rendering chunks...
computing gzip size...
public/build/manifest.json              0.33 kB │ gzip:  0.16 kB
public/build/assets/app-PN0_4lrT.css  246.80 kB │ gzip: 35.12 kB
public/build/assets/app-CWefeVrW.js    21.81 kB │ gzip:  7.20 kB

✓ built in 174ms
```

Both migration applications reported “Nothing to migrate”; status lists all five migrations as Ran. Blade templates cached successfully and were then cleared. Final integrity probes returned zero orphan, wrong-role, cross-course or duplicate violations. Cross-database reads were rejected for each application's dedicated database account. Core records match their pre-M6 snapshots.

## Browser and API verification

The managed-browser limitation was resolved using an authorized local Playwright/Chromium test environment. Administrator provisioning uses the existing secret-stdin command; subsequent teacher/student flows use actual forms and normal Fortify authentication. CSRF is enabled; a missing-token logout returns 419. No passwords/session credentials are included in evidence.

Browser journeys cover teacher creation/mandatory password change, assignment/reassignment, course/lesson edits/publication/order/archive, catalogs, enrollment/withdrawal/re-enrollment, completion/undo, progress after refresh, reports, cross-role denials, pagination/filtering, keyboard navigation and responsive layouts. Sixteen explicit MySQL assertions confirm writes/history.

The external HTTP client validates JSON using AJV 2020-12 and documented schemas, including safe error responses. It tests all protected operations without authentication and without their required abilities, as well as expired/revoked tokens, current account deactivation and issuance throttling. The expiry check waits for a real one-minute token to expire; no timestamp manipulation is used. Eight simultaneous completion requests yield one stored completion. Full endpoint inventory: `API_ENDPOINT_INVENTORY.md`.

OpenAPI 3.1.1 is structurally validated by Redocly 2.70.0 with telemetry explicitly disabled. The initial telemetry attempt was rejected by automatic approval review; the documented opt-out provided a safer successful execution. JSON-schema validation and route parity are separate additional checks.

## Performance and remaining limits

The benchmark uses 300 synthetic students, 30 courses, 600 lessons, 3,000 enrollments and 28,800 completions. Business query counts remain 3/4/8 for course/enrollment/admin-report responses as page sizes increase. The administrator aggregate is the heaviest measured endpoint: p95 486.29 ms sequential and 1,530.20 ms with four concurrent requests. These local measurements are not a production capacity claim. See `PERFORMANCE_REPORT.md`.

Remaining unverified areas: Safari/Firefox and physical devices; screen-reader certification and quantitative layout-shift metrics; OS vulnerability scanning; real production TLS/proxy configuration, backup restore, monitoring and production load/endurance. MFA, email verification/recovery, full student-account administration and comprehensive audit logging are not implemented. Decide whether those capabilities are mandatory for the intended rollout.

## Handoff contents

1. `Penguin-Academy-FINAL.zip` — complete application source, lockfiles, compiled assets, migrations/factories, original and new tests, QA tooling, documentation and evidence.
2. This report and `FINAL_QA_REPORT.md`.
3. `SECURITY_AUDIT.md`, `BROWSER_TEST_RESULTS.md`, `PERFORMANCE_REPORT.md`, `DEPLOYMENT_GUIDE.md`.
4. `API_ENDPOINT_INVENTORY.md`, `app/docs/API.md`, complete `app/docs/openapi.json`.
5. Final PHPUnit/JUnit and command evidence under `evidence/m6/final/`.
6. A separate archive verification receipt with SHA256, CRC/source checks and excluded-path/secret-scan results.

The archive excludes private environment files, credentials, database contents, runtime binaries/caches/logs, vendor and node_modules. No operational credentials are supplied; provision your own administrator through the existing secure command. The development database remains as it was before M6 (zero core records in the verified baseline). M6 ends with this handoff; deployment requires separate authorization.
