# ADR 0009 — Reprise d’une réservation assistée

## Statut

Accepté — 20 septembre 2026

## Contexte

Le paiement en ligne peut rester une friction pour les voyageurs Bus au Bénin. Un passager doit pouvoir préparer son trajet dans KORA App puis demander une assistance par téléphone ou WhatsApp, sans recréer une seconde réservation et sans bloquer un siège pendant l’attente.

La demande n’est plus dirigée vers une agence choisie par le passager. Elle est d’abord adressée au support client central, dont les coordonnées sont configurées dans KORA. Le dossier entre ensuite dans une file réseau que les agences habilitées peuvent consulter et traiter.

## Décision

### Support central et file réseau

Le backend expose les coordonnées configurées pour les canaux appel et WhatsApp. Chaque canal est indépendant : si le canal demandé n’est pas configuré, la demande échoue avec `503 assisted_booking_support_unavailable`; aucun autre canal et aucune coordonnée d’agence ne servent de fallback.

Le dossier `assisted_bookings` est créé sans agence affectée, avec le statut `pending`. Cette file réseau d’assistance est visible par les agences habilitées et les rôles réseau autorisés. Un appel ou un message n’envoie rien automatiquement : l’application reçoit uniquement les coordonnées et les liens d’action.

### Réservation unique et modèle d’affectation

Une réservation conserve le même UUID et la même référence publique immuable `KBR-XXXXXXXX` pendant tout le parcours. Le dossier d’assistance reste un journal opérationnel un-à-un.

Le champ physique historique `preferred_agency_id` est conservé pour limiter la migration, mais il est nullable et représente désormais l’**agence traitante**. Les dossiers historiques conservent leur agence; les nouveaux dossiers commencent sans affectation. Les contrats publics exposent `handling_agency` et `assigned_agent`, jamais `preferred_agency`.

Le **claim** est une prise en charge atomique : la réservation puis le dossier sont verrouillés, l’agence de l’agent est enregistrée avec l’agent et le statut passe à `in_progress`. Le premier claim valide gagne; un claim concurrent reçoit un conflit et ne peut pas écraser l’affectation.

Une **réaffectation** est réservée aux permissions réseau. Elle choisit une agence active, efface l’agent courant et remet le dossier à `pending` dans la file de l’agence cible. L’agence cible doit effectuer un claim séparé. Une **libération** efface l’agence et l’agent et remet le dossier dans la file réseau générale. Chaque transition journalise l’acteur, les anciennes et nouvelles affectations, le motif et les éléments commerciaux utiles.

### Absence de ressources réservées pendant l’attente

Tous les holds de siège et de crédit entreprise sont libérés au passage en assistance. L’échéance du dossier correspond au premier cutoff commercial effectif de ses départs. Les règles de vendabilité et la disponibilité sont réévaluées avant chaque action sensible et par la tâche périodique.

### Machine à états et arbitrage

L’assistance suit les états `pending`, `in_progress`, `cancelled`, `refused`, `unavailable`, `expired` et `finalized`. La réservation ajoute `assistance_pending` et `unavailable`.

La reprise du paiement en ligne reste possible tant que le dossier est `pending` et sans agence. La reprise agence et la reprise en ligne s’arbitrent par verrouillage; la première transaction validée gagne et l’autre reçoit un conflit explicite.

### Reprise, prix et sièges

Une reprise conserve les mêmes départs, origines et destinations. Le devis est recalculé et les sièges souhaités encore libres sont proposés; aucun hold n’est écrit tant que la matrice complète passager–jambe n’est pas valide. L’acquisition atomique existante reste l’unique interface profonde pour les ventes normales et assistées.

### Paiement et émission

Après claim, la préparation de la vente au guichet reste inchangée. L’agent doit être l’agent affecté, appartenir à l’agence traitante et disposer d’une session de caisse conforme. Paiement, caisse, agence de vente, facture fiscale, réservation `paid` et assistance `finalized` restent enregistrés dans la transaction existante.

### Permissions

- `assisted-bookings.view-network` donne la vue réseau.
- `assisted-bookings.reassign-network` autorise les réaffectations inter-agences.
- `assisted-bookings.claim` permet à un agent rattaché à une agence de réclamer un dossier libre ou placé dans sa file.
- `assisted-bookings.manage-agency` conserve les opérations locales prévues.
- Une agence non traitante ne peut ni préparer la vente ni confirmer le paiement.
- La Direction générale conserve sa vue réseau selon ses permissions actuelles, sans droit de claim implicite.

## Historique

La version initiale de MEZ-80 routait directement la demande vers une agence sélectionnée dans l’application et exposait des coordonnées d’appel et WhatsApp propres à chaque agence. Cette approche est remplacée par le support centralisé et la file réseau afin d’éviter un routage prématuré et de permettre à la première agence disponible de traiter le dossier.

## Conséquences

- Les références, UUID et codes publics restent les seuls identifiants exposés aux interfaces REST et Inertia.
- Les coordonnées `call_phone` et `whatsapp_phone` sont supprimées de la table `agencies`; elles résident dans `kora.assisted_bookings.support`.
- Aucune agence, départ, bus ou arrêt n’est déduit du choix appel/WhatsApp.
- Le TTL des devis reste configuré par `KORA_QUOTE_TTL_MINUTES`.
- L’interface mobile de sélection de l’assistance relève de MEZ-81; MEZ-80 livre le contrat REST et KORA OPS Web.
