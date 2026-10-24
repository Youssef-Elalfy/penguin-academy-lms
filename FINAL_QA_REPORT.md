# Penguin Academy — final QA report

Local M6 acceptance is complete within the scope below. No deployment took place. Production operational prerequisites remain unverified and are listed separately rather than folded into passing test totals.

## Evidence types and exact final results

| Evidence type | Result | Evidence |
| --- | --- | --- |
| Baseline PHPUnit/MySQL | 313 passed / 2,141 assertions; 0 failures/errors/skips | `evidence/m6/phpunit-baseline.xml` |
| Final PHPUnit/MySQL | 328 passed / 2,216 assertions; 0 failures/errors/skips; 15.352228 s | `evidence/m6/final/phpunit.xml`, `phpunit.log` |
| Retained/new cases | All 313 baseline cases retained; 15 new; none removed | `evidence/m6/test-retention.json` |
| Real Chromium scenarios | 33 passed / 0 failed | `evidence/m6/browser/results.json` |
| Exact-width checks | 78 passed, including pagination revisits; widths 375/768/1440 | Browser layout records |
| Automated accessibility | 24 scans / 0 final violations; incomplete/manual-review entries preserved | Browser accessibility records |
| Independent real HTTP contract checks | 15 groups passed / 0 failed; 126 responses; 24/24 successful operations | `evidence/m6/api-contract.json` |
| OpenAPI structural validation | Redocly 2.70.0: 0 errors / 0 warnings / 0 ignored | `evidence/m6/openapi-final.json` |
| Route/spec parity | Exact match of 24 operations | `evidence/m6/route-contract-parity.json` |
| Performance HTTP workload | 120 measured requests + 3 warmups, all 200; 9 separate PHP-kernel measurements | `evidence/m6/performance.json` |
| Database integrity | Zero violations in each final database and in populated performance fixtures | Final integrity JSON and `performance-database-integrity.json` |
| Database isolation | Both database users denied reads of the other environment | Final preservation JSON |
| Migrations | All 5 existing migrations applied in both databases; no new M6 migrations | Final migrate/status logs |
| Build / Composer / Blade | All commands exit 0 | `evidence/m6/final/` |
| Dependency advisory checks | Composer, app npm and QA npm: 0 reported vulnerabilities/advisories | Audit JSON files |
| Protected source | 35 original test/migration/lockfiles unchanged | `protected-source-final.json` |

These are not a combined “total tests” figure. Browser scenarios, HTTP-client cases, PHPUnit tests, schema checks and source review are distinct methods.

## Regression coverage

The complete suite retains authentication, registration/logout, role permissions, course ownership and CRUD, lesson CRUD/order/publication, enrollment/withdrawal/idempotency, completion/undo/curriculum changes, report calculations, token lifecycle/abilities/expiration/throttling, API CRUD/pagination/serialization, cross-course/student denial, database constraints and eight multi-process concurrency cases.

M6 adds 13 security regressions and 2 API serialization regressions. Before fixes, the new security set recorded 10 failures out of 12; the API creation regression recorded 2 failures out of 2. The final suite includes these corrected rules plus the added positive confirmation test, with zero failures. No existing test was weakened or removed.

Independent HTTP acceptance checks real response schemas using AJV draft-2020-12 plus formats. The client takes paths/methods/operation IDs from OpenAPI rather than substituting Laravel's test kernel. It verifies 23 missing-authentication and 23 missing-ability responses, cross-role/ownership rules, duplicate enrollment/completion, eight simultaneous completion requests, actual token expiration after one minute, revocation, account deactivation and issuance throttling. See `API_ENDPOINT_INVENTORY.md` for observed statuses.

## Preservation and migrations

The original project directory is preserved. Development uses `penguin_academy` with `penguin_dev`; tests use `penguin_academy_test` with `penguin_test`. Real permission probes verify cross-database reads are denied. The five core development tables were empty at the verified M6 baseline and remain empty; all original row snapshots match. The isolated test core tables are empty after scoped cleanup. No development reset, table rebuild or sample production account was introduced.

Five existing migrations remain byte-identical to M5 and report Ran in both databases. No M6 schema change was needed. Foreign keys, composite uniqueness, soft deletion and history semantics were checked under populated fixtures and by existing tests, not just empty-table counts.

## Acceptance decision

| Criterion | Status | Scope / limit |
| --- | --- | --- |
| Required automated tests pass | PASS | 328 PHPUnit tests, plus independent browser/HTTP checks |
| Critical/high application findings resolved | PASS within reviewed scope | None identified; lower-severity findings corrected/mitigated, residual timing limits disclosed |
| Database integrity and preservation | PASS | Both existing local databases and populated test fixtures |
| API contract verification | PASS | Complete compatible validator, 24-route parity, real successful/denied HTTP responses |
| Authenticated role journeys | PASS | Normal Fortify/administrator flows in real Chromium |
| Mobile/tablet/desktop layout checks | PASS | Exact 375/768/1440 widths; contained table scroll |
| Limitations and future prerequisites documented | PASS | Security, browser, performance and deployment reports |
| Actual production infrastructure readiness | UNVERIFIED / NOT AUTHORIZED | TLS/proxy, OS hardening, backups, monitoring, operational recovery and production load |

The project is ready for source handoff and controlled staging evaluation. It is **not declared unconditionally production-ready**. A separately approved deployment must satisfy the operational prerequisites and any organization-specific identity, recovery and compliance requirements.

## Reproduction and failed-check history

`tools/verify-m6.sh` runs final migrations/status, full PHPUnit with JUnit, Composer validation/platform requirements, Vite build, Blade compilation/clear, route inventory and database integrity/preservation. Use it through the existing database wrapper. Separate browser/API/performance runners live in `tools/qa/` and use only disposable test records.

Historical failures and environment issues are retained: initial browser archive/inflater errors, teacher-redirect/keyboard timing harness assertions, before-fix accessibility/security/API findings, the benchmark's overly broad initial SQL-count comparison, and Pint's Git-only `--dirty` option. Formatting succeeded using explicit changed PHP paths because this source handoff is not a Git checkout. npm emits a non-blocking managed-environment `http-proxy` configuration warning; the build succeeds. The package guard initially matched its own private-key marker literal; the guard was corrected and the final archive scan passes without excluding the guard source.

The initial Redocly run attempted external telemetry and automatic approval review rejected that action. The verified rerun uses documented `REDOCLY_TELEMETRY=off`; validation succeeds locally without requiring telemetry. No unsafe fallback or approval bypass was used.

Final required local checks have zero failed or skipped cases and no unresolved local execution blocker. Optional/unavailable coverage remains explicitly unverified: other browser engines/real devices, assistive-technology certification, quantitative Core Web Vitals, OS vulnerability scanning, production backup restoration/TLS and production-scale endurance/load testing.
