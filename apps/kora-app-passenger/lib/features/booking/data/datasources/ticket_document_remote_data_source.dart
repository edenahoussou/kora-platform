import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';

/// Le PDF tel que l'API le rend : ses octets, et le nom qu'elle lui donne.
class TicketPdfPayload {
  const TicketPdfPayload({required this.bytes, this.fileName});

  final Uint8List bytes;

  /// `null` quand la réponse ne porte pas de `Content-Disposition`.
  final String? fileName;
}

/// Couche DATA : le téléchargement du PDF officiel d'un billet.
///
/// `GET /api/v1/tickets/{uuid}/pdf`, livré par MEZ-82 : le document que le
/// backend génère pour le web et WhatsApp, octet pour octet. Comme les autres sources, celle-ci laisse remonter les
/// `DioException` : c'est le dépôt qui les traduit.
class TicketDocumentRemoteDataSource {
  const TicketDocumentRemoteDataSource(this._dio);

  final Dio _dio;

  /// Plus large que le délai commun de quinze secondes : le backend génère
  /// le PDF à la volée (QR, segments, mise en page), et un billet coupé à
  /// quinze secondes serait perdu alors qu'il arrivait.
  static const Duration receiveTimeout = Duration(seconds: 60);

  Future<TicketPdfPayload> download(String ticketId) async {
    final response = await _dio.get<List<int>>(
      ApiConstants.ticketPdf(ticketId),
      options: Options(
        // Le flux binaire tel quel : décodé en texte, un PDF est corrompu.
        responseType: ResponseType.bytes,
        receiveTimeout: receiveTimeout,
        headers: const {'Accept': 'application/pdf'},
      ),
    );

    final bytes = Uint8List.fromList(response.data ?? const []);

    // Un proxy, une page d'erreur HTML servie en 200 : sans cette
    // vérification, le voyageur enregistrerait un fichier qui ne s'ouvre
    // pas, et le croirait valable jusqu'à l'embarquement.
    if (!isPdf(bytes)) {
      throw const FormatException('La réponse n’est pas un PDF.');
    }

    return TicketPdfPayload(
      bytes: bytes,
      fileName: fileNameOf(response.headers.value('content-disposition')),
    );
  }

  /// Un PDF commence par `%PDF-`.
  static bool isPdf(List<int> bytes) =>
      bytes.length > 4 &&
      bytes[0] == 0x25 &&
      bytes[1] == 0x50 &&
      bytes[2] == 0x44 &&
      bytes[3] == 0x46 &&
      bytes[4] == 0x2D;

  /// Le nom de fichier d'un en-tête `Content-Disposition`.
  ///
  /// `filename*=UTF-8''…` (RFC 5987) l'emporte quand il est présent : c'est
  /// lui qui porte les accents. `filename="…"` sert de repli.
  static String? fileNameOf(String? header) {
    if (header == null || header.isEmpty) return null;

    final extended = RegExp(
      r"filename\*\s*=\s*[^']*'[^']*'([^;]+)",
      caseSensitive: false,
    ).firstMatch(header);
    if (extended != null) {
      try {
        final decoded = Uri.decodeComponent(extended.group(1)!.trim());
        if (decoded.isNotEmpty) return decoded;
      } on ArgumentError {
        // Encodage invalide : on retombe sur `filename=`.
      }
    }

    final plain = RegExp(
      r'filename\s*=\s*("([^"]*)"|[^;]+)',
      caseSensitive: false,
    ).firstMatch(header);
    final value = (plain?.group(2) ?? plain?.group(1))?.trim();

    return value == null || value.isEmpty ? null : value;
  }
}
