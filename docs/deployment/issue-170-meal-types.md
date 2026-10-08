# Meals 1 (#170)

Run `php artisan migrate --force` in each tenant database before serving the new code.
The migration creates `meal_types` without backfilling existing events.
New events receive Breakfast (07:00–10:00), Lunch (11:00–14:00),
Dinner (17:00–20:00), and Midnight (23:00–01:00) atomically when created.
No temporary nullability is needed. Databases that already ran the earlier
backfilling version retain their existing meal types.

To seed the four standard types for existing events, run
`php artisan db:seed --class=MealTypeSeeder --force`.
The seeder initializes only events with no meal types, including locked events,
and preserves configured types and windows on repeated runs.

Roles are not automatically granted Meals access. Admins receive the new
permissions; admins can grant View meals or Set up meals through Settings → Roles.
Set up meals includes View meals. Locked events remain read-only for everyone.

Rollback drops the table and permanently loses meal-type edits. Roll back future
dependent Meals migrations before this migration. Prefer restoring an earlier
application release while retaining the additive table when recovering a deploy.

This slice includes navigation, the empty Meals page, permissions, and meal-type
setup only. Named meals, shift meals, claims, reports, and deletion are out of scope.
