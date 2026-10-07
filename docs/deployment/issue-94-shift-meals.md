# Meals 3 (#94): shift meals

Run the normal migrations after Meals 2 (#171). The forward migration creates
`shift_meals` and `shift_meal_people`. All foreign keys are required, use short
PostgreSQL constraint names, and prevent duplicate meal rows or recipients.
There is no backfill, default grant, or temporary nullability.

Shift meals save in the existing shift transaction under the event lock. Only
explicit roster assignments receive meals; selecting everyone takes a snapshot.
Later assignments receive nothing automatically. Removing an assignment cascades
its grants. Removing its last grant requires removing the meal row in that Save.
Deleting a shift cascades its rows and grants. Event deletion removes shift meals
before meals and shifts.

A meal referenced by any shift cannot be edited or deleted. Remove all shift
references first. This is Calvin's confirmed decision and supersedes #94's older
proposal to allow meal date changes and display an off-day note. Moving a shift
onto different days requires removing meals that no longer belong to its days.

`MealEntitlementQuery` is the event-scoped source for current shift grants and
projected counts. The same meal on two shifts counts twice. Claiming, overrides,
reports, and the Team member Meals card remain in Meals 4–7.

Meals 4 (#172) must store claim identity independently of the recipient pivot:
retain the meal/person and used state when grants are removed, with any nullable
`shift_meal_id` using SET NULL on deletion. This slice verifies that retention
contract with a temporary claim table; it adds no claims table or nullable column.
Update the event deletion ordering when the real claims table arrives.

Rollback drops all shift meal rows and recipients. Roll back dependent claim
foreign keys first. Existing meals, shifts, assignments, and pass entitlements
are not removed by this migration's rollback.
