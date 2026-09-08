// Lecture des réponses de l'API Bus : le catalogue des villes, et
// l'outillage commun de lecture du JSON.
//
// Les JSON de ce fichier sont **recopiés de la staging**, sans les
// simplifier : c'est ce qui donne sa valeur au test. Un jeu d'essai
// réécrit à la main vérifierait seulement que le code fait ce que le code
// fait.
//
// Les itinéraires, eux, ont leur propre fichier.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/network/api_json.dart';
import 'package:kora_app_passenger/features/booking/data/models/city_model.dart';

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
      // L'arrêt physique est ce que `bus/itineraries` interroge : une ville
      // qui n'en porte pas ne peut pas servir de point de départ.
      expect(city.physicalStopId, '74703fce-3358-429d-9994-6b323664a419');
      expect(city.isSearchable, isTrue);
    });

    test('une ville sans arrêt physique ne peut pas être cherchée', () {
      // Bohicon est desservie par la ligne, mais le catalogue ne lui
      // rattache aucun arrêt : la proposer en départ mènerait à un 422.
      final city = CityModel.fromJson(
        ApiJson.list(kCitiesResponse).last,
      ).toEntity();

      expect(city.name, 'Bohicon');
      expect(city.physicalStopId, isNull);
      expect(city.isSearchable, isFalse);
    });

    test('dérive un code de trois lettres, accents et tirets retirés', () {
      expect(CityModel.codeFor('Cotonou'), 'COT');
      expect(CityModel.codeFor('Abomey-Calavi'), 'ABO');
      expect(CityModel.codeFor('Savè'), 'SAV');
      // Un nom plus court que trois lettres n'est pas complété.
      expect(CityModel.codeFor('Ba'), 'BA');
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
}
