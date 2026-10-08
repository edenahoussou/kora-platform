import 'dart:typed_data';

import 'package:file_saver/file_saver.dart';
import 'package:flutter/material.dart';
import 'package:printing/printing.dart';

import '../../../core/services/kora_dialogs.dart';
import '../../../injection/injection.dart';
import '../../border_manifest/domain/repositories/border_manifest_pdf_repository.dart';
import '../../ops/domain/entities/depart.dart';

/// Nom de fichier lisible et sûr : `manifeste-<trajet>-<date>` (sans
/// l'extension, ajoutée par le sélecteur de fichier).
String manifestPdfFileName(Depart depart) {
  String slug(String value) => value
      .toLowerCase()
      .replaceAll(RegExp(r'[^a-z0-9]+'), '-')
      .replaceAll(RegExp(r'^-+|-+$'), '');

  final String route = slug(depart.route);
  final String date = slug(depart.date);
  return <String>[
    'manifeste',
    if (route.isNotEmpty) route,
    if (date.isNotEmpty) date,
  ].join('-');
}

/// Télécharge le manifeste du départ en PDF (`GET /ops/departures/{uuid}/
/// manifest/pdf`, déjà géré côté serveur) et l'enregistre dans les
/// Téléchargements du téléphone. Fonctionne en mode P501 comme en mode
/// téléphone, sur ligne nationale comme internationale. Nécessite le
/// réseau (PDF généré par le serveur). Sur ligne internationale, le
/// serveur refuse (422) tant qu'une identité obligatoire manque : le
/// message liste les passagers à corriger.
Future<void> downloadManifestPdf(BuildContext context, Depart depart) async {
  KoraDialogs.showLoading(context, message: 'Téléchargement du manifeste…');
  final result = await getIt<BorderManifestPdfRepository>()
      .fetch(departureUuid: depart.id);
  if (!context.mounted) return;
  KoraDialogs.hideLoading(context);

  await result.when(
    onSuccess: (Uint8List bytes) async {
      final String name = manifestPdfFileName(depart);
      try {
        await FileSaver.instance.saveFile(
          name: name,
          bytes: bytes,
          ext: 'pdf',
          mimeType: MimeType.pdf,
        );
      } on Object {
        // Enregistrement impossible (stockage refusé/plein...) : on propose
        // le partage système, qui permet aussi d'enregistrer le fichier.
        if (!context.mounted) return;
        await KoraDialogs.warning(
          context,
          title: 'Enregistrement impossible',
          description: 'Le PDF n\'a pas pu être enregistré sur le téléphone. '
              'Il va être proposé au partage.',
        );
        await Printing.sharePdf(bytes: bytes, filename: '$name.pdf');
        return;
      }
      if (!context.mounted) return;

      final bool share = await KoraDialogs.confirm(
        context,
        title: 'Manifeste téléchargé',
        description: '$name.pdf a été enregistré dans les Téléchargements '
            'du téléphone.',
        confirmLabel: 'Partager',
        cancelLabel: 'Fermer',
      );
      if (share) {
        await Printing.sharePdf(bytes: bytes, filename: '$name.pdf');
      }
    },
    onFailure: (failure) => KoraDialogs.error(
      context,
      title: 'Téléchargement impossible',
      description: failure.message,
    ),
  );
}
