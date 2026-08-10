# Repository Guidelines

## Project Structure & Module Organization

This app contains Laravel 13, the KORA OPS Web React/Inertia UI, and the versioned REST API. Domain code belongs in `app/Modules/<Module>` with pragmatic DDD boundaries. HTTP controllers, requests, and policies stay near the relevant module when possible. React, TypeScript, Tailwind, and Vite assets live in `resources/`. Migrations, factories, and seeders live in `database/`; Pest tests live in `tests/`.

## Build, Test, and Development Commands

Run commands from `apps/kora-ops-web`.

```bash
composer install          # install PHP dependencies
npm install               # install frontend dependencies
composer dev              # start Laravel/Vite development workflow
php artisan migrate       # apply local PostgreSQL migrations
composer test             # run Pint, PHPStan, and Pest
npm run lint:check        # check frontend lint rules
npm run types:check       # check TypeScript types
npm run build             # build production frontend assets
```

Docker remains available for team parity with `docker compose up -d kora-ops-web` from the repo root.

## Coding Style & Naming Conventions

Use Laravel conventions, PSR-4 namespaces, and 4-space PHP indentation. Keep controllers short: receive the request, call a service, and return JSON or an Inertia response. Put validation in `FormRequest`, orchestration in `Services`, atomic business behavior in `Actions`, and authorization in `Policies` or `Gates`. Use TypeScript React function components, Tailwind CSS, ESLint, and Prettier for UI work. Preserve KORA vocabulary from `../../CONTEXT.md`.

## Testing Guidelines

Pest is the backend test framework. Name feature tests after user-visible behavior, for example `BookingSeatHoldTest.php`. Add focused tests for reservations, payments, tickets, seat inventory, fiscalization, cash, boarding, and API contracts. Run a targeted test with `php artisan test tests/Feature/ApiHealthTest.php`; run the full gate with `composer test`.

## Configuration & Security

Use PostgreSQL locally with `.env`; keep `.env.example` generic. Never commit real passwords, payment credentials, MECeF credentials, Firebase keys, or P501 device secrets.
