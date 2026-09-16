# Database-per-organization architecture: remove organization tables

Status: Approved and implemented for fresh and single-organization databases

Scope: Application schema, tenancy assumptions, data migration, backend, frontend contracts, tests, and operations

Repository reviewed: `ez-festival` at `7eefa45`

## Summary

Move Artist Tree from row-scoped multi-tenancy to deployment-scoped, database-per-organization isolation.

The target application has no `organizations` table, no `organization_user` pivot, no `organization_id` columns, no `Organization` model, and no organization-scoping middleware or query clauses. Every database contains exactly one organization's users and festival data. The database connection selected for the deployment is the tenant boundary.

This spec assumes **one application deployment/runtime is configured for one organization database**. The deployment supplies the organization identity and database credentials. The application does not select a tenant database from the request host at runtime.

That assumption is important. A shared runtime serving multiple organization databases would require a trusted central organization registry, host-to-organization resolution before sessions/authentication, tenant-aware queue payloads, cache and filesystem scoping, and safe connection reset for long-lived workers. None of that infrastructure exists in this repository, and deleting `organizations` without adding it would make isolation unsafe.

## Goals

- Make the organization database the only tenancy boundary.
- Remove organization membership and row-level organization scoping from the application.
- Preserve the current setup wizard, default event, per-user event choice, event locking, artist history, labels, and type lists.
- Provide a safe path for splitting an existing shared database into one database per organization.
- Keep fresh installations and automated tests simple.
- Make cross-organization data access impossible through ordinary application queries because another organization's rows are not present on the active connection.

## Non-goals

- Runtime host-based database switching in one Laravel process.
- A central control-plane database for organization provisioning, billing, or support access.
- Cross-organization reporting or global user accounts.
- User roles and permissions; the current repository has placeholder UI but no role model.
- Changing event lock semantics or the artist advancing workflow.
- Migrating cache entries, sessions, or queued jobs between organizations.

## Current-state findings

The current `Organization` record has five separate responsibilities:

1. **Tenant boundary** — `organization_id` scopes events, artist/vendor types, artists, and labels.
2. **Membership** — `organization_user` associates users to organizations.
3. **Organization metadata** — `organizations.name` supplies the setup and application-shell name.
4. **Application state** — `active_event_id` and `setup_completed_at` control the default event and setup gate.
5. **Concurrency mutex** — `ArtistService` locks the organization row before case-insensitive artist and label creation.

Removing only the table and foreign keys would therefore break behavior in several places.

### Schema dependencies

| Current table | Organization dependency | Target |
| --- | --- | --- |
| `organizations` | Tenant record, name, default event, setup state | Remove |
| `organization_user` | Membership and per-user `current_event_id` | Remove; move `current_event_id` to `users` |
| `events` | `organization_id` | Remove column |
| `vendor_types` | `organization_id` | Remove column; all rows belong to the organization database |
| `artist_types` | `organization_id` | Remove column; all rows belong to the organization database |
| `organization_artists` | `organization_id` and organization-specific unique name | Rename to `artists`; remove column |
| `artist_labels` | `organization_id` and organization-specific unique name | Remove column |
| `locations` | Scoped indirectly through `events` | No structural change |
| `artist_engagements` | Scoped indirectly through artist and event | No tenancy column; retain relational checks |
| `artist_label_assignments` | Scoped indirectly through artist and label | No structural change |

### Code dependencies

Organization behavior currently appears in routes, four middleware/support classes, approximately twenty controllers and requests, seven models, the artist repository/service, factories, the feature test suite, shared Inertia props, setup pages, application layouts, and user-facing copy.

The most consequential dependencies are:

- `User::ensureOrganization()`, `primaryOrganization()`, `effectiveEvent()`, and `setCurrentEvent()`.
- The `organization` route middleware and both setup/settings organization helper traits.
- Cross-organization ownership checks in controllers and Form Requests.
- Relationship-rooted queries such as `$organization->events()` and `$organization->artistLabels()`.
- Organization-scoped validation rules for artist types and labels.
- The organization row lock in `ArtistService`.
- Shared Inertia prop `organization` and setup page prop `organization`.

## Target architecture

### Isolation model

- Each organization has a dedicated database and a dedicated deployment configuration.
- All application tables, including `users`, live in that organization database.
- Authentication is organization-local. The same email may exist independently in multiple organization databases.
- Laravel's default database connection remains the only application connection used by web requests and normal jobs.
- A deployment must have an immutable `ORGANIZATION_KEY` for operational identification and a display `ORGANIZATION_NAME`. Neither value is used to authorize database access; database credentials provide the boundary.
- `ORGANIZATION_KEY` is used in logs, deployment inventory, backup names, and any shared Redis/filesystem prefix. It must not be derived from untrusted request input.
- `ORGANIZATION_NAME` replaces `organizations.name` in shared UI props. `APP_NAME` remains the product name, `Artist Tree`.

### Organization-level application state

Create a singleton `application_state` table and an `ApplicationState` model:

| Column | Type | Notes |
| --- | --- | --- |
| `id` | unsigned big integer | Always `1`; created during provisioning or lazily by one idempotent service |
| `default_event_id` | nullable foreign key to `events` | `nullOnDelete`; replaces `organizations.active_event_id` |
| `setup_completed_at` | nullable timestamp | Replaces `organizations.setup_completed_at` |
| timestamps | timestamps | Audit when global state changed |

This table holds only application workflow state. It is not a tenant directory or membership table.

Add a small service, for example `OrganizationContext`, as the single access point for:

- `name(): string` from `config('organization.name')` / `ORGANIZATION_NAME`.
- `state(): ApplicationState`.
- `defaultEvent(): ?Event`.
- `setupIsComplete(): bool`.
- `markSetupComplete(): void`.
- `setDefaultEvent(Event $event): void`.

Controllers and middleware must not query a singleton row ad hoc or assume it already exists.

### User event selection

Add nullable `users.current_event_id`, constrained to `events` with `nullOnDelete`.

Refactor the existing behavior as follows:

- `User::effectiveEvent()` returns the user's current event when it still exists; otherwise it returns `OrganizationContext::defaultEvent()`.
- `User::setCurrentEvent(Event $event)` updates `users.current_event_id`.
- The initial setup event is set as both the organization default and the completing user's current event.
- A newly provisioned or invited user with no current event inherits the organization default.
- Deleting a selected event clears the foreign key; the next request falls back to the organization default.

Use “current event” consistently in code. The UI may continue to say “Primary” until a separate copy change is approved.

### Target domain schema

1. Remove all `organization_id` foreign keys and indexes.
2. Rename `organization_artists` to `artists` and remove `Artist::$table`.
3. Keep `artists` reusable across events through `artist_engagements`.
4. Make artist and artist-label names unique within the organization database, which is equivalent to the existing organization-level intent.
5. Preserve original display casing while enforcing case-insensitive identity.

The current organization row lock serializes the application-level case-insensitive lookup. It must not simply be deleted. Replace it with database-enforced normalized uniqueness:

- Add `name_key` to `artists` and `artist_labels`.
- Populate it with a documented normalization function, initially `mb_strtolower(trim(name))`.
- Add a unique index on `name_key` in each table.
- Set `name_key` in the model/service for every write and backfill it during migration.
- Keep the existing retry-after-unique-violation behavior for concurrent creates.

This is portable across the repository's PostgreSQL production configuration and SQLite tests, and avoids using a settings row as a global mutex.

Do not add `organization_id`, `tenant_id`, or a renamed organization foreign key to tenant databases. That would retain row-scoped tenancy without providing isolation value.

## Application changes

### Models

- Delete `app/Models/Organization.php`.
- Remove `User::organizations()`, `primaryOrganization()`, and `ensureOrganization()`.
- Refactor `User::effectiveEvent()` and `setCurrentEvent()` to use `users.current_event_id` and `OrganizationContext`.
- Remove `organization()` relations and `organization_id` fillable fields from `Event`, `Artist`, `ArtistLabel`, `ArtistType`, and `VendorType`.
- Change `Event::ensureWritable(Organization $organization)` to `ensureWritable()`. It continues to reject locked events but no longer checks an organization foreign key.
- Add `ApplicationState` and the `OrganizationContext` service.
- Rename the artist backing table to `artists` and remove the explicit table override.

### Middleware, routing, and login flow

- Delete `EnsureOrganization`.
- Remove the `organization` middleware alias from `bootstrap/app.php`.
- Remove `organization` from authenticated and setup route groups.
- Refactor `EnsureSetupComplete` and `PostLoginRedirect` to use `OrganizationContext::setupIsComplete()`.
- Refactor `PreventLockedEventWrites` to resolve the user's effective event and call `Event::ensureWritable()` without an organization.
- Keep authentication before all organization data routes.

No middleware is needed to assert tenant membership: successful connection to the deployment's configured database establishes the tenant context, and every authenticated user in that database belongs to that organization.

### Controllers and requests

- Refactor `InteractsWithSetup::organization()` to return `OrganizationContext`; retain the timezone helper.
- Replace `InteractsWithSettings::eventForOrganization()` with a concern/helper that handles event payloads and timezones only.
- Query `Event`, `ArtistType`, `VendorType`, `Artist`, and `ArtistLabel` directly on the active database.
- Remove cross-organization `organization_id` checks from event, setup, and settings controllers.
- Retain parent-child checks that still matter, such as ensuring a `Location` belongs to the route's `Event`.
- Update lock/unlock authorization to require an authenticated user and the correct current lock state.
- Update “set primary” authorization to compare the selected event with `User::effectiveEvent()`.
- Remove organization predicates from `Rule::exists` rules for artist types and labels.
- Continue to reject writes to a non-current event in artist advancing, as the current feature does.
- Continue using Form Requests for all incoming validation and API resources/dedicated response contracts where applicable.

### Artist repository and service

- Remove `Organization` parameters from repository and service methods.
- `paginateEngagements()` scopes by the selected event; no artist organization predicate remains.
- `labelsFor()` queries `ArtistLabel` directly.
- `findOrCreateArtist()` and `findOrCreateLabel()` query by `name_key`.
- `addToEvent(Event $event, array $data)` locks the target event, calls `ensureWritable()`, and relies on unique `name_key` constraints for catalog concurrency.
- Update error copy tied to row-scoped membership to say “your festival” where that is clearer for users. Keep “organization” as the internal domain term.

### Inertia and Vue contracts

Keep the shared `organization` prop, but back it with `OrganizationContext` instead of an `Organization` database row:

```text
organization: {
  key: string,             // optional in browser output; omit unless the UI needs it
  name: string,
  setup_completed: boolean
}
```

- Setup controllers continue to pass `organization`.
- Setup pages continue to use the `organization` prop and `organizationName` layout prop.
- `SetupLayout` renders `ORGANIZATION_NAME`; it must have a safe fallback such as `Festival setup`.
- `AppLayout` uses the current event name first and organization name second, preserving the existing display order.
- Keep JavaScript variables such as `organizationName` and `organizationItems`; removing the database table does not change the domain terminology.
- Review all seven organization-related locale strings. Remove claims tied to organization membership, but do not mechanically replace valid business-language uses of “organization.”

## Database migration strategy

There are two supported paths. Choose one per environment.

### Path A: no production data

Use this for disposable development, test, and unreleased environments.

1. Replace the original schema migrations with the target schema.
2. Delete the organization pivot migration.
3. Recreate the database with `migrate:fresh`.
4. Seed one `application_state` row and a organization-local admin user.
5. Configure `ORGANIZATION_KEY`, `ORGANIZATION_NAME`, and the organization database credentials.

This produces the cleanest migration history, but it must never be used against an environment with data that must be retained.

### Path B: existing shared data

Do not run a destructive “drop organization columns” migration on the shared source. Build new target databases and copy one organization at a time.

#### Preflight

1. Back up the shared database and test restoring it.
2. Inventory every organization and its user memberships.
3. Decide how to handle users belonging to multiple organizations. Recommended: copy the user, including its password hash, into every applicable organization database; the accounts become independent after cutover.
4. Validate that every organization-scoped row has a valid organization and that indirect relationships do not cross organizations:
   - each location's event belongs to the organization being exported;
   - each engagement's artist and event belong to the same organization;
   - each label assignment's artist and label belong to the same organization;
   - each pivot `current_event_id` belongs to that pivot's organization;
   - `active_event_id` belongs to the organization.
5. Detect case-insensitive duplicate artist and label names within each organization before creating `name_key` unique indexes. Produce a conflict report; do not silently merge records.
6. Drain or stop queue workers and schedule a maintenance window unless a separate change-data-capture plan is approved.

#### Copy mapping

For each source organization, provision an empty target database at the new schema version and copy in a transaction or from one consistent source snapshot:

| Source | Target transformation |
| --- | --- |
| `organizations.name` | Deployment `ORGANIZATION_NAME` |
| `organizations.active_event_id` | `application_state.default_event_id` |
| `organizations.setup_completed_at` | `application_state.setup_completed_at` |
| members from `organization_user` + `users` | `users`; pivot `current_event_id` becomes `users.current_event_id` |
| organization `events` | `events` without `organization_id` |
| event `locations` | unchanged rows |
| organization `vendor_types` | `vendor_types` without `organization_id` |
| organization `artist_types` | `artist_types` without `organization_id` |
| organization `organization_artists` | `artists` without `organization_id`, with `name_key` |
| organization `artist_labels` | `artist_labels` without `organization_id`, with `name_key` |
| matching `artist_engagements` | unchanged rows and foreign keys |
| matching `artist_label_assignments` | unchanged rows and foreign keys |

Preserve primary keys during the copy. Empty target databases make this safe and it avoids unnecessary foreign-key remapping. Reset each sequence/identity after import.

Preserve password hashes, verification timestamps, and user profile data. Clear `remember_token` and do not copy sessions so every user signs in against the correct organization deployment after cutover.

Do not copy cache rows, sessions, queued jobs, failed jobs, job batches, or migration history from the source. Run target migrations normally, start with empty ephemeral tables, and explicitly re-enqueue any business-critical job after cutover.

#### Verification

Before routing traffic, record and compare per-organization counts for users, events, locations, types, artists, labels, engagements, and label assignments. Also verify:

- all foreign keys resolve;
- no source row for another organization is present;
- configured default/current event IDs exist in the target;
- normalized artist and label names are unique;
- setup completion matches the source organization;
- a representative user can authenticate and see the expected event and artist data;
- locked events remain read-only.

#### Cutover and rollback

1. Put the source deployment in maintenance/read-only mode.
2. Take the final consistent snapshot and rerun or finalize the copy.
3. Run verification and smoke tests.
4. Start the organization deployment and its queue workers.
5. Route only that organization's hostname to the deployment.
6. Retain the shared source database read-only for the agreed rollback period.

Rollback means routing the organization back to the unchanged shared application/database. Do not write back from a organization database into the shared source. If organization deployments have accepted writes, rollback requires a separate reconciliation plan.

## Provisioning and operations

Create one documented provisioning command or deployment workflow that:

1. creates the database and least-privilege database credentials;
2. configures `ORGANIZATION_KEY`, `ORGANIZATION_NAME`, and `DB_*`/`DB_URL`;
3. runs migrations;
4. ensures the singleton `application_state` row exists;
5. creates or imports the initial admin user;
6. runs a health check and a organization-isolation smoke test.

Operational requirements:

- Run schema migrations against every organization database before deploying code that requires the new schema. Track success per `ORGANIZATION_KEY`.
- Back up and restore each database independently.
- Include `ORGANIZATION_KEY` in structured logs and metrics, but never log database credentials.
- If Redis is shared, prefix cache, lock, session, and queue keys with the immutable organization key. If filesystem/object storage is shared, prefix paths similarly.
- Ensure queue workers use the same organization configuration as their web deployment. A job must never accept a organization-provided database name or connection string.
- Health checks should verify the configured database identity, migration version, and singleton application state without exposing those details publicly.

## Testing requirements

### Feature coverage to preserve

- Guest routes still redirect to login.
- Incomplete organization setup redirects authenticated users to setup.
- Completing setup is organization-wide and applies to every user in that database.
- Setup creates the first event, makes it the organization default, and selects it for the current user.
- A user-level current event overrides the organization default.
- Clearing/deleting a user's selected event falls back to the organization default.
- Event lists and settings query all events in the organization database.
- Locked events remain readable and reject writes.
- Artist lists remain scoped to the user's current event.
- Artist histories and labels remain reusable across events in the same organization database.
- Artist and label names remain case-insensitively unique under concurrent creation.
- Label and artist-type validation accepts only IDs present in the organization database.
- Location routes still reject a location belonging to a different event.

### Isolation coverage

Replace same-database “other organization” tests with a deployment-boundary test:

1. create two temporary databases with distinct users, event names, artists, and labels;
2. boot the application separately against each database (or run the same smoke suite once per configuration);
3. prove that each deployment returns only its own data;
4. prove that an ID known only to organization B returns 404/validation failure in organization A because the row does not exist there.

Also add schema assertions that `organizations`, `organization_user`, and `organization_artists` do not exist and that no target domain table contains `organization_id`.

### Required checks

- `php artisan test`
- `./vendor/bin/pint --test`
- `npm run format:check`
- `npm run lint`
- Fresh migration and seed on SQLite.
- Fresh migration on the production database engine.
- One rehearsal of the data-split process against a sanitized production-sized snapshot before live cutover.

## Implementation sequence

### Phase 1: target schema and core abstractions

- Add organization configuration, `ApplicationState`, and `OrganizationContext`.
- Add `users.current_event_id` and normalized name keys.
- Refactor effective-event and setup-state behavior behind tests.
- Keep this phase deployable only to new target databases; do not point it at the shared source.

### Phase 2: remove organization coupling

- Refactor middleware, routes, controllers, requests, models, repository, service, factories, Inertia props, Vue props, and copy.
- Delete the `Organization` model and organization helper/middleware code.
- Rename `organization_artists` to `artists` in the target schema.
- Rewrite organization-based tests around organization-local behavior.

### Phase 3: provisioning and migration tooling

- Add a repeatable organization database provisioning workflow.
- Add a one-off, resumable export/import command for existing data.
- The command must support dry-run, per-organization selection, conflict reporting, count verification, and a non-zero exit on any invariant failure.
- Store migration audit output outside organization databases.

### Phase 4: rehearsal and cutover

- Rehearse using a restored snapshot.
- Provision and verify every organization deployment.
- Perform the maintenance-window cutover.
- Monitor authentication failures, database connection identity, 404/403 rates, queue failures, and row counts.

### Phase 5: source retirement

- Keep the shared database read-only for the approved retention period.
- Remove old deployment secrets only after rollback is no longer required.
- Archive migration reports and backup locations.

## Acceptance criteria

- The live organization schema contains no `organizations` table, `organization_user` table, `organization_id` column, or `organization_artists` table.
- Runtime application code has no dependency on `App\Models\Organization` and no organization query scope or middleware.
- Every deployment connects to exactly one organization database configured outside the request.
- Organization display name, setup status, default event, and per-user current event all continue to work.
- Existing event locking, setup, settings, and artist advancing behavior passes the revised test suite.
- Artist and label creation remains safe under case-insensitive concurrent duplicates.
- Data-split verification proves complete per-organization copies and zero rows from other organizations.
- Sessions and remember tokens are invalidated at cutover.
- Migration, formatting, lint, and test checks pass on SQLite and the production database engine.
- Backups, restore instructions, provisioning, migration orchestration, and rollback are documented for per-organization databases.

## Decisions required before implementation

1. **Deployment topology:** approve the recommended one-deployment/one-database model. If a shared runtime must serve multiple organization databases, this spec must be replaced with a runtime-tenancy design before code changes begin.
2. **Existing data:** confirm whether any non-disposable environment needs Path B. Do not edit/squash applied migrations until this is known.
3. **Organization name source:** approve `ORGANIZATION_NAME` as deployment configuration rather than persistent editable data.
4. **Multi-organization users:** approve independent copied accounts in each organization database.
5. **Terminology:** decide whether user-facing “Organization Settings” remains valid business language or should become “Festival Settings.” This does not affect the database design.
