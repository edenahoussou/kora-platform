/// Pourquoi le suivi a été arrêté — miroir partiel de
/// `TrackingSessionCloseReason` côté backend (MEZ-100). `manualClose` :
/// utilisé en secours si aucun des trois cas métier ne s'applique (ex.
/// déconnexion de l'agent en cours de route) — le serveur clôturera de
/// toute façon la session à l'arrivée/annulation réelle du départ ; ce
/// n'est ici qu'un arrêt LOCAL de la collecte.
enum TrackingStopReason {
  arrived,
  cancelled,
  manualClose;
}
