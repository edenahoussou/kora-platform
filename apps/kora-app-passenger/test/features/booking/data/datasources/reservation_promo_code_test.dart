// Le code promo part dans le devis d'itinéraire (MEZ-73), et la remise se
// lit dans sa réponse.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/constants/api_constants.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/reservation_remote_data_source.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/itinerary.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/journey.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:mocktail/mocktail.dart';

class _MockDio extends Mock implements Dio {}

void main() {
  late _MockDio dio;
  late ReservationRemoteDataSource dataSource;
  late Departure departure;

  setUp(() {
    dio = _MockDio();
    dataSource = ReservationRemoteDataSource(dio);

    final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
    final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');
    departure = mockDeparturesFor(
      SearchCriteria(
        origin: cotonou,
        destination: malanville,
        date: DateTime(2026, 8, 14),
      ),
    ).first;
  });

  /// La réponse de `itinerary-quotes`, remise comprise quand [code] est posé.
  void stubQuote({String? code}) {
    when(() => dio.post<dynamic>(any(), data: any(named: 'data'))).thenAnswer(
      (_) async => Response<dynamic>(
        requestOptions: RequestOptions(path: ApiConstants.itineraryQuotes),
        statusCode: 200,
        data: {
          'data': {
            'itinerary_quote_uuid': 'quote-1',
            'total_amount': code == null ? 2600 : 600,
            'currency': 'XOF',
            'pricing': {
              'subtotal_amount': 2600,
              'dynamic_adjustment_amount': 0,
              'promotion': code == null
                  ? null
                  : {
                      'uuid': 'promo-1',
                      'code': code,
                      'name': 'Test',
                      'discount_amount': 2000,
                    },
              'promotion_discount_amount': code == null ? 0 : 2000,
              'group_discount': <String, dynamic>{},
              'total_amount': code == null ? 2600 : 600,
              'currency': 'XOF',
            },
          },
        },
      ),
    );
  }

  Map<String, dynamic> sentBody() =>
      verify(
            () => dio.post<dynamic>(any(), data: captureAny(named: 'data')),
          ).captured.single
          as Map<String, dynamic>;

  test('le code part dans le devis, et la remise vient du serveur', () async {
    stubQuote(code: 'TEST2000');

    final quote = await dataSource.groupQuote(
      journey: Journey(outbound: Itinerary.direct(departure)),
      passengers: 1,
      promoCode: 'TEST2000',
    );

    expect(sentBody()['promo_code'], 'TEST2000');
    expect(quote.subtotal, const Price(2600));
    expect(quote.promotion?.code, 'TEST2000');
    expect(quote.promotion?.discount, const Price(2000));
    expect(quote.total, const Price(600));
  });

  test('sans code, le devis ne porte aucun champ promo', () async {
    stubQuote();

    final quote = await dataSource.groupQuote(
      journey: Journey(outbound: Itinerary.direct(departure)),
      passengers: 1,
    );

    expect(sentBody().containsKey('promo_code'), isFalse);
    expect(quote.promotion, isNull);
  });
}
