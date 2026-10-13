import 'update_gateway.dart';

/// Le contrôle de mise à jour, entièrement remis à Google Play.
///
/// Aucun appel à KORA, aucun réglage serveur, aucun numéro de version à
/// comparer : c'est Play qui sait ce qui est publié et ce qui est
/// installé. L'application se contente de lui demander, puis de suivre.
///
/// Le seul arbitrage qui reste est **imposer ou proposer**, et il se lit
/// dans la priorité choisie au moment de publier dans la Play Console.
/// C'est le bon endroit : on y sait si la version corrige un détail ou
/// une panne.
class AppUpdate {
  const AppUpdate(this._gateway, {this.immediateFrom = 4});

  final UpdateGateway _gateway;

  /// Seuil à partir duquel la mise à jour s'impose, sur l'échelle 0–5 de
  /// la Play Console. Quatre et cinq sont réservés, par convention Google,
  /// aux versions qu'il faut vraiment installer.
  final int immediateFrom;

  /// Demande à Play s'il y a quelque chose à installer, et le lance.
  ///
  /// Silencieux de bout en bout : pas de mise à jour en attente, pas de
  /// Play, pas d'Android — rien ne se passe et rien ne s'affiche. Le
  /// contrôle de version est un confort, jamais un obstacle entre
  /// quelqu'un et son billet.
  Future<void> run() async {
    final update = await _gateway.pending();
    if (update == null) return;

    try {
      if (update.priority >= immediateFrom && update.immediateAllowed) {
        await _gateway.startImmediate();
        return;
      }

      if (update.flexibleAllowed) await _gateway.startFlexible();
    } on Exception {
      // Mise à jour refusée ou interrompue par le voyageur : c'est son
      // droit, et Play le redemandera au prochain lancement.
    }
  }
}
