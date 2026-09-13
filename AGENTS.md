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
