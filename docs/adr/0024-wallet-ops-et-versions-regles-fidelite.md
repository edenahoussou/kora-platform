# Wallet OPS et versions des règles de fidélité

## Statut

Accepté — novembre 2026

## Contexte

Le Wallet BUS disposait de contrats et d'opérations côté serveur, mais pas d'écrans OPS consolidés. Les paramètres de fidélité vivaient dans le groupe Settings Wallet sans historique versionné. Les parcours OPS mélangeaient des vérifications de permissions directes et des Policies, et les écrans de consultation ne doivent jamais déclencher le provisionnement paresseux d'un Wallet.

## Décisions

### Consultation OPS

- Le module Wallet expose un lecteur de consultation en lecture seule pour le pilotage, les comptes, les mouvements et leurs détails. Il renvoie des DTO explicites paginés ; les surfaces web exposent des UUID et ne retournent ni PIN, ni identifiant interne, ni détail d'appareil.
- La lecture d'un compte ne crée jamais un Wallet. Le provisionnement reste lié aux parcours métiers passager qui en ont besoin.
- Le pilotage agrège les comptes et mouvements KORA BUS, les soldes cash et points, la valeur d'usage des points, les échéances proches, les recharges en attente et les restitutions à traiter. Il ne présente pas de ventilation Ride/Colis ni de tendances estimées.
- Les anciennes entrées Expiration et Rapprochement redirigent vers le pilotage ou la liste des mouvements. L'expiration est affichée sur le pilotage et la fiche du compte ; le mouvement fournit le lien vers sa source pour le rapprochement.
- Les filtres s'appuient sur les attributs persistés : titulaire, téléphone, UUID, statut du passager, type de solde, sens, motif, date, échéance et agence.
- La liste Mouvements est un journal des écritures immuables effectivement passées sur un solde cash ou points, triées de la plus récente à la plus ancienne. Les opérations en attente, dont les recharges non confirmées, restent hors de ce journal jusqu'à leur comptabilisation.
- Une ligne correspond à une écriture du ledger ; plusieurs écritures peuvent partager la même source métier. La fiche montre le montant signé, le titulaire et son compte, la source BUS, l'agence, l'agent lorsqu'il est enregistré et la version de fidélité pour les mouvements de points. Elle ne reconstitue pas des soldes avant/après qui ne sont pas persistés.

### Autorisations

- Toutes les autorisations Wallet OPS — consultation globale, lookup, débit, ajustement API, restitutions, versions de règles et paramètres Wallet — passent par les Policies explicites enregistrées dans `AppServiceProvider`.
- Les contrôleurs autorisent avant d'appeler la lecture ou l'action métier. Les Form Requests Wallet ne portent pas une seconde interprétation des permissions.
- La DAF, la Direction des opérations et l'administrateur système disposent des vues globales selon leurs abilities Policy. La DAF et l'administrateur publient les règles ; la Direction des opérations les consulte. Les agents conservent le lookup, leurs restitutions et les autres opérations déjà autorisées.
- Les capacités de navigation partagées à Inertia sont calculées via les Policies. Elles ne remplacent jamais l'autorisation serveur.

### Versions de fidélité

- Une version regroupe le taux d'acquisition, la valeur d'usage, les seuils et bonus, l'expiration et les jalons de rappel déjà portés par `WalletSettings`.
- La version initiale est une copie des valeurs actives lors de l'initialisation. Un utilisateur autorisé crée un brouillon depuis la version active, le simule sans écriture, puis le programme pour une date future.
- Le cycle est `draft` → `scheduled` → `active` → `superseded`. Une version programmée est immuable. L'activation planifiée est idempotente et met à jour les paramètres actifs et les statuts dans une même transaction.
- Le groupe Settings Wallet garde les interrupteurs cash/points, les plafonds, les bornes de recharge et les holds. Les paramètres de fidélité y sont en lecture seule et sont modifiés uniquement depuis la gestion des versions.
- Les mouvements de points écrits après activation référencent la version appliquée. Les anciens mouvements restent sans version. Une activation ne réécrit aucun solde ni aucune échéance existante ; la nouvelle durée d'expiration est appliquée au prochain événement qualifiant.
- La V1 ne crée pas de campagne passager et n'ajoute pas d'action OPS d'ajustement manuel.

## Conséquences

- Le déploiement doit exécuter les migrations puis initialiser la version active depuis les valeurs Wallet de l'environnement avec `WalletLoyaltyRuleVersionSeeder`. Ce seeder est idempotent et ne remplace jamais une version existante.
- Le scheduler active chaque minute les versions arrivées à échéance, avec verrouillage et exécution sur un seul serveur.
- Les tests couvrent les Policies et routes, l'absence d'effets de bord à la lecture et à la simulation, l'activation planifiée, la préservation des dates d'expiration et l'absence de fuite d'identifiants internes.
