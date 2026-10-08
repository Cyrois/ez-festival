# Meals 2 (#171): named meals

Run the normal migrations after Meals 1 (#170). The forward migration creates
`meals` with required event/type/date/window fields, case-insensitive event-scoped
names, an event/date/window index, and window checks. It adds no default meals,
backfill, or temporarily nullable columns. PostgreSQL is the production database.

Meals reference meal types with a restrictive foreign key. Event deletion removes
meals before the event and its types. Meal types still have no delete action.

The used-meal deletion guard checks claimed `meal_assignments` rows. Assignments
retain a restrictive `meal_id` foreign key. Scheduling adds the shift-use delete
check, and event teardown removes assignments before meals and shifts.

Rollback drops all named meals. Roll back dependent shift/claim migrations first.
No pass, stock, Check-in, claim, override, or report behavior changes in this slice.
