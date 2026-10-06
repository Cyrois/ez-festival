# Per-person shift breaks (#146)

Pause scheduling writes while deploying the migration and application code together. The migration creates `shift_assignment_breaks` and copies fitting shift defaults onto existing assignments, including assignments in locked events. Old application code cannot create personal snapshots, so do not reopen scheduling writes until the new code is active.

A default break is copied only if its entire interval fits inside the person's assignment. Partial assignments may therefore gain scheduled minutes compared with the old shared-default deduction. From this deployment onward, scheduled minutes deduct only the person's own saved breaks.

`shift_break_id` is permanently nullable. Manually edited breaks and copies whose default was deleted stay independent; deleting a default clears provenance without deleting personal breaks. Assignment, shift, and event deletion cascade through personal breaks.

The migration refuses rollback while personal snapshots exist because reverting to defaults would lose individual break choices. Use a forward fix after writes resume. An empty table can be rolled back safely.

Create, Edit, and Copy save the roster and personal breaks in the same transaction as shift details, Headcount, and defaults. The main Breaks card now stages mass additions for the current roster only. It lists no saved breaks; individual editing and removal live in Edit hours. Any overlapping personal break blocks the entire addition, both in the browser and during the atomic Save replay. People whose hours cannot contain the whole break are skipped. New UI assignments start without breaks; copied assignments keep their personal breaks. Legacy defaults remain available internally for existing data and copy compatibility. The `ShiftBreakService` replays the `break_operations` draft log in order to enforce the all-or-nothing overlap rule against the roster at each mass addition. It compares normalized final break values independently of display order and rejects actual mismatches. Database reads and personal-break persistence live in `ShiftBreakRepository`. Explicit assignment keys keep each mass addition scoped to the people present when it was staged. Stable draft break keys resolve to newly persisted defaults during Save; no source shift lookup is needed when creating a copy.

Meals and a separate per-person reset-to-shift-time control are outside this change.

Resizing a person's hours in the roster grid removes their breaks that no longer fit entirely inside the resized interval. The removal is staged with the hours change and persisted on Save; expanding the hours again does not restore removed breaks. Moving the whole bar still moves its breaks by the same offset. Manual Edit hours continues to validate staged breaks on Save.

Personal break blocks in the roster are display-only. Edit individual breaks in the Edit hours popup and save through the page draft.
