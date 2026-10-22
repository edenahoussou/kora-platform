# ADR 0018 — Trajectoire de la période sur l'écran Rentabilité

## Statut

Accepté — 24 février 2026

## Contexte

Le bandeau « Caisse de la période et service rattaché » était doublement redondant : la recette de service figurait déjà dans les KPIs du hero, et l'écart de timing était déjà restitué par la chip « Rapprochement caisse / service » de la grille de contrôles. L'écran manquait par ailleurs de dimension temporelle : impossible de voir la marche vers la rentabilité au fil de la période.

## Décisions

### Le bandeau est remplacé par une courbe cumulative

Deux séries cumulées sur les buckets de `RevenuePeriod::bucket()` (jour ≤45j, semaine ≤180j, mois sinon) :

- **Recette nette cumulée**, datée par date de départ — sémantique « période de service » cohérente avec tout l'écran ;
- **Coûts engagés cumulés**, datés par `expenses.occurred_at`, y compris les coûts non affectés — le dernier point cumulé égale donc `costs.total`, en cohérence avec le hero.

La recette cumulée de la **période précédente** est superposée en pointillé, alignée par rang de bucket (les périodes ont même durée, mais les buckets `month` peuvent différer d'un cran aux frontières → `null` hors correspondance).

### Datation des non-rattachés

Un paiement non rattaché (`unattributed`) n'a pas de départ : il est bucketé à sa date `confirmed_at`. Les dépenses non affectées restent à leur `occurred_at`. Ainsi les cumuls finaux restent égaux aux totaux du rapport quelle que soit la qualité d'affectation.

### Jour de bascule

Premier bucket où `revenueCum > 0 && revenueCum >= costCum` — affiché comme repère vertical et mention « rentable à partir du JJ/MM ».

### Suppressions et limites

- Le bloc de rapprochement disparaît de l'UI ; le calcul backend `reconciliation` est **conservé** (chip timing des contrôles + lignes de synthèse de l'export XLSX).
- Moins de 3 buckets (preset « Aujourd'hui », début de semaine) : le graphique est masqué, les contrôles restent.
- Sous filtre entité : les courbes sont scopées (mécanique ADR 0017), jamais de courbe cash.
- Pas de feuille « Trajectoire » dans l'export en V1.

## Implémentation

`ProfitabilityTimeline` (service dédié) consomme `revenue.byDeparture` + `departureMeta.scheduledAt`, `revenue.unattributedByDate` (nouveau, nécessite `confirmed_at` sur les paiements) et `costs.byDate` (nouveau, rempli depuis `occurred_at`). `RevenuePeriod::bucketKey()` factorise la clé de regroupement partagée avec `ConsolidatedRevenueReport`.
