# Penguin Academy — Milestone 5 implementation and verification report

**Prepared 23 October 2026 (Africa/Cairo). Scope: M5 only.**

M5 is implemented inside the original Penguin Academy Laravel project. The versioned API, Sanctum token controls, frontend refinements and documentation are complete. **All 313 PHPUnit tests / 2141 assertions passed**, including all **187 unchanged M1–M4 tests**. Authenticated browser workflows and the exact requested mobile/tablet/desktop widths remain unverified for the specific reasons below. This is not a production-deployment or full browser acceptance claim.

## 1. Preserved baseline and environment

The original working directory is `penguin-academy-m1/app`; no replacement Laravel application was created. The initial source comparison matched 179 application files to the approved M4 archive. All 27 protected prior test/fixture/bootstrap-test, migration and lockfile paths remained byte-for-byte unchanged. Historical M1–M4 reports remain included.

| Component | Verified version |
| --- | --- |
| PHP | 8.3.6 |
| Laravel | 13.38.0 |
| Composer | 2.10.3 |
| Sanctum / Fortify | 4.3.3 / 1.41.0 |
| MySQL Community | 8.4.13 |
| PHPUnit | 12.5.40 |
| Bootstrap / Vite | 5.3.8 / 8.3.4 |
| Node / npm | 24.19.0 / 11.9.0 |

No application dependency or lockfile changes were required. The existing private development runtime remains usable. PHP is not installed on the global PATH; activate `source .runtime/env.sh`. The internal preview uses a separate path context and rewrites the private wrappers to `/site`; after stopping it, the existing runtime restoration script restored the host paths. This was resolved without replacing the app or database.

## 2. API implementation

There are **24 implemented HTTP operations** under `/api/v1` (23 Laravel route records because completion PUT/DELETE share a route). All requested operations are present, with additional token, publication, ordering and authorized overview-report operations.

| Resource | Operations |
| --- | --- |
| Tokens | POST /tokens; GET /tokens; DELETE /tokens/current; DELETE /tokens/{token} |
| Courses | GET/POST /courses; GET/PATCH/DELETE /courses/{course}; PUT /courses/{course}/publication |
| Lessons | GET/POST /courses/{course}/lessons; GET/PATCH/DELETE /courses/{course}/lessons/{lesson}; PUT /courses/{course}/lessons/{lesson}/publication; PUT /courses/{course}/lessons/order |
| Enrollments | GET/POST /enrollments; DELETE /enrollments/{enrollment} |
| Learning | GET /enrollments/{enrollment}/progress; PUT/DELETE /enrollments/{enrollment}/lessons/{lesson}/completion |
| Reports | GET /reports/overview |

The complete method/ability/role inventory is in `app/docs/API.md`; machine-readable routes are in `evidence/m5/final/api-routes.json`.

### Shared application behavior

- API course/lesson/enrollment/completion writes invoke the existing **CourseActions, LessonActions, EnrollmentActions and CompletionActions**. These classes were preserved.
- Progress and reporting reuse **ProgressService and AcademyAnalytics**. No separate API progress formula was introduced.
- **CourseCatalog** extracts the existing catalog query and is shared by Blade and API controllers. **EnrollmentDirectory** scopes API enrollment queries before pagination.
- API Form Requests inherit the existing course/lesson, publication, reorder and delete/withdraw validation. PATCH supports partial changes. Unknown fields and protected identifiers are rejected.
- API Resources whitelist response fields. Course teachers expose ID/name only; enrollment student identity is available only inside the authorized enrollment scope; token metadata excludes hashes and plaintext.
- Nested lesson binding rejects mismatched course/lesson IDs. Enrollment-root completion checks the exact enrollment owner and matching course before invoking the shared transaction.
- Course/lesson deletion retains soft archival. Enrollment deletion invokes withdrawal. Duplicate enrollment returns the existing row; re-enrollment preserves the original joined date and completion history.
- Publication remains an explicit action. Teacher creation assigns the teacher to themselves; only administrators may reassign eligible teachers.
- Completion and undo remain idempotent and blocked after withdrawal or for inaccessible/unpublished/archived lessons.
- Existing Blade routes, authentication and role dashboards remain operational under the preserved tests.

### Responses and limits

Resources use `data`; paginated collections add `links` and `meta`. Default page size is 20, maximum 100. Filters are validated and pagination links include only validated parameters.

Status codes: 200 reads/updates/idempotent existing enrollments; 201 new resources/tokens; 204 archive/withdrawal/revocation; 401 invalid authentication; 403 forbidden; 404 missing/archived/mismatched resource; 405 unsupported method; 409 stale lesson order; 422 validation; 429 throttling; 500 sanitized unexpected failure.

API errors use stable `message`/`code` and field `errors` where appropriate. The error handler preserves Retry-After and Allow headers. It does not serialize SQL, exception traces or internal error details even with local debug enabled. Responses are marked `no-store, private`.

## 3. Authentication and authorization verification

Sanctum authenticates bearer tokens only for this API; browser cookies cannot bypass ability checks. The existing Fortify web guard continues to handle Blade sessions.

Token issuance verifies existing credentials, normalizes email, checks active status and mandatory password-change state, validates a role-compatible explicit ability list, and stores only Sanctum's token hash. A user-row transaction lock enforces a maximum of ten active tokens. Default expiry is 60 minutes; maximum is 1440 minutes. A global 24-hour ceiling also applies. Tokens without an expiry are rejected.

The plaintext token is returned once. Metadata listing and revocation are owner-scoped. Revocation immediately blocks subsequent requests. Account/password security workflows continue to revoke tokens as before. Ability/account middleware runs before resource binding; Policies and Actions enforce current role, ownership and enrollment.

| Boundary | Executed evidence |
| --- | --- |
| Real bearer authentication | Actual persisted Sanctum tokens, not Sanctum::actingAs |
| Invalid, expired, revoked, no-expiry and globally expired tokens | 401 assertions |
| Disabled account / mandatory password change | 401 / 403 assertions |
| Current role changes | Old teacher token cannot mutate after role changes to Student |
| Abilities on every protected operation | All 23 protected operations checked for missing authentication and missing ability |
| Teacher reassignment | Previous teacher loses report/enrollment/progress access |
| Student and teacher isolation | Cross-student and cross-course read/write tests |
| Privileged/forged inputs | Role, teacher/student IDs, course/position/publication metadata and unknown fields rejected |
| Historical records | Withdrawal/re-enrollment/archive tests assert database persistence |
| Serialization | Protected account/token fields absent |
| Throttling | Issuance, per-user API limit shared across tokens, and shared academy-write limit tested |

Issuance limits: 5/minute per normalized email+IP and 30/minute per IP. Authenticated API limit: 120/minute per user. Writes additionally share the existing 60/minute academy-write budget. Failed attempts count. Token credentials are rejected in URL parameters.

No real token or password is included in this report, documentation or delivery evidence. Test fixtures use isolated, synthetic credentials solely in the test database. No default development administrator, authentication backdoor or injected session was created.

## 4. Frontend and design system

The existing Bootstrap/Blade visual identity was refined:

- Stronger secondary text, form boundaries and academy-colored links.
- Consistent button targets, wrapping filters/actions/pagination, and resilient long text.
- Focusable, labeled table overflow regions with semantic headers/captions.
- Focusable main landmarks for skip navigation.
- Server validation summaries receive focus; existing field errors and escaped output remain.
- Mobile offcanvas exposes expanded state and retains Bootstrap keyboard/focus handling.
- Lesson reading width is constrained; narrow-screen controls and actions stack/wrap.
- Reduced motion and visible focus are preserved.
- Bootstrap JavaScript now imports only the used Offcanvas component.

`app/DESIGN.md` documents typography, spacing, colors, components, interaction patterns and verification limits. No Taste/Impeccable package was available in the supplied skill catalog; no such tool execution is claimed.

Selected final contrast calculations include: body/canvas **11.59:1**, secondary/white **5.75:1**, white/primary **6.59:1**, form boundary/canvas **3.19:1**, and sidebar focus **7.86:1**. Several additional course-text pairs were darkened after calculation. These calculations are not a full rendered accessibility audit.

Final frontend build, after the last contrast adjustment:

```text
vite v8.3.4 building client environment for production...
✓ 15 modules transformed.
public/build/manifest.json              0.33 kB │ gzip: 0.16 kB
public/build/assets/app-CBS9keng.css   246.71 kB │ gzip: 35.09 kB
public/build/assets/app-CWefeVrW.js     21.81 kB │ gzip: 7.20 kB
✓ built in 165ms
```

The earlier M4 JavaScript bundle was approximately 81 kB; the final M5 bundle is 21.81 kB. Bootstrap CSS remains included. npm emitted an environment warning about the inherited `http-proxy` config; it did not fail the build. Blade compilation passed and compiled views were then cleared.

## 5. Automated test results

Initial baseline:

```text
187 tests passed; 1268 assertions; 0 failures/errors/skips.
```

Final complete run:

```json
{"tool":"phpunit","result":"passed","tests":313,"passed":313,"assertions":2141,"duration_ms":46660}
```

| Group | Tests | Assertions | Failures / errors / skipped |
| --- | ---: | ---: | --- |
| Preserved M1–M4 | 187 | 1268 | 0 / 0 / 0 |
| New M5 | 126 | 873 | 0 / 0 / 0 |
| Total | **313** | **2141** | **0 / 0 / 0** |

| M5 test class | Cases | Assertions |
| --- | ---: | ---: |
| ApiTokenTest | 25 | 214 |
| ApiCourseTest | 16 | 105 |
| ApiLessonTest | 9 | 64 |
| ApiEnrollmentTest | 8 | 64 |
| ApiProgressTest | 11 | 71 |
| ApiReportingTest | 3 | 47 |
| ApiContractTest | 54 | 308 |

Actual JUnit: `evidence/m5/final/phpunit.xml`; console output: `evidence/m5/final/phpunit.log`. A separate full pre-UI run also passed 313/2141. The final run followed PHP formatting and Blade changes; the only later product change was calculated CSS contrast adjustment, followed by a successful Vite rebuild.

The eight existing multi-process concurrency cases remain included, exercising enrollment, lesson positions/reordering, duplicate completion/undo and completion races with withdrawal/archival. New M5 requests use the same transaction/locking behavior; no separate production-load test was performed.

### Failures encountered and resolved

- Initial token tests: two failures, caused by comparing microseconds against second-precision MySQL timestamps and expecting a parent validation key instead of the actual duplicate-array child key. Test expectations were corrected; no expiry or duplicate-ability rule was relaxed.
- Initial course DELETE test: unauthorized payload validation ran before the action's policy. API deletes were connected directly to the existing policy-aware delete/withdraw Form Requests. The corrected authorization test passed.
- Initial write-throttle test: the preceding denied write correctly consumed one shared budget slot. The test was corrected to count that attempt.
- Pint `--dirty` returned: **“The [--dirty] option is only available when using Git.”** This supplied project has no Git metadata. Pint was run successfully against the 52 changed/new PHP files explicitly.
- A runtime command after preview initially returned `php: command not found` because preview had rewritten private wrapper paths. After stopping preview and restoring the existing runtime wrappers, PHP and verification commands ran successfully.
- A large initial OpenAPI file write hit the shell argument-size limit. It was written with the file patch tool and then compacted through reusable response components.

All failures above are resolved. None is counted as a final passing check without rerun evidence.

## 6. Database and migration evidence

The development and test databases remained separate: `penguin_academy` and `penguin_academy_test`, with their dedicated users. Tests refuse to run against the development database or cached configuration.

Both migration commands returned:

```text
INFO Nothing to migrate.
```

All five existing migrations are recorded as Ran in both databases:

```text
0001_01_01_000000_create_users_table                         [1] Ran
0001_01_01_000001_create_cache_table                         [1] Ran
0001_01_01_000002_create_jobs_table                          [1] Ran
2026_10_21_001334_create_academy_schema                       [2] Ran
2026_10_21_133758_add_lesson_soft_deletes_and_order_version   [3] Ran
```

M5 needed **no new migration or index**. Tables, column definitions, indexes, foreign keys and migration history match the before snapshot. Existing InnoDB/utf8mb4 schema and UTC session timezone were verified. All seven integrity checks returned zero violations in both databases: missing course parents, invalid teacher/student roles, cross-course/missing completion parents, duplicate enrollment pairs, duplicate completion pairs and duplicate lesson positions.

The development database contained **zero users, courses, lessons, enrollments and completions before M5**, and still contains zero. The private row comparison confirms all existing core records unchanged. MySQL test fixtures were genuinely created and mutated during tests, then rolled back/cleaned by the existing isolation strategy. No database reset or destructive rebuild occurred. Runtime session/cache activity is separate from academy record preservation.

Evidence: `database-before.json`, `database-after.json`, `database-preservation.json`, `schema-before.json`, `schema-after.json`, and the two final integrity JSON files.

## 7. Performance and OpenAPI verification

Complete bearer-authenticated response measurements, including token lookup/user loading and one last-used write:

| Response | 1 result row | 12 result rows | SELECT statements |
| --- | ---: | ---: | ---: |
| Course catalog | 6 SQL statements | 6 | 5 |
| Enrollment listing with progress | 7 | 7 | 6 |
| Overview report with summary | 11 | 11 | 10 |

Query counts did not grow per returned row. The automated test asserts equal counts and a bound of 12. Fixture creation is excluded from the measurement. No unnecessary index/cache layer was added. These are query-count measurements, not representative production latency or contention benchmarks.

The OpenAPI 3.1.1 specification describes implemented endpoints, abilities/roles, validation, response resources, errors, pagination, filters and synthetic examples. Verification checked **24 operations across 15 path templates**, **205 local references**, no broken references, required path parameters, unique operation IDs, and exact route/ability parity in PHPUnit.

A full external OpenAPI meta-schema validator was not installed and was not run. JSON parsing, references and implementation inventory were verified; interoperability validation with an external OpenAPI client remains useful M6 work.

## 8. Actual browser verification and blockers

Real browser inspection **did execute**, using supported cloud Chrome/Playwright controls against the existing local Laravel preview. No standalone replacement browser runtime was installed.

Completed on 22 October:

- Welcome page and its login/registration navigation.
- Labeled sign-in controls and appropriate autocomplete attributes.
- Guest visits to Admin, Teacher and Student dashboards all redirected to the actual sign-in page.
- Tab traversal through password, remember-device checkbox and Sign in, with a computed 3px focus outline.
- Clicking Sign in with empty inputs showed native “Please fill out this field.” feedback; no credential was entered.
- Registration page labels: Full name, Email address, Password, Confirm password.
- Login/registration document width equaled **1363px**, matching the actual **1363 × 936** viewport.
- A real screenshot was inspected internally; no generated/fabricated screenshot is delivered.

**Authenticated blocker:** no development user accounts exist. The advertised browserAuth workflow requires existing user-supplied credentials, prohibits account creation through browserAuth and prohibits lower-level credential entry. No secure creation/sign-in handoff was completed. A browserAuth credential request was not issued because there was no existing development account to authenticate. This is an unmet authentication prerequisite, not a failed application login or a browserAuth “unavailable” response.

**Responsive blocker:** the exposed browser API has no viewport/device resizing method and advertises no browser capabilities. Actual **375px, 768px and 1440px checks were not executed**. CSS breakpoints/wrapping were reviewed and refined, but that is not proof of browser layout correctness at those widths.

Consequently, browser login/logout, role redirects after successful login, authenticated dashboards, course/lesson writes, enrollment/withdrawal, completion/undo, refresh persistence, staff reports and authenticated mobile/keyboard flows remain unverified. Their Laravel HTTP/database tests passed; they are explicitly separate evidence.

The available console buffer contained earlier Chrome-extension metadata errors, not application-origin errors. No complete authenticated console-cleanliness claim is made. Public browser inspection preceded the final small contrast-only CSS adjustment on 23 October.

See `evidence/m5/browser-verification.json`. The internal preview was stopped afterward. No password was exposed, no session injected and no authentication bypass introduced.

## 9. Delivery and reproducibility

Deliverables:

- `Penguin-Academy-M5.zip`: complete updated source, migrations/factories, tests, lockfiles, built frontend assets, setup tools, historical reports/evidence and M5 documentation/evidence.
- `MILESTONE_5_REPORT.md`: this report.
- `app/docs/openapi.json` and `app/docs/API.md`.
- `evidence/m5/final/phpunit.xml`: actual JUnit.
- Route, build, migration, integrity, query-count, contrast and browser evidence.

The archive excludes private environments, application keys/passwords, private database contents, vendor, node_modules, runtime binaries/caches, application logs and private helper output. Final ZIP CRC, entry/source-byte comparison, secret-value screening and SHA-256 are recorded in the delivery verification receipt created from the final archive bytes.

Existing initialized workspace verification:

```bash
source .runtime/env.sh
python3 tools/with-database.py -- bash tools/verify-m5.sh
```

Run only one database wrapper/preview at a time. Fresh-extraction setup and secure interactive administrator provisioning are documented in README.md. Do not reinitialize an existing database.

## 10. Remaining M6 work and limits

1. Provision deliberate non-production role accounts through the supported flows, complete secure user authentication handoff, and execute the outstanding browser scenarios.
2. Verify 375/768/1440 layouts in a browser with supported viewport controls, including offcanvas navigation, tables, text overflow, keyboard interaction and reorder controls.
3. Run broader accessibility/browser compatibility checks and representative cohort/load/concurrency profiling.
4. Validate OpenAPI against a full external validator/client and perform further production-oriented security review.
5. Prepare separately authorized production TLS/proxy, backup/recovery, secrets rotation, monitoring and expired-token maintenance. No deployment is included.
6. Email verification/recovery, MFA/passkeys, rich-text/media, certificates and other unrelated product features remain outside this implementation.

**M5 stops here. No M6 work, deployment or paid resource activation is authorized by this delivery.**
