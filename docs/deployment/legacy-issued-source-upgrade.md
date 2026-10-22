# Legacy issued-source upgrade repair

## Problem

The #198 location-removal migration correctly refuses to discard a non-null
issued source location unless a linked give-out preserves it. Older databases
can contain issued records from before the movement ledger was introduced.
An October 21 local upgrade had four such records among five total issues.
Creating give-out movements now would fabricate history and debit stock again.

## Fix and deployment

Ship the new `2026_10_17_000001_preserve_legacy_issued_sources.php` migration and
backend together, back up the database, and pause check-in writes for cutover.
Run `php artisan migrate --force`. The filename intentionally sorts before the
already-shipped `2026_10_17_000002_remove_location_from_issued_entitlements.php`;
the original migration and its protection are not edited.

The compatibility migration copies source locations only for issues without a
linked movement into `legacy_issued_entitlement_sources`. After copying, it
clears those old source values so the original removal can proceed. Correctly
linked sources stay in the ledger. Conflicting event or linked-movement sources
still require reconciliation and are refused before schema/data changes.
Databases that already removed the old column receive an empty archive table.

The archive is read by Check-in Audit as the Giver fallback, including server
search and sorting. It is source metadata, not a movement or a stock holder.
Issued codes, issuer, timestamps, movement rows and balances remain unchanged.
Recorded by stays unavailable when no movement exists. These old records do not
become synthetic rows in Inventory History or affect code reservations.

No temporary nullable foreign key or extra event/organization column is added.
Location removal checks archived history in both request validation and service
before the restricted FK can fail. Event deletion clears archives before their
issued parents and locations. A populated archive cannot be rolled back after
the old column is removed. Before removal, rollback restores old locations
without discarding them.

The separate `2026_10_18_000001_require_group_for_hired_team_members.php`
migration refuses Hired members without groups. Assign their actual event groups
before rerunning migrations; this inventory fix does not invent group choices.

## Verification

- Focused SQLite and PostgreSQL: 82 tests passed, 648 assertions on each engine.
- Full regression suites: PostgreSQL 945 passed (13,715 assertions); SQLite
  912 passed with 33 PostgreSQL-only skips (13,341 assertions). Pint passed.
- Regression tests cover legacy source copying through the original column
  removal, unchanged issue data and ledger/balances, audit search/sort, existing
  linked movement preservation, conflict refusal, already-upgraded databases,
  reversible pre-removal rollback, refusal of lossy rollback, location deletion
  guards and comprehensive child-first event deletion.
- Rehearsed against a protected backup restored into an isolated PostgreSQL
  database. Inventory compatibility and removal succeeded; before/after hashes
  matched for every remaining issued column, all four source locations and the
  entire movement ledger. The rehearsal also identified one missing hired group.
- After the user confirmed that member's group, the remaining migrations passed
  on the clone. A fresh backup preceded the local upgrade, which completed all
  four pending migrations. Post-upgrade hashes again matched issued data, all
  four legacy sources and the entire movement ledger; no migrations remain.
