# Modèle de calcul de la rentabilité

Référence de calcul de l'écran `/profitability`. Décisions : [ADR 0015](adr/0015-perimetre-et-sources-rentabilite.md) et [ADR 0016](adr/0016-affectation-couts-cout-km-seuil.md). Toutes les valeurs sont en XOF et en entiers.

## 1. Périmètre temporel

- Période de service : départs dont `scheduled_at` ∈ `[début local, fin locale + 1 jour)` en `Africa/Porto-Novo`.
- Comparaison : période précédente de même durée (comportement `RevenuePeriod`).
- Coûts : dépenses `approved`, `currency = XOF`, `occurred_at` dans la période.
- Référentiels lus avec `withTrashed()` : bus, ligne, départ.

## 2. Recette nette de service

Pour chaque paiement confirmé en XOF d'une réservation rattachée aux départs de la période :

```
recetteBrute = Σ paiements confirmés
déductions   = Σ annulations reconnues (plafonnées par paiement)
recetteNette = recetteBrute − déductions
```

Répartition du paiement :

1. Par passager : `total_snapshot.passenger_allocations` (index = `passenger_index`). Si absent ou de somme nulle : parts égales entre les passagers portés par les segments.
2. Par segment d'un passager : poids `total_snapshot.legs[].final_amount` quand la jambe correspond au départ du segment (`leg_order` + `departure_uuid`) ; sinon parts égales entre ses segments.
3. Chaque segment porte un `departure_id` : la somme des parts donne la recette du départ.
4. Réservation sans segment : **recette non affectée** (visible au niveau réseau, jamais dans un axe).
5. Vente sans agence : regroupée sous « Application passager — hors agence ».

Une annulation reconnue réduit la recette du départ avec la même clé de répartition que le paiement (`reconnu / montant du paiement`).

## 3. Coûts

### Centre de coût

| Centre de coût | Axe départ | Axe bus | Axe ligne | Axe agence |
| --- | --- | --- | --- | --- |
| `departure_id` | direct | direct (bus du départ) | direct (ligne du départ) | direct (agence de la dépense) |
| `bus_id` | réparti (bus-km) | direct | réparti (bus-km par ligne) | direct (agence de la dépense) |
| `line_id` | réparti (km de la ligne) | réparti (bus-km par bus) | direct | direct (agence de la dépense) |
| aucun | non affecté | non affecté | non affecté | direct |

Une dépense sans centre de coût n'est jamais répartie sur des départs : les départs ne sont pas rattachés à une agence.

### Poids de répartition

- Carburant, péages, maintenance corrective/diagnostic : `bus-km`.
- Maintenance préventive, assurance, leasing : `bus-km`, repli à parts égales.
- Fournitures, services, autres : parts égales entre les départs du centre de coût.
- Poids nuls ou absents : parts égales.

```
coutAffecte = Σ parts des dépenses directes + Σ parts des dépenses réparties
tauxCouverture = coutAffecte / (coutAffecte + coutNonAffecte)
```

## 4. Kilomètres

```
distance(départ) = Σ haversine(position[i], position[i+1]) sur les fixes valides
```

Filtres : `status = fix`, coordonnées présentes, `accuracy ≤ trackingDegradedAccuracyMeters`, trou ≤ `profitabilityGpsMaxGapMinutes`, vitesse implicite ≤ `profitabilityGpsMaxSpeedKmh`.

```
couverture = durée couverte par des fixes valides / durée de session
```

- `couverture ≥ profitabilityGpsMinCoveragePercent` → distance **GPS**.
- Sinon, ou sans session → distance planifiée (`lines.distance_km`, sinon `max(stops.km_from_origin) − min(stops.km_from_origin)`) → badge **estimé**.

## 5. Marge, TCO et seuil

```
contribution     = recetteNette − coûts directs − coûts répartis
tauxMarge        = contribution / recetteNette        (null si recetteNette ≤ 0)
resultatReseau   = recetteNetteRéseau − coûtsAffectés − coûtsNonAffectés
TCO(bus)         = Σ dépenses affectées au bus
coût/km          = TCO(bus) / km(bus)
résultat/place   = (recetteNette(bus) − TCO(bus)) / siègesVendus(bus)
```

Variables : carburant, péages, maintenance corrective/diagnostic. Fixes : assurance, leasing, maintenance préventive, fournitures, services, autres.

```
contributionParSiege = (recetteNette − coûtVariable) / siègesVendus
siegesSeuil          = ceil(coûtFixe / contributionParSiege)
statut               = atteint si siègesVendus ≥ siegesSeuil
                       sous_seuil sinon
                       non_calculable si siègesVendus = 0 ou contributionParSiege ≤ 0
```

Sièges vendus = nombre de `seat_id` distincts des segments occupants du départ. Capacité = `bus.seat_count`.

## 6. Exemple chiffré

Départ de 100 km, bus de 50 places, 40 sièges vendus.

- Recette nette du départ : 800 000.
- Carburant affecté au bus, réparti : 300 000 (variable).
- Assurance mensuelle du bus, répartie : 100 000 (fixe).

```
contributionParSiege = (800 000 − 300 000) / 40 = 12 500
siegesSeuil          = ceil(100 000 / 12 500) = 8
statut               = atteint (40 ≥ 8)
contribution         = 800 000 − 400 000 = 400 000 → marge 50 %
```

## 7. Contrôles affichés

- **Rapprochement** : recettes cash de la période (vue Recettes) vs recettes rattachées aux départs vs écart de timing.
- **Couverture** : taux de couverture des coûts, nombre de dépenses sans centre de coût.
- **Annulations post-période** : montant reconnu après la fin de période, inclus dans la vue accrual et signalé.
- **Devises** : paiements et dépenses non XOF exclus et comptés.
- **Données manquantes** : départs sans bus, bus sans capacité, GPS absent.

## 8. Limites

- Modèle de gestion, pas vérité comptable : la saisie du centre de coût conditionne la qualité des marges.
- Pas de CassKai/RH, pas de colis, pas de commissions KORA POINT.
- Pas de carnet carburant litres/odomètre ni de module maintenance complet.
- Les rapports historiques évoluent avec les annulations post-période ; l'export est horodaté.
