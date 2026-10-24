# M4 browser verification — 2026-10-22 UTC

## Executed in the real browser

Managed local preview started successfully:
```
sites-preview start /workspace/scratch/c2d7cdfd8394/penguin-academy-m1
Sites preview running at http://terminal.local:4173/
```

Chrome (cdp), through the supported CUA/Playwright browser interface, opened the final built application:
- Welcome page rendered: title "Welcome · Penguin Academy".
- Navigated individually to /admin/dashboard, /teacher/dashboard, /student/dashboard.
- All three navigations ended at /login with title "Sign in · Penguin Academy".
- The visible form exposed labeled Email address and Password controls, a remember-device checkbox, and Sign in button.
- Real Tab key navigation moved focus from Email address → Password → remember-device checkbox → Sign in.
- Read-only DOM measurement at the sign-in page returned:
```json
{"documentWidth":1363,"focusTag":"BUTTON","focusText":"Sign in →","outline":"3px","viewport":{"height":936,"width":1363}}
```
This establishes no horizontal document overflow at that desktop viewport and a 3px focused-button outline. It is not a full accessibility audit.

An old tab handle was unavailable after resuming:
```
Tab not found in browser 1.
```
Opening a fresh tab in the same selected browser succeeded. No browser timeout recurred.

## Exact authenticated-verification blocker

Both the before/after database inspections show zero development users. No administrator, teacher, or student account was provisioned for browser use, and secure user credential entry/manual signup handoff was not completed.

The advertised browserAuth guidance requires secure user-provided credentials, prohibits entering sign-in values through Playwright/other model-visible browser methods, and states: "Do not use browserAuth to create accounts." No browserAuth credential request was submitted; no unavailable/error response from that capability is claimed. No credentials, sessions, or authentication bypasses were injected.

Consequently these remain NOT browser-verified:
- Authenticated Admin, Teacher, and Student dashboards.
- Course/lesson navigation and staff progress reports.
- Enrollment, withdrawal, completion, undo, and persistence after an actual browser refresh.
- Authenticated mobile layouts and keyboard workflows.

PHPUnit HTTP integration tests exercise those server workflows against MySQL, including subsequent GETs that render persisted progress. They are separate from real browser end-to-end tests.

No local Playwright authentication shortcut, browser cookie injection, screenshots of fabricated dashboards, or alternate browser automation was used. The preview was stopped after inspection. No deployment occurred.
