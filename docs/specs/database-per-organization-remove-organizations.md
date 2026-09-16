# Database-per-client architecture (keep organizations metadata + membership)

Status: Approved Calvin lock — one database per festival company; keep `organizations` + `organization_user`; no `organization_id` on child tables; no `application_state`

Scope: Application schema, tenancy assumptions, backend, frontend contracts, tests, and operations

## Summary

Move Artist Tree from row-scoped multi-tenancy to deployment-scoped, **database-per-client** isolation.

Each database contains exactly one festival company. The database connection is the tenant wall. Child domain tables (`events`, types, artists, labels, …) do **not** carry `organization_id` and must not join organizations for scoping.

Within that single-client database we still keep:

1. **`organizations`** — singleton metadata row: `name`, `active_event_id`, `setup_completed_at`
2. **`organization_user`** — membership (unique `organization_id` + `user_id`); event-level ACL is parked for a later PR
3. **`users.current_event_id`** — per-user primary/current event override

There is **no** `application_state` table. Setup complete + default/active event live on the organization row.

This assumes **one application deployment/runtime is configured for one organization database**. The deployment supplies organization identity and database credentials.

## Goals

- Make the organization database the only tenancy boundary for festival data rows.
- Keep organization membership (`organization_user`) so authenticated users must belong to the org.
- Keep organization metadata (name, active event, setup gate) on `organizations`.
- Preserve the setup wizard, default/active event, per-user current event, event locking, artist history, labels, and type lists.
- Keep fresh installations and automated tests simple (`migrate:fresh` clean creates).

## Non-goals

- Runtime host-based database switching in one Laravel process.
- Event-level user allow/deny ACL (parked).
- Reintroducing `organization_id` on events, types, artists, or labels.
- Cross-organization reporting or global user accounts.
- Changing event lock semantics or the artist advancing workflow.

## Target schema (clean create migrations)

Keep Laravel `0001_*` migrations. Domain migrations create:

1. **`organizations`**: id, name, active_event_id nullable (FK to events `nullOnDelete`, two-step), setup_completed_at nullable, timestamps
2. **`events`**: id, name, starts_on, ends_on, timezone, locked default false, timestamps — **no** organization_id
3. **`organization_user`**: id, organization_id FK cascade, user_id FK cascade, timestamps, unique(organization_id, user_id) — **no** event access columns yet
4. **`locations`**, **`vendor_types`**, **`artist_types`** — no organization_id
5. **`artists`** (name_key unique), engagements, labels (name_key), engagement label assignments, optional legacy artist_label_assignments, notes — no organization_id
6. **`users.current_event_id`** nullable FK events nullOnDelete

Ordered domain migrations:

1. `2026_09_11_000001_create_events_and_types_tables.php` — organizations, events, organization_user, locations, vendor/artist types
2. `2026_09_11_000002_add_current_event_id_to_users_table.php`
3. `2026_09_15_000001_create_artists_tables.php`
4. `2026_09_16_000001_create_artist_engagement_notes_table.php`

## Application design

### OrganizationContext

Single access point for:

- `name(): string` — prefer `config('organization.name')` / `ORGANIZATION_NAME`, fallback to `organizations.name`
- `organization(): Organization` — singleton `firstOrCreate(id=1)`
- `defaultEvent(): ?Event` — reads `organizations.active_event_id`
- `setDefaultEvent(Event)` — writes `active_event_id`
- `setupIsComplete()` / `markSetupComplete()`

### Membership

- `EnsureOrganization` middleware calls `User::ensureOrganization()` so the user is attached to the singleton org via `organization_user`.
- Seeder creates Test User and attaches membership; `setup_completed_at` stays null until the wizard finishes.
- Setup complete also calls `ensureOrganization()` so the completing user is a member.

### User event selection

- `User::effectiveEvent()` — user's `current_event_id` if present, else org active/default event
- `User::setCurrentEvent(Event)` updates `users.current_event_id`
- Setup sets both org active event and the completing user's current event

### Models

- `Organization` — belongsToMany User; belongsTo activeEvent; **no** hasMany events/types/artists via organization_id
- Delete `ApplicationState`
- Domain models query globally within the DB (the DB is the wall)

## Testing requirements

- Assert `organizations` and `organization_user` **exist**
- Assert **no** `application_state` table
- Assert **no** `organization_id` on events/artists/labels/types
- Setup from scratch: Test User can complete wizard; org `setup_completed_at` null until ready
- Per-user current event overrides org active event; deleted user event falls back to org default

## Acceptance criteria (Calvin lock)

- DB-per-client isolation; one DB = one festival company
- `organizations` and `organization_user` kept
- No `application_state`
- No `organization_id` on child tables
- Setup + active/default event on organization row
- Event-level user access not built in this PR
- `users.current_event_id` for per-user primary/current event; membership-only pivot
