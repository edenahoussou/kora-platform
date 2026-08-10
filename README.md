# KORA Platform

Monorepo du MVP KORA BUS valide par le client. Le perimetre MVP est centre sur l'exploitation Bus de bout en bout : recherche, reservation, vente guichet, inventaire par segment, tarification dynamique, paiement, billet, fiscalisation MECeF, caisse, embarquement P501 hors ligne, GPS bus, liberation et revente de sieges.

Le repo garde aussi le nommage cible de l'ecosysteme : KORA App pour les clients, KORA OPS Mobile pour les operations Bus/terrain, KORA OPS Web pour l'ERP interne, et KORA Driver plus tard pour Ride.

## Structure

- `apps/kora-ops-web`: Laravel 13, React, TypeScript et Inertia pour KORA OPS Web et l'API REST.
- `apps/kora-app-passenger`: application Flutter client Bus dans KORA App.
- `apps/kora-ops-mobile`: application Flutter Android/P501 pour chauffeur et convoyeur.
- `packages`: contrats et packages partages.
- `docs`: contexte, ADR, API, delivery et runbooks.
- `infra/docker`: services locaux PostgreSQL et Redis.

## MVP Exclu

KORA Colis, KORA Wallet, KORA POINT, KORA Ride, KORA Driver, comptabilite SYSCOHADA complete, RH, paie et flotte avancee sont hors MVP Bus.

## Demarrage local

```bash
cd /var/www/html/kora-platform
docker compose up -d kora-ops-web
cd apps/kora-ops-web
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan test
```
