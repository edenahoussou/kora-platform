# Gate CI local obligatoire avant toute PR ou push

Ne jamais créer de PR ni pousser de branche sans avoir rejoué **localement** la totalité du job CI `lint-and-test` et, si des fichiers docs-relevant ont changé, le job `docs` de `.github/workflows/ci.yml`.

## Équivalent local du job `lint-and-test`

Depuis `apps/kora-ops-web`, dans cet ordre :

```bash
php artisan wayfinder:generate --with-form
composer lint:check        # Pint
composer types:check       # PHPStan
npm run lint:check         # ESLint
npm run types:check        # TypeScript
npm run format:check       # Prettier — gate distinct de lint:check, souvent oublié
npm run build              # Vite
php artisan test           # Pest complet
```

Le timeout Composer (300 s) peut tuer `composer test` localement : lancer `php artisan test` directement, sans limite.

## Équivalent local du job `docs`

Obligatoire dès qu'un changement touche `app/Modules/**/Http/Controllers/**`, `app/Modules/**/Http/Requests/**`, `app/Modules/**/Http/Resources/**`, `routes/**`, `composer.*` ou `docs/mintlify/**` :

```bash
composer docs:generate
git diff --exit-code -- docs/mintlify/api/openapi.yaml  # doit être vide → sinon committer la spec
cd docs/mintlify && npm ci && npx mintlify validate
```

Si `docs:generate` modifie `openapi.yaml`, committer la spec régénérée — la CI échoue sinon (« OpenAPI spec is stale »).

## En cas d'échec

Corriger la cause, jamais contourner (pas de `--skip`, pas de baseline PHPStan/ESLint modifiée pour faire passer). Une PR ne se crée qu'une fois les deux jobs simulés verts.
