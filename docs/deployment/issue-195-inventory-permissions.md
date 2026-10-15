# Inventory permissions (#195)

This change was implemented in PR #215 after #193 / PR #214. Both are merged
into `main`, including the final Credentials review correction (`e424d78`).
No schema migration, permission backfill, deployment downtime, or temporary
nullability is introduced.

Roles gain independent `inventory.pass_shift` and `inventory.pass_group`
permissions. `inventory.manage` includes both, but grants neither Check people
in nor any Credentials write key. Existing `checkin.edit` and its implied
`checkin.view` remain unchanged; `inventory.handle` is not introduced. Existing
roles receive no new permission automatically; admins select the new keys.

Manage inventory opens the current event's Locations page and permits adding
and renaming names only. Requests refuse Type fields, including empty/null
values. Non-admin managers cannot delete locations or open other admin Settings
areas. Admins retain other-event access, optional types, and existing deletion
guards for shifts, issues, and stock history. Event locks continue to refuse
all writes while preserving reads. Personal Account access is unchanged.

Managers can read the Entitlements catalog, item details, location balances,
and the paginated issued log through #193's `view-entitlements` ability. Item
editing and stock adjustment still need their independent Credentials keys.
Products and Passes do not become available through Manage inventory alone.
Navigation and adjustment deep links respect these boundaries.

The #214 follow-up preserves ProductsController's named `view-credentials`
check and its original admin-only gate definition. The merge into #195 keeps
that definition alongside the Inventory read and Locations gates, without
granting additional role access. All 45 related regression tests, Pint,
Prettier, and ESLint passed after this prerequisite update.

The later #214 correction (`391d98f`) checks positive opening-stock permission
in creation authorization, returning 403 without `credentials.stock.adjust`.
Quantity validation uses the fixed integer/minimum-zero/maximum-2147483647
range. Item updates independently authorize item editing and ignore stock
fields. After merging this correction, all 65 affected Credentials,
Entitlements, event/Inventory permissions, target, and Locations tests passed,
as did Pint, formatting, and lint. The #195 feature scope is unchanged.

The final #214 review correction (`e424d78`) removed the duplicate Entitlements
edit route, moved issued-log reads into the Entitlement service, and preserved
read-only Pass views for locked events. The Inventory regression test now
asserts that the old named route is absent and its former URL returns 404.
The combined full SQLite and PostgreSQL suites passed with this assertion.
This follow-up changes only the test and these deployment notes.

## Receiver and manager queries

Calvin confirmed the following in the #195 specification conversation, which
is the implementation authority where the GitHub ticket still differs:

- A hired, login-enabled person with an account and active event role may
  receive location stock with **Check people in or Manage inventory**. Admins
  need login enabled and a Person, but need no Team engagement. Managers may
  receive a pull into their own Person holder. This supersedes the ticket's
  Check people in-only receiver rule and blanket requester exclusion for pulls.
- Return-to-manager targets need Manage inventory under those same hired,
  active-role, login/account conditions; login-enabled admins also qualify.
  Exclude the actor's Person for this list and for person transfers.
- Losing Check people in blocks a holder's Return action; a manager must take
  stock back. The actual Return/Take back routes belong to #196/#197. This PR
  does not introduce them or claim that their write gates are implemented.

`InventoryTargetsQuery` returns SQL query builders for later server-paginated
pickers and matching single-target checks. The queries use the existing PHP
permission expansion source, expose only Person ID/name, deduplicate admins
with Team records, and read current role/login eligibility rather than caching
it. `isManagerTarget` checks the destination only; later flows must authorize
their actor and revalidate the target inside their write transaction.

Stock continues to belong to `(event_id, person_id)` or a location. No holder,
balance, or history moves when someone joins the Team. No new public picker or
movement endpoint is exposed. Current Check-in still consumes from a selected
location until #196/#198 ship together; its permission remains Check people in.

## Intentionally out of scope

The Check-in Inventory tab, My stock/holdings screens, pass/return/take-back
flows, membership removal safeguards, own-stock Consume, and inventory reports
remain in #196–#199. There is no new navigation, dropped frame 57g, admin-account
holder fallback, credential creation change, or permission migration.

## Verification

- Full SQLite suite: 773 passed, 19 PostgreSQL-only skips, 10,579 assertions.
- Full PostgreSQL suite: all 792 passed, 10,758 assertions, including existing
  concurrency tests; isolated PostgreSQL 17 UTF-8 `inventory_195_test`.
- Full frontend suite: 249 passed; one pre-existing failure in
  `shiftTimelineRoster.test.js:658` expects a payload without
  `is_supervisor: false`. The same failure is documented on the #214 base.
- Focused inventory/permissions/location/Credentials tests: 45 passed.
- Pint, Prettier, ESLint, production build, and diff whitespace checks passed.
- Browser verification used a separate disposable preview database. Manager
  add/rename persisted, preserved Type, and offered no delete controls or
  forbidden Settings links. Entitlements and its issued log were readable
  without mutation controls. Locked Locations retained content and hid writes.
  Mobile verification at 390px had no horizontal overflow. Manage inventory
  visibly checked/disabled both included passes while Check people in remained
  unchecked. No browser errors/warnings appeared in these flows.

The application database was not migrated or changed. Synthetic preview data
and screenshots are local verification artifacts and are not committed.
