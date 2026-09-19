import 'dart:typed_data';

import '../../../../core/result/result.dart';

/// Manifeste frontière en PDF A4 — mode téléphone uniquement (MEZ-94), pas
/// d'imprimante thermique. Même principe que `BoardingPdfRepository` : le
/// serveur génère le document (jamais de montant) et refuse (422
/// `manifest_incomplete`) si une information obligatoire manque.
abstract class BorderManifestPdfRepository {
  Future<Result<Uint8List>> fetch({required String departureUuid});
}
