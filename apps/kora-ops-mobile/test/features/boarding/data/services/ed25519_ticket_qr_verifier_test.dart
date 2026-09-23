import 'dart:convert';

import 'package:cryptography/cryptography.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/boarding/data/services/ed25519_ticket_qr_verifier.dart';
import 'package:kora_ops_mobile/features/boarding/domain/entities/ticket_qr_verification.dart';

/// Encode un token `base64url(payload).base64url(signature)` — miroir de
/// `BuildTicketQrPayloadAction::base64UrlEncode` côté backend, pour
/// produire ici de vrais tokens signés à vérifier.
String _base64UrlEncode(List<int> bytes) =>
    base64Url.encode(bytes).replaceAll('=', '');

Future<String> _sign(
  SimpleKeyPair keyPair,
  Map<String, Object?> payload,
) async {
  final List<int> payloadBytes = utf8.encode(json.encode(payload));
  final Ed25519 algorithm = Ed25519();
  final Signature signature =
      await algorithm.sign(payloadBytes, keyPair: keyPair);
  return '${_base64UrlEncode(payloadBytes)}.${_base64UrlEncode(signature.bytes)}';
}

void main() {
  late SimpleKeyPair keyPair;
  late String publicKeyBase64;
  late Ed25519TicketQrVerifier verifier;

  setUp(() async {
    // Vrai keypair Ed25519 généré pour chaque test — pas la clé de
    // production (voir AppConfig.ticketSigningPublicKeyBase64) : ces
    // tests portent sur l'ALGORITHME, indépendamment de la clé réelle.
    keyPair = await Ed25519().newKeyPair();
    final SimplePublicKey publicKey = await keyPair.extractPublicKey();
    publicKeyBase64 = base64.encode(publicKey.bytes);
    verifier = Ed25519TicketQrVerifier(publicKeyBase64: publicKeyBase64);
  });

  test('signature valide : décode le payload (t, s)', () async {
    final String token = await _sign(keyPair, <String, Object?>{
      'v': 2,
      't': 'ticket-uuid-1',
      's': 'AB12CD',
    });

    final TicketQrVerification result = await verifier.verify(token);

    expect(result, isA<TicketQrVerified>());
    result as TicketQrVerified;
    expect(result.ticketUuid, 'ticket-uuid-1');
    expect(result.shortCode, 'AB12CD');
  });

  test('champ court code (s) absent : accepté, shortCode null', () async {
    final String token = await _sign(
        keyPair, <String, Object?>{'v': 2, 't': 'ticket-uuid-2'});

    final TicketQrVerification result = await verifier.verify(token);

    expect(result, isA<TicketQrVerified>());
    expect((result as TicketQrVerified).shortCode, isNull);
  });

  test('payload altéré après signature : signature invalide', () async {
    final String token =
        await _sign(keyPair, <String, Object?>{'v': 2, 't': 'ticket-uuid-1'});
    final List<String> parts = token.split('.');

    // Remplace le payload par un autre, signé légitimement par ailleurs,
    // sans toucher à la signature d'origine — simule une falsification
    // (copier un vrai token puis changer le billet visé).
    final String forgedPayload = _base64UrlEncode(
        utf8.encode(json.encode(<String, Object?>{'t': 'ticket-uuid-HACKED'})));
    final String forgedToken = '$forgedPayload.${parts[1]}';

    final TicketQrVerification result = await verifier.verify(forgedToken);

    expect(result, isA<TicketQrSignatureInvalid>());
  });

  test('signée par une autre clé (pas la clé KORA) : signature invalide',
      () async {
    final SimpleKeyPair otherKeyPair = await Ed25519().newKeyPair();
    final String token = await _sign(
        otherKeyPair, <String, Object?>{'v': 2, 't': 'ticket-uuid-1'});

    final TicketQrVerification result = await verifier.verify(token);

    expect(result, isA<TicketQrSignatureInvalid>());
  });

  test('pas au format payload.signature : malformé', () async {
    expect(await verifier.verify('pas-un-token'), isA<TicketQrMalformed>());
    expect(await verifier.verify('a.b.c'), isA<TicketQrMalformed>());
    expect(await verifier.verify(''), isA<TicketQrMalformed>());
  });

  test('base64url invalide : malformé (jamais une exception)', () async {
    final TicketQrVerification result =
        await verifier.verify('!!!caractères invalides!!!.abc');

    expect(result, isA<TicketQrMalformed>());
  });

  test('signature cryptographiquement correcte mais payload sans champ t : '
      'malformé', () async {
    final String token = await _sign(
        keyPair, <String, Object?>{'v': 2, 'other_field': 'no-ticket-uuid'});

    final TicketQrVerification result = await verifier.verify(token);

    expect(result, isA<TicketQrMalformed>());
  });

  test('clé publique de production (base64) accepte un format 32 octets',
      () {
    // Vérifie juste que la construction ne lève pas — la clé réelle
    // (AppConfig.ticketSigningPublicKeyBase64) n'a pas de contrepartie
    // privée disponible ici pour un vrai test de bout en bout.
    expect(
      () => Ed25519TicketQrVerifier(
          publicKeyBase64: '9csxdLdBfzIoFPHrt76YDy/F43oY6rtU6gCZEcSFE8A='),
      returnsNormally,
    );
  });
}
