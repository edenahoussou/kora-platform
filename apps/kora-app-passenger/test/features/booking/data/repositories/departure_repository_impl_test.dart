// La traduction des erreurs techniques en échecs métier.
//
// C'est le seul travail du repository, et c'est celui dont dépend
// directement ce que lit le voyageur : « Vérifie ta connexion » plutôt que
// « DioException [connectionError] ».
//
// La règle vit dans `ApiFailureMapper`, partagé par les deux dépôts ; on
// l'éprouve ici à travers celui de la recherche, qui est le seul branché
// sur le réseau aujourd'hui.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/exceptions.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_data_source.dart';
import 'package:kora_app_passenger/features/booking/data/repositories/departure_repository_impl.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/city.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure_search.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:mocktail/mocktail.dart';

class MockBookingDataSource extends Mock implements BookingDataSource {}

void main() {
  late MockBookingDataSource dataSource;
  late DepartureRepositoryImpl repository;

  const cotonou = City(id: 'Cotonou', name: 'Cotonou', code: 'COT');
  const abidjan = City(id: 'Abidjan', name: 'Abidjan', code: 'ABI');
  final criteria = SearchCriteria(
    origin: cotonou,
    destination: abidjan,
    date: DateTime(2026, 8, 20),
  );

  /// L'erreur telle que la lève Dio pour un statut HTTP donné.
  DioException httpError(int statusCode, {Object? body}) {
    final options = RequestOptions(path: '/api/v1/bus/cities');
    return DioException(
      requestOptions: options,
      type: DioExceptionType.badResponse,
      response: Response<dynamic>(
        requestOptions: options,
        statusCode: statusCode,
        data: body,
      ),
    );
  }

  setUp(() {
    dataSource = MockBookingDataSource();
    repository = DepartureRepositoryImpl(dataSource);
  });

  test('rend les données du data source telles quelles', () async {
    when(() => dataSource.getCities()).thenAnswer((_) async => const [cotonou]);

    final result = await repository.getCities();

    expect(result.getOrElse(() => const <City>[]), const [cotonou]);
  });

  test('une liste vide reste un succès, pas un échec', () async {
    when(
      () => dataSource.searchDepartures(criteria),
    ).thenAnswer((_) async => const DepartureSearch.oneWay([]));

    final result = await repository.search(criteria);

    expect(result.isRight(), isTrue);
    expect(
      result.getOrElse(() => const DepartureSearch.oneWay([])).outbound,
      isEmpty,
    );
  });

  group('erreurs réseau', () {
    Future<Failure> failureFor(Object error) async {
      when(() => dataSource.getCities()).thenThrow(error);
      final result = await repository.getCities();
      return result.fold((failure) => failure, (_) => fail('succès inattendu'));
    }

    test('connexion perdue → NetworkFailure', () async {
      final failure = await failureFor(
        DioException(
          requestOptions: RequestOptions(path: '/'),
          type: DioExceptionType.connectionError,
        ),
      );

      expect(failure, isA<NetworkFailure>());
    });

    test('délai dépassé → TimeoutFailure', () async {
      // Pas un `NetworkFailure` : le téléphone est en ligne, c'est le
      // serveur qui traîne. Les confondre envoyait le voyageur chercher
      // une panne chez lui — ce qui est arrivé sur la connexion par
      // e-mail, qu'un appel bloqué côté serveur faisait expirer.
      final failure = await failureFor(
        DioException(
          requestOptions: RequestOptions(path: '/'),
          type: DioExceptionType.receiveTimeout,
        ),
      );

      expect(failure, isA<TimeoutFailure>());
      expect(failure.message, contains('trop de temps'));
    });

    test('401 → AuthFailure', () async {
      expect(await failureFor(httpError(401)), isA<AuthFailure>());
    });

    test('422 → ValidationFailure', () async {
      expect(await failureFor(httpError(422)), isA<ValidationFailure>());
    });

    test('500 → ServerFailure', () async {
      expect(await failureFor(httpError(500)), isA<ServerFailure>());
    });

    test('le message du serveur est repris pour l’écran', () async {
      final failure = await failureFor(
        httpError(500, body: const {'message': 'Catalogue en maintenance.'}),
      );

      expect(failure.message, 'Catalogue en maintenance.');
    });

    test(
      'les exceptions des sources locales sont toujours traduites',
      () async {
        expect(
          await failureFor(const ServerException('Jeu de données illisible.')),
          isA<ServerFailure>(),
        );
        expect(
          await failureFor(const NetworkException()),
          isA<NetworkFailure>(),
        );
      },
    );

    test('une erreur imprévue ne remonte jamais brute au Bloc', () async {
      expect(
        await failureFor(const FormatException('réponse illisible')),
        isA<UnexpectedFailure>(),
      );
    });
  });
}
