import 'dart:convert';

import 'package:cryptography/cryptography.dart';

import '../../domain/entities/ticket_qr_verification.dart';
import '../../domain/services/ticket_qr_verifier.dart';

/// Implémentation réelle — vérifie un token QR de billet en Ed25519, sans
/// réseau. Miroir exact de `VerifyTicketQrSignatureAction` côté backend :
/// même format de token, même algorithme, clé publique correspondant à
/// la clé privée qui signe côté serveur (voir `BuildTicketQrPayloadAction`).
///
/// Étapes, dans cet ordre (comme côté backend) :
/// 1. Découper le token en `payload` et `signature` sur le premier `.`.
/// 2. Décoder les deux segments en base64url (alphabet `-_`, sans
///    padding — voir [_base64UrlDecode]).
/// 3. Vérifier la signature Ed25519 des octets bruts du payload — AVANT
///    de les interpréter comme du JSON : un payload dont la signature
///    est invalide ne doit jamais être décodé/utilisé, quel que soit son
///    contenu apparent.
/// 4. Seulement si la signature est valide, décoder le JSON et extraire
///    `t` (uuid du billet, obligatoire) et `s` (code court, optionnel).
class Ed25519TicketQrVerifier implements TicketQrVerifier {
  Ed25519TicketQrVerifier({required String publicKeyBase64})
      : _publicKey = SimplePublicKey(
          base64.decode(publicKeyBase64),
          type: KeyPairType.ed25519,
        );

  final SimplePublicKey _publicKey;
  static final Ed25519 _algorithm = Ed25519();

  @override
  Future<TicketQrVerification> verify(String token) async {
    final List<String> parts = token.split('.');
    if (parts.length != 2) return const TicketQrMalformed();

    final List<int>? payloadBytes = _base64UrlDecode(parts[0]);
    final List<int>? signatureBytes = _base64UrlDecode(parts[1]);
    if (payloadBytes == null || signatureBytes == null) {
      return const TicketQrMalformed();
    }

    final bool isSignatureValid;
    try {
      isSignatureValid = await _algorithm.verify(
        payloadBytes,
        signature: Signature(signatureBytes, publicKey: _publicKey),
      );
    } on Object {
      // Longueur de signature/clé incorrecte, ou toute autre anomalie
      // native — un token qui ne respecte pas le format attendu n'est
      // pas un billet KORA, pas une erreur technique à remonter.
      return const TicketQrMalformed();
    }

    if (!isSignatureValid) return const TicketQrSignatureInvalid();

    return _decodePayload(payloadBytes);
  }

  TicketQrVerification _decodePayload(List<int> payloadBytes) {
    try {
      final Object? decoded = json.decode(utf8.decode(payloadBytes));
      if (decoded is! Map<String, dynamic>) return const TicketQrMalformed();

      final Object? ticketUuid = decoded['t'];
      if (ticketUuid is! String || ticketUuid.isEmpty) {
        return const TicketQrMalformed();
      }

      final Object? shortCode = decoded['s'];
      return TicketQrVerified(
        ticketUuid: ticketUuid,
        shortCode: shortCode is String ? shortCode : null,
      );
    } on Object {
      return const TicketQrMalformed();
    }
  }

  /// Base64url (alphabet `-_`, sans padding) → octets bruts — voir
  /// `BuildTicketQrPayloadAction::base64UrlEncode` côté backend, dont
  /// c'est l'exacte opération inverse.
  List<int>? _base64UrlDecode(String input) {
    try {
      final String normalized = input.replaceAll('-', '+').replaceAll('_', '/');
      final int paddingNeeded = (4 - normalized.length % 4) % 4;
      return base64.decode(normalized + ('=' * paddingNeeded));
    } on FormatException {
      return null;
    }
  }
}
