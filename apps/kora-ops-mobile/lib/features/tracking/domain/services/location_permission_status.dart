/// Résultat d'une vérification/demande de permission de localisation —
/// abstraction au-dessus de `geolocator`'s `LocationPermission` (voir
/// `GeolocatorLocationProvider`), pour que le reste du code ne dépende
/// jamais du package directement (même principe que `BarcodeScanner`/
/// `ThermalPrinter` pour le matériel P501).
enum LocationPermissionStatus {
  /// Localisation précise autorisée, y compris en arrière-plan
  /// (`ACCESS_BACKGROUND_LOCATION`) — le seul état qui permet de démarrer
  /// le suivi (voir ticket MEZ-101, service premier plan).
  grantedAlways,

  /// Autorisée seulement quand l'app est au premier plan — insuffisant
  /// pour ce ticket (suivi écran verrouillé/app en arrière-plan), mais
  /// distingué de [denied] pour guider le convoyeur vers le bon réglage
  /// (« Toujours autoriser » plutôt que « Réessayer »).
  grantedWhileInUse,

  denied,

  /// Android : refusée de façon permanente, la boîte de dialogue système
  /// ne se réaffichera plus — seul un passage par les réglages de l'app
  /// peut corriger (voir `TrackingAvailability.correctiveAction`).
  deniedForever;

  bool get allowsBackgroundTracking =>
      this == LocationPermissionStatus.grantedAlways;
}
