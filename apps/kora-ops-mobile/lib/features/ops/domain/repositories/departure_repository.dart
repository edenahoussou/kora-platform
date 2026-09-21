import '../../../../core/result/result.dart';
import '../entities/depart.dart';

/// Couche DOMAIN : départs affectés au convoyeur connecté — voir MEZ-92 /
/// document.md § Tournée.
///
/// Contrairement au manifeste, la liste des départs n'est **pas** mise en
/// cache pour un usage hors ligne : le ticket ne le demande pas (seul le
/// manifeste doit rester consultable sans réseau une fois téléchargé), et
/// une liste de départs périmée pourrait faire gérer à l'agent une course
/// qui ne lui est plus affectée.
abstract class DepartureRepository {
  Future<Result<List<Depart>>> listAssigned({String? date, String? status});

  /// Détail complet (arrêts, équipage, `manifestVersion`) — appelé à la
  /// sélection d'un départ depuis la Tournée.
  Future<Result<Depart>> show(String departureUuid);

  /// Fait progresser le départ (embarquement → parti → arrivé, MEZ-98).
  /// [status] : `boarding`, `departed` ou `arrived`. Idempotent côté
  /// serveur (redemander le statut déjà atteint renvoie 200 sans effet —
  /// rejeu après coupure réseau) ; retourne le statut confirmé, à
  /// répercuter sur le [Depart] déjà en mémoire (`copyWith`) plutôt que
  /// de retélécharger tout le détail. Réservé à l'équipage affecté
  /// (`departures.progress`).
  Future<Result<String>> updateStatus(
    String departureUuid,
    String status, {
    DateTime? occurredAt,
  });

  /// Enregistre un retard (`0` minute pour l'annuler) — départ programmé
  /// ou en embarquement uniquement.
  Future<Result<void>> recordDelay(
    String departureUuid,
    int delayMinutes,
    String delayReason,
  );
}
