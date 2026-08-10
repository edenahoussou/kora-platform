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

