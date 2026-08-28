// Lecture des réponses de l'API Bus.
//
// Les JSON de ce fichier sont **recopiés depuis
// `docs/mintlify/guides/bus-search.mdx`**, sans les simplifier : c'est ce
// qui donne sa valeur au test. Un jeu d'essai réécrit à la main
// vérifierait seulement que le code fait ce que le code fait.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/network/api_json.dart';
import 'package:kora_app_passenger/features/booking/data/models/city_model.dart';
import 'package:kora_app_passenger/features/booking/data/models/departure_model.dart';
import 'package:kora_app_passenger/features/booking/data/models/line_model.dart';
import 'package:kora_app_passenger/features/booking/data/models/stop_model.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';

import '../../../../support/bus_api_fixtures.dart';

void main() {
  group('CityModel', () {
    test('lit une ville et en fait une City du domaine', () {
      final model = CityModel.fromJson(ApiJson.list(kCitiesResponse).first);

      expect(model.city, 'Cotonou');
      expect(model.country, 'BJ');
      expect(model.stopsCount, 3);

      final city = model.toEntity();
      // L'identifiant est le nom : c'est la clé qu'attendent les filtres
      // `from` / `to` de l'API.
      expect(city.id, 'Cotonou');
      expect(city.name, 'Cotonou');
      expect(city.code, 'COT');
      // Une ville peut desservir plusieurs arrêts : aucun n'est « la »
      // gare de la ville.
      expect(city.station, isNull);
      expect(city.label, 'Cotonou');
    });

    test('dérive un code de trois lettres, accents et tirets retirés', () {
      expect(CityModel.codeFor('Cotonou'), 'COT');
      expect(CityModel.codeFor('Abomey-Calavi'), 'ABO');
      expect(CityModel.codeFor('Savè'), 'SAV');
      // Un nom plus court que trois lettres n'est pas complété.
      expect(CityModel.codeFor('Ba'), 'BA');
    });
  });

  group('StopModel', () {
    test('lit un arrêt et garde son uuid pour l’endpoint des sièges', () {
      final stop = StopModel.fromJson(kCotonouStop);

      expect(stop.uuid, '6b72fe4a-5b40-307c-bc24-f79acf9a1bb9');
      expect(stop.name, 'Gare de Cotonou');
      expect(stop.city, 'Cotonou');
      expect(stop.stopOrder, 1);
      expect(stop.kmFromOrigin, 0.0);
    });

    test('la ville d’un arrêt porte le nom de la gare', () {
      final city = StopModel.fromJson(kCotonouStop).toCity();

      // Même identifiant que la ville venue de `/bus/cities` : les deux
      // désignent bien la même ville.
      expect(city.id, 'Cotonou');
      expect(city.station, 'Gare de Cotonou');
      expect(city.label, 'Cotonou · Gare de Cotonou');
    });

    test('un arrêt qui porte le nom de sa ville n’ajoute pas de gare', () {
      // Cas réel du staging : la ligne L1 nomme son arrêt `Cotonou`, dans
      // la ville `Cotonou`. Sans ce cas, l'écran affichait
      // « Cotonou · Cotonou ».
      final city = StopModel.fromJson(const {
        'uuid': 'e7f7c1ea-da0f-4f57-b64c-5ca2b7d20267',
        'name': 'Cotonou',
        'city': 'Cotonou',
        'country': 'BJ',
        'stop_order': 1,
        'km_from_origin': 0,
      }).toCity();

      expect(city.station, isNull);
      expect(city.label, 'Cotonou');
    });
  });

  group('LineModel', () {
    test('lit une ligne, ses pays traversés et ses arrêts', () {
      final line = LineModel.fromJson({
        ...kLine,
        'stops': [kCotonouStop, kBohiconStop, kAbidjanStop],
      });

      expect(line.uuid, '6ff8f7f6-1eb3-3525-be4a-3932c805afed');
      expect(line.code, 'COT-ABJ');
      expect(line.countriesCrossed, ['BJ', 'CI']);
      expect(line.distanceKm, 1050.5);
      expect(line.estimatedDurationMinutes, 480);
      expect(line.stops.map((stop) => stop.city), [
        'Cotonou',
        'Bohicon',
        'Abidjan',
      ]);
    });

    test('la ligne imbriquée dans un départ n’a pas d’arrêts', () {
      expect(LineModel.fromJson(kLine).stops, isEmpty);
    });
  });

  group('DepartureModel', () {
    test('lit le départ complet de la documentation', () {
      final departure = DepartureModel.fromJson(kDeparture).toEntity()!;

      expect(departure.id, 'a3091549-9179-454e-8440-58179f2208df');
      // Date et heure recomposées en heure locale.
      expect(departure.departsAt, DateTime(2026, 8, 20, 6));
      expect(departure.seatsAvailable, 32);
      expect(departure.seatsTotal, 49);
      expect(departure.isSoldOut, isFalse);
      // Aucun service à bord dans la réponse : on n'en promet aucun.
      expect(departure.amenities, isEmpty);
    });

    test('le tarif passe de XOF au FCFA affiché sur les billets', () {
      final departure = DepartureModel.fromJson(kDeparture).toEntity()!;

      expect(departure.price.amount, 15000);
      expect(departure.price.currency, 'FCFA');
    });

    test('le badge affiche le bus réel, faute de gamme dans l’API', () {
      final departure = DepartureModel.fromJson(kDeparture).toEntity()!;

      expect(departure.busLabel, 'Mercedes O500');
      expect(departure.vehicleLabel, 'Mercedes O500');
      // La gamme reste renseignée pour les écrans sans repli, mais elle
      // ne vient pas de l'API.
      expect(departure.comfort, BusComfort.classique);
    });

    test('un départ sans bus affecté retombe sur la gamme', () {
      final json = Map<String, dynamic>.from(kDeparture)..remove('bus');
      final departure = DepartureModel.fromJson(json).toEntity()!;

      expect(departure.busLabel, isNull);
      expect(departure.vehicleLabel, 'KORA Classique');
      expect(departure.seatsTotal, 0);
    });

    test('le trajet complet garde la durée annoncée par la ligne', () {
      final departure = DepartureModel.fromJson(kDeparture).toEntity()!;

      expect(departure.line.id, '6ff8f7f6-1eb3-3525-be4a-3932c805afed');
      expect(departure.line.origin.label, 'Cotonou · Gare de Cotonou');
      expect(departure.line.destination.label, "Abidjan · Gare d'Abidjan");
      expect(departure.line.distanceKm, 1051);
      expect(departure.line.duration, const Duration(minutes: 480));
      // 06:00 + 8 h — la valeur qu'annonce `estimated_arrival_at`, obtenue
      // sans jamais mélanger une heure locale et un horodatage UTC.
      expect(departure.arrivesAt, DateTime(2026, 8, 20, 14));
    });

    test('une portion de ligne voit sa durée ramenée au prorata', () {
      // Cotonou → Bohicon : 120 km des 1050,5 km de la ligne.
      final json = Map<String, dynamic>.from(kDeparture)
        ..['destination_stop'] = kBohiconStop;
      final departure = DepartureModel.fromJson(json).toEntity()!;

      expect(departure.line.destination.name, 'Bohicon');
      expect(departure.line.distanceKm, 120);
      // 480 min × 120 / 1050,5 ≈ 54,8 min — et non les 8 h du terminus.
      expect(departure.line.duration.inMinutes, 54);
      expect(departure.line.segments, hasLength(1));
      expect(departure.line.stops, isEmpty);
    });

    test('accepte une heure de départ avec les secondes', () {
      final json = Map<String, dynamic>.from(kDeparture)
        ..['departure_time'] = '06:30:00';

      expect(
        DepartureModel.fromJson(json).toEntity()!.departsAt,
        DateTime(2026, 8, 20, 6, 30),
      );
    });

    test('un départ sans date exploitable est écarté, pas inventé', () {
      final json = Map<String, dynamic>.from(kDeparture)
        ..['departure_date'] = '';

      expect(DepartureModel.fromJson(json).toEntity(), isNull);
    });

    test('un champ manquant ne fait pas tomber la lecture', () {
      final departure = DepartureModel.fromJson(const {
        'uuid': 'incomplet',
        'departure_date': '2026-08-20',
      }).toEntity()!;

      expect(departure.id, 'incomplet');
      expect(departure.departsAt, DateTime(2026, 8, 20));
      expect(departure.seatsAvailable, 0);
      expect(departure.isSoldOut, isTrue);
      expect(departure.price.amount, 0);
      expect(departure.line.duration, Duration.zero);
    });
  });

  group('ApiJson', () {
    test('ouvre l’enveloppe data et ignore ce qui n’est pas un objet', () {
      expect(ApiJson.list(kCitiesResponse), hasLength(3));
      expect(ApiJson.list(const {'data': 'oups'}), isEmpty);
      expect(ApiJson.list(null), isEmpty);
    });

    test('accepte une réponse déjà à plat', () {
      expect(
        ApiJson.list(const [
          {'city': 'Cotonou'},
        ]),
        hasLength(1),
      );
    });

    test('lit un nombre quel que soit le type reçu', () {
      expect(ApiJson.intOrNull(49), 49);
      expect(ApiJson.intOrNull('49'), 49);
      expect(ApiJson.intOrNull(49.0), 49);
      expect(ApiJson.intOrNull('nope'), isNull);
      expect(ApiJson.doubleOrNull(1050), 1050.0);
      expect(ApiJson.doubleOrNull('1050.5'), 1050.5);
    });
  });

  test('lit le montant final du tarif, et non le tarif de base', () {
    final departure = DepartureModel.fromJson({
      ...kDeparture,
      'fare_preview': {
        'fare_uuid': 'ae8feee9-1a78-4745-83f1-f12817bbcb46',
        'base_amount': 15000,
        // Une règle de remplissage a majoré le tarif : c'est ce montant
        // que le passager paiera, et donc celui qui s'affiche.
        'final_amount': 18000,
        'currency': 'XOF',
        'is_quote': true,
      },
    }).toEntity()!;

    expect(departure.price.amount, 18000);
    expect(departure.price.currency, 'FCFA');
  });

  test('retombe sur le tarif de base quand le final manque', () {
    final departure = DepartureModel.fromJson({
      ...kDeparture,
      'fare_preview': {'base_amount': 12000, 'currency': 'XOF'},
    }).toEntity()!;

    expect(departure.price.amount, 12000);
  });
}
