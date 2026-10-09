# Shift supervisor (#167)

Run `php artisan migrate --force` before deploying the updated application. The forward migration adds a non-null `shift_assignments.is_supervisor` boolean, defaulting to false, and a partial unique index allowing one supervisor per shift on PostgreSQL and SQLite. Existing shifts have no supervisor; there is no temporary nullability or manual backfill.

The supervisor is selected from the final roster through the shift page's existing Save transaction. Any rostered person, including an Extra assignment, may supervise. The designation belongs to the whole shift, independently of personal hours and other overlapping shifts. Existing assignment overlap warnings remain unchanged. Deleting the assignment clears the designation through the existing deletion paths; no new foreign keys or event-deletion ordering are introduced.

Supervisor-only changes preserve hours, breaks and meals. Copy shift carries the designation onto the copied assignment, without keeping its source assignment ID. Requests omitting the supervisor field preserve the saved designation unless its assignment is removed.

Clock-in, approval, extra access, supervisor-specific permissions, and changes to the Assign popup are out of scope. Existing `scheduling.view`, `scheduling.edit` and event-lock gates apply. Rolling back the migration preserves assignments but discards supervisor selections; roll back application code first.
