# Meals 7 (#175): meal report

Historical behavior below is superseded by [#256](issue-256-meal-report.md) for grouping, Assigned, Total, phone layout and CSV removal.

Meals Report (`/reports`) contains the Meals card. The page and CSV export
(`/reports/meals/export`) use `meals.report.view` after #191, including for locked
events. Calvin's Oct 8 navigation correction places the link under Kitchen as
“Report,” between Meals and Configure. Kitchen's children are filtered
independently: Meals and Configure use `meals.view`, while Report uses
`meals.report.view`. Kitchen stays
visible when either permission is granted, and its parent links to the first
visible child. This slice adds no financial report.

Calvin's confirmed Oct 8 decisions supersede the ticket's earlier override
formula and approve an aggregate-table exception: the whole current event uses
the shared DataTable without pagination, searching or user sorting. Configured
named meals appear separately even when dates/types match or counts are zero.

Projected reuses MealEntitlementQuery's active non-override grant scope, including
its existing source-free ordinary grants. Separate shift grants count separately.
Used is every currently claimed assignment, including warning-confirmed claims
and overrides. Extras is the claimed override subset of Used; Unclaim reduces
both counts for an override. Remaining is max(Projected - Used, 0) per named meal
row. Total is Projected - Used - Extras, without clamping, as explicitly requested
by Calvin. Used still includes Extras, so Total subtracts that subset again.
The page and CSV contain no day or event total rows.

Claims group by their stable named meal ID, including after the Scheduling source
is removed. The row shows the meal’s current name/date/type; original claim
metadata remains stored on each assignment for audit. Overnight meals use their
start date.
The query aggregates counts in SQL in one database snapshot. The page and CSV
share the same report resource and columns. No report cache is introduced.

## Deployment

No migration, backfill, new nullable fields or event-deletion changes. Calvin
confirmed that #191 does not backfill existing roles: assign `meals.report.view`
explicitly for report access. Existing `meals.view` alone no longer grants it.
The seeders create an admin account and no permission-bearing roles; the admin
retains report access through the existing bypass. Deploy the matching backend
and frontend build. Rollback consists of reverting this slice.
The CSV uses ISO dates, translated headings, and escaped text cells;
spreadsheet formula prefixes in user-entered type names are escaped as text.

Labour cost/Team hours (#69), money, and changes to meal assignment or claiming
are intentionally out of scope. The meal report stays under Kitchen; Team hours
lives under Team (#189). The meal report page, export and payload require
`meals.report.view` independently of other Kitchen access.
