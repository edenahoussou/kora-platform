// Lecture de `GET /api/v1/bus/itineraries`.
//
// Ce point d'entrée a remplacé `bus/departures`, que MEZ-63 a retiré, et
// il ne rend pas la même chose : des **itinéraires** composés de tronçons,
// là où l'ancien rendait des départs à plat. Le JSON est recopié de la
// staging, sans le simplifier — c'est ce qui donne sa valeur au test.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/booking/data/models/itinerary_model.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';

import '../../../../support/bus_api_fixtures.dart';

/// L'itinéraire de la fixture, avec [changes] appliqués à son unique
/// tronçon.
Map<String, dynamic> itineraryWith(Map<String, Object?> changes) => {
  ...kItinerary,
  'legs': [
    {...kItineraryLeg, ...changes},
  ],
};

void main() {
  test('lit l’itinéraire complet de la staging', () {
    final departure = ItineraryModel.fromJson(kItinerary).toEntity()!.first;

    // L'identifiant est celui du **départ**, pas de l'itinéraire : c'est
    // lui que réclament le plan des sièges et le devis.
    expect(departure.id, '56f674c2-7795-4427-9839-ff5de7345ade');
    expect(departure.departsAt, DateTime.utc(2026, 9, 9, 6).toLocal());
    expect(departure.seatsAvailable, 32);
    expect(departure.seatsTotal, 55);
    expect(departure.isSoldOut, isFalse);
  });

  test('le tarif est celui du tronçon, converti en FCFA', () {
    // `amount` et non `total_amount` : sur un itinéraire à correspondance
    // le total couvrirait plusieurs tronçons, et la carte n'en montre
    // qu'un.
    final departure = ItineraryModel.fromJson(kItinerary).toEntity()!.first;

    expect(departure.price.amount, 31600);
    expect(departure.price.currency, 'FCFA');
  });

  test('la gamme vient du service, plus d’un repli sur le bus', () {
    // `service_class_code` est neuf : `bus/departures` ne le rendait pas,
    // et l'app affichait « Classique » pour tout le monde.
    final departure = ItineraryModel.fromJson(kItinerary).toEntity()!.first;

    expect(departure.comfort, BusComfort.confort);
    expect(departure.busLabel, 'KORA Coach 55');
    expect(departure.vehicleLabel, 'KORA Coach 55');
  });

  test('une gamme inconnue ne vide pas la liste des résultats', () {
    // Une gamme ajoutée côté serveur ne doit pas faire disparaître le
    // départ : mieux vaut l'annoncer en entrée de gamme que pas du tout.
    final departure = ItineraryModel.fromJson(
      itineraryWith({'service_class_code': 'platine'}),
    ).toEntity()!.first;

    expect(departure.comfort, BusComfort.classique);
  });

  test('un bus sans marque retombe sur la gamme', () {
    final departure = ItineraryModel.fromJson(
      itineraryWith({
        'bus': {'uuid': 'b-1', 'seat_count': 55},
      }),
    ).toEntity()!.first;

    expect(departure.busLabel, isNull);
    expect(departure.vehicleLabel, 'KORA Confort');
  });

  test('garde les arrêts du tronçon, pas ceux de la recherche', () {
    // Deux tables distinctes côté serveur : la recherche interroge des
    // arrêts *physiques*, le devis et le plan des sièges des arrêts de
    // ligne. Envoyer les premiers là où l'on attend les seconds rend 422.
    final departure = ItineraryModel.fromJson(kItinerary).toEntity()!.first;

    expect(departure.originStopId, 'd27a305c-4a4b-405c-9ba6-0371e69024dd');
    expect(departure.destinationStopId, '6b0c1666-6eaa-4b67-a216-df1667ae6be1');
  });

  test('le trajet international est reconnu à ses deux pays', () {
    final departure = ItineraryModel.fromJson(kItinerary).toEntity()!.first;

    expect(departure.originCountry, 'BJ');
    expect(departure.destinationCountry, 'CI');
    expect(departure.isInternational, isTrue);
  });

  test('l’arrivée découle de la durée annoncée par la ligne', () {
    final departure = ItineraryModel.fromJson(kItinerary).toEntity()!.first;

    expect(departure.line.code, 'L1');
    expect(departure.line.duration, const Duration(minutes: 1110));
    // 06:00 UTC + 18 h 30 — l'horodatage que porte `arrival_at`.
    expect(departure.arrivesAt, DateTime.utc(2026, 9, 10, 0, 30).toLocal());
  });

  test('la distance manquante ne fait pas tomber la lecture', () {
    // `bus/itineraries` ne publie pas `distance_km` : elle ne sert qu'au
    // détail de la ligne, que la recherche n'affiche pas.
    expect(
      ItineraryModel.fromJson(kItinerary).toEntity()!.first.line.distanceKm,
      0,
    );
  });

  test('le nom de l’arrêt ne s’affiche que s’il ajoute quelque chose', () {
    // « Cotonou · Cotonou » n'apprendrait rien au voyageur.
    final departure = ItineraryModel.fromJson(kItinerary).toEntity()!.first;
    expect(departure.line.origin.label, 'Cotonou');

    final named = ItineraryModel.fromJson(
      itineraryWith({
        'origin_stop': {
          'uuid': 's-1',
          'name': 'Gare de Cotonou',
          'city': 'Cotonou',
          'country': 'BJ',
        },
      }),
    ).toEntity()!.first;
    expect(named.line.origin.label, 'Cotonou · Gare de Cotonou');
  });

  test('une correspondance est lue avec tous ses tronçons', () {
    // Elle était écartée tant que l'app ne savait pas l'afficher : un
    // Porto-Novo → Parakou que le serveur savait composer n'arrivait
    // jamais à l'écran, et la recherche répondait « Aucun voyage ».
    final itinerary = ItineraryModel.fromJson(kConnectedItinerary).toEntity()!;

    expect(itinerary.isDirect, isFalse);
    expect(itinerary.legs, hasLength(2));
    expect(itinerary.connectionsCount, 1);
    // Le changement se fait là où le second bus prend son départ.
    expect(itinerary.connectionCities.map((city) => city.name), ['Cotonou']);
    expect(itinerary.origin.name, 'Porto-Novo');
    expect(itinerary.destination.name, 'Parakou');
  });

  test('les heures et le prix sont ceux du voyage entier', () {
    // Additionner les durées de lignes oublierait l'attente à la
    // correspondance, et additionner les tronçons ignorerait le barème
    // du serveur.
    final itinerary = ItineraryModel.fromJson(kConnectedItinerary).toEntity()!;

    expect(itinerary.departsAt, DateTime.utc(2026, 9, 9, 6).toLocal());
    expect(itinerary.arrivesAt, DateTime.utc(2026, 9, 9, 16).toLocal());
    expect(itinerary.duration, const Duration(hours: 10));
    expect(itinerary.total.amount, 12000);
    expect(itinerary.connectionMargin, const Duration(minutes: 45));
  });

  test('l’attente se lit entre l’arrivée d’un bus et le départ du suivant', () {
    final itinerary = ItineraryModel.fromJson(kConnectedItinerary).toEntity()!;

    expect(itinerary.connectionWaits, [const Duration(hours: 1)]);
  });

  test('les places du voyage sont celles du tronçon le plus rempli', () {
    // Il faut une place sur chaque bus : annoncer celles du premier
    // vendrait un voyage que le second ne peut pas honorer.
    final itinerary = ItineraryModel.fromJson(kConnectedItinerary).toEntity()!;

    expect(itinerary.seatsAvailable, 4);
  });

  test('un tronçon illisible écarte tout le voyage', () {
    // En garder la moitié vendrait un trajet qui ne mène pas où il est
    // annoncé.
    final broken = <String, dynamic>{
      ...kConnectedItinerary,
      'legs': <Map<String, dynamic>>[
        kPortoNovoCotonouLeg,
        {...kCotonouParakouLeg, 'departure_at': ''},
      ],
    };

    expect(ItineraryModel.fromJson(broken).toEntity(), isNull);
  });

  test('un itinéraire sans date exploitable est écarté, pas inventé', () {
    expect(
      ItineraryModel.fromJson(itineraryWith({'departure_at': ''})).toEntity(),
      isNull,
    );
  });

  test('un champ manquant ne fait pas tomber la lecture', () {
    final departure = ItineraryModel.fromJson(const {
      'connections_count': 0,
      'legs': [
        {
          'departure_uuid': 'incomplet',
          'departure_at': '2026-09-09T06:00:00+00:00',
        },
      ],
    }).toEntity()!.first;

    expect(departure.id, 'incomplet');
    expect(departure.seatsAvailable, 0);
    expect(departure.isSoldOut, isTrue);
    expect(departure.price.amount, 0);
    expect(departure.line.duration, Duration.zero);
    expect(departure.amenities, isEmpty);
  });
}
