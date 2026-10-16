# Person-to-person stock movements (#197)

My stock has one **Move stock** button and popup with an item, quantity and
combined destination picker. Locations and eligible managers/admins are always
available without a pass permission. Other eligible people use the shift/group
permission union; managers/admins may move to any eligible receiver. Locations,
managers, shared-shift people and other group members are grouped in the picker.
People are filtered, deduplicated and paginated on the server. A manager appears
once, under Managers, even when also on the giver's shift or in their group.

Move starts at quantity 1 and allows any positive whole quantity up to the held
balance. Review names the quantity, item and destination; Confirm performs the
movement. Cancel never moves stock. A location or manager destination always
records `return`; another eligible person records `transfer`. Classification is
rechecked on the server, including when a recipient becomes a manager after the
picker loaded. Take back remains separate because its source is another holder.

The single Move button replaces the former Return and Pass buttons and their
separate-popup/disabled-Pass requirement, as explicitly approved by Calvin in this
chat. It is disabled for locked events, inactive holders or no held stock. Losing
shift/group eligibility removes those people from the picker while locations and
managers remain available. An expired target on save returns to the editable form
with a validation Toast and refreshes the eligible people.

Calvin's confirmed specification in this chat governs two stale ticket passages:
- Shift passing uses the **parent shift's hours**, not individual assignment hours.
  Both people must be assigned to that shared shift. Its inclusive window runs
  from 30 minutes before its start through 30 minutes after its end, using the
  event's local wall clock. Multiple shared shifts form a deduplicated union.
- Group passing works off shift and has no time window.

`InventoryTargetsQuery::SHIFT_GRACE_MINUTES` is the single grace constant. The
picker and write-time pass eligibility use the same shift/group query.
Writes re-evaluate time and current roles, login, membership and target eligibility
inside the event transaction, after locking participants. Request-cached grants
and an earlier picker result cannot authorize a stale write.

Managers open a separate Take back popup from a person's Holdings detail. They
may move all or part of one item to their own Person holder, a location, or another
eligible person, with an optional note (up to 255 characters). The source can be
inactive; its consent and current receiving eligibility are not required. Taking
back to the source itself is refused. The source's My stock history names the
manager as "Taken back by …"; existing event-access rules still govern that page.
There are no additional alerts. Clearing every item releases the existing
un-hiring/event-access/admin-removal guards.

Move appends `return` for locations/managers and `transfer` for other people;
Take back appends `take_back`, with actor separate from source and destination. They
reuse the ledger's event/item/holder locks, nonnegative balance check, rollback
and post-commit Entitlements cache invalidation. Locked events refuse all writes.
Holders remain `(event_id, person_id)` or locations; joining Team never changes
holder identity or creates a membership transfer.

## Deployment

No migration, data backfill, permission remap, dependency change or temporary
nullability is introduced. Deploy backend and frontend together after #196. Its
release-together requirement with #198 remains; this change does not implement
own-stock Consume. Existing ledger types already support these movements, so
application rollback requires no schema downgrade or historical-data rewrite.

Return submissions choose exactly one of `location_id` and manager `person_id`.
The former location-only endpoint is retained and extended; no duplicate Return
write route is introduced. Pull keeps its separate location-and-person contract.
The combined UI uses these existing Return and Transfer write contracts according
to the selected destination. Only one new combined people-picker endpoint is
exposed; the draft separate manager/pass-picker endpoints were removed. All new
writes have Form Requests and all picker responses use API Resources.

## Intentionally out of scope

Own-stock Consume (#198), My stock summaries/history redesign (#211), event-wide
history filters/export (#199/#213), bulk moves, new navigation, clock-in changes,
and additional stock-removal or Credentials permissions.

## Verification

Feature coverage includes permission/target unions, admins, self exclusions,
full/partial movement types and actors, inactive recovery, literal server-side
search/paging, foreign IDs/events, ambiguous destinations, validation, stale
eligibility, locked events, guard clearance and event deletion. Frozen event-zone
clock tests cover exact grace boundaries, just outside them, different individual
assignment hours, overnight shifts and multiple shared shifts.

Real independent-connection PostgreSQL tests cover competing Pass/Take back and
Return/Pass for the last unit, group changes while a write waits, and manager or
receiver permission revocation. Mounted popup checks cover confirmation, cancel,
errors, reopening, quantity defaults, manager returns and all Take back targets.

The application database is not migrated or changed. PostgreSQL and browser
verification use separate disposable `inventory_197_test` / `inventory_197_preview`
databases. Verification results are recorded after final checks below.

### Original slice verification — October 15, 2026 (before popup combination)

- Full PostgreSQL: **833 passed**, 11,520 assertions, including real competing
  transactions and the final shift/group picker query.
- Full SQLite: **804 passed**, 29 PostgreSQL-only skips, 11,199 assertions.
- Final focused PostgreSQL Inventory/ledger-race selection: **62 passed**.
- Mounted popup tests: **9 passed**. Full frontend: **270 passed, 1 pre-existing
  failure** in `shiftTimelineRoster.test.js:642`; its expected staged-hours payload
  omits `is_supervisor: false`. Neither that test nor its component was modified.
- Laravel Pint, Prettier, ESLint, production build and diff whitespace checks pass.
- Browser verification on synthetic preview data confirmed Pass (12 → 9), Return
  to a manager (9 → 7), inactive-holder recovery to a location (5 → 0), and partial
  Take back to Me (7 → 5), with refreshed lists, confirmations and Toasts. SQL
  readback confirmed movement types, source/destination, actor and optional notes.
- The source holder's history rendered "Taken back by Preview Manager". Off-shift
  shift-only staff retained Return while Pass was visible and disabled. The final
  pass picker rendered its server-derived shift heading and shared Avatar options.
- Take back fit the 390px mobile viewport with all controls reachable. No browser
  warnings/errors were recorded in the verified flows. The temporary viewport was
  reset after testing.

Local verification screenshots: `/private/tmp/inventory-197-my-stock-history.jpg`
and `/private/tmp/inventory-197-take-back-mobile.jpg`. Synthetic fixture scripts,
preview services and databases are not committed.

### Combined Move stock verification — October 15, 2026

- Focused PostgreSQL inventory/permissions/targets and competing writes:
  **72 passed**, 1,244 assertions. Covers combined paging/search, deduplication,
  manager priority, no-pass/off-shift manager availability, exact shift grace
  boundaries, and server classification after recipient promotion to manager.
- Focused SQLite inventory/permissions/targets: **43 passed**, 923 assertions.
- Mounted popups: **9 passed**, including locations and managers recording Return,
  people recording Transfer, quantity 1, confirmation, stale target errors and
  unchanged Take back destinations. Pint, format, lint, build and whitespace
  checks pass.
- Browser: the same Move stock popup moved one unit each to an eligible person,
  a manager and a location. The held balance refreshed 5 → 4 → 3 → 2. History and
  SQL readback showed `transfer`, `return`, `return`, with the giver as actor.
  The combined picker showed Locations / storage, Managers and On your shift now;
  no browser warnings/errors occurred. Saved screenshot:
  `/private/tmp/inventory-197-move-stock-form.jpg`.
