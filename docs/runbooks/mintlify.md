# Mintlify — Documentation API Mobile

La documentation d'intégration API destinée aux développeurs mobile est gérée via [Mintlify](https://mintlify.com) et hébergée sur Mintlify Cloud.

## Structure

```
docs/mintlify/
├── docs.json              # Config Mintlify (navigation, thème, auth)
├── api/
│   └── openapi.yaml       # Spec OpenAPI 3.0.3 généré par Scribe (commité)
├── introduction.mdx       # Page d'accueil (allégée)
├── concepts.mdx           # Concepts clés (vocabulaire métier)
├── quickstart.mdx         # Démarrage rapide
├── guides/                # Guides pratiques
│   ├── authentication.mdx     # Flow OTP + PIN + OAuth (concis)
│   ├── bus-search.mdx          # Recherche : villes → lignes → départs → sièges
│   ├── seat-selection.mdx      # Statuts de sièges + logique de segment
│   ├── quotes-and-holds.mdx    # Devis figé + blocage de sièges
│   ├── booking-lifecycle.mdx   # États d'une réservation
│   ├── payment-lifecycle.mdx   # Initiation → polling → confirmation
│   ├── webhooks.mdx            # Événements de paiement
│   ├── devices.mdx             # Appareils de confiance
│   ├── preferences.mdx         # Notifications, langue, thème
│   └── error-handling.mdx      # Structure d'erreur + codes métier
├── reference/             # API Reference (une page par sous-ressource)
│   ├── introduction.mdx       # Intro API (conventions, environnements, auth)
│   ├── auth/
│   │   ├── otp.mdx             # OTP request + verify
│   │   ├── sessions.mdx        # register/complete, login, pin/reset, unlock, pin/change, logout
│   │   ├── oauth.mdx           # OAuth sociale (Google, Facebook, TikTok)
│   │   ├── profile.mdx         # GET/PUT /me
│   │   ├── devices.mdx         # GET/DELETE devices
│   │   └── preferences.mdx     # GET/PUT preferences
│   ├── bus/
│   │   ├── cities.mdx
│   │   ├── lines.mdx
│   │   ├── departures.mdx
│   │   └── seats.mdx
│   ├── booking/
│   │   ├── quotes.mdx
│   │   ├── holds.mdx
│   │   ├── reservations.mdx
│   │   └── passengers.mdx
│   ├── payments/
│   │   ├── initiate.mdx
│   │   ├── show.mdx
│   │   └── confirm.mdx
│   └── health.mdx
├── security/              # Section Sécurité
│   ├── best-practices.mdx     # Tokens, OTP, appareils, données de paiement
│   └── rate-limiting.mdx      # Throttles, headers, stratégie de retry
└── snippets/              # Snippets réutilisables
    ├── dart-auth.mdx
    ├── dart-http-client.mdx
    └── ts-types.mdx           # Interfaces TypeScript partagées
```

## Navigation (docs.json)

La navigation est organisée en 4 onglets :

1. **Démarrage** — introduction, concepts, quickstart, authentification
2. **Guides** — parcours passager, compte & sécurité, webhooks
3. **API Reference** — une page par sous-ressource (auth, bus, booking, payments, health)
4. **Sécurité** — bonnes pratiques, rate limiting

## Standard des pages de référence

Chaque page de référence API suit le modèle FedaPay :

- **Titre** + méthode + URL en haut
- **Description du body de requête** au centre (tous les paramètres documentés)
- **Tous les codes de réponse** documentés avec schéma + exemple JSON
- **4 exemples de requête** : cURL, JavaScript, TypeScript, Dart
- **Bouton Try it out** (playground) généré depuis le bloc OpenAPI

Voir `RESTRUCTURATION_PLAN.md` §6 pour le standard complet.

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
3. Créer une page `reference/<domaine>/<endpoint>.mdx` avec 4 exemples (cURL, JS, TS, Dart) et tous les codes de réponse
4. Ajouter la page dans la navigation de `docs.json` (groupe correspondant)
5. Commiter le spec mis à jour + les changements
6. La CI valide que `mintlify validate` passe

## Ajouter un guide

1. Créer `guides/<nom>.mdx`
2. L'ajouter dans la navigation de `docs.json` (groupe « Parcours passager », « Compte & sécurité » ou « Webhooks »)
3. Valider avec `npm run build`
