# Inventory movement ledger (#194)

Stock belongs to a person within one event or to a location in that event.
Calvin confirmed on October 13, 2026 that Team membership must not change stock
ownership or movement history. This supersedes #194's Team engagement/admin
account holder split and the "Joined the Team" transfer. Later inventory slices
must use the same person holder (`users.person_id` for the signed-in account).
There is no holder merge or membership-related movement.

`stock_holders` has exactly one of `location_id` and `person_id`, with one holder
per event/target. Holder identity cannot be changed. `stock_movements` records
one item, positive integer quantity, source/destination, type, actor, optional
note and issue link. Checks, event-scoped foreign keys and endpoint triggers
validate movement shapes. Give-outs link uniquely to a matching issued item.
Actor references may become null on account deletion, matching existing audit
records; the movement and person holder remain. Other nullable fields express
holder/movement types, not temporary incomplete data.

Movement types use the string-backed `App\Enums\StockMovement` enum in services
and the model cast. Stored string values are unchanged; this refactor needs no
additional migration.

Balances are SQL sums of incoming minus outgoing movements. The stock service
locks the event first, rechecks the lock, then locks the item and affected
holders in order before checking the source balance and writing. The ledger is
authoritative; cached totals are display-only and invalidated after commit.
The Entitlements list uses a new cache version to avoid legacy cached totals.

Existing opening amounts and adds write `add`; removals write `remove` with a
required nonblank reason. Current Check-in still selects a location and writes
a one-unit `give_out` alongside the issue in the same transaction. Its existing
permissions, pagination and optional Code field are preserved. There are no
new screens, routes or permission keys.

## Deployment

Deploy application and the new forward migration together while stock writes
are stopped. The migration creates the new tables/constraints and drops
`entitlement_adjustments` without copying data: #194 explicitly confirms that
there is no legacy stock data to retain. Shipped migrations are unchanged.
Existing issued records and other catalog data are not rewritten. The catalog
seeder creates opening adds once and does not rewrite or duplicate movements.

Event deletion removes movements, issued records and holders before dependent
locations/items/Team records. Location deletion is refused while stock history
exists, including at zero balance; unused holder rows are removed on valid
location deletion. Item deletion is refused while any holder has a nonzero
balance, or while passes, expected entitlements or issues reference it. As
before, deleting an otherwise unused zero-stock item removes its stock log.

Rollback is destructive: its `down()` restores the empty old table and its
indexes for schema reset/rebuild, but cannot translate person holdings or
recover discarded adjustments. After this ledger has been used, prefer a
forward fix or restore a pre-deployment backup rather than rolling it back.
PostgreSQL trigger functions support repeated `migrate:fresh` rebuilds.

## Scope and verification

Movement storage supports add, remove, pull, return, transfer, take back and
give out. There are no public person-stock write endpoints in this slice;
receiver/manager eligibility and permissions belong to #195 and the later
inventory flows. Stock remains location-selected at Check-in until #198.
Inventory screens, reports, CSV export and ticket sales are out of scope.

Tests cover constraints on SQLite and PostgreSQL, balances, immutable person
identity/history through joining the Team, reasons, foreign events, locks,
issue conflicts and rollback, item/location/event deletion, repeatable seeding,
batched reads, cache invalidation and real independent-connection PostgreSQL
races for the last location/person unit and first holder creation.

### Validation results (October 13, 2026)

- Focused stock tests: 44 passed on SQLite; PostgreSQL stock/concurrency,
  migration rebuild and event-deletion selection: 72 passed.
- `composer test`: 746 passed, 19 PostgreSQL-only tests skipped, one existing
  error in `EventPermissionsTest` at line 271 (old `people.data` page payload).
- `composer test:pgsql`: 765 passed, the same existing permission-test error.
- Frontend tests: 236 passed, one existing assertion in
  `shiftTimelineRoster.test.js` at line 658 omits `is_supervisor: false`.
- Both failures reproduce on a clean `origin/main` snapshot. No unrelated
  product or test changes were made to mask them.
- Enum refactor: 148 related SQLite tests and 65 PostgreSQL stock/concurrency,
  seeder and migration tests passed, including enum reads and unchanged raw/serialized values.
- Pint, Prettier, ESLint and the production frontend build passed.

PostgreSQL verification used an isolated PostgreSQL 17 cluster and
`ez_festival_test`, with two independent connections for concurrency tests.
The application's development/production database was not migrated.
