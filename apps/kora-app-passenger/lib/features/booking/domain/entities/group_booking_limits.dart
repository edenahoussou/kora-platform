/// Les bornes d'une réservation de groupe, telles que MEZ-57 les fixe.
///
/// **Pourquoi en dur ?** Le serveur les tient dans `group_booking_settings`
/// (`minimum_passengers` 5, `maximum_passengers` 10), mais le seul point
/// d'entrée qui les publie — `GET group-booking-settings` — exige la
/// permission `group-discounts.view`, réservée à l'administration : un
/// passager reçoit 403. L'app n'a donc aucun moyen de les lire.
///
/// Ces valeurs sont un **confort d'affichage**, jamais une règle : c'est
/// le serveur qui tranche, en refusant (422) un `passenger_count` hors
/// bornes. Si l'exploitation change le barème sans que l'app suive, le
/// voyageur verra le refus du serveur plutôt qu'un silence — désagréable,
/// mais honnête.
///
/// À supprimer le jour où le back exposera ces bornes publiquement.
library;

/// Nombre de voyageurs à partir duquel la remise de groupe s'applique.
const int kGroupMinimumPassengers = 5;

/// Plafond d'un achat groupé en une seule transaction.
const int kGroupMaximumPassengers = 10;
