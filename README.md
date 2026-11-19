# KORA Platform

Monorepo de la plateforme KORA. Le MVP KORA BUS est livre et en exploitation. Le Wallet, la fidelite et le reseau KORA POINT sont egalement sortis en production ; le prochain chantier est KORA Colis interurbain.

## Etat du projet

| Phase | Contenu | Statut |
| --- | --- | --- |
| 1 - KORA BUS (MVP) | Recherche, reservation, vente guichet, inventaire par segment, tarification dynamique, paiement, billet signe Ed25519, fiscalisation MECeF, caisse, embarquement P501 hors ligne, GPS bus, liberation et revente de sieges | Livre et en exploitation |
| 2 - Wallet & fidelite | Compte voyageur, Wallet cash, points, holds, recharges Mobile Money, remboursements exclusivement en Wallet, parrainage, versions de regles de fidelite | Livre et en exploitation ; durcissement des cas limites en cours (ADR 0024 a 0026) |
| 2 - KORA POINT | Reseau de distribution partenaire, espace partenaire web, visites terrain, PLV, commissions et reversements | Livre et en exploitation (ADR 0021 a 0023) |
| 3 - KORA Colis | Envois interurbains hub a hub sur depart Bus direct, pesee, tarification, paiements partages, etiquette QR et code de retrait | Prochain chantier ; socle en revue sur la branche `feat/colis-socle` |

Hors perimetre actuel : KORA Ride, KORA Driver, collecte et livraison a domicile (phase VTC), comptabilite SYSCOHADA complete, RH, paie et flotte avancee. Toute extension de perimetre passe par un ADR ou un ticket de changement formel, puis met ce tableau a jour.

Les livraisons sont tracees par les tags `v1.0.x`. Chaque fusion sur `main` part automatiquement en staging ; la production se deploie sur tag.

## Structure

- `apps/kora-ops-web`: Laravel 13, React, TypeScript et Inertia pour KORA OPS Web et l'API REST.
- `apps/kora-app-passenger`: application Flutter client Bus et Wallet dans KORA App.
- `apps/kora-ops-mobile`: application Flutter Android/P501 pour chauffeur et convoyeur.
- `packages`: contrats et packages partages.
- `docs`: contexte, ADR, API, delivery et runbooks.
- `infra/docker`: services locaux PostgreSQL et Redis.

## Modules metier

Le backend est un monolithe modulaire DDD. Les modules de la plateforme vivent dans `apps/kora-ops-web/app/Modules` :

- Socle Bus : `BusNetwork`, `Departures`, `SeatInventory`, `DynamicPricing`, `Booking`, `Payments`, `Ticketing`, `Fiscalization`, `Boarding`, `Cash`, `FleetLite`, `Sync`, `Tracking`.
- Compte et distribution : `IdentityAccess`, `Passengers`, `Wallet`, `KoraPoints`, `CompanyAccounts`, `AppDistribution`.
- Exploitation et pilotage : `AssistedBooking`, `Support`, `Notifications`, `Audit`, `Settings`, `Monitoring`, `Expenses`, `Finance`, `Profitability`, `Dashboard`.

## Demarrage local

```bash
cd /var/www/html/kora-platform
docker compose up -d kora-ops-web
cd apps/kora-ops-web
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
composer test
```

## Documentation

- `CONTEXT.md`: glossaire metier et perimetre de reference.
- `docs/adr`: decisions d'architecture et de perimetre.
- `docs/api`: contrats API et specification OpenAPI.
- `docs/runbooks`: deploiement, CI/CD, developpement local, releases mobiles.
- `docs/delivery`: guides de recette et decisions de sprint.

## Signature des billets (Ed25519)

Les QR codes des billets sont signes avec **Ed25519** (libsodium, asymetrique). Le serveur signe avec la cle privee (`KORA_TICKET_SIGNING_PRIVATE_KEY`), le P501 verifie hors ligne avec la cle publique (`KORA_TICKET_SIGNING_PUBLIC_KEY`). La cle publique peut etre distribuee ouvertement sans risque.

- Exporter la cle publique pour le P501 : `php artisan tickets:public-key`
- Generer une nouvelle paire de cles : voir `AGENTS.md` > Ticket QR Signing.
- Implementation : `apps/kora-ops-web/app/Modules/Ticketing/Actions/BuildTicketQrPayloadAction.php`
