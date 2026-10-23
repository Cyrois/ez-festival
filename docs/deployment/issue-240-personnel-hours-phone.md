# Personnel hours: phone layout

Calvin confirmed that #240 covers all four current tabs: Summary, By team, By shift and By location, including the location list and selected-location people. Use the locked #240 and #252 phone frames with the shipped Personnel wording and existing pay/cost rules.

Summary uses the existing compact total tiles and shared DataTable summary records: name first, then Scheduled / Approved / Unapproved, with cost beneath hours. Report tables opt into visible mobile chevrons through `dt-summary-action`; other summary tables keep their existing defaults. Record names reserve space for actions, and action cells drop their desktop padding on phones so 44px controls fit inside the table. Loading tiles and long content stay within their available width. Date, day-navigation, row-action and Retry controls retain the desktop presentation while providing larger phone targets.

No routes, requests, JSON contracts, queries, calculations, permissions, URL selections or request-cache behavior change. Locked-event reads and permission-dependent new-tab links remain available as before. Export, team/day breakdowns and per-person shift expansion are intentionally out of scope.

Deploy application code and compiled assets together. No migration, backfill, new index, persistent cache or temporary nullability is introduced. Rollback needs only the previous code and assets.

## Verification

Browser checks use the running PostgreSQL-backed application for normal data. A temporary read-only localhost proxy supplies delayed, failed, empty and long-content responses for layout checks without changing application data or production code.

| Screen/state | Viewport | Result |
| --- | --- | --- |
| All four tabs: loaded, loading, error, Retry recovery, empty and long content | 390 × 844 | Document and main content remain 390px wide; report tabs fit on one line. |
| Selected-location people | 390 × 844 | Roster and empty state fit; member links retain new-tab behavior. |
| Expanded shift roster, including error recovery and long content | 390 × 844 | Parent and nested tables fit without clipping; the shift remains expanded through Retry. |
| Expanded shift roster with long content | 320 × 740 | Document remains 320px wide; both tables fit at 286px. |
| All four loaded views | 1280 × 720 | Ordinary desktop table cells remain; existing desktop control sizes are retained. |

Phone row/expand actions and Retry targets measure at least 44 × 44px. Summary chevrons open By team with the chosen team. Hourly pay, volunteer treatment, cost lines, missing-rate notes and unavailable Approved / Unapproved values remain readable. Loaded report views produce no browser warnings or errors. Phone screenshots and measured bounds were captured during the checks.

The PHP suite passes: 923 tests, with 36 skipped. All 33 Team hours frontend tests pass. The full frontend suite has 337 passing tests and nine existing failures in `tests/Frontend/teamCheckIn.test.js`; the same nine failures reproduce from an unchanged archive of base commit `4515df5`. These failures concern the separate check-in entitlement UI and are outside #240.
