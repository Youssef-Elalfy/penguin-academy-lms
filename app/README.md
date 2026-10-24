# Penguin Academy application

Laravel 13 source through the final M6 handoff. See the [workspace README](../README.md) for installation, secure administrator provisioning and verification. Final evidence and limitations are in [MILESTONE_6_REPORT.md](../MILESTONE_6_REPORT.md) and [FINAL_QA_REPORT.md](../FINAL_QA_REPORT.md). UI rules are in [DESIGN.md](DESIGN.md); API contracts are in [docs/API.md](docs/API.md) and [docs/openapi.json](docs/openapi.json).

The complete LMS retains Fortify authentication, policies, MySQL relationships/history, course/lesson/enrollment workflows, completion/undo, progress/reporting and 24 Sanctum API operations. M6 adds minimal verified corrections and final QA tooling/documentation.

Actual final results: 328 PHPUnit tests / 2,216 assertions, zero failures/errors/skips; 33 real authenticated Chromium scenarios; 78 exact 375/768/1440 layout checks; 24 axe scans with zero final violations; 24 API operations verified in 126 real HTTP responses. Evidence types are distinct.

No default credentials or operational QA accounts are provided. No deployment occurred. Production prerequisites, remaining browser/device limits and measured performance considerations are documented in the final reports.
