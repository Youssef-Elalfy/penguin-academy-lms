# Penguin Academy — M6 security audit

Scope: the original local Laravel application, its web/API routes, source, dependency lockfiles and isolated MySQL test data. No external targets or production systems were tested. This is a scoped engineering review and verification record, not a claim that all possible vulnerabilities have been excluded.

## Findings, correction and evidence

| ID | Severity | Finding and reproduction | Correction | Retest evidence |
| --- | --- | --- | --- | --- |
| SEC-01 | Low | A valid 72-byte bcrypt password followed by extra bytes was accepted by web login, current-password checks and Fortify confirmation. It requires knowing the valid 72-byte prefix; this is not an authentication bypass without that secret. | Bind a Fortify-compatible LoginRequest using the existing byte-limit validator; apply the same limit to current-password fields and confirmation callback. Exact valid passwords still work. | `security-regressions-before.log` reproduces acceptance; `SecurityAuditTest` checks exact/suffixed/multibyte login, account password change, privileged teacher creation and confirmation. Final JUnit includes the corrected cases. |
| SEC-02 | Low, defense in depth | Normal responses lacked CSP, and exception responses did not consistently retain the existing security-header set. Requests to `/login`, a missing page, and unauthenticated `/api/v1/courses` reproduce the missing headers. | Centralize `SecurityHeaders::apply`; apply it to normal and exception responses. Restrict script/form/frame/object origins. | Three response-type regressions plus authenticated browser flows with the CSP active. Real HTTP missing-CSRF request returns 419 with CSP. |
| SEC-03 | Low, configuration hardening | No application HSTS header on a simulated production HTTPS response. | Set one-year HSTS only when environment is production and the request is HTTPS. Leave local HTTP unchanged. | Production-HTTPS and local-HTTP regression tests. Actual production TLS/proxy behavior remains unverified and is a deployment prerequisite. |
| SEC-04 | Low, timing hardening | The custom Fortify callback returned immediately for an absent account, without the minimum timebox already used by API issuance. | Wrap custom web authentication in a 200 ms minimum timebox. | Regression confirms a delay is applied for an absent account; retained authentication tests cover accepted/rejected credentials. This is a floor, not a constant-time proof; production hash cost and server load can exceed it. |

Initial new security regressions: 12 tests, 2 passed and 10 failed, establishing the defects before correction. The first corrected run, including affected authentication/teacher tests, passed 40 tests / 238 assertions. A positive exact-password Fortify-confirmation case was then added and is included in the final complete suite. Original tests were not changed to make results pass.

A separate functional defect found by the external API client is documented in `MILESTONE_6_REPORT.md`: a newly created course serialized its database-default lesson-order version as null. Refreshing the saved model before serialization corrects the contract without changing permission or persistence semantics.

No critical or high-severity application finding was identified in this review. This statement is limited to the reviewed source and executed cases, and does not certify an unconfigured production environment.

## Authentication, sessions and credentials

- Registration uses the existing Fortify action, rejects role/identity escalation fields and always creates an active Student. Password hashing uses the configured Laravel hasher; plain passwords are not serialized.
- Login/session regeneration, logout invalidation, remember-token handling, inactive-account rejection, temporary-teacher-password gating and password-change revocation are covered by retained feature tests.
- Browser QA uses randomly generated disposable credentials in process memory. Admin provisioning uses the existing protected console command with secret standard input; teachers are created through the administrator form; students register through Fortify. The browser fills or types into normal login forms. No privileged session injection, authentication bypass, hardcoded admin token or temporary public route was introduced.
- Final browser/HTTP QA uses the dedicated test database with normal web CSRF/session middleware enabled. Missing-CSRF logout returns 419 and the user remains signed in. The actual session cookie is HttpOnly and SameSite=Lax. Sessions persist encrypted in MySQL. Local HTTP uses a non-Secure cookie; HTTPS/Secure-cookie behavior on production infrastructure is not represented as browser-tested.
- No passwords, bearer values, session cookies, private environment files or private database contents are included in the handoff. Source tests contain non-production fixture inputs only; they do not create a default operational account.

## Authorization, input and persistence

| Reviewed area | Source and verification |
| --- | --- |
| Role/ownership policies | Course, Lesson, Enrollment, LessonCompletion, User and token policies; existing authorization/IDOR regression classes; actual browser cross-role/cross-teacher denials |
| Current API state | `auth:sanctum`, `EnsureApiAccount`, required abilities and policies; independent HTTP tests reject disabled accounts and missing abilities on all 23 protected operations |
| Teacher/student eligibility | Form Requests and shared transactional participant checks validate active Teacher assignments and active Student enrollments; forged IDs rejected |
| Cross-course/student isolation | Scoped nested bindings and policy checks; cross-course lesson 404, foreign student progress/withdrawal/completion 403; current-user identity supplied by auth |
| Mass assignment | Restricted fillable fields, allowlisted request fields and resource fields; privileged user/role/owner inputs prohibited |
| SQL injection | Eloquent/query-builder bindings; reviewed aggregate SQL contains application-owned expressions; sort/filter options are constrained, not interpolated request SQL |
| XSS | Escaped Blade output and plain-text lesson rendering; real browser displays a script-shaped lesson payload as text without execution |
| CSRF | Normal web middleware and CSRF-bearing forms; no exemption added for QA; actual 419 rejection verified |
| Transactions/concurrency | Existing course/enrollment/lesson locks, retry transactions and uniqueness/composite foreign keys; PHPUnit multi-process race tests and eight concurrent HTTP completion requests |
| History | Course/lesson soft deletion and enrollment withdrawal preserve completion records; re-enrollment reuses the same enrollment; actual browser and HTTP persistence assertions |
| Error disclosure | API exceptions use consistent safe JSON and proper status codes; retained tests reject trace/SQL disclosure; APP_DEBUG=false for real HTTP/browser tests |
| Headers | CSP, nosniff, DENY, no-store, referrer and permissions policies on success/error responses; HSTS conditional on production HTTPS |

No raw lesson HTML, file upload, rich-text editor or dynamic user-provided SQL feature is present. This reduces the reviewed attack surface but does not remove the need to validate future features.

## API token controls

Sanctum stores token hashes, not plaintext tokens. Issuance requires valid credentials, an active account, no pending mandatory password change, a finite role-allowed ability list and bounded expiry. Wildcards cannot be requested. The maximum is ten active tokens per account. Metadata and revocation are owner-scoped; API cookies are not used as bearer authentication.

The independent HTTP client verifies all 24 documented operations, all 23 unauthenticated rejections, all 23 missing-ability rejections, current account deactivation, owned/current token revocation, invalid credentials, forged identifiers, duplicates and schema-safe responses. A real one-minute student token is used normally, allowed to expire in wall-clock time, then rejected with 401. No token timestamp or session was injected to obtain that result. Five invalid issuance attempts return 401 and the sixth returns 429. Exact totals and status evidence are in `evidence/m6/api-contract.json`.

Existing PHPUnit coverage additionally verifies expiry bounds, expired tokens, current-role enforcement despite privileged abilities, token limits, throttling and private response-field protection. Token abilities are a necessary condition and do not replace policies.

## Dependency and secret checks

- `composer audit --locked --format=json`: zero reported advisories and no abandoned packages in the recorded result.
- Application `npm audit --json`: zero reported vulnerabilities.
- Isolated QA-tooling `npm audit --json`: zero reported vulnerabilities after pinned tooling installation.
- Application Composer/npm lockfiles and all original M5 tests/migrations are unchanged; 35 protected files are checked against the pre-M6 hashes.
- Final archive validation checks CRC integrity, source-byte equality, forbidden runtime/dependency/environment paths and occurrences of the actual configured application/database secrets. Tokens remain in test-process memory and are never written as evidence. Runtime server logs and database files are excluded.

These package audits reflect the advisory databases available at execution time. They are not an OS/container image scan or proof that every bundled browser/system library is vulnerability-free. The extracted Ubuntu PHP package revision is recorded in the manifest; use current vendor-supported patch builds for any eventual server.

## Remaining limits and production prerequisites

- Production TLS, trusted proxies/hosts, web-server isolation, secret-manager setup, OS hardening, monitoring, backup restoration and operational recovery are **UNVERIFIED here** because no production environment was authorized.
- MFA/passkeys, email verification, emailed account recovery, full student-account administration and a tamper-resistant activity audit log are not implemented. Decide whether these are required for the intended public rollout before launch.
- CSP permits inline styling for Bootstrap; it does not permit inline script execution. Local Vite hot development deliberately omits CSP while its hot file is present. Remove hot files from deployment; the production environment does not take that exception.
- The login timebox reduces a trivial early-return difference. Rate limits remain essential; timing behavior under actual production hash costs/load is not proven constant.
- Chromium desktop/headless evidence does not cover Safari/Firefox, real mobile devices or assistive-technology certification. See the browser report.
- The administrator aggregate report reached p95 1.53 seconds with four concurrent requests in the measured fixture. This is a performance planning issue, not evidence of production capacity.

`DEPLOYMENT_GUIDE.md` describes the prerequisite decisions and future procedures. M6 does not authorize deployment or claim unconditional production readiness.
