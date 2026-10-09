<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Code style

### PHP

- PHP follows Laravel Pint with the `laravel` preset (`pint.json`).
- Indent with 4 spaces.
- Run `./vendor/bin/pint` (or `php vendor/bin/pint`) before opening a PR.
- Check without writing: `./vendor/bin/pint --test`.
- **Never inline `$request->validate([...])` in controllers.** Always use Laravel Form Request classes for incoming validation (e.g. `App\Http\Requests\Setup\StoreEventRequest`, `ContinueLocationsRequest` with `suggestions.*` rules). Controllers call `$request->validated()` only.
- **Response contracts:** JSON endpoints return API Resources (`JsonResource`) or dedicated response classes — do not hand-build ad-hoc JSON arrays in controllers. Inertia redirects may keep `RedirectResponse`, but validation still goes through a Form Request.
- Use conventional named controller actions such as `index`, `show`, `store`, `update`, and `destroy`. Do not use invokable controllers or define `__invoke()` methods.

### Vue / JavaScript

- Vue and JS under `resources/` use Prettier (`.prettierrc.json`).
- Critical: `singleAttributePerLine: true` — every Vue attribute/prop/param on its own line. Never put multiple attributes on one line.
- ESLint (`eslint.config.js`) enforces `vue/max-attributes-per-line` (max 1 on single-line and multi-line).
- Before a PR: `npm run format` then `npm run lint`.
- Check without writing: `npm run format:check`.

### SCSS / Tailwind build

- Global custom styles live in `resources/scss/`, loaded through `resources/scss/app.scss`; compose partials with Sass `@use` and use shallow nesting.
- Vite compiles Sass first, then `@tailwindcss/postcss` resolves Tailwind directives such as `@apply`, `@utility`, and `@variant`. Do not also enable `@tailwindcss/vite` or run Tailwind's own CSS through Sass.
- `resources/css/tailwind.css` is the CSS-only bootstrap for Tailwind/vendor imports, source scanning, configuration, and theme tokens. `_bootstrap.scss` preserves its plain CSS import for PostCSS.
- SCSS is for the existing shared gutter utility and third-party integration styling (such as DataTables). Keep Tailwind utilities in Vue templates, retain shared UI components, and do not add scoped styles or page-specific styling systems.
- Continue using theme variables / Tailwind `@apply`; do not duplicate the palette in Sass variables. Preserve cascade layers and selector specificity when nesting.

## Artist Tree frontend rules

- Do not hardcode user-facing strings in Vue components. Put copy in `lang/*.json` and use `laravel-vue-i18n` (`$t` / `t()`).
- Style with Tailwind utility classes only. No scoped CSS, no large inline style blocks for layout/branding.
- Define design tokens in `tailwind.config.js` and mirror them in `@theme` in `resources/css/tailwind.css`. Prefer `primary` / `secondary` for new work; `brand` / `accent` remain aliases of those same hex values so existing `bg-brand` / `text-accent` classes keep working. Also: `text-charcoal`, `bg-page`, `bg-ground`, `text-muted`, `border-line`, `text-danger`, `text-success`, `text-warning`, radius `0.5rem` (`rounded-lg` / `--radius`). Do not sprinkle raw hex in class strings.
- Compose screens from `resources/js/components/ui` (Button, Input, Textarea, Select, Checkbox, Radio, Switch, Badge, Label, Tag, FormField, Toast, Icon, Avatar, Tabs, SegmentedControl, Card, EmptyState, Table, …). Do not one-off restyle controls per page. Extend the kit in a PR when something is missing.
- For new or changed product dropdown controls, use `CustomDropdown` from `resources/js/components/ui/custom-dropdown`; do not use the native `Select` component for dropdowns.
- Page body layout: center page content with Tailwind’s `container mx-auto` wrapper. If a page needs a narrower `max-w-*` body, retain `mx-auto` so it stays centered; do not leave constrained page bodies left-aligned. `AppLayout` already supplies the standard responsive page padding.
- Fixed bottom form action bars must align their Cancel/Save row to the actual page body edges. Reuse the shell’s responsive wrapper (`container mx-auto px-4 md:px-6`) and give the inner action row the **same centered `max-w-*` value as that page’s body** (`max-w-6xl` body → `max-w-6xl` actions, `max-w-5xl` body → `max-w-5xl` actions). Do not copy another page’s width blindly. Keep Cancel left, Save right, respect `lg:left-[var(--app-sidebar-width)]`, and reserve body space (normally `pb-24`) so content is not hidden behind the fixed bar.
- UI glyphs: use the `Icon` component (Font Awesome Free SVG). Register needed icons in `resources/js/icons.js` — do not invent text-glyph icons (✓ / × / ⋯) and do not import entire `fas`/`far`/`fab` packs.
- Designer lock: use `Tag` for artist labels; use `Badge` (especially `pill`) for statuses. Do not swap those roles.
- Layout/data kit (`Avatar`, `Tabs`, `SegmentedControl`, `Card`, `EmptyState`, `Table`) is **light-only** until a dedicated dark-mode pass. Do not half-wire `dark:` on these primitives in product screens yet.
- `EmptyState` is for page/section voids (dashed border). In-table empty: one `TableCell` with colspan + muted copy (or a future `TableEmpty` slot) — do not nest `EmptyState` inside `Table` borders.
- Tiny visual gallery: authenticated `/ui` (`resources/js/pages/Ui/Index.vue`) for Designer pass on the current slice.
- Auth screens: small centered form, teal primary (`primary` / `brand` / `#1F7A74`), soft blue links (`secondary` / `accent` / `#3D6B8A`), page ground (`page` / `#F4F5F7`), charcoal text (`charcoal` / `#1A1A1A`). Gray borders use `line` (`#E5E7EB`) so Tailwind’s default `border` color is not clobbered.
- Setup wizard screens (`resources/js/pages/Setup/*`, `resources/js/layouts/SetupLayout.vue`): same Tailwind + i18n rules; match Designer admin-setup mock (sidebar with Setup active, step pills, Skip + Save and continue). Shared Toast covers setup form errors.
- Match Designer mocks when implementing screens.
- No `.claude/` / CLAUDE.md.

JSON locale files live at repo-root `lang/` (e.g. `lang/en.json`), not `resources/lang`. Wire `i18nVue` in `resources/js/app.js` with `import.meta.glob('../../lang/*.json')`.

## Process / HARD STOP

- Only build what Calvin explicitly locked for the current slice. No hasty pages, interim nav, unsigned UI, or invented navigation.
- If unclear whether something is locked, stop and ask — do not invent product behavior.
- Keep PRs small enough to review in a few minutes; stack branches when useful. Large PRs only for necessary structuring/refactors.
- After every new PR or meaningful update on Cyrois/ez-festival, the human workflow pings Artist-Tree Code Reviewer with PR number, URL, branch, one-line summary (agents should leave a clear PR body for that).
- Do not merge unless Calvin explicitly says to merge.
- Before PRs: `vendor/bin/pint`, `npm run format`, `npm run lint`, relevant tests.


## Codex failure modes (HARD)

These are recurring Codex mistakes on this repo. Treat them as hard stops — do not repeat them.

### Bootstrap / AGENTS.md

- If Laravel Boost / Pint / the existing `AGENTS.md` product rules are already present, **do not** re-run `composer require laravel/boost`, `php artisan boost:install`, or rewrite the boost bootstrap block at the top of this file.
- Prefer **appending** new hard rules under this section (or the relevant domain section). Do not replace Calvin’s product locks with generic Boost boilerplate.

### Migrations & schema

- **Never rewrite an already-shipped `create_*` migration** that may have run in any environment. Change defaults/columns with a **new** forward migration.
- Production runs on **PostgreSQL**; SQLite is only supported for automated tests. Index, constraint, and foreign-key names must be at most 63 bytes because PostgreSQL silently truncates longer names. Pass an explicit short name when Laravel's generated name would exceed that limit.
- Deleting an event goes through `EventService::delete`. When adding an event-scoped table with a `restrict` foreign key, delete its rows there in child-first order and cover it in the comprehensive event-deletion test.
- Do not assume a row has a particular id in code, seeders, or tests. PostgreSQL sequences do not reset when a test transaction rolls back.
- On PostgreSQL, one failed statement aborts its transaction. Do not query after catching a database error unless the failing statement ran inside a nested `DB::transaction()` savepoint.
- `down()` methods must not be lossy for shared remaps (e.g. mapping every `teal`/`slate` row back to a legacy token wipes post-refactor data). Prefer irreversible `down()` with a comment when remap is one-way.
- Do not add schema columns that are unused in the same PR (e.g. `event_id` on values tables with no read/write path). **Wire them in the same PR or do not add them.**
- Column names must **not** collide with Eloquent relation method names (e.g. a `notes` text column vs `notes()`). Rename the column or the relation before shipping.
- After DB-per-client, **never reintroduce `organization_id`** (or equivalent) on tenant child tables.

### Location-scoped inventory & FK deletes

- If stock / adjustments / entitlements are location-scoped, **every write path** (adjust, consume, issue, reverse, opening balance when qty ≠ 0) must require an **event-scoped** `location_id` end-to-end: Form Request → service → DB. No null / “Unassigned” writes on those paths.
- When a FK uses `restrict` / `restrictOnDelete`, the destroy Form Request (and service) must **block with a validation/domain error** before the database throws a 500.
- Prefer NOT NULL (or a dated follow-up migration to NOT NULL) once product forbids nulls — do not leave permanent nullable “temporary” FKs without a plan called out in the PR body.

### Refactors & leftover surface area

- When lifting a feature from one domain to global (e.g. artist check-in → global check-in), **rename** routes, controllers, Form Requests, resources, Vue pages, i18n keys, and tests to match the new scope in the same PR.
- Delete dead stubs and **do not leave dual write routes** (old + new POST) after a move.
- Remove orphan Resources, filters, composables, and pages left behind by the move.
- Deep-link targets (`id="…"`, query params, hash anchors) must land on the **actual** UI element (e.g. passes panel), not a sibling card.

### Shared constants & UI reuse

- Card section headings use `CardTitle` from `resources/js/components/ui/card`. Keep title typography in that component; callers may set spacing and heading level but must not restyle its font, size, or color.
- Shared enums / color tokens / label palettes have **one server source of truth** (PHP support class or equivalent). Vue must import a generated module, receive props from the backend, or share one module — **do not triplicate** maps across Tag / Combobox / PHP.
- Prefer existing UI kit components (`Tag`, `Badge`, tables, dialogs). Do not reintroduce one-off checkbox/button pickers where a shared combobox/tag pattern already exists.
- Do not leave duplicate selected-chip rows beside a combobox that already shows removable chips.

### Lists, authorization, performance

- **Clickable table standard (Calvin, 2026-10-09):** Lists that open a row's detail/view page use the shared `DataTable`, `navigateDataTableRow` from `resources/js/lib/dataTableRowNavigation.js`, and a final narrow, right-aligned, unlabeled chevron column (`Icon` with `chevron-right`). The chevron is a real link with a translated accessible label; it opens the same destination as the row and supports keyboard navigation. For dialog actions use `activateDataTableRow` and a labeled chevron button instead. Keep secondary links/actions independent; row navigation must ignore interactive controls and selected text. Do not add a repeated primary View/Edit/Check in button to every navigable row. Keep existing permission/lock gates and server-side filtering/pagination rules.
- Production index/list pages with tabular data must render with the shared `DataTable` component (`resources/js/components/ui/data-table`). Do not hand-build sortable tables, search wiring, or pagination controls when `DataTable` covers the list. Use its `serverSide` mode when the query is server-paginated.
- Production index/list pages: **filter and paginate on the server** by default. Do not hydrate unbounded tables into memory and filter in PHP or the browser.
- **Exception (Calvin, extended 2026-09-30): Vendors, Artists, Entitlements, Passes, and Settings → Events only.** These lists stay small enough to load their full scoped set and search/sort/filter/paginate client-side with the shared `DataTable` component (`resources/js/components/ui/data-table`), provided:
  - the query stays scoped to the current event — never cross-event;
  - Vendors and Artists stay around 200 rows or fewer — if a list could grow past that, switch to server paging (or DataTables `serverSide`);
  - sort and search use raw values, not rendered slot HTML (use `render: { display: '#slot' }` for slot cells);
  - Entitlements and Passes cache their list lookups per event; Settings → Events caches its organization-wide lookup; every create, update, delete, entitlement adjustment, label change, and event lock/unlock clears the affected cache;
  - every other list (Global Team, event Team, Patrons, check-in, Roles, Scheduling, and anything new) still filters and paginates on the server unless Calvin decides otherwise.
- **Exception (Calvin, 2026-10-03): Schedule → Shifts & People loads every shift and full roster for one selected location/day in a single response. The selected day is the bound; do not paginate or cap its shifts or people. Keep roster and overlap queries batched.**
- **Exception (Calvin, 2026-10-03, #144): The Edit shift page's Roster is a dedicated timeline showing the complete roster for one saved shift. Use shared UI primitives and batched roster/overlap loading; no DataTable, pagination, row cap, or collapsed rows in this timeline. Assignment candidate search and other people lists keep their existing pagination rules.**
- **Exception (Calvin, 2026-10-07, #174): The Team member page's Meals section loads the complete meal set for one member in the current event. Use the shared `DataTable` with client-side pagination at 10 rows per daily table. This does not change Kitchen's server pagination or other people-list rules.**
- **Exception (Calvin, 2026-10-08, #175): Reports → Meals shows the complete aggregate report for one current event using the shared `DataTable` without pagination, search, or user sorting. Aggregate grants and claims in SQL; send one row per named meal ID, including its name, date, type and counts. Do not merge separate meals sharing a date/type. Configured meals with no grants or claims show zero counts. Claimed overrides count within Used and in the Extras subset. Remaining is max(Projected − Used, 0) per meal row; Total is Projected − Used − Extras, without clamping. Do not add day or event total rows.**
- Any table that queries or joins `users` or `people` — including Global Team, Team → Advancement, and team member lists — must paginate on the server, never in the browser. Global Team specifically uses the shared `DataTable` in `serverSide` mode.
- Do not `Gate::authorize` / policy-check **per row in a loop** when a single ability plus a query scope is enough.
- Client-only chips/filters for types that always return empty are not “global” — either wire the data or hide the chip until the type exists.

### Stubs, product invention, tests

- Do not invent product behavior. If the slice is a draft, say so in the PR body and leave explicit `TODO`s — do not ship `window.prompt` / placeholder QR / fake flows as if finished.
- Feature tests for write paths must cover at least: wrong-event / foreign id, locked event, already-consumed / conflict, destroy-with-children (FK restrict), and validation failure via Form Request (not only happy path).

### PR hygiene

- PR body must list: intent, what is intentionally out of scope, migration/deploy notes, and any temporary nullability.
- Keep PRs reviewable; do not mix unrelated refactors with feature work.
- Ping Artist-Tree Code Reviewer after opening or meaningfully updating the PR (number, URL, branch, one-line summary).

## Tenancy / data model

- Product is one Artist Tree app for music-festival back office; each festival company is a client/organization.
- **DB-per-client isolation:** one database = one organization. Do not put `organization_id` on child tables (events, types, artists, labels, vendors, custom_fields, custom_field_values, etc.). Do not join `organizations` into ordinary list/detail queries for scoping — the DB connection is the wall.
- Keep the `organizations` table (name, `active_event_id` / default event, `setup_completed_at`). Keep `organization_user` for org-level membership.
- Do not use an `application_state` table — setup/default event live on the organization row.
- Control-plane (org directory, DB routing across clients) is outside this tenant DB — do not build it unless locked.
- Event-level user access (who can open which event) is **parked** — do not build allow/deny event ACL until Calvin locks that slice (Roles 2 #81 delivers this). Org membership is enough for now.
- Per-user current/primary event: `users.current_event_id` (fallback to org active/default event). Primary is only the default open event on login — not a write gate.

## Write / lock gates

- An event does **not** have to be the user’s primary to be writable.
- Writes blocked only when the event is **locked** (or user not in org).
- Source of truth: `Event::isLocked()` / `Event::ensureWritable()` (throws when locked). Inertia may expose `is_locked`, `is_read_only` (same as locked), and page `canWrite` as `! $event->isLocked()` — do not invent a separate primary-based write flag.
- Locked/archived events are read-only for audit; Owner can lock for now.

## Settings IA

- On Settings pages: hide main App sidebar; show only Settings sidebar.
- Groups: Organization Settings, Event Settings.
- Top of Settings sidebar: Back to Dashboard.
- Global Settings has one org-wide **Roles** list (`/settings/roles`). Roles are global to the organization (no `event_id`, no per-event Roles tab, no copying roles between events). Roles are turned off, not deleted.
- Event Settings: Events list + Locations / Users for the **primary** event; pages must state they edit the primary event and that Events is where you change primary.
- Event create/edit lives in the Settings shell (not regular AppLayout).
- Do not invent Settings nav items beyond what is locked/shipped.

## App sidebar

- On desktop, the user name, email, and gray outlined sign-out icon live at the top-right of the app header. They do not appear in the desktop sidebar.
- On mobile, the same user controls remain fixed at the bottom of the off-canvas drawer, with at least 44px tap targets.
- Settings is the final item in the scrollable main navigation list, not a pinned footer item.
- The desktop collapse control is a full-width row pinned at the bottom of the sidebar. A collapsed desktop sidebar temporarily expands on hover without changing the saved preference. Preserve the normal expanded navigation on mobile regardless of the stored desktop collapse preference.

## Setup wizard

- First-run setup: focused shell (no app sidebar + top breadcrumbs). Wordmark + step pills + form ~max-w 720px centered.
- Event step is required (no Skip until a saved event exists). Locations / Vendor types / Artist types may skip.
- Suggested defaults show as “Suggested · not saved until Continue”; persist only on Save and continue — do not seed on page load/event create.
- Required labels: red *. Empty required → red input + error Toast.
- Ready step: big green check, “You’re all set…”, note configs under Settings, Next → dashboard.

## Artists

- Advancing list + create + **View** (not “Edit”) for engagement details.
- Route/page naming: View (`artists.view`, `Artists/View.vue`).
- Details | Note log split; Back left of breadcrumbs; Cancel/Save on Details when writable.
- Name is on the reusable artist; Status + Type on the engagement; **labels are engagement-scoped** (not org-artist-wide sync).
- Statuses: `idea|outreach|negotiating|contract_sent|confirmed|declined`.
- Notes: append-only, newest first; `user_id` nullable `nullOnDelete`; no fee in this phase; contracts phase-2 label only.
- Artist Advancing = full artist-flow access via event role (not notes-only) — formal role gate waits on People; do not build People assign UI yet.
- Case-insensitive artist/label uniqueness via `name_key` in the tenant DB.

## Patrons / vendors / crew (domain locks)

- Patrons: never backoffice login. Fields name*, email*, phone, do_not_contact, custom fields. Not tickets. Access/invite/roles live on people/membership, not patrons.
- Artists/vendors: no login for now unless a later lock says otherwise.
- Tickets/credentials/orders: schema may exist in docs/locks but checkout is not first build unless Calvin locks a slice — do not invent checkout.

## Permissions (general)

- A screen, button, or stored file (contract, fee, etc.) only opens if the person’s role includes the permission. New features get a permission from the start. Do not ship gated features without a permission hook when roles exist.
- Roles come from the one org-wide Roles list in Global Settings (Calvin, 2026-09-27; replaces the 2026-09-24 per-event rule). A person gets access to an event by holding a role on their Team record for that event; the same role can be held at several events. An off role grants nothing. Permissions resolve on each request for the current event, never cached in the login session or token. Permission checks come later — until then gates allow any signed-in user.

## Frontend error handling

- Inertia forms: use `toastFormErrors` / `useFlashToast` (`showError` + `showFormError`) for validation failures (see Setup pages and Artists/View).
- App shells already use `useInertiaErrorToast` for flash + invalid responses — do not duplicate carelessly.

## Naming

- Product brand spelling in docs and UI copy: **Artist Tree** (two words). Use hyphenated **Artist-Tree** only for agent/team names (e.g. Artist-Tree Code Reviewer), not the product.
- Wall is called **organization**, not client (client retired in product language).
- Prefer existing i18n keys; don’t hardcode strings.

## Testing on PostgreSQL

- The default `composer test` suite uses in-memory SQLite.
- Before a PR that changes migrations or queries, create the test database once with `createdb ez_festival_test`, then run `composer test:pgsql`. Override `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, or `DB_PASSWORD` in the shell when local PostgreSQL settings differ.
