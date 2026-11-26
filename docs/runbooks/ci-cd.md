# CI/CD Pipeline

Le pipeline CI/CD est gere par GitHub Actions. Les runners sont heberges par GitHub (ubuntu-latest). Le deploiement cible le VPS KORA via SSH.

## Vue d'ensemble

```
PR/push ──► CI (ci.yml)          ──► lint + test + build (kora-ops-web)
PR/push ──► CI Flutter (ci-flutter.yml) ──► analyze + test + build (apps + packages)
CI verte sur main / branche choisie ──► Deploy Staging ──► images staging-<sha>
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

Le staging se déploie automatiquement après une CI réussie sur `main`. Pour tester une feature ou un hotfix avant fusion :

1. Pousser la branche interne et attendre les checks de sa PR si elle en a une.
2. Dans **Actions → Deploy Staging → Run workflow**, sélectionner `main` comme branche du workflow et renseigner la branche à tester dans le champ `branch`.
3. Vérifier le SHA dans le résumé du run et tester sur https://staging.koraexpress.app. Le VPS utilise les fichiers de ce SHA et les deux images `staging-<sha>`.
4. Fusionner la feature après validation. Pour rendre le staging à l’équipe, lancer le workflow avec `branch=main`.

Le staging est partagé. Un déploiement automatique sur `main` ou un autre lancement manuel peut remplacer la branche testée. Prévenir l’équipe et utiliser une base isolée pour une migration incompatible avec les autres versions.

### `deploy-production.yml` — Déploiement production

Créer un nouveau tag `v*` sur le **SHA explicitement choisi**. Le workflow construit les deux images avec ce tag, vérifie que le VPS récupère le même commit, sauvegarde la base avant migration et vérifie la santé de l’API. Le résumé du run indique le SHA et le tag livrés.

- **Livraison normale** : choisir un commit de `main` validé en staging.
- **Hotfix** : retrouver le tag du dernier déploiement réussi et vérifier la révision sur le VPS. Partir de ce tag, tester la branche de hotfix en staging, taguer son commit exact, puis reporter le correctif dans `main` via une PR.

```bash
git fetch origin --tags
git switch -c hotfix/correction v1.0.29  # Exemple : remplacer par le tag réellement en ligne
# Après validation, définir RELEASE_SHA et RELEASE_TAG pour cette livraison
git tag "$RELEASE_TAG" "$RELEASE_SHA"
git push origin "refs/tags/$RELEASE_TAG"
```

**Transition** : la première version compatible est le premier tag livré avec le nouveau workflow et les deux variables Compose. Consigner ce tag ici après sa première livraison. Pour partir d’un tag antérieur, reporter le commit d’infrastructure sur le hotfix avant de le tester et de le taguer. GitHub exécute le workflow présent dans la révision taguée. Ne jamais déplacer ou réutiliser un tag publié.

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

Créer aussi `STAGING_DEPLOY_HOST`, `STAGING_DEPLOY_PORT`, `STAGING_DEPLOY_USER` et `STAGING_DEPLOY_SSH_KEY` avant d’activer le nouveau workflow. Fournir des identifiants propres au staging. Les secrets `DEPLOY_*` restent réservés au workflow production. Réserver le lancement manuel et les branches internes déployables aux mainteneurs de confiance. Des comptes SSH différents sur un hôte Docker partagé ne constituent pas une isolation du VPS.

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

Utiliser la procédure de redéploiement par référence dans [le runbook de déploiement](deployment.md). Choisir le SHA et les deux images du même déploiement réussi. Avant de revenir à une version précédente, vérifier que son code accepte le schéma courant. Une restauration de base est une intervention distincte qui peut supprimer les écritures intervenues depuis la sauvegarde.
