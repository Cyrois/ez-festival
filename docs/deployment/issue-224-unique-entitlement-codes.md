# Unique entitlement codes (#224)

An item's existing Details editor gains **Needs a unique code**, editable with
`credentials.entitlements.edit`. It defaults to false and is absent from create.
The Consume entitlement popup requires a code for enabled items, focuses the
input, accepts scanner Enter, and displays a duplicate error beside that input.
Existing item and check-in permission, event-scope and locked-event gates remain.

Codes compare after trimming their ends and lowercasing with PHP's multibyte
normalizer. Display codes retain their casing and internal spaces. A value used
by any item in the event after deployment, including optional codes, blocks a later
unique-code consume. Optional codes retain their existing ability to repeat.
The same value is available in a different event. Both required and optional
codes have a 64-character limit in the popup, Form Request and consume service.

The event-scoped reservation table has a composite primary key on event and code
key. Reservation, issuance, consumed status and the single-unit give-out movement
commit together. A duplicate or stock failure leaves no partial write. The
consume service re-reads the setting under the existing event/item locks, so an
earlier popup or Form Request cannot bypass a changed setting. Turning the
setting off never releases a previously used code. Event deletion clears its
reservations through `EventService::delete` before deleting restricted children.

## Deployment and rollback

The forward migration adds a non-null boolean and an empty reservation table.
It does not read or backfill existing issued codes; those codes remain unchanged
and do not block new check-ins. There are no temporary nullable columns,
unit-level held-stock rows or new dependencies.

Pause check-in writes while deploying PHP, running `php artisan migrate --force`
and replacing/restarting old application workers. Resume only with the new code
and built frontend assets: older workers do not reserve codes. The entitlement
list cache advances from v4 to v5 so cached items include the new flag.

Application rollback should precede migration rollback. The migration's `down()`
removes only the flag and reservation table; it preserves issued code text,
issuances and movements. Reapplying it starts with an empty reservation table
and defaults all item settings to false again.

## Intentionally out of scope

Code editing/correction, extra history UI, generated or printed codes, camera
scanning, bulk Consume, stock-transfer changes, navigation and own-stock Consume
(#198). Preserve the audit view from #229. The application's database is not
migrated by implementation tests.

## Verification

Feature tests cover required/malformed codes, event-wide normalized duplicates,
optional codes, existing codes remaining unreserved, setting changes, rollback
after stock failure, permissions, foreign events, locked events, repeated
consumption, migration rollback and event deletion. Real PostgreSQL races cover duplicate codes on
different items and enabling the setting while Consume waits on the event lock.
Mounted popup tests cover focus, required/optional states, scanner Enter, busy
guards, inline errors, successful dismissal and resetting the input.

Verified October 17, 2026:

- Full PostgreSQL: 861 passed, 11,955 assertions, including both new races.
- Full SQLite: 830 passed, 31 PostgreSQL-only skips, 11,612 assertions.
- Full frontend: 291 passed.
- Pint, Prettier, ESLint, production build and diff whitespace checks passed.
