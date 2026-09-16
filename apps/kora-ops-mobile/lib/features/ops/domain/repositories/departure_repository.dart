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
}
