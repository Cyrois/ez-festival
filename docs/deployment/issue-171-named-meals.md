# Meals 2 (#171): named meals

Run the normal migrations after Meals 1 (#170). The forward migration creates
`meals` with required event/type/date/window fields, case-insensitive event-scoped
names, an event/date/window index, and window checks. It adds no default meals,
backfill, or temporarily nullable columns. PostgreSQL is the production database.

Meals reference meal types with a restrictive foreign key. Event deletion removes
meals before the event and its types. Meal types still have no delete action.

The used-meal deletion guard checks `meal_claims` when that table exists. Meals 4
(#172) must retain its restrictive `meal_id` foreign key and cover the guard with
the real claims schema; this slice tests it with a temporary claim table only.
Meals 3 (#94) adds the separate shift-use delete check and child-first event
teardown for its own tables. Neither downstream table is created here.

Rollback drops all named meals. Roll back dependent shift/claim migrations first.
No pass, stock, Check-in, claim, override, or report behavior changes in this slice.
