import 'dart:io';

import 'package:file_saver/file_saver.dart';
import 'package:open_filex/open_filex.dart';
import 'package:printing/printing.dart';

import '../../domain/entities/ticket_document.dart';

/// Ce que le voyageur fait du PDF officiel, avec les capacités natives du
/// téléphone. Le fichier est toujours celui du backend, octet pour octet.
abstract final class TicketDocumentActions {
  /// Ouvre le PDF dans la visionneuse du téléphone. `false` quand aucune
  /// application ne sait l'ouvrir.
  static Future<bool> open(TicketDocument document) async {
    final result = await OpenFilex.open(document.path, type: 'application/pdf');

    return result.type == ResultType.done;
  }

  /// Le sélecteur de partage du système : WhatsApp, courriel, Drive…
  static Future<void> share(TicketDocument document) async {
    await Printing.sharePdf(
      bytes: await File(document.path).readAsBytes(),
      filename: document.fileName,
    );
  }

  /// Une copie hors de l'espace privé de l'app, là où le voyageur choisit.
  /// `false` quand il referme le sélecteur sans rien enregistrer.
  static Future<bool> saveCopy(TicketDocument document) async {
    final name = document.fileName.toLowerCase().endsWith('.pdf')
        ? document.fileName.substring(0, document.fileName.length - 4)
        : document.fileName;

    final path = await FileSaver.instance.saveAs(
      name: name,
      bytes: await File(document.path).readAsBytes(),
      fileExtension: 'pdf',
      mimeType: MimeType.pdf,
    );

    return path != null;
  }
}
