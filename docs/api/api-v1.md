# API REST V1

Groupes d'API initiaux conformes au CDC MVP Bus.

- `/api/v1/auth`: OTP, connexion PIN, renouvellement, revocation et appareils.
- `/api/v1/bus`: villes, lignes, departs, details et disponibilite des sieges.
- `/api/v1/pricing`: simulation tarifaire, devis, regles applicables et prix fige.
- `/api/v1/bookings`: creation, passagers, blocage sieges, expiration et annulation.
- `/api/v1/payments`: initiation, statut, webhooks et reprise client.
- `/api/v1/tickets`: liste, detail, QR Code et telechargement.
- `/api/v1/ops/departures`: planning, manifeste, statuts et synchronisation terrain.
- `/api/v1/ops/boarding`: validation, no-show, conflits et synchronisation batch.
- `/api/v1/ops/seat-releases`: descente anticipee, liberation de segments, confirmation et historique.
- `/api/v1/ops/fleet-lite`: kilometrage, carburant, peages, positions et incidents.
- `/api/v1/fiscalization`: statut fiscal, reprises internes, rapprochement MECeF et consultation autorisee.

## Auth MVP

Endpoints publics:

- `POST /api/v1/auth/otp/request`: demande OTP par email ou téléphone (inscription incluse) ; livraison synchrone WhatsApp ou email, `503 otp_delivery_unavailable` si aucun canal joignable.
- `POST /api/v1/auth/otp/verify`: verification OTP et emission d'un jeton de verification court.
- `POST /api/v1/auth/register/complete`: creation/activation compte par PIN et appareil de confiance.
- `POST /api/v1/auth/login`: connexion par email ou téléphone + PIN ; `email` reste accepté comme alias rétrocompatible ; OTP obligatoire sur nouvel appareil.
- `POST /api/v1/auth/pin/reset`: reinitialisation PIN apres OTP WhatsApp ou email.

Endpoints proteges par token Sanctum:

- `GET /api/v1/auth/me`
- `GET /api/v1/auth/devices`
- `DELETE /api/v1/auth/devices/{device:uuid}`
- `POST /api/v1/auth/logout`

Les payloads publics exposent uniquement les UUID et valeurs textuelles. Les emails sont normalises en minuscules et les numeros locaux ou internationaux en format international avant resolution et rate limiting. Les codes OTP et PIN sont toujours stockes hashes.

## `/api/v1/bus`

Endpoints publics de lecture pour la recherche KORA BUS. Ces routes sont limitees par `throttle:bus-search` et exposent uniquement des UUID ou codes metier.

- `GET /api/v1/bus/cities` : villes desservies derivees des arrets actifs.
- `GET /api/v1/bus/lines?from=Cotonou&to=Abidjan` : lignes actives, filtrables par segment origine/destination.
- `GET /api/v1/bus/lines/{line_uuid}` : detail d'une ligne avec arrets ordonnes.
- `GET /api/v1/bus/departures?date=2026-08-15&from=Cotonou&to=Abidjan` : departs programmes pour une date et un segment.
- `GET /api/v1/bus/departures/{departure_uuid}` : detail depart avec ligne, bus, segment, disponibilite et apercu tarifaire.
- `GET /api/v1/bus/departures/{departure_uuid}/seats?origin_stop_uuid=...&destination_stop_uuid=...` : plan des sieges pour un segment.

La disponibilite siege vaut `available`, `occupied` ou `blocked`. Les statuts de reservation bloquants sont `held`, `confirmed`, `paid`, `ticketed` et `boarded`.

`fare_preview` est un apercu du tarif actif; il ne cree pas de devis fige. Le devis serveur fige reste porte par `/api/v1/pricing`.

## `/api/v1/tickets`

Endpoints proteges par token Sanctum pour la consultation des billets du passager.

- `GET /api/v1/tickets` : liste paginee de tous les billets du passager authentifie (tous statuts confondus), avec un contrat enrichi pour l'ecran « Mes voyages » de l'app passager (route, date/heure de depart, siege, gare, montant, QR code, statut mappe en `confirmed` / `completed` / `cancelled`). Un billet appartient au passager si `created_by_user_id` ou `payer_user_id` de la reservation parente correspond a l'utilisateur connecte.
- `GET /api/v1/tickets/{ticket_uuid}` : detail d'un billet individuel par UUID public, reserve au proprietaire de la reservation.
- `GET /api/v1/bookings/{reservation_uuid}/tickets` : billets emis d'une reservation specifique (un par passager), apres confirmation du paiement.

## Wallet passager

Les routes Wallet ci-dessous utilisent le préfixe `/api/v1` et le jeton du voyageur.

### Créer ou rejouer une recharge

`POST /wallet/topups`

```json
{
  "amount": 5000,
  "method": "mobile_money",
  "provider": "kkiapay",
  "idempotency_key": "c64dbcce-cffa-464c-ab62-719c06755489"
}
```

KKiaPay collecte le réseau dans le widget. Pour une recharge Mobile Money FedaPay ou MTN, fournir aussi `phone` et `mobile_money_network`. Le serveur retourne `201` pour une création et `200` lorsqu'il retrouve la recharge. Un rejeu réseau réutilise la clé. Une demande différente avec une clé déjà liée reçoit `409`.

```json
{
  "data": {
    "uuid": "13f884dc-1b38-4447-9167-05cb7ba11ab5",
    "amount": 5000,
    "status": "pending",
    "payment": {
      "uuid": "e87e9032-6ae4-43d9-ac9c-5570d524813f",
      "status": "pending",
      "provider": "kkiapay",
      "provider_reference": null,
      "next_action": "open_widget",
      "widget": {
        "key": "public-sandbox-key",
        "sandbox": true,
        "amount": 5000,
        "partnerId": "e87e9032-6ae4-43d9-ac9c-5570d524813f",
        "paymentmethod": ["momo"],
        "countries": ["BJ"]
      },
      "checkout_url": null,
      "polling_interval_seconds": 10,
      "failure": null
    }
  }
}
```

- `open_widget` ouvre le widget à partir de `payment.widget`. À son retour, transmettre le `transaction_id` à `POST /payments/{payment_uuid}/verify`, puis consulter `GET /wallet/topups/{uuid}` si le paiement reste en cours. Le retour du widget seul ne prouve pas l'encaissement.
- `open_url` ouvre `payment.checkout_url` pour FedaPay carte. Au retour, appeler la vérification serveur. `verify` invite à vérifier l'état serveur, notamment après la demande Mobile Money sur le téléphone.
- `wait` signale une initiation en cours ou indéterminée. Respecter l'intervalle de vérification et conserver les UUID. `retry` indique une tentative terminée en échec. Utiliser une nouvelle clé si le voyageur choisit de recommencer. `done` indique que la recharge a été créditée.
- `payment.failure` contient, le cas échéant, `code`, `message`, `retryable` et `diagnostic_reference`. Joindre cette référence à un ticket support.

`GET /wallet` expose `settings.points_expiry_reminder_days` et `settings.loyalty_bonuses` avec `welcome_points`, `referral_points`, `anniversary_points`, `usage_points` et `usage_threshold`.

`GET /wallet/transactions?direction=credit&balance_type=points` accepte les deux filtres indépendamment. Les liens par curseur conservent les filtres. Le champ `direction` accepte `credit` ou `debit`.

`GET /auth/me` et les réponses de session exposent `data.user.registration_origin`. Les valeurs sont `phone`, `email`, `assisted`, `social` et `unknown`.

Pour acheter un billet, `points_amount` est un nombre de points et `wallet_amount` un montant cash en FCFA. Le complément opérateur correspond au prix moins le cash Wallet et la valeur des points. La réponse paiement expose `wallet_funding` avec ces deux quantités, `point_value_fcfa`, `points_value_fcfa` et `provider_amount_fcfa`.
