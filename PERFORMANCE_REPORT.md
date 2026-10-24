# Penguin Academy — performance validation

Measured local results, not a production scalability claim. Evidence: `evidence/m6/performance.json`, `performance-run-2.log` and `performance-database-integrity.json`. Recorded execution: 2026-10-23T19:24:03.274Z to 2026-10-23T19:24:28.564Z (runtime UTC timestamps).

## Workload and method

The isolated test database contained 1 synthetic administrator, 5 teachers, 300 students, 30 published courses, 600 published lessons, 3,000 enrollments and 28,800 completion records. Each course had 100 enrollments and 20 lessons; learner completion varied from zero to twenty lessons. Fixtures were committed to MySQL, then removed only by their unique QA namespace. Existing development data was not used or changed. Integrity checks found zero violations while the fixture data was present.

Environment: Ubuntu 24.04 development runtime, Linux 6.18.55, AMD EPYC 9V74 80-Core Processor, 9 reported logical CPUs and 10451439616 reported memory bytes. Runtime resources are shared/managed and may vary. PHP 8.3.6, MySQL 8.4.13, Laravel 13.38.0; built-in local PHP server with `PHP_CLI_SERVER_WORKERS=4`, no production PHP-FPM/opcache deployment. Disposable fixture hashing used cost 4; authentication throughput at production cost 12 was not benchmarked.

Query/memory measurements used a fresh PHP process and a real Laravel HTTP kernel per representative bearer-authenticated request, connected to MySQL. These are **in-process HTTP measurements**, not network latency. SQL templates and EXPLAIN plans are retained without bindings or bearer values. Peak PHP allocator memory includes framework bootstrap; it excludes MySQL, browser and whole-server resident memory.

Latency measurements used **real loopback HTTP**: one warmup followed by 20 sequential requests and five batches of four simultaneous requests for each of three role/endpoint pairs. All 120 measured requests and three warmups returned 200; zero workload failures. Tokens were issued normally through the application. The samples are small; p95 is nearest-rank, not a capacity guarantee.

## Query count, response time and memory

| Actor | Request | Rows returned | All SQL | Business read SQL | Kernel ms | SQL ms | PHP peak MiB |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: |
| admin | `/api/v1/courses?per_page=1` | 1 | 14 | 3 | 45.65 | 6.86 | 30 |
| admin | `/api/v1/courses?per_page=100` | 30 | 11 | 3 | 44.66 | 5.49 | 30 |
| admin | `/api/v1/enrollments?per_page=1` | 1 | 12 | 4 | 40.43 | 6.09 | 28 |
| admin | `/api/v1/enrollments?per_page=100` | 100 | 12 | 4 | 63.67 | 16.71 | 30 |
| admin | `/api/v1/reports/overview?per_page=1` | 1 | 17 | 8 | 480.36 | 435.05 | 28 |
| admin | `/api/v1/reports/overview?per_page=100` | 30 | 16 | 8 | 480.09 | 438.98 | 28 |
| teacher0 | `/api/v1/reports/overview?per_page=100` | 6 | 17 | 6 | 141.32 | 104.12 | 28 |
| student0 | `/api/v1/enrollments?per_page=100` | 10 | 15 | 4 | 47.54 | 9.80 | 30 |
| student0 | `/api/v1/enrollments/4389/progress` | summary | 11 | 3 | 30.85 | 5.09 | 28 |

“All SQL” includes authentication, token last-use updates and database-backed throttle/cache operations. “Business read SQL” excludes cache queries, token queries/writes, and the one current-user authentication lookup. This definition is implemented in `tools/qa/performance.php`.

Increasing course rows from 1 to 30 kept business reads at 3; enrollments from 1 to 100 kept them at 4; reports from 1 to 30 kept them at 8. No N+1 growth was observed for these responses. Total SQL counts vary with throttle-cache initialization and whether the token last-use timestamp changes. The first benchmark harness incorrectly asserted equality of *all* SQL and failed on 14 versus 11; its evidence is retained in `performance-initial.json`. The corrected comparison isolates domain reads and still retains every SQL statement/count. No application behavior was changed to improve these numbers.

EXPLAIN shows existing course/deletion/publication, enrollment/course/withdrawal, lesson/course/order and composite completion indexes serving scoped lookups. Report aggregation scans derived aggregates and enrollment indexes; it still does work proportional to the enrollment/curriculum volume. An unchanged query count is not constant execution cost. No additional indexes or caches were added without demonstrated need.

## Real HTTP latency

| Actor | Endpoint | Workload | Samples | p50 ms | p95 ms | Max ms |
| --- | --- | --- | ---: | ---: | ---: | ---: |
| admin | `/api/v1/reports/overview?per_page=20` | sequential | 20 | 447.31 | 486.29 | 492.25 |
| admin | `/api/v1/reports/overview?per_page=20` | 4 concurrent | 20 | 647.29 | 1530.20 | 1597.86 |
| teacher0 | `/api/v1/reports/overview?per_page=20` | sequential | 20 | 115.49 | 147.53 | 172.55 |
| teacher0 | `/api/v1/reports/overview?per_page=20` | 4 concurrent | 20 | 170.60 | 335.60 | 452.12 |
| student0 | `/api/v1/enrollments?per_page=20` | sequential | 20 | 21.52 | 25.11 | 25.18 |
| student0 | `/api/v1/enrollments?per_page=20` | 4 concurrent | 20 | 23.95 | 76.24 | 89.19 |

The administrator report is the heaviest measured endpoint: sequential p95 486.29 ms and four-concurrent p95 1,530.20 ms on this fixture/environment. This is a meaningful remaining capacity consideration. Benchmark on the actual deployment topology with agreed latency targets, realistic data growth and production hashing before a public launch. Do not infer a supported user count from this run.

## Concurrent writes and frontend

The independent API acceptance client sends eight simultaneous completion PUTs over real HTTP to the multi-worker server; each succeeds and MySQL retains exactly one completion. The retained PHPUnit suite additionally exercises enrollment, lesson append/reorder, completion/undo and withdrawal/archival races using separate PHP/MySQL processes. See the final JUnit and API evidence for their exact results.

Vite's actual final asset sizes and gzip estimates appear in `evidence/m6/final/build.log`. Only Bootstrap Offcanvas JavaScript is imported by the application. Assets are same-origin; typography uses system fallbacks. Responsive pages were exercised in real Chromium. No Lighthouse/Core Web Vitals score, slow-network simulation, production TLS timing, browser memory profile, long-duration soak, failure-injection exercise or load-balanced capacity test is claimed.

## Reproduction

From the initialized project root, with no other database wrapper running:

```bash
source .runtime/env.sh
npm ci --prefix tools/qa --ignore-scripts --no-fund --no-audit
python3 tools/with-database.py -- node tools/qa/performance.mjs
```

The runner checks the dedicated test database/user, uses unique synthetic data and cleans its own records. It does not reset tables. Its measurements depend on local workload and resources and should not be expected to reproduce identical milliseconds.
