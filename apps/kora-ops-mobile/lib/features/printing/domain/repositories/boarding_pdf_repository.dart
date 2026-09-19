import 'dart:typed_data';

import '../../../../core/result/result.dart';

/// PDF de confirmation d'embarquement — mode téléphone uniquement, voir
/// ticket "Supporter le scan par caméra téléphone en alternative au
/// P501". Remplace l'impression thermique (`ThermalPrinter`), inutile
/// sans terminal P501.
abstract class BoardingPdfRepository {
  Future<Result<Uint8List>> fetch({
    required String departureUuid,
    required String ticketUuid,
  });
}
