# CI/CD Pipeline

Le pipeline CI/CD est gere par GitHub Actions. Les runners sont heberges par GitHub (ubuntu-latest). Le deploiement cible le VPS KORA via SSH.

## Vue d'ensemble

```
PR/push ──► CI (ci.yml)          ──► lint + test + build (kora-ops-web)
PR/push ──► CI Flutter (ci-flutter.yml) ──► analyze + test + build (apps + packages)
merge main ──► Deploy Staging    ──► build image GHCR + deploy VPS:8080
tag v*    ──► Deploy Production  ──► build image GHCR + backup + deploy VPS:80
tag v*    ──► Release Mobile     ──► build APK signé + GitHub Release
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

### `ci-flutter.yml` — CI Flutter sur PR et push main

Declenche sur les pull requests et push vers `main` lorsque des fichiers Flutter sont modifies (`apps/kora-app-passenger/`, `apps/kora-ops-mobile/`, `packages/flutter-design-system/`, `packages/flutter-networking/`). Execute en parallele :

**Job `packages` (matrix)** — pour `flutter-design-system` et `flutter-networking` :

1. `flutter pub get`
2. `dart format --set-exit-if-changed` (verification du formatage)
3. `flutter analyze` (analyse statique avec `analysis_options.yaml` strict)
4. `flutter test` (tests unitaires, widget et golden)

**Job `passenger-app`** — pour `kora-app-passenger` :

1. `flutter pub get` (resout aussi les dependances path vers les packages)
2. `dart format --set-exit-if-changed`
3. `flutter analyze`
4. `flutter test`
5. `flutter build apk --debug` (build de non-regression Android)

**Job `ops-mobile`** — pour `kora-ops-mobile` :

Le job verifie la presence d'un `pubspec.yaml`. Tant que l'app Flutter n'est
pas scaffolded, le job se skip proprement (notice GitHub Actions). Des qu'elle
sera generee, le job s'activera automatiquement avec les memes etapes que
`passenger-app` (pub get, format, analyze, test, build apk debug).

### `deploy-staging.yml` — Deploiement staging

Declenche automatiquement sur push vers `main` (apres merge d'une PR).

1. Build l'image Docker `kora-ops-web:staging`
2. Push au GitHub Container Registry (`ghcr.io/edenahoussou/kora-ops-web`)
3. SSH au VPS : `git pull` + `docker compose pull` + `docker compose up -d` + migrations + cache

URL staging : `http://51.255.196.129:8080`

### `deploy-production.yml` — Deploiement production

Declenche manuellement via un tag git `v*` (ex: `git tag v0.1.0 && git push origin v0.1.0`).

1. Build l'image Docker `kora-ops-web:latest` + `kora-ops-web:v0.1.0`
2. Push au GitHub Container Registry
3. SSH au VPS : backup DB + `git pull` + `docker compose pull` + `docker compose up -d` + migrations + cache

URL prod : `http://51.255.196.129`

### `release-mobile.yml` — Release APK Android

Declenche via un tag git `v*` (meme tag que la prod web — les deux workflows tournent en parallele).

1. Build l'APK release signe (`flutter build apk --release`) avec le keystore de production
2. Upload l'APK en artefact GitHub Actions (90 jours)
3. Cree une GitHub Release avec l'APK attache (telechargeable depuis la page Releases)

Si les secrets du keystore ne sont pas configures, le build retombe sur la signature debug (APK non distribuable sur le Play Store mais installable par sideloading).

Voir `docs/runbooks/mobile-release.md` pour le setup complet du keystore et la procedure de release.

URL prod : `http://51.255.196.129`

## Secrets GitHub Actions

Configurer dans GitHub > edenahoussou/kora-platform > Settings > Secrets and variables > Actions :

| Secret | Description |
|--------|-------------|
| `DEPLOY_SSH_KEY` | Cle privee SSH pour deploiement VPS (`kora_deploy_ed25519`) |
| `DEPLOY_HOST` | `51.255.196.129` |
| `DEPLOY_PORT` | `2222` |
| `DEPLOY_USER` | `deploy` |

Note : `GITHUB_TOKEN` est fourni automatiquement par GitHub Actions pour le login au GHCR. Pas besoin de le configurer manuellement.

## Deboguer un build en echec

1. Ouvrir la PR dans GitHub > Actions
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

### Deboguer un build Flutter en echec

Reproduire localement :

```bash
# Packages partages
cd packages/flutter-design-system   # ou flutter-networking
flutter pub get
dart format --set-exit-if-changed lib test
flutter analyze
flutter test

# App passager
cd apps/kora-app-passenger
flutter pub get
dart format --set-exit-if-changed lib test
flutter analyze
flutter test
flutter build apk --debug
```

Note : les golden tests du design-system peuvent echouer sur CI si les polices
differt. En cas de besoin, regenerer les goldens localement puis committer :
`flutter test --update-goldens`.

## Rollback

Le compose est en **pull-only** : on revient sur un tag d'image publie dans GHCR,
jamais par `git checkout` + rebuild sur le VPS (voir « Stockage Docker du VPS »
dans `deployment.md`).

### Staging

```bash
ssh kora-vps
cd /opt/kora-staging

# Tags staging-<sha> pousses par la CI a chaque deploiement
docker images 'ghcr.io/edenahoussou/kora-ops-web' --format '{{.Tag}}\t{{.CreatedAt}}' | grep staging- | head -5

# Revenir sur un build precedent
KORA_IMAGE_TAG=staging-<sha> docker compose -f docker-compose.staging.yml pull
KORA_IMAGE_TAG=staging-<sha> docker compose -f docker-compose.staging.yml up -d --no-build
```

Le tag reste actif tant que `KORA_IMAGE_TAG` est exporte ; pour revenir au flux
normal, retirer la variable (ou la commenter dans `.env.staging`) puis relancer
`pull` + `up -d --no-build`.

### Production

```bash
ssh kora-vps
cd /opt/kora-prod

# Restaurer le backup DB si la migration est en cause
ls -t backups/db-backup-*.sql | head -5
docker compose -f docker-compose.prod.yml exec -T postgres psql -U kora kora_prod < backups/db-backup-XXXX.sql

# Revenir a un tag precedent (tags v* pousses par deploy-production)
KORA_IMAGE_TAG=v1.0.4 docker compose -f docker-compose.prod.yml pull
KORA_IMAGE_TAG=v1.0.4 docker compose -f docker-compose.prod.yml up -d --no-build
```

Les tags `staging-<sha>` et `v*` restent sur GHCR meme apres une purge locale :
la purge hebdomadaire du VPS ne supprime que les anciens tags locaux (les 2
derniers `staging-<sha>` sont conserves).
