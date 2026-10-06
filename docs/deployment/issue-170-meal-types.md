# Meals 1 (#170)

Run `php artisan migrate --force` in each tenant database before serving the new code.
The forward migration creates `meal_types` and supplies Breakfast (07:00–10:00),
Lunch (11:00–14:00), Dinner (17:00–20:00), and Midnight (23:00–01:00) to every
existing event, including locked events. New events receive the same defaults
atomically when created. No temporary nullability or user-data backfill is needed.

Roles are not automatically granted Meals access. Admins receive the new
permissions; admins can grant View meals or Set up meals through Settings → Roles.
Set up meals includes View meals. Locked events remain read-only for everyone.

Rollback drops the table and permanently loses meal-type edits. Roll back future
dependent Meals migrations before this migration. Prefer restoring an earlier
application release while retaining the additive table when recovering a deploy.

This slice includes navigation, the empty Meals page, permissions, and meal-type
setup only. Named meals, shift meals, claims, reports, and deletion are out of scope.
