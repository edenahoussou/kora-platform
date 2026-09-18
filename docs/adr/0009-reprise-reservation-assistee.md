# ADR 0009 — Reprise d’une réservation assistée

## Statut

Accepté — 18 septembre 2026

## Contexte

Le paiement en ligne reste une friction importante pour les voyageurs Bus au Bénin. Un voyageur doit pouvoir préparer son trajet dans KORA App, puis confier la finalisation et l’encaissement à une agence par téléphone ou WhatsApp, sans que l’agence recrée silencieusement une seconde réservation.

Le délai entre la demande et le dépôt des fonds interdit de conserver un blocage de siège ou de crédit entreprise. La reprise doit néanmoins résister aux ventes concurrentes, aux changements de prix, aux cutoffs commerciaux, aux départs retardés ou annulés et aux doubles soumissions.

## Décision

### Réservation unique et référence publique

Une réservation conserve le même UUID et la même référence publique immuable `KBR-XXXXXXXX` pendant tout le parcours. Le dossier `assisted_bookings` est un journal opérationnel un-à-un ; il ne remplace pas la réservation et n’autorise jamais l’accès par la seule connaissance de sa référence.

L’envoi à une agence place la réservation en `assistance_pending` et ses segments en `requested`. Un segment `requested` mémorise le siège souhaité mais reste exclu de l’inventaire occupé et de la contrainte PostgreSQL d’exclusion.

### Absence de ressources réservées pendant l’attente

Tous les holds de siège et de crédit entreprise sont libérés au passage en assistance. L’échéance du dossier correspond au premier cutoff commercial effectif de ses départs. Les retards et règles courantes de vendabilité sont réévalués avant chaque action sensible et par une tâche périodique.

### Machine à états et arbitrage

L’assistance suit les états `pending`, `in_progress`, `cancelled`, `refused`, `unavailable`, `expired` et `finalized`. La réservation ajoute `assistance_pending` et `unavailable`.

La réservation est verrouillée avant le dossier d’assistance dans toutes les opérations concurrentes. La prise en charge agence et la reprise du paiement en ligne ne sont possibles qu’en état `pending` : la première transaction validée gagne et l’autre reçoit un conflit HTTP explicite. Toutes les transitions sont inscrites dans le journal d’activité existant avec l’acteur, l’agence, le motif, le prix et les sièges utiles.

### Reprise, prix et sièges

Une reprise conserve les mêmes départs, origines et destinations. Un changement d’itinéraire impose une nouvelle réservation. Le devis est recalculé et les sièges souhaités encore libres sont proposés ; aucun nouveau hold n’est écrit tant que la matrice complète passager–jambe n’est pas valide.

L’opération `ApplyItinerarySeatHoldAction` constitue l’unique interface profonde d’acquisition atomique. La création normale et les reprises assistées partagent ainsi les mêmes verrous de départ et de siège, contrôles de planning, validations de vendabilité et contraintes d’inventaire.

### Paiement et émission

La reprise agence entre dans le parcours de vente guichet existant. Les politiques autorisent explicitement l’agent attributaire sans transférer la propriété de la réservation. Les permissions guichet, l’agence de l’agent et sa session de caisse ouverte restent obligatoires.

Le montant encaissé doit correspondre exactement au devis recalculé. Paiement, mouvement de caisse ou conversion du crédit entreprise, agence de vente, facture fiscale, réservation `paid` et assistance `finalized` sont enregistrés dans une seule transaction. L’émission asynchrone et idempotente des billets reste séparée et réutilise le job existant ; son échec ne rouvre pas l’assistance.

### Permissions

- Les agents et managers traitent uniquement la file de leur agence.
- Les managers peuvent libérer, réaffecter dans leur agence et refuser avec motif.
- Les directions Opérations et Commerciale peuvent consulter, réaffecter et refuser sur le réseau.
- La Direction générale dispose d’une vue réseau en lecture seule.
- La DAF et les autres agences n’obtiennent aucun accès par la permission générique `bookings.view`.
- L’administrateur système possède les permissions spécialisées, sans contourner les prérequis financiers du guichet.

## Conséquences

- La référence, l’UUID et les codes publics sont les seuls identifiants exposés aux interfaces REST et Inertia.
- `call_phone` et `whatsapp_phone` sont deux canaux d’agence facultatifs et indépendants, normalisés au format international.
- Une agence d’assistance reste une entité commerciale ; aucun lien avec un départ, un bus ou un arrêt n’est créé.
- Le TTL des devis est configuré par `KORA_QUOTE_TTL_MINUTES`, avec une valeur par défaut de 10 minutes.
- L’interface mobile de sélection de l’assistance reste du ressort de MEZ-81 ; MEZ-80 livre le contrat REST et KORA OPS Web.
