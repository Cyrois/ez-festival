# Inventory history and item holdings (#199)

## Behavior and confirmed decisions

Check-in now has Check-in | My stock | Inventory | History. History is a separate
manager/admin tab; Inventory keeps current holdings and stock movement actions.
The former holder-dialog history is replaced by the History tab's Holder filter.
My stock remains restricted to its viewer. The nested Holdings / Movement history
proposal on #225 is superseded by Calvin's October 21 specification decision.

Event-wide history, its remote holder/recorder catalogues and CSV require
`inventory.manage`. Item pages retain their existing `view-entitlements` gate
for item-only history, options and paged people holdings. Those endpoints bind
the item and current event; parameters cannot broaden their scope. An item-only
viewer receives no event-wide history or export URL.

Both history tables default to 10 rows and offer 10 / 20 / 50 through
CustomDropdown and the shared DataTable. Applying filters or changing size
returns to page one. Item, Holder, Recorded by, movement type and date range
filter in SQL. Blank dates include all history; a range requires both dates,
inclusive in the event timezone, without restricting it to the event dates.
Movement timestamps are stored in UTC and ordered by timestamp then ID descending.

Holder matches both ledger endpoints and checked-in recipients, including people
who never held stock. Give-out recipients and codes come from the linked issued
record, without creating a receiving stock holder. Historical location givers,
notes and missing actors remain readable. There is no synthetic movement
backfill for old issued records without a movement.

The CSV has the same logical columns and filters, with codes in the Receiver
cell, event-local timestamps including offsets, and text protection for
spreadsheet formula prefixes. It ignores table pagination and streams eager-loaded
batches of 500. A maximum movement ID bounds the export against concurrent new
movements. No unbounded people or movement collection is hydrated for a table.

The item page retains location stock and adds its complete people-held total
plus a server-paged people list. Counts use the existing ledger. Inactive holders
are flagged using batched current eligibility, including admin exceptions.

## Migration and deployment

Run `php artisan migrate --force` when deploying the new backend and built assets.
The forward migration adds nullable integer `entitlement_items.low_stock_level`,
without a configured default. Existing items stay unset. No existing create
migration, ledger, issued record or holder identity is rewritten.

The setting accepts blank or whole numbers 0 through 2147483647 through the item
update Form Request. It is absent from Create. Omission preserves the current
value; explicit blank clears it; zero is a configured level. Existing item
editors may change it, other item viewers see it read-only, and locked events
refuse writes at middleware and service boundaries.

The setting is exposed in item and Inventory item-option resources. The event
item cache advances to v6 and item edits retain after-commit invalidation.
Nullable is the permanent product meaning of no configured warning, not a
temporary migration state. No new foreign keys or deletion-order changes apply.

The application has not been deployed to production. No legacy-source
compatibility table or backfill is required. Development databases with Hired
members still need a group assigned before the hired-group constraint runs.

Migration rollback removes only the new low-stock setting; stock, movements,
issued records and unique-code settings remain. Preserve configured levels
before rollback if needed. The old issued-log route and exclusive supporting
code and translations are retired; deploy the backend and frontend together.

## Intentionally out of scope

Low-stock warning rendering and the shift/group Holdings redesign (#225),
My stock enhancements (#211), free-text history search (#213), code correction,
new reports, new movement types and new stock write flows.

## Verification

- Full SQLite: 906 passed, 33 PostgreSQL-only skips; 13,297 assertions.
- Full PostgreSQL: 939 passed; 13,671 assertions, using the local test database.
- Full frontend: 344 passed.
- Pint, Prettier, ESLint, production build and diff whitespace checks passed.
- Feature tests cover each filter and combinations, foreign IDs, pagination,
  check-in recipients, all movement types, notes/codes/labels, event-local DST
  bounds, permissions and revocation, locked reads/writes, people totals,
  inactive/admin flags, a multi-batch CSV, safe CSV text, null/zero/clearing,
  cache invalidation and migration rollback/reapply preservation.
- Mounted frontend tests cover filter and size resets, CSV query parity,
  scoped item views, escaped values, codes beside Receiver, remote paging,
  validation, failure/Retry and manager-only navigation.
- Browser verification used synthetic data in a separate PostgreSQL preview
  database. Verified the top-level History tab, 10-to-20 pagination, a filter
  matching a checked-in recipient without stock, item history and inactive
  holders, native CSV download, and low-stock save/reload. Database readback
  showed the saved level and an unchanged movement count; the reloaded page
  had no browser errors. No application database was migrated or modified.
