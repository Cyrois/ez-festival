# Personnel hours: By location

Intent: add By location after Summary, By team and By shift. The implementation is stacked on local `codex/190-personnel-pay-cost` (134c0ad), which includes the three-tab prerequisite (78e1a72). Review the #252 diff against that base.

Deploy code and compiled assets together after #249 and #190. No migration, backfill, new permission, persistent cache, or temporary nullability is introduced. Rollback uses the previous code and assets.

Location totals aggregate assignments in SQL by shift location, then person. Overnight assignments count in full on the shift's event-local start day. Zero-hour locations are hidden after employment filtering. Selected-location rosters include all assigned people in the range regardless of member status, including assigned people whose breaks reduce their hours to zero; they exclude unassigned Hired people. Rows are complete, in name order, with no paging, search or sorting. The list never loads people or issues per-location queries. Distinct missing-rate people are counted once in the event note even when assigned at several visible locations.

Both JSON endpoints and page addresses use the existing report permission through the central route map and Form Requests. Foreign-event location addresses fail validation; foreign-event people endpoints return 404. Member links require team.view, open in a new tab, and omit contact details. Locked events retain pay and cost access. Approved cost remains unavailable; Unapproved has no cost field.

The location picker includes All locations and the complete event catalogue independently of filtered rows. Dates and employment carry across tabs; location selection stays in the URL. Successful location results use the shared page-visit cache with selection validation, named errors and Retry. Location links derive their query from live filter state so deferred Inertia history updates cannot retain a previous employment filter. The 390px view uses the shared summary mobile table and three compact total tiles.

Rounding decision (Calvin, 2026-10-21): Summary must total 0.02 when a paid person at 0.30/hour works one minute at each of two locations, each costing 0.01. Round combined selected-range minutes per person/location half up using integer cents. Summary / By team person rows and team/event totals sum those rounded person/location costs; Budget uses that same calculation over the whole event. This supersedes #190's combined-person range rounding. Apply date and employment filters before aggregation. By shift retains its existing person/shift rounding. Sub-cent regressions cover 0.005 and 0.004 costs, filtered ranges, both employment filters, the By team person row, and the whole-event Budget query on both databases.

Out of scope: CSV/email, location setup changes, grouping by location type, currency configuration, rate history, clock-in and per-person shift breakdowns.

Validation: SQLite and PostgreSQL feature suites, frontend tests, Pint, Prettier, ESLint and the production build. Browser verification uses disposable PostgreSQL fixtures and checks row drill-in, picker replacement and All locations, Paid filtering, new-tab member links, and the 390px layout without horizontal overflow.
