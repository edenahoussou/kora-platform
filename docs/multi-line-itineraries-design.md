# Note de conception — Itinéraires multi-lignes avec correspondances (MEZ-51)

> Ticket : [MEZ-51](https://linear.app/mezabodev/issue/MEZ-51/booking-cadrer-les-parcours-passager-avec-correspondances-multi-lignes)
> Statut : proposition à valider avant ouverture des tickets d'implémentation (MEZ-57, MEZ-58, MEZ-59).
> Principe directeur : **le MVP Bus est en staging, aucune donnée de production ne dépend du schéma actuel**. Cette note assume des breaking changes sur `reservations`, `reservation_segments` et `stops` plutôt que de conserver des colonnes ou des chemins de compatibilité pour l'existant. Les tickets d'implémentation appliqueront ces changements directement, sans étape intermédiaire "compatible avec les deux modèles".

---

## 1. Contexte et périmètre

Aujourd'hui, une réservation KORA BUS correspond à **un seul départ, sur une seule ligne**. Ce parcours (recherche → devis → hold → passagers → paiement → billet) reste le cas majoritaire et n'est pas remis en cause dans son expérience utilisateur.

Cette note cadre le cas où le trajet du voyageur nécessite **plusieurs lignes bout à bout**, par exemple `Malanville → Cotonou` (ligne L1) puis `Cotonou → Abidjan` (ligne L4), avec un changement de bus à Cotonou.

**Dans le périmètre** :
- Recherche automatique d'itinéraires à une ou plusieurs jambes.
- Correspondance entre l'arrivée d'une jambe et le départ de la suivante, avec marge configurable.
- Disponibilité de sièges par jambe, prix total agrégé.
- Hold, paiement, ticketing, modification et annulation sur un itinéraire multi-lignes.
- Impacts guichet, application mobile, manifeste, embarquement.

**Hors périmètre (MVP)** :
- Réacheminement automatique en cas de retard rendant une correspondance impossible (traitement manuel + alerte uniquement).
- Suivi GPS temps réel.
- Wallet, Colis, KORA Driver, KORA Ride.
- Correspondance entre deux arrêts physiques différents (ex. changer de gare dans la même ville en taxi) — non gérée par le système.
- Itinéraires couvrant plusieurs jours calendaires.
- Groupes dont les voyageurs empruntent des itinéraires différents.

---

## 2. Glossaire

| Terme | Définition |
|---|---|
| **Itinéraire** | Résultat de recherche : une ou plusieurs jambes ordonnées permettant de relier une origine à une destination, le même jour. |
| **Jambe** | Un trajet continu sur **un seul départ** (`departure_id`), avec une origine, une destination, un siège et un voyageur. C'est l'unique niveau entre l'itinéraire et le voyageur — il n'y a pas de sous-découpage "segment O/D" distinct de la jambe (voir §6). |
| **Point d'arrêt physique** | Lieu réel où un voyageur peut descendre d'un bus et monter dans un autre (gare, gargote, carrefour). Indépendant des lignes qui le desservent. |
| **Correspondance** | Transition entre deux jambes consécutives d'un même itinéraire, valide uniquement si l'arrêt de fin de la jambe N et l'arrêt de début de la jambe N+1 partagent le **même point d'arrêt physique**, avec un délai suffisant. |
| **Marge de correspondance** | Durée minimale configurable entre l'heure d'arrivée prévue d'une jambe et l'heure de départ prévue de la suivante, en dessous de laquelle la correspondance n'est pas proposée. |
| **Réservation parent** | Une `Reservation` regroupant toutes les jambes d'un itinéraire pour un ensemble de voyageurs. |
| **Billet** | Un `Ticket`, par voyageur, couvrant l'intégralité de son itinéraire (toutes ses jambes), avec un QR code unique contrôlé jambe par jambe à l'embarquement. |

---

## 3. Recherche et classement des itinéraires

- La recherche d'itinéraires est **automatique** : le voyageur saisit une origine, une destination et une date ; le système propose tous les itinéraires valides (directs et à correspondance) sans que l'utilisateur ait à composer lui-même les jambes.
- Un itinéraire n'est proposé que s'il est **complet** : si un segment est indisponible (sièges épuisés, départ fermé à la vente) ou si aucune correspondance compatible n'existe à un point donné, l'itinéraire partiel n'est pas affiché. Le système ne propose jamais "jambe 1 confirmée, jambe 2 à trouver".
- **Classement** : nombre de correspondances (croissant) → heure d'arrivée finale (croissante) → prix total (croissant, critère secondaire à égalité des deux premiers).
- Le MVP limite la recherche à un **voyage effectué le même jour calendaire** : aucune correspondance n'est proposée si la jambe suivante part le lendemain.

---

## 4. Arrêts et points d'arrêt physiques

### 4.1 État actuel et problème de fond

La table `stops` (`database/migrations/2026_08_13_070000_create_kora_bus_reference_tables.php`) rattache chaque arrêt à **une seule ligne** (`line_id`, `stop_order` propre à la ligne), avec un champ `city` en texte libre. Il n'existe aujourd'hui **aucune identité stable** représentant "ce point d'arrêt physique", indépendamment de la ligne qui le dessert.

Ce n'est pas seulement un manque pour le futur multi-lignes : c'est déjà une source de fragilité dans le parcours mono-ligne actuel. `ResolveLineSegmentStopsAction::resolveStop()` fait correspondre l'origine/destination demandées par comparaison de chaîne insensible à la casse sur `city` :

```php
if ($city) {
    return $stops->first(fn (Stop $stop): bool => Str::lower($stop->city) === Str::lower($city));
}
```

Ce mécanisme est utilisé dès aujourd'hui par `DepartureSearchService` (recherche "from/to" mono-ligne). Il a deux défauts :
- Deux arrêts de lignes différentes portant le même nom de ville mais correspondant à des gares physiquement distinctes matcheraient à tort comme "le même endroit" — dangereux pour une correspondance, où le voyageur doit changer de bus au même endroit physique.
- Deux arrêts désignant le même lieu réel mais nommés différemment (variantes orthographiques, quartier vs ville) ne matchent pas.

### 4.2 Modèle cible (breaking, sans rétrocompatibilité)

- Nouvelle table `physical_stops` : identité canonique du lieu (nom, ville, pays, latitude/longitude).
- `stops.physical_stop_id` devient une **colonne obligatoire** (FK non nullable) vers `physical_stops`. La table `stops` ne garde que ce qui est propre à la ligne : `line_id`, `stop_order`, `km_from_origin`, statut.
- `ResolveLineSegmentStopsAction::resolveStop()` est réécrit pour matcher par `physical_stop_id` (ou un code stable exposé publiquement), et non plus par comparaison de texte sur `city`. Ce changement corrige au passage la recherche mono-ligne actuelle, il n'est pas réservé au cas multi-lignes.
- **Règle de correspondance** : la jambe N+1 n'est proposée après la jambe N que si l'arrêt de destination de la jambe N et l'arrêt d'origine de la jambe N+1 partagent le même `physical_stop_id`.
- **Chantier de bascule des données** : regrouper les ~arrêts existants par point physique réel n'est pas automatisable par un simple script de correspondance sur `city` (plusieurs gares peuvent partager une même ville). Ce travail nécessite une revue manuelle via une interface d'administration (rattacher/fusionner des arrêts existants à un `physical_stop`). Il est identifié ici comme un **prérequis opérationnel** des tickets d'implémentation, pas résolu par cette note.

---

## 5. Correspondances et marge

- Chaque `Departure` porte déjà des horaires en instants absolus : `scheduled_at` (UTC), `estimated_arrival_at`, et un détail par arrêt dans `stop_times`. Le calcul de la marge de correspondance **réutilise ces champs existants** — pas de nouveau moteur d'horaires à construire.
- La marge de correspondance est calculée en **instants absolus** (UTC), jamais en heure locale brute, pour rester correcte même quand les deux lignes traversent des fuseaux horaires différents (le module planning gère déjà 16 fuseaux ouest-africains).
- La marge minimale est **configurable** (valeur par défaut à définir en configuration métier, pas en dur dans le code), et s'applique entre `estimated_arrival_at` de la jambe N et `scheduled_at` de la jambe N+1 au même point d'arrêt physique.
- Si l'écart est inférieur à la marge configurée, la correspondance n'est pas proposée à la recherche — l'itinéraire est simplement absent des résultats (voir §3).

---

## 6. Modèle cible de données (breaking, sans rétrocompatibilité)

### 6.1 Fusion "jambe" / "segment O/D"

Le modèle actuel a déjà une table `reservation_segments` portant exactement les attributs d'une jambe : `departure_id`, `seat_id`, `origin_stop_id`, `destination_stop_id`, `origin_stop_order`, `destination_stop_order`, `reservation_passenger_id`, `status`. Il n'y a donc **pas de nouvelle table "jambe" à créer** : `reservation_segments` est étendu avec :

- `leg_order` : position de la jambe dans l'itinéraire (1, 2, 3…).
- Un lien vers le point d'arrêt physique de correspondance (dérivable de `destination_stop_id` → `stops.physical_stop_id`, pas de duplication nécessaire).
- Les horaires prévus d'arrivée/départ à la correspondance (dérivables de `Departure.estimated_arrival_at` / `scheduled_at`, également pas de duplication nécessaire dans le cas général — à confirmer en implémentation si un besoin de figer un snapshot apparaît, sur le même principe que `PriceQuote.snapshot`).

Le terme "segment O/D" est retiré du glossaire comme concept distinct : il désigne la même chose que "jambe". Toute référence future dans le code ou les contrats API doit utiliser un seul vocabulaire.

La contrainte GIST `reservation_segments_no_overlap` existante (scopée par `departure_id` + `seat_id` + plage de `stop_order`) reste valable telle quelle par jambe : chaque jambe a son propre `departure_id`, donc la contrainte protège chaque jambe indépendamment sans modification.

### 6.2 Suppression de `reservations.departure_id`

La colonne `reservations.departure_id` (`NOT NULL`, `restrictOnDelete`) est **supprimée** du modèle cible. Une réservation peut désormais couvrir plusieurs départs (un par jambe), donc un unique `departure_id` sur la réservation n'a plus de sens et n'est pas conservé "pour compatibilité".

Dans le modèle cible, le départ affiché pour une réservation (ex. dans un reçu guichet) se lit via la première jambe : `reservation->segments()->orderBy('leg_order')->first()->departure`.

### 6.3 Diagramme des relations cible

```
Reservation (parent)
 ├─ ReservationSegment[]  (= jambes, ordonnées par leg_order, chacune → 1 Departure)
 │    └─ Seat, Stop origine, Stop destination, ReservationPassenger
 ├─ ReservationPassenger[]
 │    └─ Ticket (1 par voyageur, couvre toutes ses jambes)
 └─ Payment[]

Stop (par ligne) ──belongsTo──> PhysicalStop (identité canonique du lieu)
```

---

## 7. Devis, hold, paiement, ticketing

### 7.1 Devis multi-jambes

- Le prix est calculé **par jambe** en réutilisant le moteur existant (`Fare` + `PricingRule` par ligne, `CalculateQuoteAction`/`CalculateFarePreviewAction`), puis agrégé pour obtenir le prix total de l'itinéraire.
- Aucun nouveau moteur de tarification n'est introduit : le multi-lignes est une agrégation de devis mono-ligne existants, un par jambe.

### 7.2 Hold atomique ("tout ou rien")

- Toutes les jambes d'un itinéraire sont bloquées dans **une seule transaction DB**, sur le modèle de `CreateSeatHoldAction` existant (`DB::transaction` + `lockForUpdate()` par départ).
- Pour un itinéraire à plusieurs départs, les départs impliqués sont verrouillés **dans un ordre déterministe** (tri par `departure_id`), afin d'éviter les deadlocks entre deux holds concurrents qui partageraient des jambes en ordre inverse.
- La contrainte GIST anti-chevauchement existante suffit par jambe (elle est scopée par `departure_id`) : aucune nouvelle contrainte DB n'est nécessaire pour l'atomicité multi-jambes, seule la logique applicative (transaction unique, verrouillage ordonné) doit être généralisée.
- Si une seule jambe échoue (siège indisponible, départ fermé), la transaction entière est annulée : aucun hold partiel n'est laissé en place.

### 7.3 Ticketing

- `Ticket` est déjà scopé par voyageur (`reservation_passenger_id`), pas par segment — le modèle actuel supporte déjà nativement "un billet par voyageur couvrant tout son itinéraire", sans changement de structure.
- Le contrôle à l'embarquement se fait **jambe par jambe** : le QR code du billet est scanné à chaque montée, et la validation vérifie que le voyageur a bien une jambe active pour ce départ précis (via `reservation_segments`).

### 7.4 Modification et annulation

- **Cas volontaire** : un voyageur (ou un agent) peut annuler ou modifier une jambe individuelle de son itinéraire sans annuler les autres jambes, tant que la réservation le permet (statut modifiable). Le remboursement partiel se calcule sur le prix de la jambe concernée (issu du devis agrégé par jambe, §7.1).
- **Cas subi (retard opérationnel)** : si un retard rend une correspondance impossible après réservation, le système déclenche une **alerte** et bascule sur un **traitement manuel** côté opérations. Aucun réacheminement automatique n'est prévu dans le MVP.
- Ces deux cas doivent être distingués explicitement dans les contrats des tickets d'implémentation, pour éviter de ne traiter que le cas "retard" en oubliant l'annulation volontaire d'une jambe (exigée par le livrable de cadrage du ticket MEZ-51).

---

## 8. Scénarios d'erreur et d'indisponibilité

| Scénario | Comportement |
|---|---|
| Un segment de l'itinéraire n'a plus de siège disponible | L'itinéraire n'est pas proposé à la recherche (pas d'itinéraire incomplet, §3). |
| Aucune correspondance compatible (marge non respectée ou point physique différent) | L'itinéraire indirect n'est pas proposé ; seuls les itinéraires directs ou via un autre point de correspondance restent visibles. |
| Hold : une jambe échoue pendant la transaction | Rollback complet, aucun siège bloqué sur aucune jambe, réponse 409 avec le siège en conflit. |
| Retard après réservation rendant une correspondance impossible | Alerte déclenchée, traitement manuel par l'équipe opérations, pas de réacheminement automatique. |
| Annulation volontaire d'une jambe intermédiaire | La réservation reste valide pour les jambes restantes ; remboursement calculé sur la jambe annulée uniquement. |

---

## 9. Exemple complet — Malanville → Cotonou → Abidjan

**Recherche** : `GET /api/v1/bus/itineraries?from=Malanville&to=Abidjan&date=2026-09-10`

```json
{
  "itineraries": [
    {
      "connections_count": 1,
      "total_amount": 27500,
      "currency": "XOF",
      "departure_at": "2026-09-10T06:00:00Z",
      "arrival_at": "2026-09-10T19:30:00Z",
      "legs": [
        {
          "leg_order": 1,
          "departure_uuid": "dep-l1-uuid",
          "line_code": "L1",
          "origin_stop_uuid": "stop-malanville-uuid",
          "destination_stop_uuid": "stop-cotonou-gare-uuid",
          "departure_at": "2026-09-10T06:00:00Z",
          "arrival_at": "2026-09-10T13:00:00Z",
          "amount": 15000
        },
        {
          "leg_order": 2,
          "departure_uuid": "dep-l4-uuid",
          "line_code": "L4",
          "origin_stop_uuid": "stop-cotonou-gare-uuid",
          "destination_stop_uuid": "stop-abidjan-uuid",
          "departure_at": "2026-09-10T13:45:00Z",
          "arrival_at": "2026-09-10T19:30:00Z",
          "amount": 12500
        }
      ],
      "connection_margin_minutes": 45
    }
  ]
}
```

**Devis** (`POST /api/v1/bus/itinerary-quotes`) : agrège les deux devis de jambe et fige un `total_amount` + `expires_at`, sur le modèle de `PriceQuote.snapshot`.

**Hold** (`POST /api/v1/bus/itinerary-holds`) : crée une `Reservation` unique avec deux `ReservationSegment` (`leg_order` 1 et 2), chacun sur son propre `departure_id`, dans une seule transaction.

**Billets** : un `Ticket` par voyageur, un seul QR code, scanné une première fois à Malanville (jambe 1) et une seconde fois à Cotonou (jambe 2).

---

## 10. Intégration avec les achats groupés et comptes entreprise (référence MEZ-57)

- Un achat groupé multi-lignes concerne des voyageurs partageant **le même itinéraire complet** (mêmes jambes, mêmes correspondances). Les voyageurs à itinéraires différents ne sont pas inclus dans une même réservation groupée (MVP).
- Seuils déjà validés côté MEZ-59 et repris ici sans changement : réservation groupée disponible à partir de **5 voyageurs** ; remise de **10 %** à partir de **10 voyageurs**, règle commerciale distincte du seuil de groupe.
- Le prix est calculé par jambe (§7.1) puis agrégé par voyageur, puis pour le groupe ; la remise groupe s'applique sur le montant total agrégé éligible.
- Le compte entreprise est débité une seule fois du total de la réservation groupée multi-lignes ; une fiche et un billet restent conservés pour chaque voyageur individuellement.
- L'ordre de calcul (tarif de base par jambe → tarification dynamique → remise groupe → autres promotions → total) doit rester celui figé dans MEZ-57.

---

## 11. Impacts guichet, mobile, manifeste, embarquement

- **Guichet (MEZ-59)** : l'écran de vente doit permettre de sélectionner un itinéraire à plusieurs jambes (pas seulement un départ), afficher le détail prix par jambe, et gérer le hold/paiement unique pour l'ensemble. La maquette actuelle ne couvre pas cet écran — à concevoir.
- **Mobile (MEZ-58)** : le parcours de recherche doit afficher les itinéraires multi-lignes classés (§3), avec le détail des correspondances et la marge affichée au voyageur.
- **Manifeste** : le manifeste d'un départ donné ne doit lister que les voyageurs dont une jambe correspond à ce départ précis (via `reservation_segments.departure_id`), pas l'intégralité des voyageurs de leur réservation parent.
- **Embarquement** : le contrôle QR à l'embarquement valide la jambe correspondant au départ scanné (§7.3), pas l'ensemble de l'itinéraire d'un coup.

---

## 12. Annexe — Inventaire des call sites impactés

Cette liste est fournie pour que les tickets d'implémentation partent d'un état des lieux connu plutôt que de le redécouvrir. Aucun de ces fichiers n'est modifié par MEZ-51 lui-même.

**Suppression de `reservations.departure_id`** (remplacement par lecture de la première jambe) :
- `app/Modules/Booking/Models/Reservation.php` — relation `departure()`.
- `app/Modules/Booking/Http/Controllers/CounterSaleController.php` — lignes 199, 202, 299, 365, 517 (lecture directe de `reservation->departure` pour les reçus et redirections guichet).
- `app/Modules/Booking/Actions/UpdateReservationPassengersAction.php` — ligne 63 (vérification ligne internationale via `reservation->departure->line`).
- `app/Modules/Booking/Actions/CreateSeatHoldAction.php` — lignes 55, 144, 157 (création de la réservation avec un `departure_id` unique).

**Remplacement du matching par `city` texte libre par `physical_stop_id`** :
- `app/Modules/BusNetwork/Actions/ResolveLineSegmentStopsAction.php` — méthode `resolveStop()`, lignes 46-57.
- `app/Modules/Departures/Services/DepartureSearchService.php` — `resolveCandidateLineIds()` et `buildDepartureData()`, qui délèguent à `ResolveLineSegmentStopsAction`.

**Extension de `reservation_segments`** (ajout `leg_order` + lien point d'arrêt physique) :
- Migration `database/migrations/2026_08_13_072000_create_kora_bus_booking_inventory_tables.php` (table `reservation_segments`).
- `app/Modules/Booking/Models/ReservationSegment.php`.

**Nouvelle entité `physical_stops`** :
- Migration `database/migrations/2026_08_13_070000_create_kora_bus_reference_tables.php` (table `stops`) — ajout `physical_stop_id` obligatoire.
- `app/Modules/BusNetwork/Models/Stop.php`.

---

## 13. Critères de sortie

Cette note est considérée comme validée lorsque les points suivants sont actés sans ambiguïté :

- [ ] Un itinéraire multi-lignes est défini sans ambiguïté (glossaire §2), avec un seul concept "jambe" (pas de doublon "segment O/D").
- [ ] Les points d'arrêt physiques sont modélisés (§4), avec le problème de fond actuel documenté et sa correction actée sans rétrocompatibilité.
- [ ] La recherche automatique et son classement sont décrits (§3).
- [ ] La marge de correspondance est documentée, en instants absolus (§5).
- [ ] Le comportement en l'absence de correspondance ou de disponibilité est fixé (§3, §8).
- [ ] Le hold tout-ou-rien est défini, y compris la prévention des deadlocks (§7.2).
- [ ] Le modèle réservation parent → jambes → voyageurs est documenté sans niveau intermédiaire superflu (§6).
- [ ] La compatibilité avec les réservations mono-segment est assurée **fonctionnellement** (le parcours à une seule jambe continue de fonctionner), sans que le schéma conserve de colonne ou de logique de compatibilité historique.
- [ ] L'intégration avec les achats groupés et les comptes entreprise est explicitée et cohérente avec MEZ-57/MEZ-59 (§10).
- [ ] Les contrats des tickets MEZ-57, MEZ-58 et MEZ-59 sont alignés avec cette note, y compris la liste des call sites impactés (§12).

Une fois validée, cette note sert de référence pour compléter MEZ-57 (contrat groupe/entreprise multi-segments), MEZ-58 (parcours mobile) et MEZ-59 (implémentation guichet). Les migrations, modèles et endpoints décrits ici sont implémentés dans ces tickets, pas dans MEZ-51.
