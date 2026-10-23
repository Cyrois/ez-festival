# Holdings by shift or team (#225)

## Intent and confirmed decisions

Inventory replaces its old positive-holder People list/detail dialog with a
person-by-item roster for one selected shift or team. Both roster and Locations
use the shared DataTable with server pagination at 25 rows. No complete-roster
pagination exception applies. The top-level Check-in, My stock, Inventory and
History navigation remains permission-scoped; History stays separate.

Managers see event shifts and teams, plus Locations / storage and Adjust.
Supervisors see only shifts they supervise, past or future, without a time window
or pass permission. Their Inventory capability is separate from Check-in access.
Supervisors' person destinations must be eligible people assigned to the selected
shift. Supervision grants action authority, not eligibility to receive stock.
Inactive and zero-stock people stay visible; inactive people cannot receive Give.

Low stock level now also saves on item Create, superseding the earlier #199
Create omission. It accepts blank or whole units 0–2147483647. Null permanently
means no warning; zero warns at zero. Item-edit permissions remain unchanged.

## Flows and data

Give, Take back and Adjust each save their entire item set in one transaction.
Distinct items produce one movement each; invalid or insufficient lines roll
back all movements and holder creation. Give uses location or own stock, with
supervisors restricted to their own stock. Take back loads all positive holdings,
prefills their full quantities, permits zero to skip a line, and rejects an
all-zero submission. Person destinations are checked again when saved.

Adjust moves multiple items between one location and one selected person. Its
Person field starts empty. Location-to-person uses eligible receiving people and
records Pull without a note. Person-to-location searches all stock-holding
people, including inactive holders; it records Take back, or Return for the
actor's own stock, with an optional note. Location-to-location is unsupported.

Counts come from the existing event/person/location ledger, never shift-specific
holders. Reads start from roster membership, left-join holders, and batch balances
and eligibility. Empty reads do not create holders. Successful moves refresh
current holdings and locations; existing after-commit item-cache invalidation is
preserved. New screens use translated copy, shared item labels, accessible low
warnings, sticky desktop matrix headers/name/actions, and stacked phone cards.

The former unscoped person list, holder-stock/receiver HTTP routes, header Pull
flow and single-item Take back HTTP contract are retired. Holdings stock and
receiver reads now require a selected shift/team and source person. Own-stock
Move and the event/item History contracts remain intact.

## Deployment

Deploy backend and built frontend assets together. No migration or backfill is
needed: stock holders, movements, supervisor flags and low_stock_level already
exist. No new tables, foreign keys, temporary nullability or deletion ordering
changes apply. No production data has been rewritten. A source rollback has no
schema rollback; levels saved on Create remain ordinary existing item settings.

## Intentionally out of scope

Kits (#226), whole-shift close-out, historical Holdings, Holdings export,
location-to-location movements, unit-code handling, My stock enhancements (#211),
and History filters/export changes.

## Verification

- Full SQLite: 919 passed, 34 PostgreSQL-only skips (13,482 assertions).
- Full PostgreSQL before final follow-ups: 947 passed (13,820 assertions).
- Final inventory PostgreSQL regressions: 55 passed (942 assertions), including
  a real concurrent multi-item Give proving the losing submission fully rolls back.
- Frontend coverage mounts the real DataTable for pagination, safe colored headers,
  column filtering, inactive actions, phone-card markup and threshold boundaries;
  batch dialogs cover source availability, Take back defaults/zero quantities,
  errors, resets, scopes and Adjust direction/note behavior.
- The full frontend suite has nine pre-existing failures in teamCheckIn.test.js.
  Re-running that file with the original HEAD DataTable reproduces all nine.
  The existing EntitlementTable is missing its Recorded by column while its audit
  indexes/tests still expect it. That unrelated repair is not included here.
- Synthetic browser verification used only ez_festival_225_preview for stock moves.
  Verified manager picker/grid, zero-stock warnings, Give, full Take back prefill,
  Adjust's empty Person/default direction, refreshed counts and ledger readback.
  Verified supervisor-only navigation and no History, Team picker or Locations.
  The browser check caught and fixed nullable empty-search handling in remote
  options; an HTTP regression test now sends the UI's actual empty-search query.
