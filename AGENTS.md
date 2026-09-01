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

**NEVER sign commits.** Do not add `Generated with [Devin](https://devin.ai)`, `Co-Authored-By: Devin`, or any other AI tool signature / co-author line to commit messages. Keep commit messages simple and clean — no co-authors, no tool mentions.

Before pushing a branch or merging a pull request, run the relevant local gate and confirm the equivalent GitHub Actions checks are green. For KORA OPS Web changes, run at minimum `composer test`, `npm run build`, and when the production image or deploy pipeline may be affected, `docker build -f infra/docker/kora-ops-web/Dockerfile.prod -t kora-ops-web:local-check .` from the repository root. Do not merge based only on a PR check if the post-merge `main` deployment workflow is failing.

**Merge strategy**: Always use merge commits (`gh pr merge --merge`), never squash. The repository is configured for merge commits by default — squashing destroys individual commit history and should not be used.

## CI/CD

The pipeline runs on GitHub Actions. Workflows live in `.github/workflows/`:

- `ci.yml`: lint + test + build on PR and push to `main` (status check required).
- `deploy-staging.yml`: auto-deploy to VPS staging (`:8080`) on merge to `main`.
- `deploy-production.yml`: manual deploy to VPS production (`:80`) on tag `v*`.

Docker images are pushed to GitHub Container Registry (`ghcr.io/edenahoussou/kora-ops-web`). Production images use `infra/docker/kora-ops-web/Dockerfile.prod` (multi-stage) with Nginx (`infra/docker/nginx/`). See `docs/runbooks/ci-cd.md` and `docs/runbooks/deployment.md` for details.

GitHub Actions secrets: `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_PORT`, `DEPLOY_USER`. The `GITHUB_TOKEN` is provided automatically for GHCR authentication.

## Configuration

Keep secrets in local `.env` files only. Do not commit real database passwords, API keys, fiscalization credentials, payment credentials, or P501 device secrets.
