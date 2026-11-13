// La répartition d'un paiement mixte, jusqu'au réseau.
//
// L'écran calculait correctement « 3 700 F de solde, 0 de complément »,
// le bloc portait les deux montants, et le dépôt les **oubliait** en
// appelant la source distante. Le serveur répondait alors « le champ
// wallet amount est obligatoire quand method est wallet », et le billet
// ne pouvait pas se payer.
//
// Les tests de bloc ne l'ont pas vu : ils interrogent un bouchon de
// dépôt, pas celui qui parle au réseau. Ceux-ci couvrent le chaînon
// manquant.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/constants/api_constants.dart';
import 'package:kora_app_passenger/features/payment/data/datasources/payment_remote_data_source.dart';
import 'package:kora_app_passenger/features/payment/data/repositories/payment_repository_impl.dart';
import 'package:mocktail/mocktail.dart';

class MockDio extends Mock implements Dio {}

void main() {
  late MockDio dio;
  late PaymentRepositoryImpl repository;

  /// La réponse minimale qu'attend `PaymentAttemptModel`.
  Response<dynamic> answer() => Response<dynamic>(
    requestOptions: RequestOptions(path: ApiConstants.paymentInitiate),
    statusCode: 201,
    data: {
      'data': {'uuid': 'pay-1', 'status': 'pending', 'next_action': 'verify'},
    },
  );

  /// Le corps envoyé au serveur lors du dernier appel.
  Map<String, dynamic> sentBody() {
    final captured = verify(
      () => dio.post<dynamic>(
        ApiConstants.paymentInitiate,
        data: captureAny(named: 'data'),
      ),
    ).captured.single;

    return Map<String, dynamic>.from(captured as Map);
  }

  setUp(() {
    dio = MockDio();
    repository = PaymentRepositoryImpl(PaymentRemoteDataSource(dio));

    when(
      () => dio.post<dynamic>(any(), data: any(named: 'data')),
    ).thenAnswer((_) async => answer());
  });

  test(
    'un paiement par Wallet envoie la part du solde et celle des points',
    () async {
      await repository.initiate(
        reservationId: 'res-1',
        method: 'wallet',
        provider: '',
        idempotencyKey: 'kora-1',
        walletAmount: 3700,
        pointsAmount: 200,
      );

      final body = sentBody();
      expect(body['wallet_amount'], 3700);
      expect(body['points_amount'], 200);
      expect(body['method'], 'wallet');
    },
  );

  test('sans Wallet, les deux parts sont absentes du corps', () async {
    // Le serveur distingue « pas de Wallet dans ce paiement » de « zéro
    // franc de Wallet » : envoyer zéro ouvrirait un débit nul.
    await repository.initiate(
      reservationId: 'res-1',
      method: 'mobile_money',
      provider: 'fedapay',
      idempotencyKey: 'kora-2',
      network: 'mtn_momo',
      phone: '+2290166000001',
    );

    final body = sentBody();
    expect(body.containsKey('wallet_amount'), isFalse);
    expect(body.containsKey('points_amount'), isFalse);
  });

  test('le Wallet ne porte aucun encaisseur', () async {
    // Le solde ne passe par aucune passerelle : le serveur refuse un
    // provider sur un paiement qu'il règle lui-même.
    await repository.initiate(
      reservationId: 'res-1',
      method: 'wallet',
      provider: '',
      idempotencyKey: 'kora-3',
      walletAmount: 1000,
    );

    expect(sentBody().containsKey('provider'), isFalse);
  });
}
