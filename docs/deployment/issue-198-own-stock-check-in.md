# Own-stock Check-in (#198)

## Intent and confirmed decisions

Check-in consumes one unit from the signed-in Person's holder in the current
event. This applies to artists, vendors and Team members, including admins who
have no Team engagement. There are no account/engagement holder identities,
membership transfers, client-selected source holders, or location fallbacks.

Calvin confirmed in the #198 specification conversation on October 17, 2026:

- Preserve the shipped permission inheritance: Manage inventory and either pass
  permission include Check people in. Such users may consume held stock. This
  supersedes the ticket's manager-without-check-in refusal criterion.
- Preserve the existing Audit view toggle. Giver, Receiver and Recorded by
  appear in Audit view. Calvin subsequently reverted the Details popup to its
  original Code, Checked in by and Checked in at fields. Checked in by comes
  from the issued record, including historical records without a movement.

The Consume dialog shows `You have N left` only inside the dialog. Pending
actions are disabled when the actor cannot consume or holds none. Inactive
holders see the locked 57f explanation. The server derives the source from the
actor and freshly rechecks permissions, recipient eligibility, event lock,
expected status and balance inside the transaction. It locks the event first,
then actor engagement/role/person/account records, entitlement/item and holder.
The existing movement service also locks the source and checks the ledger.
Failed writes leave no issue, consumption status or give-out movement behind.

The check-in read endpoint returns only the viewer's item balances, batched in
SQL, without creating holders or loading location balances. Audit data resolves
Giver through the immutable movement's from-holder, Receiver through the issued
entitlement's person assignment, and Recorded by through the movement actor.
Historical location givers remain readable. Give-out still has no to-holder;
checking someone in does not credit their stock.

## Migration / deployment

`2026_10_17_000002_remove_location_from_issued_entitlements.php` removes the
issued location index, foreign key and column. No temporary nullable column or
new holder/balance storage is introduced. Existing ledger rows are unchanged.

Before dropping the column, the migration requires every non-null historical
issued location to have a matching linked give-out from that location holder.
It refuses deployment if history is missing; reconcile it before proceeding.
Do not create artificial ledger debits just to pass the guard. Back up first.
SQLite's table rebuild preserves dependent ledger triggers and verifies foreign
keys; PostgreSQL alters the existing table directly.

Deploy schema, backend and frontend together with check-in writes paused during
the cutover: the old application reads and writes the removed column. Resume
with the new application after migration. Empty databases can roll back; once
issued rows exist the migration refuses rollback. Restore a backup or fix
forward rather than inventing source locations for person-issued stock.

Location deletion still refuses any stock history, even when current stock is
zero. Both its Form Request and service use the ledger. Event deletion removes
movements before issues and holders, preserving the existing restricted-FK order.

This branch starts from main at `e3cf45d`. The separately committed #224 work is
not part of that base. Code remains optional here. When integrating #224,
preserve its required/unique-code validation and reservations around the new
`consume($expected, $actor, $code)` signature, and update its fixtures to seed
held stock rather than location-selected consumption. Do not reintroduce a
source selector or add the unrelated #224 changes to this slice.

## Intentionally out of scope

Undo, code correction, new movement types, location fallback, permission
redesign, Holdings/My stock redesign and event-wide reports/search/export.
No new navigation, dependencies or `given_by` field are added.

## Verification

- Feature coverage: own-stock artist/vendor/Team writes, admins without Team,
  inherited permissions, missing/zero stock and no fallback, actor-only response
  data, stale/revoked permissions, foreign IDs, invalid recipients, validation,
  duplicate issuance, locks, historical location audit and deletion guards.
- Migration coverage: refuses unrepresented history, preserves linked history
  and ledger balances, removes the column and refuses lossy rollback.
- PostgreSQL concurrency coverage: duplicate issuance, last unit shared by two
  entitlements, Consume versus Return in both arrival orders, and permission
  revocation while a Consume waits for the event lock.
- Mounted frontend coverage: popup-only counts, optional code and source-free
  payload, blocked dialogs, stale-stock errors, clean reopening, Audit toggle,
  Details, escaped names and successful refresh.

Validation on October 17, 2026:

- Full PostgreSQL suite: 852 passed, 11,920 assertions, including 31 real
  PostgreSQL concurrency cases, using isolated `ez_festival_198_test`.
- Full SQLite suite: 821 passed, 31 PostgreSQL-only skips, 11,568 assertions.
- Full frontend suite: 293 passed.
- Pint, Prettier, ESLint, production build and diff whitespace checks passed.
- Browser verification used a disposable SQLite database with synthetic users:
  no location selector, popup-only own count, disabled zero-stock action,
  successful last-unit consumption, refreshed status, Details and Audit columns.
  Database readback confirmed one linked give-out, its actor/source/recipient,
  and a zero source balance. No application errors were recorded in the browser.
- The application database was not migrated. The preview and test databases
  were separate from the configured application database.
