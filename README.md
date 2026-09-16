# ez-festival

Festival software built with Laravel 13 and Tailwind CSS 4.

## Stack

- PHP 8.3+
- Laravel 13
- Tailwind CSS 4 (`@tailwindcss/vite`)
- Vite
- Laravel Boost (dev) for MCP / agent tooling
- Ready for Laravel Cloud

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# Set ORGANIZATION_KEY, ORGANIZATION_NAME, and the dedicated organization DB_* values in .env.
npm install
npm run build
php artisan migrate
php artisan serve
```

## Organization database isolation

Artist Tree uses one deployment and one database per organization. The configured database is the tenancy boundary: users and festival data in that database belong to that organization, and the application does not select database connections from request input.

- `ORGANIZATION_KEY` is an immutable operational identifier used for deployment inventory, logging, backups, and shared-service prefixes.
- `ORGANIZATION_NAME` is the festival name shown in the application shell and setup flow.
- Each deployment requires dedicated database credentials. If Redis or object storage is shared, prefix its keys or paths with `ORGANIZATION_KEY`.
- Run migrations, backups, restores, queue workers, and health checks independently for every organization deployment.

The detailed architecture and data-split runbook are in [`docs/specs/database-per-organization-remove-organizations.md`](docs/specs/database-per-organization-remove-organizations.md).

For an existing database, take a verified backup before running migrations. The conversion migration preserves a database containing zero or one organization, but it is intentionally irreversible and stops if multiple organizations or case-insensitive artist/label conflicts are present. A multi-organization database must be split with the runbook before the conversion is applied.

For local frontend hot reload:

```bash
composer run dev
```

## Status

The setup, event settings, event locking, and artist advancing slices are implemented.
