# ADR 0016 — Affectation des coûts, coût/km et seuil de rentabilité

## Statut

Accepté — 19 octobre 2026

## Contexte

Pour calculer une contribution par bus, ligne, départ et agence, il faut rattacher les dépenses opérationnelles à un centre de coût exploitable. Le module Dépenses ne connaissait qu'une agence et une catégorie : impossible de savoir quel bus, quelle ligne ou quel départ avait consommé le carburant ou la réparation. Le coût/km exigeait par ailleurs une distance fiable, alors que la distance planifiée et la distance GPS coexistent.

## Décisions

### Centre de coût structuré

Trois clés étrangères nullables sont ajoutées à `expenses` : `bus_id`, `line_id`, `departure_id` (suppression restreinte). Une dépense porte **au plus un** centre de coût ; la cohérence départ ↔ bus ↔ ligne est validée (le départ doit appartenir au bus et à la ligne déclarés). La saisie reste optionnelle : une dépense sans centre de coût demeure un coût non affecté.

### Périmètre de saisie

Le rattachement à un bus, une ligne ou un départ est réservé aux profils réseau (`expenses.view-network`) : DAF, direction générale et direction de l'exploitation. Une agence est une entité commerciale, sans périmètre opérationnel sur les départs ; ses agents saisissent donc des dépenses d'agence, non affectées par construction. Une dépense réseau exige la sélection de l'agence support, car la dépense reste rattachée à une caisse.

### Trois couches de coûts

- **Direct** : la dépense porte le centre de coût de l'axe consulté (départ, bus ou ligne).
- **Réparti** : la dépense porte un centre de coût plus large et est ventilée sur les départs de la période par une règle déterministe.
- **Non affecté** : la dépense ne peut pas être rattachée à l'axe (dépense d'agence pour un axe opérationnel, ou absence de centre de coût). Il est visible au niveau réseau/agence et n'entre jamais dans la marge d'un axe.

### Règles de répartition

- Carburant, péages, maintenance corrective et diagnostic, pneus : au **bus-km** (GPS, sinon planifié).
- Maintenance préventive, assurance, leasing : au **bus-km**, à parts égales entre les départs du bus si aucune distance n'est connue.
- Fournitures, services, autres : à parts égales entre les départs du centre de coût.
- Coût de ligne : au **km des départs de la ligne**.
- Une dépense d'agence sans centre de coût n'est **pas** répartie sur des départs : l'agence est une entité commerciale, les départs ne lui sont pas rattachés (règle du module Départs). Elle reste un coût non affecté pour les axes bus/ligne/départ.

### Distance : GPS d'abord, planifié en repli

- Source : `tracking_sessions` et `tracking_positions`, distance haversine entre positions valides consécutives.
- Une position est valide si `status = fix`, coordonnées présentes, précision ≤ `trackingDegradedAccuracyMeters`.
- Un segment est ignoré si le trou dépasse `profitabilityGpsMaxGapMinutes` ou si la vitesse implicite dépasse `profitabilityGpsMaxSpeedKmh`.
- La couverture d'un départ est la part de la durée de session couverte par des fixes valides. Sous `profitabilityGpsMinCoveragePercent`, ou sans session, la distance planifiée (`lines.distance_km`, sinon `stops.km_from_origin`) est utilisée et marquée **estimé**.
- Le badge **GPS** ou **estimé** et le taux de couverture sont toujours affichés.

### Seuil fixe/variable en sièges

- Variables : carburant, péages, maintenance corrective/diagnostic, pneus.
- Fixes : assurance, leasing, maintenance préventive, fournitures, services, autres.
- Contribution par siège = (recette nette − coût variable) / sièges vendus.
- Sièges seuil = arrondi supérieur (coûts fixes / contribution par siège).
- Statut `atteint` si sièges vendus ≥ sièges seuil, `sous_seuil` sinon, `non_calculable` si aucun siège vendu ou contribution ≤ 0.
- Le seuil réseau applique la même formule aux totaux réseau.

## Conséquences

- Le modèle est un **modèle de gestion**, pas une vérité comptable. La couverture des coûts et les non affectés sont affichés pour éviter toute lecture abusive.
- Les référentiels soft-deleted (bus, ligne, départ) sont lus avec `withTrashed()` pour préserver l'historique.
- Les rapports historiques peuvent évoluer avec les annulations post-période : chaque export horodate sa génération et le contrôle dédié isole ces annulations.
- Performance : calcul à la volée avec agrégation en mémoire et index dédiés ; un snapshot pourra être introduit plus tard si les volumes l'exigent.
