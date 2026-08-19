# Mintlify — Documentation API Mobile

La documentation d'intégration API destinée aux développeurs mobile est gérée via [Mintlify](https://mintlify.com) et hébergée sur Mintlify Cloud.

## Structure

```
docs/mintlify/
├── docs.json              # Config Mintlify 4.x (navigation, thème, auth)
├── api/
│   └── openapi.yaml       # Spec OpenAPI 3.0.3 généré par Scribe (commité)
├── introduction.mdx       # Page d'accueil
├── quickstart.mdx         # Démarrage rapide
├── authentication.mdx     # Guide auth OTP + PIN + Sanctum
├── guides/                # Guides pratiques
│   ├── bus-search.mdx
│   ├── seat-selection.mdx
│   └── error-handling.mdx
├── reference/             # API Reference auto-générée depuis OpenAPI
│   ├── auth.mdx
│   ├── bus.mdx
│   └── health.mdx
└── snippets/              # Snippets Dart réutilisables
    ├── dart-auth.mdx
    └── dart-http-client.mdx
```

## Pré-requis

- Node.js 18+
- Le CLI Mintlify : `npm install -g mintlify`

## Développement local

```bash
cd docs/mintlify
npm install
npm run dev    # Serveur local sur http://localhost:3333
```

## Régénérer le spec OpenAPI

Le spec `api/openapi.yaml` est généré par Scribe depuis les annotations PHP des contrôleurs. Pour le régénérer :

```bash
cd apps/kora-ops-web
composer docs:generate
```

Cette commande :
1. Lance `php artisan scribe:generate --force`
2. Copie `storage/app/private/scribe/openapi.yaml` vers `docs/mintlify/api/openapi.yaml`

Le spec doit être **commité** dans le repo. La CI vérifie qu'il est à jour.

## Build de validation

```bash
cd docs/mintlify
npm run build   # Valide que la doc build sans erreur
```

## Déploiement — Mintlify Cloud

### Configuration initiale (one-time)

1. Créer un compte sur [Mintlify Cloud](https://mintlify.com)
2. Connecter le repo GitHub `edenahoussou/kora-platform`
3. Configurer le **root directory** : `docs/mintlify`
4. Activer l'**authentication** : email allowlist (restreindre aux emails `@korabus.app` ou allowlist manuelle)
5. Configurer le domaine custom (optionnel) : `docs.korabus.app`

### Déploiement automatique

- **Production** : Mintlify Cloud déploie automatiquement sur chaque merge vers `main`
- **Preview** : Mintlify Cloud crée une preview deployment sur chaque PR
- L'URL de preview est postée en commentaire sur la PR par Mintlify Cloud

### Authentification

L'accès à la documentation est restreint. Les développeurs mobile doivent être ajoutés à l'allowlist email dans le dashboard Mintlify Cloud.

## Ajouter un nouvel endpoint

1. Implémenter le controller avec les annotations Scribe (`@group`, `@bodyParam`, `@queryParam`, `@response`)
2. Régénérer le spec : `composer docs:generate`
3. Si l'endpoint appartient à un nouveau groupe, créer `reference/<group>.mdx` et l'ajouter dans `docs.json`
4. Commiter le spec mis à jour + les changements
5. La CI valide que `mintlify validate` passe

## Ajouter un guide

1. Créer `guides/<nom>.mdx`
2. L'ajouter dans la navigation de `docs.json` (groupe "Guides")
3. Valider avec `npm run build`
