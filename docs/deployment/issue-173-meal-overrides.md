# Meals 5 (#173): meal overrides

Calvin's confirmed Oct 7 decisions supersede the original ticket's separate-only
Override count and direct Undo deletion. An override gives a source-free meal
grant to a hired Team member in the current event and claims it immediately in
one transaction. It counts as Used; Override is also tracked as its origin and
as a subset of Used. The override count decreases on Unclaim, while the origin
badge remains on the unused grant. Scheduling's projected quantities
exclude override grants. An unused meal of the same type and meal day blocks
another override, including an override restored by Unclaim.

Giving needs the new `meals.override` permission. It includes `meals.view`, not
ordinary Claim/Unclaim or override removal. Existing `meals.claim` still controls
Claim and one-tap Unclaim. Removing an unused override uses `meals.undo_claim`,
labelled "Remove meal overrides", which separately includes `meals.view`.
Existing roles receive neither new permission automatically; administrators
configure them in Settings → Roles.

Claimed meals cannot be removed. Correct an override by Unclaiming it first,
then confirming Remove override; Unclaim keeps the grant and its original
operator/time, and allows it to be claimed again. Claimed overrides also protect
meal and Team member deletion through the existing history guards. Removing an
unused override deletes its grant; no removal audit is added.

All writes recheck permissions, hired status and event writability under the
event lock. Locked events remain read-only, including for admins. The day rule
matches Claim: the event's current date plus yesterday's overnight meals until
(and including) their serving-window end, counted under their start date.

## Deployment

Run `php artisan migrate` with the matching build. The new forward migration
`2026_10_07_000004_add_meal_override_origin` adds the discriminator and persistent
origin fields to `meal_assignments`; existing rows default to non-overrides.
There is no claim-table replacement or data remap. Origin operator is nullable
only for ordinary grants or deletion of the operator account. Source shift
references stay permanently nullable for source-free grants.

Deploy backend and frontend together, and refresh old Kitchen tabs. Rollback
refuses while any override grant exists, because dropping its origin would turn
it into a normal grant. Event deletion already removes all assignments before
meals and members; the comprehensive deletion test includes an override.

The Team member Meals presentation (#174) and report (#175) are out of scope.
Their readers must use the override discriminator and the revised Used semantics.
