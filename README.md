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
npm install
npm run build
php artisan migrate
php artisan serve
```

For local frontend hot reload:

```bash
composer run dev
```

## Status

Scaffolding only. No festival domain features yet.
