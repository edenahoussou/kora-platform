import 'dart:io';
import 'dart:typed_data';

import 'package:path_provider/path_provider.dart';

import '../../domain/entities/ticket_document.dart';

/// Couche DATA : les PDF officiels enregistrés sur le téléphone.
///
/// Un dossier par billet, et **un seul fichier** dedans : retélécharger
/// remplace le document au lieu d'en empiler un second. Le dossier porte
/// l'UUID du billet, le fichier le nom donné par le backend.
///
/// L'écriture passe par un fichier temporaire renommé à la fin : un
/// téléchargement interrompu ne laisse jamais un PDF tronqué à la place
/// d'un billet valable.
class TicketDocumentStore {
  TicketDocumentStore({Future<Directory> Function()? root})
    : _root = root ?? _defaultRoot;

  final Future<Directory> Function() _root;

  static const String _partName = '.download.part';

  /// L'espace privé de l'app : le billet n'y est lisible que par elle, et
  /// l'ouverture comme le partage passent par les capacités natives.
  static Future<Directory> _defaultRoot() async {
    final documents = await getApplicationDocumentsDirectory();
    return Directory('${documents.path}${Platform.pathSeparator}tickets');
  }

  /// Le document déjà enregistré pour ce billet, s'il y en a un.
  Future<TicketDocument?> find(String ticketId) async {
    final directory = await _directoryOf(ticketId);
    if (!await directory.exists()) return null;

    await for (final entity in directory.list()) {
      if (entity is File && _nameOf(entity) != _partName) {
        return TicketDocument(
          ticketId: ticketId,
          fileName: _nameOf(entity),
          path: entity.path,
        );
      }
    }

    return null;
  }

  /// Enregistre [bytes] comme le document de ce billet, en remplaçant le
  /// précédent.
  Future<TicketDocument> save(
    String ticketId, {
    required Uint8List bytes,
    String? fileName,
  }) async {
    final directory = await _directoryOf(ticketId);
    await directory.create(recursive: true);

    final part = File('${directory.path}${Platform.pathSeparator}$_partName');
    await part.writeAsBytes(bytes, flush: true);

    final name = safeFileName(fileName, fallback: 'KORA-$ticketId.pdf');

    // Le nouveau document est complet sur le disque : l'ancien peut partir.
    await for (final entity in directory.list()) {
      if (entity is File && entity.path != part.path) await entity.delete();
    }

    final target = await part.rename(
      '${directory.path}${Platform.pathSeparator}$name',
    );

    return TicketDocument(
      ticketId: ticketId,
      fileName: name,
      path: target.path,
    );
  }

  Future<Directory> _directoryOf(String ticketId) async {
    // L'UUID devient un nom de dossier : rien d'autre que des lettres, des
    // chiffres et des tirets, pour qu'aucune valeur ne sorte de la racine.
    if (!_safeId.hasMatch(ticketId)) {
      throw ArgumentError.value(
        ticketId,
        'ticketId',
        'UUID de billet invalide',
      );
    }

    final root = await _root();
    return Directory('${root.path}${Platform.pathSeparator}$ticketId');
  }

  static final RegExp _safeId = RegExp(r'^[A-Za-z0-9-]{1,64}$');

  /// L'identifiant a la forme d'un UUID de billet : lettres, chiffres et
  /// tirets, rien qui puisse désigner un autre chemin — ni sur le disque,
  /// ni dans l'URL de l'API.
  static bool isValidTicketId(String ticketId) => _safeId.hasMatch(ticketId);

  static String _nameOf(File file) => file.uri.pathSegments.last;

  /// Le nom du backend, gardé autant que possible, mais sans rien qui
  /// puisse désigner un autre dossier : ni séparateur, ni `..`, ni
  /// caractère refusé par Android ou iOS.
  static String safeFileName(String? raw, {required String fallback}) {
    final base = (raw ?? '').split(RegExp(r'[/\\]')).last.trim();
    final cleaned = base
        .replaceAll(RegExp(r'[\x00-\x1F<>:"|?*]'), '_')
        .replaceAll(RegExp(r'^\.+'), '');

    if (cleaned.isEmpty) return fallback;

    return cleaned.toLowerCase().endsWith('.pdf') ? cleaned : '$cleaned.pdf';
  }
}
