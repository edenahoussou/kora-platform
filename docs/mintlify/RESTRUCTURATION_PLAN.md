# Plan de restructuration de la documentation API KORA BUS

> Modèle d'organisation visé : **FedaPay** (https://docs.fedapay.com).
> On se concentre sur deux piliers — **Documentation** (concepts + guides) et **API Reference** —
> car KORA n'expose pas de SDK propriétaire.

---

## 1. Diagnostic de l'état actuel

### 1.1 Ce qui existe

```
docs/mintlify/
├── docs.json              # 3 onglets : Getting Started / Guides / API Reference
├── introduction.mdx
├── quickstart.mdx
├── authentication.mdx     # 768 lignes — monolithique
├── guides/{bus-search, seat-selection, error-handling}.mdx
├── reference/{auth, bus, health}.mdx   # 3 pages, listes de <Method> sans détail
└── api/openapi.yaml       # généré Scribe, 20 paths, 4 tags (Bus/Auth/Health/Payments)
```

### 1.2 Problèmes identifiés (vs modèle FedaPay)

| # | Problème | Impact |
|---|----------|--------|
| 1 | **Pas de page « Concepts clés »** | Le vocabulaire métier (Départ, Segment O/D, Devis, Hold, Réservation, Appareil, Token Sanctum, OTP) n'est défini nulle part de façon centralisée. `CONTEXT.md` existe côté repo mais n'est pas exposé à l'intégrateur. |
| 2 | **`authentication.mdx` monolithique (768 lignes)** | Mélange guide pas-à-pas, référence d'endpoints, OAuth, PIN reset, unlock, appareils, préférences, déconnexion. Illisible d'un bloc. |
| 3 | **API Reference à seulement 3 pages** (`auth`, `bus`, `health`) | Pas de granularité par ressource. Les endpoints **Payments** (3) et **Bookings/Holds** (3) ne sont pas dans la navigation de référence — ils sont uniquement évoqués en bas de `bus.mdx`. |
| 4 | **Pas de page d'introduction à l'API Reference** | FedaPay a une page « Introduction à l'API » qui présente la structure REST, l'auth, les environnements. KORA n'a rien d'équivalent dans l'onglet Reference. |
| 5 | **Pas de guides par ressource** | FedaPay a « Gestion des clients », « Gestion des collectes », « Gestion des dépôts ». KORA n'a pas de guide « Cycle de vie d'une réservation », « Cycle de vie d'un paiement », « Gestion des appareils ». |
| 6 | **Pas de section Sécurité / bonnes pratiques** | FedaPay a une section Security (bonnes pratiques, fraudes). KORA expose des tokens Sanctum, des OTP, des fingerprints d'appareils — aucune page ne consolide les recommandations. |
| 7 | **`openapi.yaml` pauvre** | Scribe génère des `responses: default` sans schéma de réponse ni exemples complets. La reference `.mdx` ne compense pas (listes brutes de `<Method>`). |
| 8 | **Pas de page Webhooks** | Les paiements ont un webhook de confirmation (`/payments/{uuid}/confirm`) et le guide mentionne un « webhook de paiement tardif ». Aucune page ne documente les événements, signatures, retry. |
| 9 | **Navigation non organisée par domaine** | Tout est aplati. Pas de regroupement Auth / Catalogue Bus / Réservation & Paiement / Santé. |
| 10 | **Pas de page « Environnements & clés »** | Les base URLs sont répétées dans introduction + quickstart + authentication. Pas de page dédiée « Créer un compte / récupérer un token » comme FedaPay. |

### 1.3 Couverture réelle de l'API (20 endpoints, 5 domaines)

| Domaine | Endpoints | Documenté dans la nav ? |
|---------|-----------|--------------------------|
| Health | 2 | ✅ `reference/health` |
| Auth | 14 | ✅ `reference/auth` (mais 1 page pour 14 endpoints) |
| Catalogue Bus | 6 (lecture) | ✅ `reference/bus` |
| Devis & Holds | 2 | ⚠️ évoqué dans `bus.mdx` |
| Réservations | 3 | ❌ absent de la nav |
| Paiements | 3 | ❌ absent de la nav (tag existe dans openapi) |

---

## 2. Architecture cible (inspirée FedaPay, adaptée KORA)

### 2.1 Principes directeurs

1. **Deux piliers explicites** : *Documentation* (concepts + guides) et *API Reference* (une page par ressource, pas par tag).
2. **Une page « Concepts clés »** qui fixe le vocabulaire métier avant tout guide.
3. **Guides organisés par ressource métier**, pas par cas d'usage isolé.
4. **API Reference granulaire** : une page par ressource (Auth, Appareils, Préférences, Catalogue Bus, Départs & Sièges, Devis, Holds, Réservations, Passagers, Paiements, Santé), chaque page listant ses endpoints avec descriptions.
5. **Éclatement de `authentication.mdx`** : le flow devient un guide ; les endpoints rejoignent la reference par sous-ressource.
6. **Section Sécurité** dédiée (tokens, OTP, appareils de confiance, rate limiting, bonnes pratiques).
7. **Section Webhooks** dès qu'un événement métier notifie un système tiers (paiement).

### 2.2 Navigation cible (`docs.json`)

```
Anchor « Documentation »
├── Tab « Démarrage »
│   ├── Introduction                       (introduction.mdx — réécrite)
│   ├── Concepts clés                      (concepts.mdx — NOUVEAU)
│   ├── Quickstart                         (quickstart.mdx — révisé)
│   └── Environnements & authentification  (guides/authentication.mdx — réécrit, ~150 lignes)
│
├── Tab « Guides »
│   ├── group « Parcours passager »
│   │   ├── Recherche de départs           (guides/bus-search.mdx — existant, révisé)
│   │   ├── Sélection de sièges            (guides/seat-selection.mdx — existant)
│   │   ├── Devis & blocage de sièges      (guides/quotes-and-holds.mdx — NOUVEAU, extrait)
│   │   ├── Cycle de vie d'une réservation (guides/booking-lifecycle.mdx — NOUVEAU)
│   │   └── Cycle de vie d'un paiement     (guides/payment-lifecycle.mdx — NOUVEAU)
│   ├── group « Compte & sécurité »
│   │   ├── Gestion des appareils          (guides/devices.mdx — NOUVEAU, extrait de auth)
│   │   ├── Préférences utilisateur        (guides/preferences.mdx — NOUVEAU, extrait)
│   │   └── Gestion des erreurs            (guides/error-handling.mdx — existant)
│   └── group « Webhooks »
│       └── Événements de paiement         (guides/webhooks.mdx — NOUVEAU)
│
├── Tab « API Reference »
│   ├── Introduction à l'API               (reference/introduction.mdx — NOUVEAU)
│   ├── group « Authentification »
│   │   ├── OTP                             (reference/auth/otp.mdx — NOUVEAU)
│   │   ├── Sessions & PIN                  (reference/auth/sessions.mdx — NOUVEAU)
│   │   ├── OAuth sociale                   (reference/auth/oauth.mdx — NOUVEAU)
│   │   ├── Profil (/me)                    (reference/auth/profile.mdx — NOUVEAU)
│   │   ├── Appareils                       (reference/auth/devices.mdx — NOUVEAU)
│   │   └── Préférences                     (reference/auth/preferences.mdx — NOUVEAU)
│   ├── group « Catalogue Bus »
│   │   ├── Villes                          (reference/bus/cities.mdx — NOUVEAU)
│   │   ├── Lignes                          (reference/bus/lines.mdx — NOUVEAU)
│   │   ├── Départs                         (reference/bus/departures.mdx — NOUVEAU)
│   │   └── Sièges                          (reference/bus/seats.mdx — NOUVEAU)
│   ├── group « Réservation »
│   │   ├── Devis                           (reference/booking/quotes.mdx — NOUVEAU)
│   │   ├── Blocage de sièges (holds)       (reference/booking/holds.mdx — NOUVEAU)
│   │   ├── Réservations                    (reference/booking/reservations.mdx — NOUVEAU)
│   │   └── Passagers                       (reference/booking/passengers.mdx — NOUVEAU)
│   ├── group « Paiements »
│   │   ├── Initier un paiement             (reference/payments/initiate.mdx — NOUVEAU)
│   │   ├── Consulter un paiement           (reference/payments/show.mdx — NOUVEAU)
│   │   └── Confirmer (webhook)             (reference/payments/confirm.mdx — NOUVEAU)
│   └── group « Santé »
│       └── Healthcheck                     (reference/health.mdx — existant)
│
└── Tab « Sécurité »
    ├── Bonnes pratiques                    (security/best-practices.mdx — NOUVEAU)
    └── Rate limiting & throttling          (security/rate-limiting.mdx — NOUVEAU, extrait)
```

### 2.3 Correspondance FedaPay → KORA

| FedaPay | KORA (équivalent) |
|---------|-------------------|
| Concepts clés (Clé API, Client, Transaction, Webhook…) | Concepts clés (Départ, Segment O/D, Devis, Hold, Réservation, Paiement, Billet, Appareil, Token Sanctum, OTP) |
| Gestion des clients | Gestion des réservations + passagers |
| Gestion des collectes | Cycle de vie d'un paiement |
| Gestion des dépôts | (hors MVP — non documenté) |
| Webhooks et événements | Événements de paiement |
| SDKs (PHP/Node/Ruby…) | (aucun — on fournit des snippets Dart uniquement) |
| Dashboard (onglet) | (hors périmètre doc API — c'est KORA OPS Web, doc séparée) |
| Security (bonnes pratiques, fraudes) | Sécurité (tokens, OTP, appareils, throttling) |

---

## 3. Détail des pages à créer / réécrire

### 3.1 Pages NOUVELLES (16)

| Page | Contenu | Source |
|------|---------|--------|
| `concepts.mdx` | Vocabulaire métier : Départ, Ligne, Arrêt, Segment O/D, Siège, Devis, Hold, Réservation, Paiement, Billet, Appareil, Token Sanctum, OTP. Tableaux + exemples JSON. | `CONTEXT.md` + `introduction.mdx` |
| `guides/quotes-and-holds.mdx` | Flow devis → hold → expiration. TTLs (`KORA_QUOTE_TTL_MINUTES`, `KORA_HOLD_TTL_MINUTES`). Exemples Curl+Dart. | extrait de `seat-selection.mdx` + `bus.mdx` |
| `guides/booking-lifecycle.mdx` | États d'une réservation (`held` → `confirmed` → `paid` → `ticketed` → `boarded`, `expired`, `cancelled`). Reprise après perte de session via `GET /bookings/{uuid}`. Enregistrement passagers. Libération volontaire. | extrait de `seat-selection.mdx` |
| `guides/payment-lifecycle.mdx` | Initiation → polling → confirmation (webhook). Providers. Échec après expiration de hold. | `routes/api.php` + `PaymentController` |
| `guides/devices.mdx` | Appareils de confiance, révocation, nouvel appareil → OTP. | extrait de `authentication.mdx` |
| `guides/preferences.mdx` | Lecture/écriture préférences (push, whatsapp, email, locale, theme). | extrait de `authentication.mdx` |
| `guides/webhooks.mdx` | Événement de confirmation paiement, signature, idempotence, rejeu, comportement sur réservation expirée. | `PaymentController::confirm` + tests |
| `reference/introduction.mdx` | Structure REST, versionnement `/api/v1/`, conventions (UUID, `data`/`error`), environnements, auth Bearer. | fusion `introduction.mdx` + `error-handling.mdx` (partie conventions) |
| `reference/auth/otp.mdx` | `POST /auth/otp/request`, `POST /auth/otp/verify` | `reference/auth.mdx` + `authentication.mdx` |
| `reference/auth/sessions.mdx` | `register/complete`, `login`, `pin/reset`, `unlock`, `pin/change`, `logout` | idem |
| `reference/auth/oauth.mdx` | `POST /auth/oauth/token` + providers + codes d'erreur | extrait `authentication.mdx` |
| `reference/auth/profile.mdx` | `GET/PUT /auth/me` | idem |
| `reference/auth/devices.mdx` | `GET /auth/devices`, `DELETE /auth/devices/{uuid}` | idem |
| `reference/auth/preferences.mdx` | `GET/PUT /auth/preferences` | idem |
| `reference/bus/{cities,lines,departures,seats}.mdx` | Un endpoint ou groupe par page | `reference/bus.mdx` éclaté |
| `reference/booking/{quotes,holds,reservations,passengers}.mdx` | Endpoints réservation | `bus.mdx` (bas) + routes |
| `reference/payments/{initiate,show,confirm}.mdx` | Endpoints paiement | routes + `PaymentController` |
| `security/best-practices.mdx` | Stockage du token, rotation, fingerprints, OTP, ne pas logger les secrets | nouveau |
| `security/rate-limiting.mdx` | Throttles, headers, backoff | extrait `error-handling.mdx` |

### 3.2 Pages à RÉÉCRIRE (5)

| Page | Changement |
|------|------------|
| `introduction.mdx` | Alléger : présentation produit + périmètre MVP + lien vers Concepts. Retirer les conventions techniques (→ `reference/introduction`). |
| `quickstart.mdx` | Garder le parcours villes → départs. Ajouter un mini-flow réservation. Référencer les nouveaux guides. |
| `guides/bus-search.mdx` | Retirer la partie devis/hold (→ `quotes-and-holds.mdx`). Garder villes → lignes → départs → sièges. |
| `guides/seat-selection.mdx` | Retirer la partie cycle de réservation (→ `booking-lifecycle.mdx`). Garder statuts + logique segment + UI Flutter. |
| `guides/error-handling.mdx` | Retirer rate limiting (→ `security/rate-limiting.mdx`). Garder structure d'erreur + codes métier + gestion Flutter. |

### 3.3 Pages à SUPPRIMER / fusionner (3)

| Page | Devenir |
|------|---------|
| `authentication.mdx` (768 lignes) | Remplacé par `guides/authentication.mdx` (flow court ~150 lignes) + pages reference/auth/* |
| `reference/auth.mdx` | Éclaté en `reference/auth/{otp,sessions,oauth,profile,devices,preferences}.mdx` |
| `reference/bus.mdx` | Éclaté en `reference/bus/{cities,lines,departures,seats}.mdx` + `reference/booking/*` |

### 3.4 Snippets à enrichir

- `snippets/dart-auth.mdx` — ajouter helper `authDio` réutilisable.
- `snippets/dart-http-client.mdx` — exposer `KoraApiClient.create(authToken:)`.
- (Optionnel) `snippets/dart-error-handler.mdx` — interceptor `KoraApiException`.

---

## 4. Plan d'exécution par phases

### Phase 1 — Fondations (sans casser l'existant)

1. Créer `concepts.mdx` (vocabulaire métier depuis `CONTEXT.md`).
2. Créer `reference/introduction.mdx` (conventions + environnements).
3. Réécrire `introduction.mdx` (allégée).
4. Mettre à jour `docs.json` : ajouter onglets « Sécurité » et groupes, sans retirer l'ancien.

**Livrable** : la doc existante reste accessible, les nouvelles pages s'ajoutent.

### Phase 2 — Éclatement de l'authentification

5. Créer `guides/authentication.mdx` (flow OTP+PIN+Sanctum court).
6. Créer `reference/auth/{otp,sessions,oauth,profile,devices,preferences}.mdx`.
7. Créer `guides/devices.mdx` et `guides/preferences.mdx`.
8. Supprimer l'ancien `authentication.mdx` et `reference/auth.mdx`.
9. Mettre à jour `docs.json`.

**Livrable** : l'auth n'est plus un monolithe.

### Phase 3 — API Reference par ressource

10. Éclater `reference/bus.mdx` → `reference/bus/{cities,lines,departures,seats}.mdx`.
11. Créer `reference/booking/{quotes,holds,reservations,passengers}.mdx`.
12. Créer `reference/payments/{initiate,show,confirm}.mdx`.
13. Supprimer l'ancien `reference/bus.mdx`.
14. Mettre à jour `docs.json`.

**Livrable** : couverture complète des 20 endpoints, organisée par ressource.

### Phase 4 — Guides métier de bout en bout

15. Créer `guides/quotes-and-holds.mdx`.
16. Créer `guides/booking-lifecycle.mdx`.
17. Créer `guides/payment-lifecycle.mdx`.
18. Créer `guides/webhooks.mdx`.
19. Réviser `guides/bus-search.mdx` et `guides/seat-selection.mdx` (retraits).
20. Mettre à jour `docs.json`.

**Livrable** : parcours complets passager, comme les « Gestion des X » de FedaPay.

### Phase 5 — Sécurité & qualité

21. Créer `security/best-practices.mdx`.
22. Créer `security/rate-limiting.mdx` (extrait de `error-handling.mdx`).
23. Réviser `guides/error-handling.mdx`.
24. Enrichir `openapi.yaml` : ajouter schémas de réponse + exemples manquants (Payments, Bookings) — soit via annotations Scribe, soit édition manuelle complétée.
25. `npm run build` de validation Mintlify.
26. Mettre à jour `docs/runbooks/mintlify.md` (nouvelle structure).

**Livrable** : doc production-ready, build vert.

---

## 5. Amélioration de l'`openapi.yaml`

Le spec actuel (généré Scribe) a des `responses: default` sans schéma. Deux options :

- **Option A (recommandée)** : enrichir les annotations Scribe (`@response`, `@responseField`) dans les contrôleurs pour que `composer docs:generate` produise un spec complet. Conforme au runbook existant.
- **Option B** : maintenir un spec OpenAPI manuel complet à côté, utilisé par Mintlify. Plus de contrôle, mais double source de vérité.

→ Préférer **Option A** pour rester dans le pipeline existant. Compléter manuellement uniquement les endpoints que Scribe ne couvre pas bien (webhook confirm).

Tags OpenAPI à aligner sur la navigation :
- `Auth` → `Auth.Otp`, `Auth.Sessions`, `Auth.OAuth`, `Auth.Profile`, `Auth.Devices`, `Auth.Preferences`
- `Bus` → `Bus.Catalog`, `Bus.Sales` (quotes/holds)
- Ajouter `Bookings`, `Payments` (existe déjà), `Health` (existe déjà).

---

## 6. Standard des pages de référence API (modèle FedaPay)

Chaque page de référence API doit reproduire l'expérience FedaPay : **titre + méthode + URL + bouton Try** en haut, **description du body de requête et des réponses** au centre, **exemples de requête et de réponses** à droite (panneau latéral Mintlify).

### 6.1 Structure obligatoire d'une page de référence

```mdx
---
title: <Titre de l'endpoint>
description: <Une ligne>
---

# <Titre de l'endpoint>

<Phrase d'introduction — à quoi sert cet endpoint, quand l'appeler.>

<ApiMethod
  method="POST"
  path="/api/v1/bus/quotes"
  openapi="
    openapi: 3.0.3
    paths:
      /api/v1/bus/quotes:
        post:
          summary: Créer un devis tarifaire
          description: |
            <description complète>
          requestBody:
            required: true
            content:
              application/json:
                schema:
                  type: object
                  properties:
                    departure_uuid:
                      type: string
                      format: uuid
                      description: UUID du départ.
                    origin_stop_uuid:
                      type: string
                      format: uuid
                      description: UUID de l'arrêt d'origine.
                    # ... tous les champs
                  required:
                    - departure_uuid
                    - origin_stop_uuid
                    - destination_stop_uuid
                    - passenger_count
          responses:
            '201':
              description: Devis créé.
              content:
                application/json:
                  schema:
                    # ... schéma complet
                  example:
                    # ... exemple JSON
            '401':
              description: Token absent ou invalide.
              content:
                application/json:
                  schema:
                    # ... schéma erreur
                  example:
                    # ... exemple
            '422':
              description: Validation échouée ou devis expiré.
              content:
                application/json:
                  schema:
                    # ...
                  example:
                    # ...
          x-codeSamples:
            - lang: curl
              label: cURL
              source: |
                curl -X POST https://api.korabus.app/api/v1/bus/quotes \
                  -H 'Authorization: Bearer $TOKEN' \
                  -H 'Content-Type: application/json' \
                  -d '{ ... }'
            - lang: javascript
              label: JavaScript
              source: |
                const res = await fetch('https://api.korabus.app/api/v1/bus/quotes', {
                  method: 'POST',
                  headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                  body: JSON.stringify({ ... }),
                });
            - lang: typescript
              label: TypeScript
              source: |
                // avec typage KoraQuoteRequest
            - lang: dart
              label: Dart
              source: |
                final res = await authDio.post('/api/v1/bus/quotes', data: { ... });
  "
/>
```

### 6.2 Règles de contenu

#### Titre et en-tête
- **Titre** : verbe d'action + ressource, en français (ex: « Créer un devis », « Lister les villes », « Récupérer une réservation »).
- **Description frontmatter** : une ligne, affichée dans la navigation et le SEO.
- **Introduction** : 2-4 lignes expliquant **quand** appeler cet endpoint dans le parcours, pas seulement **ce qu'il fait**.

#### Body de requête (centre)
- Lister **tous** les paramètres : path, query, body.
- Pour chaque champ : `nom`, `type`, `required/optional`, `description`, `exemple`.
- Distinguer les champs publics (UUID/codes métier) des contraintes (format date `YYYY-MM-DD`, PIN 4 chiffres, etc.).

#### Réponses (centre + droite)
- Documenter **TOUS** les codes de réponse possibles pour l'endpoint, pas seulement le succès.
- Pour chaque code : `description` + `schéma` + `exemple` JSON complet.

Tableau de référence des codes à couvrir selon l'endpoint :

| Code | Quand l'inclure |
|------|-----------------|
| `200` | Succès GET/PUT |
| `201` | Création POST (quote, hold, paiement, inscription) |
| `202` | Action asynchrone (OTP request, login nouvel appareil) |
| `204` | DELETE réussi (révocation appareil, libération hold, logout) |
| `400` | Requête malformée (si applicable) |
| `401` | Endpoint authentifié — token absent/invalide |
| `403` | Permission insuffisante, compte suspendu |
| `404` | UUID introuvable (show, seats, booking, payment) |
| `409` | Conflit (hold concurrent, siège déjà réservé) |
| `422` | Validation échouée, devis expiré, contrat incohérent |
| `423` | Compte verrouillé (login, pin/change) |
| `429` | Rate limit dépassé (tous les endpoints throttled) |
| `500` | Erreur serveur (optionnel mais recommandé) |

> **Règle** : si un endpoint peut retourner un code, il doit être documenté avec un exemple. Pas de `responses: default` générique.

#### Exemples de requête (panneau droit)
**4 langages obligatoires** pour chaque endpoint :

| Langage | Label | Usage |
|---------|-------|-------|
| `curl` | cURL | Test rapide, debugging |
| `javascript` | JavaScript | Intégration web/Node, fetch natif |
| `typescript` | TypeScript | Intégration typée (interfaces KORA) |
| `dart` | Dart | Application mobile Flutter (cible principale) |

Règles :
- Les exemples JS/TS utilisent `fetch` (pas d'SDK propriétaire — KORA n'en a pas).
- L'exemple TypeScript inclut des **interfaces typées** pour la requête et la réponse (ex: `KoraQuoteRequest`, `KoraQuoteResponse`).
- L'exemple Dart utilise le pattern `KoraApiClient.create(authToken:)` du quickstart + `dio`.
- L'exemple cURL utilise `https://api.korabus.app` (production) comme base URL.
- Pour les endpoints authentifiés, tous les exemples incluent le header `Authorization: Bearer $TOKEN` (cURL) / `Bearer ${token}` (JS/TS) / `authDio` (Dart).

#### Bouton Try / Playground
- Mintlify génère automatiquement le bouton « Try it out » depuis le bloc OpenAPI inline.
- **Condition** : le bloc OpenAPI doit contenir `servers` avec les URLs d'environnement, ou le `docs.json` doit définir `api.server`.

### 6.3 Snippets réutilisables (DRY)

Pour éviter de répéter 4 blocs de code dans chaque page, définir dans `snippets/` :

| Snippet | Contenu |
|---------|---------|
| `snippets/dart-http-client.mdx` | `KoraApiClient.create({authToken})` + `dio` configuré |
| `snippets/dart-auth.mdx` | `authDio` = client avec token injecté |
| `snippets/js-fetch.mdx` | Helper `koraFetch(path, opts)` avec base URL + headers |
| `snippets/ts-types.mdx` | Interfaces TypeScript partagées (`KoraCity`, `KoraDeparture`, `KoraQuote`, `KoraReservation`, `KoraPayment`, etc.) |
| `snippets/curl-base.mdx` | Base URL + headers communs cURL |

Ces snippets sont importés dans les pages via `<Include>` ou référencés dans les `x-codeSamples`.

### 6.4 Modèle de page TypeScript — interfaces partagées

Définir un fichier `snippets/ts-types.mdx` centralisant toutes les interfaces :

```typescript
// Types partagés API KORA BUS v1

export interface KoraCity {
  city: string;
  country: string;
  stops_count: number;
}

export interface KoraDeparture {
  uuid: string;
  departure_date: string;
  departure_time: string;
  estimated_arrival_at: string | null;
  status: 'scheduled' | 'cancelled' | 'completed';
  line: KoraLine;
  bus: KoraBus;
  origin_stop: KoraStop;
  destination_stop: KoraStop;
  available_seats_count: number;
  fare_preview: KoraFarePreview;
}

export interface KoraQuoteRequest {
  departure_uuid: string;
  origin_stop_uuid: string;
  destination_stop_uuid: string;
  service_class_uuid: string | null;
  passenger_count: number;
}

export interface KoraQuoteResponse {
  uuid: string;
  departure_uuid: string;
  amount_per_passenger: number;
  total_amount: number;
  currency: string;
  expires_at: string;
}

export interface KoraApiError {
  error: {
    code: string;
    message: string;
    [key: string]: unknown;
  };
}

// ... etc pour Reservation, Payment, Seat, Device, Preferences
```

Chaque page de référence importe uniquement les types qu'elle utilise.

### 6.5 Exemple complet — page `reference/booking/quotes.mdx`

```mdx
---
title: Créer un devis
description: Figer le tarif d'un segment avant le blocage de sièges.
---

# Créer un devis

Après avoir choisi un départ et un segment O/D, appelez cet endpoint pour
obtenir un **tarif figé** (valable 10 minutes par défaut). Le mobile doit
conserver l'UUID du devis et le transmettre lors de la création du hold.

<ApiMethod
  method="POST"
  path="/api/v1/bus/quotes"
  openapi="
    # ... bloc OpenAPI complet avec :
    #   - requestBody schema (departure_uuid, origin_stop_uuid,
    #     destination_stop_uuid, service_class_uuid, passenger_count)
    #   - responses 201 (succès + exemple), 401, 422 (devis expiré,
    #     segment invalide, départ introuvable)
    #   - x-codeSamples : curl, javascript, typescript, dart
  "
/>

## Champs de la requête

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `departure_uuid` | UUID | ✅ | UUID du départ sélectionné. |
| `origin_stop_uuid` | UUID | ✅ | UUID de l'arrêt d'origine du segment. |
| `destination_stop_uuid` | UUID | ✅ | UUID de l'arrêt de destination. |
| `service_class_uuid` | UUID \| null | ❌ | Classe de service (null = défaut). |
| `passenger_count` | integer | ✅ | Nombre de passagers (≥ 1). |

## Codes de réponse

| Code | Description |
|------|-------------|
| `201` | Devis créé — `expires_at` indique la durée de validité. |
| `401` | Token Sanctum absent ou invalide. |
| `422` | Départ introuvable, segment invalide, devis expiré, ou `passenger_count` < 1. |

<Card title="Étape suivante" href="/reference/booking/holds" icon="arrow-right">
  Le devis en main, créez un **blocage de sièges** (hold).
</Card>
```

---

## 7. Critères d'acceptation

### Structure & navigation
- [ ] `npm run build` (Mintlify) passe sans erreur.
- [ ] Chaque endpoint des `routes/api.php` (20) a une page de référence dédiée ou groupée par ressource.
- [ ] Aucune page ne dépasse ~250 lignes (sauf guides de bout en bout légitimement longs).
- [ ] Une page « Concepts clés » existe et couvre le vocabulaire `CONTEXT.md`.
- [ ] Une section « Sécurité » existe.
- [ ] Un guide « Webhooks » existe.
- [ ] `docs.json` reflète la navigation cible (§2.2).
- [ ] `docs/runbooks/mintlify.md` est à jour.
- [ ] L'ancien `authentication.mdx` est supprimé (plus de monolithe de 768 lignes).

### Pages de référence API (standard §6)
- [ ] Chaque page de référence contient un bloc OpenAPI inline (`<ApiMethod>` ou équivalent) avec schéma de requête **et** schémas de réponse.
- [ ] Chaque page documente **tous** les codes de réponse possibles (200/201/202/204/401/403/404/409/422/423/429 selon l'endpoint) avec un exemple JSON pour chacun.
- [ ] Chaque page fournit **4 exemples de requête** : cURL, JavaScript, TypeScript, Dart (via `x-codeSamples`).
- [ ] Les exemples TypeScript incluent des interfaces typées partagées (depuis `snippets/ts-types.mdx`).
- [ ] Les exemples Dart utilisent le pattern `KoraApiClient` / `authDio` du quickstart.
- [ ] Le bouton « Try it out » (playground) est fonctionnel sur chaque page.
- [ ] Aucun `responses: default` sans schéma dans les pages de référence.
- [ ] Le fichier `snippets/ts-types.mdx` centralise toutes les interfaces TypeScript partagées.

---

## 8. Décisions à valider avant exécution

1. **Granularité de l'API Reference** : une page par endpoint (style FedaPay strict, ~20 pages) ou une page par sous-ressource (style proposé, ~16 pages) ? → *Proposition : par sous-ressource (plus maintenable).*
2. **Section Sécurité** : onglet séparé (comme FedaPay) ou groupe dans « Documentation » ? → *Proposition : onglet séparé.*
3. **openapi.yaml** : Option A (Scribe enrichi) ou B (manuel) ? → *Proposition : A.*
4. **Webhooks** : documenter dès maintenant (le endpoint `confirm` existe) ou attendre une vraie signature HMAC ? → *À valider : documenter le contrat actuel + marquer la signature comme roadmap.*
5. **Langue** : tout en français (comme actuel et FedaPay FR) ? → *Proposition : oui.*
6. **Blocs OpenAPI inline vs `openapi.yaml` global** : chaque page de référence embarque-t-elle son propre bloc OpenAPI inline (style FedaPay, maximal contrôle) ou s'appuie-t-elle sur le `openapi.yaml` global via `<Method path="...">` (plus DRY, moins de contrôle sur les exemples) ? → *Proposition : blocs inline par page pour les exemples et codes de réponse complets, `openapi.yaml` global conservé comme source Scribe pour cohérence.*
7. **Interfaces TypeScript** : les définir dans un snippet MDX central (`snippets/ts-types.mdx`) ou générer un vrai package `packages/kora-types` consommable ? → *Proposition : snippet MDX pour la doc (MVP), package dédié en roadmap.*
