# Repository Guidelines

## Scope

This monorepo hosts the validated KORA BUS MVP. Read `CONTEXT.md` before changing domain names, scope, or module boundaries. The MVP is Bus only: do not introduce KORA Ride, KORA Driver, Wallet, Colis, KORA POINT, full SYSCOHADA accounting, payroll, or advanced fleet modules without an explicit change request.

## App-Specific Guides

Follow the closest `AGENTS.md` for the files you edit:

- `apps/kora-ops-web/AGENTS.md`: Laravel, React/Inertia, API, migrations, and backend tests.
- `apps/kora-app-passenger/AGENTS.md`: future Flutter passenger Bus app.
- `apps/kora-ops-mobile/AGENTS.md`: future Flutter Android/P501 field operations app.

Shared contracts and packages live in `packages/`. Architecture decisions, API notes, delivery notes, and runbooks live in `docs/`.

## Repository Commands

Run app commands from the app directory. For the active backend/web app:

```bash
cd apps/kora-ops-web
composer test
npm run build
```

## Commit & Pull Request Guidelines

This repository has no established Git history yet. Use concise conventional-style commits, for example `feat: scaffold bus booking module` or `fix: correct pgsql health check`. Pull requests should include scope, linked client requirement or ticket, migration notes, test results, and screenshots for UI changes.

## Configuration

Keep secrets in local `.env` files only. Do not commit real database passwords, API keys, fiscalization credentials, payment credentials, or P501 device secrets.
