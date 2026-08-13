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

- `POST /api/v1/auth/otp/request`: demande OTP email.
- `POST /api/v1/auth/otp/verify`: verification OTP et emission d'un jeton de verification court.
- `POST /api/v1/auth/register/complete`: creation/activation compte par PIN et appareil de confiance.
- `POST /api/v1/auth/login`: connexion par email + PIN; OTP obligatoire sur nouvel appareil.
- `POST /api/v1/auth/pin/reset`: reinitialisation PIN apres OTP email.

Endpoints proteges par token Sanctum:

- `GET /api/v1/auth/me`
- `GET /api/v1/auth/devices`
- `DELETE /api/v1/auth/devices/{device:uuid}`
- `POST /api/v1/auth/logout`

Les payloads publics exposent uniquement les UUID et valeurs textuelles. Les codes OTP et PIN sont toujours stockes hashes.

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
