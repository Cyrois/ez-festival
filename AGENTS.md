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

## Artist Tree frontend rules

- Do not hardcode user-facing strings in Vue components. Put copy in `lang/*.json` and use `laravel-vue-i18n` (`$t` / `t()`).
- Style with Tailwind utility classes only. No scoped CSS, no large inline style blocks for layout/branding.
- Define design tokens in `tailwind.config.js` and mirror them in `@theme` in `resources/css/app.css`. Prefer `primary` / `secondary` for new work; `brand` / `accent` remain aliases of those same hex values so existing `bg-brand` / `text-accent` classes keep working. Also: `text-charcoal`, `bg-page`, `bg-ground`, `text-muted`, `border-line`, `text-danger`, `text-success`, `text-warning`, radius `0.5rem` (`rounded-lg` / `--radius`). Do not sprinkle raw hex in class strings.
- Compose screens from `resources/js/components/ui` (Button, Input, Textarea, Select, Checkbox, Radio, Switch, Badge, Label, Tag, FormField, Toast, Icon, Avatar, Tabs, SegmentedControl, Card, EmptyState, Table, …). Do not one-off restyle controls per page. Extend the kit in a PR when something is missing.
- Page body layout: center page content with Tailwind’s `container mx-auto` wrapper. If a page needs a narrower `max-w-*` body, retain `mx-auto` so it stays centered; do not leave constrained page bodies left-aligned. `AppLayout` already supplies the standard responsive page padding.
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

## Tenancy / data model

- Product is one Artist Tree app for music-festival back office; each festival company is a client/organization.
- **DB-per-client isolation:** one database = one organization. Do not put `organization_id` on child tables (events, types, artists, labels, vendors, custom_fields, custom_field_values, etc.). Do not join `organizations` into ordinary list/detail queries for scoping — the DB connection is the wall.
- Keep the `organizations` table (name, `active_event_id` / default event, `setup_completed_at`). Keep `organization_user` for org-level membership.
- Do not use an `application_state` table — setup/default event live on the organization row.
- Control-plane (org directory, DB routing across clients) is outside this tenant DB — do not build it unless locked.
- Event-level user access (who can open which event) is **parked** — do not build allow/deny event ACL until Calvin locks that slice. Org membership is enough for now.
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
- Event Settings: Events list + Locations / Roles / Users for the **primary** event; pages must state they edit the primary event and that Events is where you change primary.
- Event create/edit lives in the Settings shell (not regular AppLayout).
- Do not invent Settings nav items beyond what is locked/shipped.

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
- Org role = org-scoped (Settings, types, create event, People). Event work uses event roles — required for event access when that system lands.

## Frontend error handling

- Inertia forms: use `toastFormErrors` / `useFlashToast` (`showError` + `showFormError`) for validation failures (see Setup pages and Artists/View).
- App shells already use `useInertiaErrorToast` for flash + invalid responses — do not duplicate carelessly.

## Naming

- Product brand spelling in docs and UI copy: **Artist Tree** (two words). Use hyphenated **Artist-Tree** only for agent/team names (e.g. Artist-Tree Code Reviewer), not the product.
- Wall is called **organization**, not client (client retired in product language).
- Prefer existing i18n keys; don’t hardcode strings.
