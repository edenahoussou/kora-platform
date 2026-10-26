# ADR 0020 — Contrat `seatMap` partagé : grille canonique + état dynamique des sièges

## Statut

Accepté — novembre 2026

## Contexte

Le `grid` canonique des gabarits de sièges (`bus_compositions.grid`) est devenu la source unique de géométrie de cabine côté OPS Web (fiche bus, éditeur de gabarit, vente guichet, portail entreprise, détail départ). Deux clients mobiles doivent consommer le même plan sans casser les versions déjà publiées :

- **App passager (Kevin)** : consomme déjà `GET /api/v1/bus/departures/{departure}/seats` qui renvoie une liste plate de sièges sans coordonnées. Le client reconstruit la position en parsant le code (`2A → rangée 2, colonne A`), ce qui affiche un plan déformé dès que la numérotation sort de la convention 2+2.
- **P501 (ops-mobile)** : le `SeatMap` de l'écran Descente anticipée découpe une liste plate en rangées de 4 avec chauffeur/convoyeur figés, et ne connaît que les sièges occupés du manifeste — jamais la cabine complète. Le terminal travaille hors-ligne : le plan doit voyager dans le payload de synchronisation, pas dans une requête séparée.

La suppression ou le remplacement de l'endpoint passager existant casserait les versions publiées : les anciennes apps mobiles vivent des mois après une release.

## Décisions

### Un contrat `seatMap` en deux blocs : géométrie statique + état dynamique

La géométrie (`grid` : `rows`, `cols`, `cells` avec `kind`/`x`/`y`/`code`/`seatKind`/`maintenance`) est séparée de l'état des sièges (`seats` : `code`, `status`, `sellable`, et `availability` côté passager). La géométrie est immuable par gabarit et cacheable ; l'état change à chaque consultation de disponibilité. Les cellules `seat` joignent les entrées `seats`/`data` sur le `code`.

`composition_uuid` identifie le gabarit : clé de cache côté client et signal de détection de plan périmé (réassignation d'un gabarit au bus entre deux synchronisations). Aucun identifiant numérique n'est exposé.

### Extension rétrocompatible de l'endpoint passager existant

`GET /api/v1/bus/departures/{departure}/seats` n'est ni supprimé ni remplacé : la collection `data` reste inchangée, et `composition_uuid` + `grid` sont ajoutés en métadonnées top-level via `additional()`. Les versions publiées continuent de lire `data` ; les nouvelles lisent `grid` quand il est présent (`null` pour un bus sans gabarit → fallback sur le comportement actuel). Pas de nouvel endpoint dédié tant que ce contrat suffit.

### `data.seatMap` dans le payload de synchronisation P501

`GET /api/v1/ops/departures/{uuid}` expose `seatMap` en clé sœur de `bus`/`crew` : `compositionUuid`, `grid`, et `seats` (état matérialisé : `status` active/maintenance/blocked, `sellable`). La clé n'est sérialisée que lorsque la relation `bus.composition` est chargée (endpoint `show`), ce qui évite un N+1 sur la liste des départs. Le plan concerne le départ (bus affecté × gabarit), pas le bus en abstraction.

### Libérations de siège P501 : codes seuls, inchangé

`POST /api/v1/ops/seat-releases` (et `no-show-closure`) restent adressés par `seat_code` : la commande ne nécessite pas d'UUID de siège. Le `seatMap` sert au *rendu* du plan, pas au verbe métier.

### Détection de péremption exposée, remédiation différée

Un terminal synchronisé avant une réassignation de gabarit affiche un plan obsolète (problème préexistant sur les codes dérivés). `composition_uuid` permet au P501 de *détecter* l'écart ; le blocage d'embarquement ou la resync forcée ne sont pas implémentés dans cette passe — décision produit à prendre avec les retours terrain.

## Conséquences

- Kevin (app passager) écrit deux DTOs (`SeatCell`, `SeatState`) et un renderer de grille muet (~200 lignes, zéro logique métier : la géométrie est calculée serveur). Il peut cacher `grid` par `composition_uuid` et garder le parsing de code en fallback.
- Le dev P501 remplace le découpage 2+2 par le rendu du `grid` offline ; `composition_uuid` compare plan caché vs plan synchronisé.
- Un bus sans gabarit renvoie `grid`/`seatMap` `null` : chaque client conserve son rendu plat actuel.
- Toute évolution du contrat devra rester additive : la suppression de `data` (liste plate) n'est envisageable qu'une fois les anciennes versions éteintes — et la liste reste le contrat « inventaire » indépendamment du plan.
