# MVP Bus uniquement

Statut : partiellement remplacé, conservé comme historique.

Le Wallet et la fidélité Bus ont été inclus par [l'ADR 0004](0004-wallet-fidelite-bus.md). KORA POINT a depuis été livré (ADR 0021 à 0023) et KORA Colis interurbain est la phase suivante. Restent hors périmètre les exclusions Ride, Driver, collecte et livraison à domicile (phase VTC), comptabilité SYSCOHADA complète, RH, paie et flotte avancée.

Le MVP valide par le client est exclusivement centre sur KORA BUS. KORA Colis, KORA Wallet, KORA POINT, KORA Ride et KORA Driver sont exclus du socle initial et doivent etre traites comme phase 2 ou demande de changement formelle.

## Consequences

Le scaffold initial ne cree aucun module Colis, Wallet, Point, Ride ou Driver. Les remboursements du MVP Bus sont geres par un registre operationnel audite, sans creer de solde Wallet client.

