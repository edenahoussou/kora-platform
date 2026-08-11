# CI/CD Pipeline

Le pipeline CI/CD est gere par Gitea Actions. Le runner tourne sur le VPS (`51.255.196.129`).

## Vue d'ensemble

```
PR/push ──► CI (ci.yml)          ──► lint + test + build
merge main ──► Deploy Staging    ──► build image + deploy VPS:8080
tag v*    ──► Deploy Production  ──► build image + backup + deploy VPS:80
```

## Workflows

### `ci.yml` — CI sur PR et push main

Declenche sur chaque pull request et push vers `main`. Execute :

1. Pint (PHP code style check)
2. PHPStan level 7 (static analysis)
3. ESLint (frontend lint)
4. TypeScript types check (tsc)
5. Prettier (format check)
6. Pest (tests backend, SQLite in-memory)
7. Vite build (verification du build frontend)

Si une etape echoue, la PR est bloquee (status check requis sur `main`).

### `deploy-staging.yml` — Deploiement staging

Declenche automatiquement sur push vers `main` (apres merge d'une PR).

1. Build l'image Docker `kora-ops-web:staging`
2. Push au registry Gitea (`gitea.mezagency.fr/bj/kora-platform/kora-ops-web`)
3. SSH au VPS : `git pull` + `docker compose pull` + `docker compose up -d` + migrations + cache

URL staging : `http://51.255.196.129:8080`

### `deploy-production.yml` — Deploiement production

Declenche manuellement via un tag git `v*` (ex: `git tag v0.1.0 && git push origin v0.1.0`).

1. Build l'image Docker `kora-ops-web:latest` + `kora-ops-web:v0.1.0`
2. Push au registry Gitea
3. SSH au VPS : backup DB + `git pull` + `docker compose pull` + `docker compose up -d` + migrations + cache

URL prod : `http://51.255.196.129`

## Secrets Gitea Actions

Configurer dans Gitea > BJ/kora-platform > Settings > Actions > Secrets :

| Secret | Description |
|--------|-------------|
| `DEPLOY_SSH_KEY` | Cle privee SSH pour deploiement VPS |
| `DEPLOY_HOST` | `51.255.196.129` |
| `DEPLOY_PORT` | `2222` |
| `DEPLOY_USER` | `deploy` |
| `REGISTRY_USER` | User Gitea pour push d'images |
| `REGISTRY_TOKEN` | Token Gitea avec scope `package:write` |

## Deboguer un build en echec

1. Ouvrir la PR dans Gitea > Actions
2. Cliquer sur le job echoue pour voir les logs
3. Reproduire localement :
   ```bash
   cd apps/kora-ops-web
   composer install
   npm ci
   composer lint:check      # Pint
   composer types:check     # PHPStan
   npm run lint:check       # ESLint
   npm run types:check      # tsc
   npm run format:check     # Prettier
   php artisan test         # Pest
   npm run build            # Vite
   ```
4. Corriger, pousser, la CI relance automatiquement

## Rollback

### Staging

```bash
ssh kora-vps
cd /opt/kora-staging
git log --oneline -5          # identifier le commit precedent
git checkout <commit-precedent>
docker compose -f docker-compose.staging.yml up -d --build
```

### Production

```bash
ssh kora-vps
cd /opt/kora-prod

# Restaurer le backup DB
ls -t backups/db-backup-*.sql | head -5
docker compose -f docker-compose.prod.yml exec -T postgres psql -U kora kora_prod < backups/db-backup-XXXX.sql

# Revenir a un tag precedent
git checkout v0.0.9
docker compose -f docker-compose.prod.yml up -d --build
```

## Runner Gitea Actions

Le runner tourne sur le VPS en container Docker. Service systemd : `gitea-actions-runner.service`.

```bash
# Statut du runner
ssh kora-vps "sudo systemctl status gitea-actions-runner"

# Redemarrer le runner
ssh kora-vps "sudo systemctl restart gitea-actions-runner"

# Logs du runner
ssh kora-vps "sudo journalctl -u gitea-actions-runner -f"
```

Si le runner est "offline" dans Gitea > Settings > Actions > Runners, verifier que le tunnel Pangolin est up sur le VPS.
