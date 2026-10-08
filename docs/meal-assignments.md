# Meal assignments

Each `meal_assignments` row grants one meal to one event Team member. The same row stores claim time, operator, warning confirmation and the current claim token. Unclaim clears those fields and keeps the assignment.

Scheduling creates these person-owned assignments through `MealAssignmentService::syncShiftMeal`. `source_shift_id` is optional origin metadata. `shift_meal_id` and `shift_assignment_id` reconnect an active Scheduling selection; removing that selection retires its assignment. Used assignments remain visible for the applicable meal day even after their sources are removed. Readding the same person/shift/meal reactivates the same assignment and preserves Used status. Separate shifts grant separate assignments.

`MealAssignmentService::assign` supports direct person assignments without a source shift. No new direct-assignment screen is included in this change. Shift references and snapshots are permanently nullable for this supported source-free case.

Kitchen reads assignments directly, filters and paginates on the server, and uses the event timezone. Claim and Unclaim require the existing `meals.claim` permission and a writable event. All mutations lock the event first. Claim generates a fresh `claim_token`; Unclaim must match that token so stale requests cannot reverse a later claim. Meal/shift snapshots preserve the original used grant when setup changes.

## Migration and deployment

Run `php artisan migrate` with the matching application build. `2026_10_07_000003_unify_meal_assignments` creates person-owned assignments from existing shift grants, then removes `shift_meal_people`. Fresh installations create the same assignment schema.

The JSON claim payload now uses `assignment_id`. Unclaim uses `assignment_id` and `claim_token`. Deploy frontend and backend together and refresh old browser tabs.

Rollback restores shift grants while all assignments have shift origins. Direct person assignments require a forward migration instead of rollback. Rolling back drops assignment snapshots and claim information.
