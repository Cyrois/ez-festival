# Entitlement item labels (#222)

Show the item's current labels using the existing Tag colours across Check-in,
Consume, Credentials, Team pass previews, My stock, existing Holdings and holder
history, and Pull / Move stock / Take back pickers and review states.

## Confirmed behavior

- Order once on the server: natural numbers, case- and accent-insensitive name,
  then label ID when names compare equal. Browsers preserve that order.
- Check-in and item headings stack labels beneath the name. Dense tables and
  picker options put labels alongside the name on desktop, wrapping as needed.
- Below the existing `md` breakpoint, show the largest ordered prefix fitting
  one line, reserving the actual +N width. Keep at least one truncated label;
  a single long label has no +N. No labels means name only, except for the
  Entitlements list's existing muted dash.
- +N is a button naming the hidden labels. Its popup contains all current item
  labels in order, closes outside / on Escape / with Close, and returns focus.
  Native popover positioning escapes table and dialog clipping without nesting
  interactive buttons inside picker buttons.
- Preserve existing Entitlements search/filter controls; add none elsewhere.
- Keep the shipped combined Move stock flow. The 59-series mocks supply the
  label treatment; their older separate Return/Pass flows and history controls
  do not supersede confirmed product decisions.

## Deployment and compatibility

No migration, backfill, dependency installation, temporary nullability or label
snapshots. Deploy PHP and built frontend assets together. The entitlement list
cache key advances from v3 to v4; prior cached label values are not reused.
Label saves/deletes invalidate that event's list after commit, while assignment
writes retain the existing service invalidation. Roll back PHP and frontend
assets together if required.

Payloads add `labels` (id/name/color, empty array when absent); existing name,
quantity, balance, pagination and authorization behavior remain. Pass picker
options intentionally contain no stock balances. History looks up current
labels, so renaming/recolouring/removing labels changes old rows after reload.
The existing slate fallback handles unknown legacy colour tokens.

## Deliberately outside this change

No item colour, new label-management UI, new filters/sorting, CSV label columns,
stock movement changes, permission changes, navigation or report redesign.
The future Holdings grid and its label column heads belong to #225 (59h is
superseded); the fuller #198 / #199 / #211 screens should reuse this renderer
and server label contract when they ship.

## Verification

- Full PostgreSQL: 838 passed, 11,574 assertions (local DB_USERNAME override).
- Full SQLite: 809 passed, 29 PostgreSQL-only skips, 11,253 assertions.
- 55 affected frontend tests passed. Mounted label/picker tests cover fitting, resizing, no-label and long-label
  cases, popup dismissal/focus, and independent keyboard selection.
- Browser checks at 320px, 390px and desktop: current label payloads render;
  popups stay within the viewport and do not obscure Consume. Picker buttons
  are not nested. Existing list filters and desktop label column remain.
- Pint, Prettier, ESLint, production build and diff whitespace checks pass.
- Known pre-existing frontend failure: shiftTimelineRoster.test.js's staged
  hours expectation omits `is_supervisor: false`; that test and component are
  unchanged by this work.
