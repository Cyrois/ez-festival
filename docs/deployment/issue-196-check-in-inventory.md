# Check-in Inventory (#196)

Implemented locally on `codex/check-in-inventory`, based on `main` at `fc6fe6e`
after #214 and #215 merged. No commit, push, PR or merge was requested.

## Behavior and confirmed decisions

Check-in has People / Inventory tabs. View check-in alone retains the People
page without the Inventory tab. Either Inventory pass permission and Manage
inventory now include **Check people in and View check-in**, following Calvin's
confirmed change to #195's independent-permission rule. The Role editor displays
both included checks; server permission expansion enforces the same grants for
existing roles and direct role-save requests. Credentials write keys remain
independent.

My stock shows the signed-in Person's current-event items and recent movements.
Tables use server pagination at 25 rows. Staff receive no other-holder balances
or movement history. Manager-only JSON routes separately enforce authorization.
The actor is recorded separately from giver and receiver, and give-out receiver
names resolve through the linked issued entitlement. Names render as text.

Managers see Holdings with locations before people, item/type filters, literal
person-name search, per-item counts and **Total · all items**. Calvin approved
positive-stock holders only, all-item totals even when filtering an item, and
reuse of shared UI with holder details in a dialog. Zero-stock holders disappear
from this list without deleting history. Inactive holders retain their stock and
are flagged. Existing event/login revocation remains in place; no special
inactive-holder login or My stock exception is added.

Pull moves one item from a stocked current-event location to an eligible Person,
including the manager themselves or an admin without Team membership. Receiver
search and paging stay on the server; the shared CustomDropdown has opt-in remote
search and Show more support. Existing local dropdown behavior is preserved.

Return moves only the actor's own stock to a current-event location, including a
location with no existing stock. Selecting the item defaults to all held units;
partial returns work. Both flows require review/confirmation, refresh counts and
history after success, and show one Toast for each successful operation, including
consecutive identical operations. Reopening clears prior selections and stale
loading state, independent of Inertia's updated form defaults.

## Write safety and removal guards

Movement writes use Form Requests and repeat authoritative authorization,
eligibility and stock checks in the service transaction. Event locks are taken
first; relevant engagement/role/person/account records are locked and freshly
read before the existing ledger service checks balance and appends the movement.
Cached request permissions and displayed availability cannot authorize a stale
write. Stock never goes below zero and failed writes roll back holder creation.

Positive Person stock blocks hired-to-non-hired status changes, removal of event
access/role, and a Team Person rebind that would strand that Person's stock.
Guards cover the dedicated status endpoint, full/role-only Team edits and Global
Team, with request errors and service-level checks. Unauthorized edits fail their
permission checks before exposing stock-removal errors.

Turning Admin off is blocked by that Person's holdings at **any** event, including
locked and non-current events. Global Team locks the event set before stock and
identity changes so a simultaneous Pull cannot bypass removal protection. The
existing own-admin and last-admin rules remain. Once stock is cleared, ordinary
status/access/admin changes can proceed; history alone does not block them.

Person holders remain stable through joining Team. No stock moves or merges
because of membership, and no account/Team-engagement holders are reintroduced.

## Deployment and exclusions

No migration, backfill, dependency change or temporary nullability is introduced.
Deploy backend and frontend together. Existing Inventory roles gain both Check-in
permissions immediately through permission expansion, without a role-data rewrite.

**Release with #198.** This slice does not change location-selected Consume;
own-stock Consume and the issued-location migration belong to #198. Do not treat
#196 alone as the completed held-stock Check-in cutover.

Person transfers, return-to-manager and public Take back remain #197; the fuller
My stock summaries/mobile history behavior remain #211; event-wide history,
filters/search/export remain #199/#213. There are no placeholder buttons or new
sidebar destinations for those later slices. Tests clear stock through the
existing ledger to verify removal guards lift; no public recovery flow is claimed.

The existing People table privacy label now uses module translations so it also
renders inside DataTables' detached slot context. This fixes the runtime `$t`
error found while verifying People → Inventory navigation.

## Verification — October 14, 2026

- Full SQLite: **787 passed, 24 PostgreSQL-only skips**, 10,866 assertions.
- Full PostgreSQL: **811 passed**, 11,122 assertions, using isolated
  `inventory_196_test` on local PostgreSQL 17.
- Concurrency tests use independent connections for competing Pulls/Returns,
  role revocation, and both arrival orders of Pull versus status, Global Team
  event-access removal and admin removal.
- Feature coverage includes own-data isolation, direct unauthorized routes,
  foreign IDs/events, receiver eligibility, validation, insufficient stock,
  full/partial Return, locks, current/inactive holdings, pagination and literal
  searches, status/role/identity/admin guards, and clearance after recovery.
- Focused mounted frontend checks cover confirmation/cancel/error/success,
  quantity defaults, clean reopening, cancellation during remote loading,
  dropdown remote search/paging, DataTables' flat search request contract,
  escaped names and privacy translations without global injection.
- Full frontend: **257 passed, 1 pre-existing failure**. The existing unrelated
  `shiftTimelineRoster.test.js:642` failure expects a staged-hours payload without
  `is_supervisor: false`, as documented on #214/#215. No roster implementation or
  test was changed to hide it. All **14 focused Inventory/Check-in/dropdown tests**
  passed.
- Pint, Prettier, ESLint, production build and diff whitespace checks passed.
- Browser verification used disposable `inventory_196_preview`, synthetic users
  and a localhost-only preview. Pull/Return persisted the expected ledger actor,
  source/destination and quantities. Manager, ordinary-staff, inactive and locked
  states rendered correctly; the holder dialog fit 390px with no horizontal
  overflow. No application database was migrated or changed.

Screenshots are local verification artifacts in `/private/tmp/inventory-196-*.jpg`.
The local implementation spec in `docs/specs/issue-196-check-in-inventory.md`
records Calvin's confirmed decisions and initial investigation evidence.
