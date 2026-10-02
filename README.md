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
# Set the dedicated organization DB_* values in .env.
npm install
npm run build
php artisan migrate
php artisan app:make-admin you@example.com
php artisan serve
```

Start Mailpit to catch outgoing email locally, then open
[http://localhost:8025](http://localhost:8025) to view the inbox:

```bash
docker compose up -d mailpit
```

To create the local test account after rebuilding the database, run:

```bash
php artisan db:seed --class=TestUserSeeder
```

## Organization database isolation

Artist Tree uses one deployment and one database per organization. The configured database is the tenancy boundary: users and festival data in that database belong to that organization, and the application does not select database connections from request input.

- The organization name is stored in the database and configured by the client during setup.
- Each deployment requires dedicated database credentials.
- Run migrations, backups, restores, queue workers, and health checks independently for every organization deployment.

## Fresh database baseline

The migrations create the current schema directly. Historical backfills, data cleanup, and retired columns are omitted; this migration set is for new databases, not an upgrade path for databases that ran the previous history. Rebuild an existing development database before using this baseline. `migrate:fresh` deletes all data in the configured database.

For a disposable local database with the full demo dataset:

```bash
php artisan migrate:fresh --seed
```

`DatabaseSeeder` runs the user, setup, catalog, artist, vendor, and team seeders in dependency order. Running `php artisan db:seed` again updates the same demo records without duplicating them. Migrations do not insert suggested types or other demo data; the setup wizard and seeders own those writes.

The baseline preserves existing column defaults and nullability, including nullable account/person links, invitation passwords, inventory location links, and optional Team roles/forms. Tightening those constraints is outside this consolidation.

For local frontend hot reload:

```bash
composer run dev
```

## Status

The setup, event settings, event locking, and artist advancing slices are implemented.
