# Plan API — Parcours passager BUS (recherche → paiement)

> Source : analyse des maquettes `docs/maquettes/kora-app-passenger/` + modèles/migrations existants.
> Périmètre : du premier écran (accueil) jusqu'à la confirmation de paiement (booking-success).
> Hors périmètre : billets/tickets, tracking, annulation, wallet, ride, colis (Sprint 3+).

---

## 1. Parcours utilisateur — 5 étapes

```
[Accueil] → [Recherche] → [Résultats] → [Détail départ + Sièges] → [Passagers] → [Récapitulatif] → [Paiement] → [Confirmation]
```

### Écrans maquette impliqués

| Étape | Écran(s) maquette | Code maquette |
|-------|-------------------|---------------|
| 1. Accueil | `home` | HOME-01 |
| 2. Recherche | `bus-search`, `origin`, `destination`, `date`, `passengers` | BUS-01 à BUS-06 |
| 3. Résultats | `searching`, `results`, `no-results`, `offline-results` | BUS-07 à BUS-10 |
| 4. Détail + Sièges | `departure`, `stops`, `seats`, `expired` | BUS-11 à BUS-14 |
| 5. Passagers | `passenger-info`, `international-info` | BUS-15 à BUS-16 |
| 6. Récapitulatif | `summary` | PAY-01 |
| 7. Paiement | `payment-method`, `mobile-money`, `mobile-validation`, `payment-pending`, `payment-refused`, `payment-timeout`, `wallet-payment`, `card-payment-bus` | PAY-02 à PAY-08 |
| 8. Confirmation | `booking-success` | PAY-09 |

---

## 2. API existantes (déjà opérationnelles)

| Endpoint | Écran | Données retournées | Statut |
|----------|-------|--------------------|--------|
| `GET /api/v1/auth/me` | Accueil | User (nom, email, phone) | ✅ |
| `GET /api/v1/bus/cities` | Recherche (origin/destination) | Liste villes desservies | ✅ |
| `GET /api/v1/bus/lines?from=&to=` | Recherche | Lignes actives filtrées | ✅ |
| `GET /api/v1/bus/lines/{uuid}` | Détail départ | Ligne + arrêts ordonnés | ✅ |
| `GET /api/v1/bus/departures?date=&from=&to=` | Résultats | Départs du jour (heure, prix preview, places dispo) | ✅ |
| `GET /api/v1/bus/departures/{uuid}` | Détail départ | Départ + ligne + bus + segment + fare_preview | ✅ |
| `GET /api/v1/bus/departures/{uuid}/seats?origin_stop_uuid=&destination_stop_uuid=` | Plan sièges | Sièges (code, type, sellable, availability_status) | ✅ |

---

## 3. API à créer — 4 nouveaux endpoints

### 3.1 `POST /api/v1/bus/quotes` — Devis tarifaire figé

**Écran maquette** : `summary` (PAY-01) — récapitulatif avant paiement.

**Rôle** : Calculer un prix figé avec snapshot et expiration (10 min). Le devis inclut le tarif de base, le coefficient de pricing dynamique, et le montant final. Une fois figé, le prix ne change plus même si les règles de remplissage évoluent.

**Auth** : `auth:sanctum` (le passager doit être connecté).

**Request** :
```json
{
  "departure_uuid": "uuid",
  "origin_stop_uuid": "uuid",
  "destination_stop_uuid": "uuid",
  "service_class_id": "uuid|null",
  "passenger_count": 1
}
```

**Response 201** :
```json
{
  "uuid": "price_quote_uuid",
  "departure_uuid": "uuid",
  "base_amount": 12500,
  "coefficient": 1.0,
  "final_amount": 12500,
  "currency": "XOF",
  "passenger_count": 1,
  "total_amount": 12500,
  "expires_at": "2026-08-20T08:10:00Z",
  "snapshot": {
    "fare_uuid": "uuid",
    "line_code": "L2",
    "origin_stop": "Cotonou · St Michel",
    "destination_stop": "Malanville",
    "service_class": "confort"
  }
}
```

**Logique backend** :
- `CalculateQuoteAction` : récupère le `Fare` actif pour le segment O/D → applique les `PricingRule` (coefficient dynamique) → fige le snapshot → génère `expires_at` (now + 10 min)
- Modèle `PriceQuote` existe déjà (migration + model)
- Idempotent : même departure + segment + passenger_count → retourne le même devis s'il n'est pas expiré

**Tests** :
- Quote créé avec expiration 10 min
- Quote expiré → 422 si on tente de l'utiliser
- Segment O/D invalide → 422
- Passenger_count > 1 → total_amount = final_amount × passenger_count

---

### 3.2 `POST /api/v1/bus/holds` — Blocage de sièges

**Écran maquette** : `seats` (BUS-11) — timer 7:00 minutes sur la sélection.

**Rôle** : Bloquer les sièges sélectionnés pour le segment O/D pendant 15 minutes. Empêche la vente concurrente. Utilise la contrainte GIST `no_overlap` sur `reservation_segments` pour garantir l'exclusion au niveau base de données.

**Auth** : `auth:sanctum`.

**Request** :
```json
{
  "departure_uuid": "uuid",
  "origin_stop_uuid": "uuid",
  "destination_stop_uuid": "uuid",
  "price_quote_uuid": "uuid",
  "seats": [
    {
      "seat_uuid": "uuid",
      "passenger_index": 0
    },
    {
      "seat_uuid": "uuid",
      "passenger_index": 1
    }
  ]
}
```

**Response 201** :
```json
{
  "uuid": "reservation_uuid",
  "status": "held",
  "expires_at": "2026-08-20T08:15:00Z",
  "segments": [
    {
      "uuid": "segment_uuid",
      "seat_code": "4A",
      "origin_stop": "Cotonou · St Michel",
      "destination_stop": "Malanville",
      "status": "held"
    }
  ],
  "total_amount": 12500,
  "currency": "XOF"
}
```

**Response 409** (siège déjà bloqué/occupé) :
```json
{
  "message": "Le siège 4A n'est plus disponible sur ce segment.",
  "conflicting_seat_uuid": "uuid",
  "seat_code": "4A"
}
```

**Logique backend** :
- `CreateSeatHoldAction` :
  1. Valider le `PriceQuote` (non expiré)
  2. Vérifier la disponibilité de chaque siège via `FindOccupiedSeatIdsAction`
  3. Créer une `Reservation` (status=held, channel=mobile, expires_at=now+15min)
  4. Créer les `ReservationSegment` (status=held) — la contrainte GIST `no_overlap` garantit l'exclusion au niveau DB
  5. En cas de conflit GIST → rollback + retour 409
- Verrou Redis optionnel pour éviter les race conditions avant l'INSERT DB

**Exclusion de segments** :
- Un siège occupé Cotonou→Parakou (origin_order=1, dest_order=5) ne peut pas être vendu sur Cotonou→Allada (1→3) car les ranges se chevauchent
- Mais il peut être vendu sur Allada→Parakou (3→5) car les ranges se chevauchent aussi — c'est le comportement correct : le siège est libre sur ce sous-segment
- La contrainte GIST `int4range(origin_stop_order, destination_stop_order, '[)') WITH &&` gère ça nativement

**Tests** :
- Hold créé avec expiration 15 min
- Siège déjà occupé → 409
- Deux holds concurrents sur le même siège → un réussit, l'autre 409
- Hold sur sous-segment autorisé si le siège est occupé sur un segment non-chevauchant
- Quote expiré → 422

---

### 3.3 `POST /api/v1/bookings/{reservation_uuid}/passengers` — Saisie passagers

**Écran maquette** : `passenger-info` (BUS-15), `international-info` (BUS-16).

**Rôle** : Enregistrer les informations passagers associées à la réservation (nom, téléphone, document pour international).

**Auth** : `auth:sanctum`.

**Request** :
```json
{
  "passengers": [
    {
      "first_name": "Aïcha",
      "last_name": "HOUNKPÈ",
      "phone": "+2290197421860",
      "email": "aicha@example.com",
      "document_type": "passport",
      "document_number": "BJ0427851",
      "nationality": "BJ",
      "birth_date": "1994-04-18",
      "seat_uuid": "uuid"
    }
  ]
}
```

**Response 200** :
```json
{
  "uuid": "reservation_uuid",
  "status": "held",
  "passengers": [
    {
      "uuid": "passenger_uuid",
      "first_name": "Aïcha",
      "last_name": "HOUNKPÈ",
      "seat_code": "4A"
    }
  ],
  "expires_at": "2026-08-20T08:15:00Z"
}
```

**Logique backend** :
- `UpdateReservationPassengersAction` :
  1. Vérifier que la reservation est `held` et non expirée
  2. Le nombre de passagers doit correspondre au nombre de segments
  3. Créer/mettre à jour les `ReservationPassenger`
  4. Associer chaque `ReservationSegment.reservation_passenger_id`
- Validation : document_type/document_number/nationality obligatoires si la ligne traverse une frontière (`countries_crossed` > 1)

**Tests** :
- Passagers enregistrés correctement
- Trop/pas assez de passagers → 422
- Ligne internationale sans document → 422
- Reservation expirée → 422

---

### 3.4 `POST /api/v1/payments/initiate` — Initier le paiement

**Écran maquette** : `payment-method` (PAY-02) → `mobile-money` / `wallet-payment` / `card-payment-bus`.

**Rôle** : Initier le paiement pour une réservation held. Crée un `Payment` (status=pending) et déclenche le flux provider (Mobile Money, Wallet, ou Card).

**Auth** : `auth:sanctum`.

**Request** :
```json
{
  "reservation_uuid": "uuid",
  "method": "mobile_money|wallet|card",
  "provider": "mtn_momo|moov_money|celtiis_cash|wave|kora_wallet|visa|mastercard",
  "phone": "+2290197421860",
  "wallet_amount": 5000,
  "points_amount": 500,
  "idempotency_key": "client-generated-uuid"
}
```

**Response 201** :
```json
{
  "uuid": "payment_uuid",
  "status": "pending",
  "amount": 12500,
  "currency": "XOF",
  "provider": "mtn_momo",
  "provider_reference": "MM-98270341",
  "expires_at": "2026-08-20T08:15:00Z",
  "polling_interval_seconds": 10
}
```

**Response 200** (paiement déjà initié — idempotent) :
```json
{
  "uuid": "payment_uuid",
  "status": "pending|confirmed|failed",
  "amount": 12500,
  "currency": "XOF"
}
```

**Logique backend** :
- `InitiatePaymentAction` :
  1. Vérifier que la reservation est `held` et non expirée
  2. Vérifier l'idempotency_key → si un payment existe déjà avec cette clé, retourner son statut
  3. Créer le `Payment` (status=pending, idempotency_key)
  4. Pour Mobile Money : appeler l'API provider (MTN/Moov/Celtiis/Wave) pour envoyer la demande USSD/push
  5. Pour Wallet : vérifier le solde, débiter le wallet si suffisant, confirmer immédiatement
  6. Pour Card : initier le flux Stripe/redirect 3DS
  7. Retourner le payment avec provider_reference pour polling

**Tests** :
- Payment créé avec status pending
- Idempotency : même clé → retourne le même payment
- Reservation expirée → 422
- Wallet avec solde insuffisant → 422

---

### 3.5 `GET /api/v1/payments/{payment_uuid}` — Statut paiement (polling)

**Écran maquette** : `payment-pending` (PAY-05) — "Vérification automatique toutes les 10 secondes".

**Rôle** : Polling du statut de paiement par le client mobile pendant que le passager confirme sur son téléphone.

**Auth** : `auth:sanctum`.

**Response 200** :
```json
{
  "uuid": "payment_uuid",
  "status": "pending|confirmed|failed|cancelled|refunded",
  "amount": 12500,
  "currency": "XOF",
  "provider": "mtn_momo",
  "provider_reference": "MM-98270341",
  "confirmed_at": "2026-08-20T08:05:00Z|null",
  "reservation": {
    "uuid": "reservation_uuid",
    "status": "held|paid|ticketed",
    "departure_uuid": "uuid"
  }
}
```

**Logique backend** :
- Vérifier que le payment appartient au user authentifié
- Si le payment est `confirmed` → la reservation passe à `paid` (via listener/job)
- Si le payment est `failed` → retourner le motif

**Tests** :
- Payment pending → 200
- Payment confirmed → 200 avec reservation.status=paid
- Payment d'un autre user → 403

---

### 3.6 `POST /api/v1/payments/{payment_uuid}/confirm` — Webhook/callback provider

**Rôle** : Endpoint appelé par le provider (MTN, Moov, Stripe) pour confirmer/refuser le paiement. Non appelé par le mobile.

**Auth** : HMAC signature du provider (pas Sanctum).

**Request** (varie selon provider) :
```json
{
  "provider_reference": "MM-98270341",
  "status": "success|failed",
  "provider_payload": { ... }
}
```

**Logique backend** :
- `ConfirmPaymentAction` :
  1. Vérifier la signature HMAC
  2. Retrouver le payment par provider_reference
  3. Si success → payment.status=confirmed, reservation.status=paid, déclencher l'émission du ticket
  4. Si failed → payment.status=failed, libérer les sièges (reservation.status=expired)

---

## 4. Récapitulatif — Flux complet API

```
Étape 1 — Accueil
  GET /api/v1/auth/me                          [existant]

Étape 2 — Recherche
  GET /api/v1/bus/cities                       [existant]
  GET /api/v1/bus/lines?from=&to=              [existant]

Étape 3 — Résultats
  GET /api/v1/bus/departures?date=&from=&to=   [existant]

Étape 4 — Détail + Sièges
  GET /api/v1/bus/departures/{uuid}            [existant]
  GET /api/v1/bus/departures/{uuid}/seats      [existant]
  POST /api/v1/bus/quotes                      [À CRÉER — MEZ-33]

Étape 5 — Passagers
  POST /api/v1/bookings/{uuid}/passengers      [À CRÉER]

Étape 6 — Blocage sièges
  POST /api/v1/bus/holds                       [À CRÉER — MEZ-33]

Étape 7 — Paiement
  POST /api/v1/payments/initiate               [À CRÉER]
  GET /api/v1/payments/{uuid}                  [À CRÉER — polling]
  POST /api/v1/payments/{uuid}/confirm         [À CRÉER — webhook provider]

Étape 8 — Confirmation
  (Le mobile redirige vers l'écran billet une fois payment.status=confirmed)
  GET /api/v1/payments/{uuid}                  [polling → confirmed → booking-success]
```

---

## 5. Ordre des statuts de réservation

```
held → confirmed → paid → ticketed → boarded
  ↓
expired (hold expiré ou paiement échoué)
  ↓
cancelled (annulation manuelle)
```

- `held` : sièges bloqués, en attente de paiement (15 min)
- `confirmed` : passagers saisis, prêt à payer
- `paid` : paiement confirmé
- `ticketed` : billet émis avec QR code
- `boarded` : passager embarqué

---

## 6. Modèles backend existants (prêts à utiliser)

| Modèle | Table | Rôle |
|--------|-------|------|
| `PriceQuote` | `price_quotes` | Devis figé avec snapshot + expires_at |
| `Reservation` | `reservations` | Réservation (status, total, channel, expires_at) |
| `ReservationPassenger` | `reservation_passengers` | Infos passagers (nom, phone, document) |
| `ReservationSegment` | `reservation_segments` | Segment O/D + siège (contrainte GIST no_overlap) |
| `Payment` | `payments` | Paiement (provider, status, idempotency_key) |
| `Ticket` | `tickets` | Billet (qr_code, short_code, status) |

**Actions existantes** :
- `CalculateFarePreviewAction` (aperçu tarifaire — à étendre pour devis figé)
- `BuildSeatAvailabilityAction` (disponibilité sièges par segment)
- `FindOccupiedSeatIdsAction` (exclusion de segments O/D)

---

## 7. Ce qui n'est PAS dans ce périmètre (Sprint 3+)

- `GET /api/v1/tickets` — liste billets du user
- `GET /api/v1/tickets/{uuid}` — détail billet + QR code
- `POST /api/v1/tickets/{uuid}/cancel` — annulation + remboursement
- `GET /api/v1/bus/departures/{uuid}/tracking` — suivi bus temps réel
- `GET /api/v1/notifications` — notifications push
- Wallet (recharge, historique, transfert)
- KORA RIDE, KORA COLIS, Premium

---

## 8. Plan d'implémentation — ordre suggéré

### Phase 1 — Devis + Holds (MEZ-33, Urgent)
1. `CalculateQuoteAction` + `QuoteController` + `StoreQuoteRequest` + `PriceQuoteResource`
2. `CreateSeatHoldAction` + `SeatHoldController` + `StoreSeatHoldRequest` + `ReservationResource`
3. `ExpireSeatHolds` job (libère les holds expirés)
4. Tests Pest : quotes, holds, exclusion segments, accès concurrent, expiration

### Phase 2 — Passagers
5. `UpdateReservationPassengersAction` + `ReservationPassengerController` + `StorePassengersRequest` + `ReservationPassengerResource`
6. Tests Pest : saisie passagers, validation international

### Phase 3 — Paiement
7. `InitiatePaymentAction` + `PaymentController` + `InitiatePaymentRequest` + `PaymentResource`
8. `ConfirmPaymentAction` (webhook provider)
9. `GetPaymentStatusController` (polling)
10. Tests Pest : initiation, idempotency, confirmation, échec

### Phase 4 — Documentation
11. Annotations Scribe sur tous les nouveaux endpoints
12. Mise à jour `docs/api/api-v1.md` et `docs/mintlify/`
