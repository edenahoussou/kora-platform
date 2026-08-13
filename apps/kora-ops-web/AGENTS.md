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
composer test:db          # run focused KORA BUS DB tests on fast SQLite
composer test:pgsql       # run focused KORA BUS DB tests on a temporary PostgreSQL database
npm run lint:check        # check frontend lint rules
npm run types:check       # check TypeScript types
npm run build             # build production frontend assets
```

Docker remains available for team parity with `docker compose up -d kora-ops-web` from the repo root.

## Coding Style & Naming Conventions

Use Laravel conventions, PSR-4 namespaces, and 4-space PHP indentation. Keep controllers short: receive the request, call a service, and return JSON or an Inertia response. Put validation in `FormRequest`, orchestration in `Services`, atomic business behavior in `Actions`, and authorization in `Policies` or `Gates`. Use TypeScript React function components, Tailwind CSS, ESLint, and Prettier for UI work. Preserve KORA vocabulary from `../../CONTEXT.md`.

## Public Identifiers & Serialization

Numeric `id` values are internal backend identifiers only. Backend services, actions, policies, jobs, Eloquent relationships, and foreign keys may use numeric IDs freely, but web/mobile/API surfaces must expose `uuid` or domain codes instead. Do not return raw Eloquent business models to Inertia or JSON responses. Use explicit Inertia props for KORA OPS Web and `JsonResource` or DTO responses for REST/mobile APIs; keep model `$hidden = ['id']` only as a safety net.

## Testing Guidelines

Pest is the backend test framework. Name feature tests after user-visible behavior, for example `BookingSeatHoldTest.php`. Add focused tests for reservations, payments, tickets, seat inventory, fiscalization, cash, boarding, and API contracts. Run a targeted test with `php artisan test tests/Feature/ApiHealthTest.php`; run the full gate with `composer test`.

`composer test` intentionally uses SQLite in memory for fast feedback. PostgreSQL-specific invariants, especially KORA BUS seat segment exclusion constraints and migration/seed compatibility, must also be validated with `composer test:pgsql`. Provide the local password through `KORA_TEST_DB_PASSWORD`; do not commit it.

```bash
KORA_TEST_DB_PASSWORD='local-password' composer test:pgsql
```

The PostgreSQL workflow creates a temporary `kora_test_*` database, runs migrations and seeders, executes `tests/Feature/KoraBus`, and drops the temporary database on exit. The `kora` PostgreSQL role needs the `CREATEDB` attribute. When running inside Docker, set `KORA_TEST_DB_HOST=postgres`.

## Configuration & Security

Use PostgreSQL locally with `.env`; keep `.env.example` generic. Never commit real passwords, payment credentials, MECeF credentials, Firebase keys, or P501 device secrets.
