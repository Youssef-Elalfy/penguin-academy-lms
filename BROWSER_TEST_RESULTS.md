# Penguin Academy — real browser acceptance results

Final outcome: **33 passed scenarios, 0 failed; 78 recorded viewport checks; 24 axe scans, 0 reported violations.** These are real Playwright/Chromium interactions, separate from PHPUnit HTTP feature tests. Run: `m6-f59bb8cf4fd3`; runtime timestamps 2026-10-24T01:58:52.727Z to 2026-10-24T02:01:27.902Z.

## Environment and secure authentication

Playwright 1.62.1 controls local headless Chromium 153.0.8010.0 over loopback HTTP at port 4174. Browser binaries come from pinned `@sparticuz/chromium` 153.0.0; axe tooling is `@axe-core/playwright` 4.14.0. Each role gets an independent fresh browser context. `tools/qa/runtime.mjs` reads the private test connection settings, selects only `penguin_academy_test`/`penguin_test`, and uses normal local web middleware with CSRF enabled and APP_DEBUG=false.

The administrator is provisioned by the existing `academy:admin --password-stdin` console command. That administrator creates teachers through normal protected forms and confirms their password. Teachers sign in and change temporary passwords through the required form. Students register and sign in through Fortify. Passwords remain in memory; there are no credential screenshots, traces/HAR files, session injection or authentication bypasses. The student sign-in is also exercised using typing/Tab/Enter only after the form's normal autofocus.

Sixteen explicit MySQL assertions confirm provisioning, teacher flags, course assignment/publication, lesson content/order/publication, enrollment identity, completion/undo, retained withdrawal/re-enrollment history, pagination fixtures and archival history. Cleanup removes only this run's uniquely named synthetic rows in the test database. The final preservation report confirms both databases' original core records are unchanged. No operational test accounts are left as default users.

## Executed acceptance scenarios

| # | Scenario | Result |
| --- | --- | --- |
| 1 | Public authentication forms are responsive and accessible | PASS |
| 2 | Administrator login and dashboard | PASS |
| 3 | Real browser session cookies and CSRF rejection | PASS |
| 4 | Administrator creates two teacher accounts through protected forms | PASS |
| 5 | Administrator course creation, edit, teacher assignment and publication | PASS |
| 6 | Teacher login enforces password change and redirects to teacher dashboard | PASS |
| 7 | Teacher assignment scope and cross-course/admin rejection | PASS |
| 8 | Teacher creates and edits own course | PASS |
| 9 | Teacher creates, edits, publishes and reorders lessons | PASS |
| 10 | Student registration, logout, keyboard-only login and role redirect | PASS |
| 11 | Student catalog and denial before enrollment or for staff/draft content | PASS |
| 12 | Student enrolls and reads published lesson with escaped content | PASS |
| 13 | Completion, 50 percent progress and persistence after refresh | PASS |
| 14 | Undo completion and complete again | PASS |
| 15 | Withdrawal blocks lessons; re-enrollment retains progress and enrollment identity | PASS |
| 16 | Continue learning opens the next incomplete lesson | PASS |
| 17 | Teacher roster, learner report and course report | PASS |
| 18 | Administrator enrollment and progress reports | PASS |
| 19 | Server-side form validation focuses accessible error summary | PASS |
| 20 | Course pagination, filters, empty and validation states at all required widths | PASS |
| 21 | admin responsive layouts at 375, 768 and 1440 pixels | PASS |
| 22 | admin desktop sidebar navigation works | PASS |
| 23 | admin mobile navigation and keyboard focus | PASS |
| 24 | teacher responsive layouts at 375, 768 and 1440 pixels | PASS |
| 25 | teacher desktop sidebar navigation works | PASS |
| 26 | teacher mobile navigation and keyboard focus | PASS |
| 27 | student responsive layouts at 375, 768 and 1440 pixels | PASS |
| 28 | student desktop sidebar navigation works | PASS |
| 29 | student mobile navigation and keyboard focus | PASS |
| 30 | Teacher lesson archival preserves completion history and updates eligible progress | PASS |
| 31 | Administrator archival preserves learning history and revokes student access | PASS |
| 32 | Administrator and teacher logout block dashboard access | PASS |
| 33 | Automated accessibility and browser errors have no outstanding failures | PASS |

The scenarios exercise the requested administrator, teacher and student journeys. Authorization denials are genuine 403/404 responses. Browser lesson content includes an escaped script-shaped payload and confirms it never executes. Progress moves 0 → 50 → 0 → 50, survives refresh and re-enrollment, then excludes an archived completed lesson while its historical completion row remains in MySQL. Course archival also retains history and removes student access.

## Responsive and accessibility evidence

Exact viewport widths are controlled with Playwright: **375, 768 and 1440 pixels** ({375: 26, 768: 26, 1440: 26} recorded checks). There are 24 distinct role/route states; repeated course-catalog checks cover pagination. Every recorded layout check asserts the exact width, no document horizontal overflow, one primary heading in main, labeled form controls and no broken images. One additional server-validation state is checked at each required width (three checks) inside the pagination scenario. Tables deliberately scroll inside labeled focusable regions; both staff roles demonstrate ArrowRight scrolling by keyboard.

Coverage includes public login/registration; all three dashboards; navigation; teacher list/create; admin courses/create/edit/enrollment overview; teacher lesson create/order/roster; course and student reports; student catalog/course/lesson/my-courses; pagination, filters, empty states and validation errors. Menu opening by Enter, closing by Escape, focus restoration, aria-expanded and reduced-motion preference are checked for every role. Desktop sidebar links are actually clicked. Visible sidebar focus is measured as a 3px mint outline.

Axe scans use WCAG 2 A/AA and WCAG 2.1 A/AA rules on 24 desktop page/state targets. They are not 78 separate accessibility audits or a complete WCAG certification. Axe `incomplete` entries remain available in the raw report for decorative navigation glyphs/short avatar initials; targeted source/contrast and screenshot review supplements them. No NVDA/VoiceOver session was run.

Verified UI corrections:

- Align the report link's accessible name with visible “View details” text (initial scans flagged label-content-name mismatch).
- Darken the course eyebrow text from #55796c to #46695b (initial course-hero contrast was 4.28:1).
- Darken the light avatar initial from #657342 to #5f6c3e after targeted contrast review (previous pair was 4.47:1).
- Put the actual authentication form title in an h1 within main; use a non-heading promotional tagline. Empty course-list states use h2.
- Give report table columns minimum widths so words no longer break into narrow fragments. Preserve contained, keyboard-accessible horizontal scrolling instead of hiding document overflow.

Nine primary screenshots were genuinely captured and visually reviewed after the final changes. They show synthetic database-driven data, not designed mockups. Mobile/tablet captures show the full page; desktop captures use the viewport to avoid a sticky-sidebar full-page capture artifact seen in an earlier diagnostic. Separate desktop viewport diagnostics are retained locally.

Files inside `evidence/m6/browser/`:

- `admin-375.png`
- `admin-768.png`
- `admin-1440.png`
- `teacher-375.png`
- `teacher-768.png`
- `teacher-1440.png`
- `student-375.png`
- `student-768.png`
- `student-1440.png`

## Earlier blockers and failed attempts

The managed browser originally exposed no exact viewport-resize/authentication handoff suitable for the required journeys. The authorized alternative local Playwright route resolved this blocker.

The default Playwright browser download failed with `End of central directory record signature not found` (0 MiB archive). The package inflater then encountered `EINVAL: invalid argument, chown '/tmp/fonts'`. The pinned Chromium package was unpacked using Brotli and tar without ownership changes; `tools/qa/prepare-browser.mjs` reproduces the successful setup. A helper initially attempted a non-exported package.json subpath; it was corrected to resolve the public module entry. No application authentication changes were used to work around browser limitations.

Early browser attempts include a wrong expected teacher-create redirect (the application correctly redirects to edit), four axe violation instances caused by two UI defects, and a keyboard-submit navigation race in the test harness. The harness now waits for the expected URL after Enter. Historical failed attempts are retained; none are represented as a final pass. Final evidence is `browser/results.json` and `browser-final.log`.

## Remaining unverified checks

There is no remaining blocker for the required local authenticated role journeys or exact widths. Safari, Firefox, actual iOS/Android hardware, screen-reader sessions, production HTTPS/proxy behavior, long-duration use and quantitative layout-shift/Core Web Vitals metrics remain unverified. This is one Linux Chromium environment and does not establish universal browser/device compatibility.

Re-run locally after preparing dependencies and the pinned browser:

```bash
source .runtime/env.sh
npm ci --prefix tools/qa --ignore-scripts --no-fund --no-audit
node tools/qa/prepare-browser.mjs
python3 tools/with-database.py -- node tools/qa/browser.mjs
```

Only one database wrapper may run at a time. The QA runners are verified on the supplied Linux x64 runtime and require the private test environment file. All browser results are independent of the PHPUnit counts.
