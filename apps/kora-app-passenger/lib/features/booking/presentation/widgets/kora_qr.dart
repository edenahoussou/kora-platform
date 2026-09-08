import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../../core/l10n/app_localization.dart';

/// Le code d'embarquement, réellement scannable.
///
/// [payload] est ce que le serveur porte sur le billet (`qr_code`) : un
/// identifiant, pas une image — c'est l'app qui le dessine. Un contrôleur
/// scanne ce code, donc rien ici ne doit être décoratif : la version
/// précédente dérivait un motif de la référence, joli mais illisible pour
/// une douchette.
///
/// Correction de niveau **basse** (`QrErrorCorrectLevel.L`) : la charge est
/// un UUID court, et une correction plus forte densifierait le motif sans
/// rien apporter sur un écran de téléphone, propre et bien éclairé.
class KoraQr extends StatelessWidget {
  const KoraQr({super.key, required this.payload, this.size = 132});

  final String payload;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: context.l10n.ticketQrSemantics,
      child: Container(
        padding: const EdgeInsets.all(9),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(AppRadius.input),
          border: Border.all(color: AppColors.seatBorderLight),
        ),
        child: SizedBox(
          width: size,
          height: size,
          // Fond blanc explicite et module navy : un QR peint dans les
          // couleurs du thème sombre ne se scanne plus.
          child: QrImageView(
            data: payload,
            version: QrVersions.auto,
            errorCorrectionLevel: QrErrorCorrectLevel.L,
            backgroundColor: AppColors.white,
            eyeStyle: const QrEyeStyle(
              eyeShape: QrEyeShape.square,
              color: AppColors.kora,
            ),
            dataModuleStyle: const QrDataModuleStyle(
              dataModuleShape: QrDataModuleShape.square,
              color: AppColors.kora,
            ),
            // Le cadre est déjà posé par le conteneur.
            padding: EdgeInsets.zero,
          ),
        ),
      ),
    );
  }
}
