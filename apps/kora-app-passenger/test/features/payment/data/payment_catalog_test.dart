// Le catalogue des moyens de paiement : sa lecture, ce que le domaine en
// déduit, et l'appel réseau qui le rapporte.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/constants/api_constants.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/network/api_json.dart';
import 'package:kora_app_passenger/features/payment/data/datasources/payment_remote_data_source.dart';
import 'package:kora_app_passenger/features/payment/data/models/payment_catalog_model.dart';
import 'package:kora_app_passenger/features/payment/data/repositories/payment_repository_impl.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_catalog.dart';
import 'package:mocktail/mocktail.dart';

import '../../../support/payment_api_fixtures.dart';

class MockDio extends Mock implements Dio {}

void main() {
  PaymentCatalog parsed() => PaymentCatalogModel.fromJson(
    ApiJson.map(kPaymentMethodsResponse['data']),
  ).toEntity();

  group('lecture du catalogue', () {
    test('lit le pays, la devise et les deux moyens', () {
      final catalog = parsed();

      expect(catalog.country, 'BJ');
      expect(catalog.currency, 'XOF');
      expect(catalog.methods.map((method) => method.code), [
        'mobile_money',
        'wallet',
      ]);
    });

    test('lit un provider et ses contraintes d’appel', () {
      final fedapay = parsed().mobileMoneyProviders.first;

      expect(fedapay.code, 'fedapay');
      expect(fedapay.label, 'FedaPay');
      expect(fedapay.networks, ['mtn_momo', 'moov_flooz', 'celtiis_cash']);
      expect(fedapay.requires, ['phone', 'mobile_money_network']);
      expect(fedapay.pollingInterval, const Duration(seconds: 10));
      expect(fedapay.verification, 'server_verify');
      // `push_or_redirect` : le paiement peut basculer sur une page web.
      expect(fedapay.mayRedirect, isTrue);
    });

    test('le Mini Wallet revient désactivé, avec sa raison', () {
      final wallet = parsed().methodOf(PaymentCatalog.walletCode)!;

      expect(wallet.enabled, isFalse);
      expect(wallet.availability, 'out_of_mvp');
      expect(wallet.isUsable, isFalse);
      // Il n'est pas retiré du catalogue : l'écran peut le griser en
      // disant pourquoi, plutôt que de le faire disparaître.
      expect(parsed().enabledMethods.map((method) => method.code), [
        'mobile_money',
      ]);
    });

    test('les réseaux sont dédupliqués et gardent l’ordre du serveur', () {
      // `mtn_momo` est servi par FedaPay **et** par MTN MoMo : il ne doit
      // apparaître qu'une fois dans le sélecteur.
      expect(parsed().mobileMoneyNetworks, [
        'mtn_momo',
        'moov_flooz',
        'celtiis_cash',
      ]);
    });

    test('un réseau est rattaché au premier provider qui l’annonce', () {
      final catalog = parsed();

      expect(catalog.providerForNetwork('mtn_momo')?.code, 'fedapay');
      expect(catalog.providerForNetwork('moov_flooz')?.code, 'fedapay');
      // Un réseau que personne ne sert ne doit pas retomber sur un
      // provider au hasard.
      expect(catalog.providerForNetwork('wave'), isNull);
    });

    test('un provider désactivé disparaît des choix', () {
      final catalog = PaymentCatalogModel.fromJson(const {
        'country': 'BJ',
        'currency': 'XOF',
        'methods': [
          {
            'code': 'mobile_money',
            'label': 'Mobile Money',
            'enabled': true,
            'providers': [
              {
                'code': 'fedapay',
                'label': 'FedaPay',
                'enabled': false,
                'networks': ['mtn_momo'],
              },
            ],
          },
        ],
      }).toEntity();

      expect(catalog.mobileMoneyProviders, isEmpty);
      expect(catalog.mobileMoneyNetworks, isEmpty);
    });

    test('un champ manquant ne fait pas tomber la lecture', () {
      final catalog = PaymentCatalogModel.fromJson(const {}).toEntity();

      expect(catalog.country, '');
      expect(catalog.isEmpty, isTrue);
      expect(catalog.mobileMoneyNetworks, isEmpty);
    });

    test('une cadence absente ou absurde retombe sur la valeur conseillée', () {
      PaymentProvider providerWith(Object? seconds) =>
          PaymentProviderModel.fromJson({
            'code': 'fedapay',
            'enabled': true,
            'polling_interval_seconds': seconds,
          }).toEntity();

      expect(
        providerWith(null).pollingInterval,
        PaymentProvider.defaultPollingInterval,
      );
      // Zéro relancerait la vérification en boucle serrée.
      expect(
        providerWith(0).pollingInterval,
        PaymentProvider.defaultPollingInterval,
      );
      expect(providerWith(30).pollingInterval, const Duration(seconds: 30));
    });
  });

  group('appel réseau', () {
    late MockDio dio;
    late PaymentRemoteDataSource dataSource;

    setUp(() {
      dio = MockDio();
      dataSource = PaymentRemoteDataSource(dio);
    });

    test('appelle l’endpoint documenté et déballe l’enveloppe data', () async {
      when(() => dio.get<dynamic>(any())).thenAnswer(
        (_) async => Response<dynamic>(
          requestOptions: RequestOptions(path: ApiConstants.paymentMethods),
          statusCode: 200,
          data: kPaymentMethodsResponse,
        ),
      );

      final catalog = await dataSource.catalog();

      verify(() => dio.get<dynamic>(ApiConstants.paymentMethods)).called(1);
      expect(catalog.mobileMoneyNetworks, hasLength(3));
    });

    test('un 401 devient une AuthFailure, pas une erreur technique', () async {
      final options = RequestOptions(path: ApiConstants.paymentMethods);
      when(() => dio.get<dynamic>(any())).thenThrow(
        DioException(
          requestOptions: options,
          type: DioExceptionType.badResponse,
          response: Response<dynamic>(
            requestOptions: options,
            statusCode: 401,
            data: const {'message': 'Unauthenticated.'},
          ),
        ),
      );

      final result = await PaymentRepositoryImpl(dataSource).catalog();

      final failure = result.fold((failure) => failure, (_) => null);
      expect(failure, isA<AuthFailure>());
      // Le message du serveur remonte tel quel : c'est lui qui explique.
      expect(failure!.message, 'Unauthenticated.');
    });

    test('une coupure réseau devient une NetworkFailure', () async {
      when(() => dio.get<dynamic>(any())).thenThrow(
        DioException(
          requestOptions: RequestOptions(path: ApiConstants.paymentMethods),
          type: DioExceptionType.connectionError,
        ),
      );

      final result = await PaymentRepositoryImpl(dataSource).catalog();

      expect(
        result.fold((failure) => failure, (_) => null),
        isA<NetworkFailure>(),
      );
    });
  });
}
