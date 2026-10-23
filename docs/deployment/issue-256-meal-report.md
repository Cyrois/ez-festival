# Meals report (#256)

Kitchen → Report (`/reports`) retains `meals.report.view`, including report-only roles, admins and locked events. SQL aggregates one row per configured named meal for the current event, including zero-count meals. The resource groups those rows by the meal's current date and meal type in configured time-window order. There is no pagination, search, user sorting or report cache.

Assigned counts every active grant from shifts, direct assignments and overrides, claimed or not. Removed shift grants and deleted overrides do not count. Projected remains active non-override grants. Used retains every claim, including retired shift grants; Extras remains its claimed-override subset. Remaining is max(Projected − Used, 0). Total is Projected − Used, without clamping. Type and Day totals sum Assigned, Projected, Used and Extras and recalculate Remaining and Total from those sums.

Calvin confirmed during the implementation-spec review that named-meal rows include override counts: do not show separate named-person Counter override rows. The written #256 formulas and copy override conflicting frame examples and #175's older rules. In particular, 328 Projected and 309 Used gives Remaining 19. There are Day totals but no event grand total. Phone uses one card per meal type with all its named meals inside, plus a Day total card.

CSV is removed: `/reports/meals/export` returns 404, and its controller, response, route-permission entry, translations and button are gone. The role permission tooltip no longer promises an export.

## Deployment

Deploy the matching backend and frontend build together: the report resource now exposes `meals.days`, with meal-type groups and precomputed type/day totals, instead of flat `meals.rows`. No migration, backfill, index, new nullability or event-deletion changes are needed. Reverting the code and assets rolls back the change without altering stored meal assignments.

Pricing, meal budgets, people-working counts, direct-assignment UI, claim/override behavior and Scheduling changes are out of scope.
