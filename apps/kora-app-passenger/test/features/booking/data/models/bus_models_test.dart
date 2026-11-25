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
      expect(model.servedStops, 3);

      final city = model.toEntity()!;
      // L'identifiant est le nom : c'est la clé qu'attendent `from_city`
      // et `to_city`.
      expect(city.id, 'Cotonou');
      expect(city.name, 'Cotonou');
      expect(city.code, 'COT');
      // Le sélecteur ne montre que des villes : aucune gare à nommer.
      expect(city.station, isNull);
      expect(city.label, 'Cotonou');
      expect(city.isSearchable, isTrue);
    });

    test('les arrêts de la ville remontent jusqu’au sélecteur', () {
      // « Cotonou » ne dit pas d'où part le bus : le sélecteur affiche
      // donc les points d'arrêt sous la ville. Ils servent à nommer, pas
      // à choisir — la recherche part toujours au nom de la ville.
      final city = CityModel.fromJson({
        'city': 'Cotonou',
        'country': 'BJ',
        'physical_stops': [
          {'uuid': 'stop-une', 'name': 'Gare de Dantokpa'},
          {'uuid': 'stop-deux', 'name': 'Gare de Godomey'},
          // Un arrêt sans nom ne laisse pas de trou dans la ligne.
          {'uuid': 'stop-trois', 'name': ''},
        ],
      }).toEntity()!;

      expect(city.stops.map((stop) => stop.name), [
        'Gare de Dantokpa',
        'Gare de Godomey',
      ]);
      // L'identifiant suit le nom : c'est lui qui part au serveur quand
      // le voyageur précise son arrêt.
      expect(city.stops.map((stop) => stop.uuid), ['stop-une', 'stop-deux']);
      // Tant qu'aucun arrêt n'est retenu, la recherche reste par ville.
      expect(city.stopUuid, isNull);
      // La ville reste la clé de recherche, inchangée.
      expect(city.id, 'Cotonou');
      expect(city.label, 'Cotonou');
    });

    test('une ville sans arrêts nommés n’invente rien', () {
      final city = CityModel.fromJson({
        'city': 'Bohicon',
        'country': 'BJ',
        'stops_count': 1,
      }).toEntity()!;

      expect(city.stops, isEmpty);
    });

    test('une ville à plusieurs arrêts ne donne qu’une entrée', () {
      // Le cas qui faisait annoncer « Aucun voyage ce jour-là » : deux
      // entrées « Cotonou » dans le sélecteur, dont une seule portait les
      // départs du jour, et rien pour aider le voyageur à deviner
      // laquelle. Le serveur explore maintenant les deux arrêts lui-même.
      final city = CityModel.fromJson({
        'city': 'Cotonou',
        'country': 'BJ',
        'physical_stops': [
          {'uuid': 'stop-une', 'name': 'Arrêt Cotonou'},
          {'uuid': 'stop-deux', 'name': 'Cotonou'},
        ],
      }).toEntity();

      expect(city?.label, 'Cotonou');
    });

    test('une ville annoncée sans arrêt est écartée', () {
      // Le catalogue dit qu'aucun arrêt ne la dessert : la proposer
      // mènerait à une liste vide, sans rien à y corriger.
      final city = CityModel.fromJson({
        'city': 'Nulle part',
        'country': 'BJ',
        'physical_stops': <Map<String, dynamic>>[],
      }).toEntity();

      expect(city, isNull);
    });

    test('une ville est proposée quand la réponse ne parle pas d’arrêts', () {
      // `bus/cities` ne liste que des villes desservies. Le jour où elle
      // cesse de publier ses arrêts, le sélecteur ne doit pas se vider :
      // ce détail ne sert plus à chercher.
      final city = CityModel.fromJson({
        'city': 'Parakou',
        'country': 'BJ',
      }).toEntity();

      expect(city?.name, 'Parakou');
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
