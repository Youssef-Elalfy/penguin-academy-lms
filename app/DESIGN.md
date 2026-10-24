# Penguin Academy design system — M6

The established interface uses deep green navigation, warm white surfaces and restrained mint accents. M6 verifies the real Admin, Teacher and Student experience at exact 375/768/1440 widths, with functional persisted actions. No 3D or decorative animation was added.

## Typography and layout

Font stack: Inter when locally available, then system-ui, -apple-system, Segoe UI, sans-serif. No external font is required. Body text is 15px with 1.6 line height. Page headings scale approximately 30–40px; authentication headings are about 38px.

Use a 4px spacing basis. Desktop navigation is 232px wide. Main padding is 40px, reduced to 24px and 16px at narrower breakpoints. Account forms max out at 640px; authentication forms at approximately 400px.

| Token | Color |
| --- | --- |
| Canvas | #f6f8f7 |
| Surface | #ffffff |
| Navigation / heading ink | #163e42 |
| Body | #20383f |
| Primary action | #216858 |
| Secondary text | #536b64 |
| Border | #e1e8e4 |
| Active navigation | #d7ebdf |
| Focus | #277a99 |
| Active status background / text | #eaf5ee / #326247 |

Status text accompanies color. New combinations require contrast checks; no full accessibility certification is claimed.

## Components and interactions

- Shared sidebar, breadcrumb, role badge, profile chip, status alerts, and sign-out form.
- Bootstrap offcanvas navigation on mobile; sticky desktop sidebar.
- Consistent statistics cards with persisted counts and honest zero states.
- Semantic tables, responsive overflow, captions, and Bootstrap pagination.
- Teacher search and account forms with real server actions.
- Explicit labels, autocomplete hints, per-field errors, summaries, CSRF fields, and no password repopulation.
- Busy submit buttons after valid submission; server validation remains authoritative.
- Clear empty states without links to unimplemented features.
- Local inline SVG penguin mark; no external imagery or font downloads.

Keep visible focus (3px outline with offset), skip-to-content links, semantic headings, descriptive controls, and status/error roles. Respect reduced motion. No 3D, parallax, or continuous decorative motion is used.

## Course and learning components

Course catalog cards use a quiet color marker, publication label, teacher identity, real lesson/enrollment counts, and one clear detail link. Staff filters cover query, publication, sort, and administrator teacher selection. Students see published courses only.

Editor surfaces group title, slug, summary, description, and eligible teacher assignment. Publication is a separate action; draft creation cannot accidentally publish content. Confirmation prompts precede archive/withdraw submissions. Server validation remains authoritative even without JavaScript.

Lesson lists distinguish draft/published text labels. The reader preserves plain-text paragraph spacing and offers authorized previous/next lessons. Empty outlines explain the next available action without fictitious lessons or statistics.

Lesson ordering uses labeled native selects for keyboard/no-JavaScript use, enhanced with up/down buttons and an aria-live announcement. Every lesson must appear once. A stale save produces a dedicated 409 screen with a route back to the current order page.

Rosters use responsive tables, active/withdrawn badges, student search, and pagination. The student selector is explicitly separate from enrolled records. My courses distinguishes active, withdrawn, and unavailable archived courses without exposing inaccessible content links.

All links and form actions map to implemented server routes. Busy submission feedback, visible focus, error summaries, and reduced-motion rules reuse the existing design language.

## Progress and reporting components

Use the shared learning-progress component with a native progress element, accessible label, numeric percentage, completed/total text, and a textual state. The state distinguishes no published lessons, not started, in progress, and complete. Color is supplemental. Statistics cards accept numeric values with separate suffix/decimal formatting.

Completion controls are real CSRF-protected forms inside the reader. Use one primary Mark complete action or one secondary Undo completion action. Keep course progress adjacent, expose server feedback through the existing status/error components, and retain the existing busy-submit behavior. Do not optimistically claim a completion before the server persists it.

Student dashboard rows align course identity, a progress bar, and a Continue learning or Review course link. At narrower breakpoints the row stacks; completion controls become full width on small screens. Reports use captioned responsive tables, labeled filters, empty states, and pagination. No chart implies activity outside persisted records.

## M5 refinement and accessibility

Keep the existing visual identity. Use darker secondary text and visible form boundaries. Synchronize Bootstrap link RGB tokens so links match the academy green. Standard buttons and page links have a 44px minimum target height (compact table buttons 38px). Tables retain semantic headers/captions, and their labeled overflow regions are keyboard-focusable. Horizontal scrolling stays inside the table; the body is not clipped to hide overflow.

The skip link targets a focusable main landmark. Validation summaries receive focus after a server-rendered error. Mobile navigation reports aria-expanded through Bootstrap offcanvas events; Escape/focus trapping use that component. Busy submission feedback and no-JavaScript form behavior remain available. Bootstrap JavaScript imports only Offcanvas, the component used by the application.

Course cards, headings, filters, lesson actions and pagination wrap; long names and text can break without forcing document width. Reading content is constrained to 72ch. Existing 992/768/576 breakpoints govern sidebar, forms, tables and mobile stacking. Small-screen form text is 16px to avoid input zoom; narrow actions stack or wrap. Reduced-motion rules cover component transitions.

Selected calculated color contrast:

| Pair | Ratio |
| --- | --- |
| Body #20383f on canvas #f6f8f7 | 11.59:1 |
| Secondary #536b64 on white | 5.75:1 |
| Table metadata #586b61 on white | 5.69:1 |
| White on primary action #216858 | 6.59:1 |
| Form boundary #7b9085 on canvas #f6f8f7 | 3.19:1 |
| Focus #277a99 on white | 4.85:1 |
| Sidebar focus #b9dccb on #163e42 | 7.86:1 |

These are explicit-pair calculations, not a full accessibility certification. Additional subdued course-summary/hero/monogram colors were darkened after contrast calculation.

## M6 accessibility and responsive corrections

Use #46695b for eyebrow labels and #5f6c3e for light avatar initials. The report link's accessible name begins with its visible “View details” label. Login and registration use an h1 inside main; the decorative promotional tagline is not a competing heading. Course-list empty states use h2.

Table cells have an 8rem minimum, with a 14rem first column, while keeping existing maximum widths and wrapping for long identifiers. Wide data tables scroll within their labeled keyboard-focusable containers; do not hide body overflow to mask a layout defect. ArrowRight scrolling is verified for staff report tables. The existing mint focus outline remains visible on the dark sidebar.

## Final verification

Final production assets and Blade templates compiled successfully. All 328 PHPUnit tests / 2,216 assertions passed. Those backend tests are separate from browser rendering evidence.

Real local Chromium completed 33 scenarios, 78 exact-width layout checks at 375/768/1440, and 24 axe WCAG A/AA scans with zero final violations. Checks cover all role dashboards, navigation, course/lesson forms, catalog, learning/progress, rosters/reports, filters/pagination, empty and validation states. Nine primary genuine screenshots were visually reviewed. Actual keyboard checks include student login, offcanvas Enter/Escape/focus restoration, desktop navigation and horizontal table scrolling. Reduced-motion preference and visible focus were verified.

Axe incomplete entries for short decorative glyphs/initials remain documented; this is not an accessibility certification. Other browser engines, physical mobile devices, actual screen-reader sessions and quantitative Core Web Vitals/layout-shift measurements are unverified. See ../BROWSER_TEST_RESULTS.md and ../evidence/m6/browser/results.json. No Taste or Impeccable package was available in the supplied skills catalog; the existing design system was refined through actual browser/a11y inspection without claiming those tools ran.
