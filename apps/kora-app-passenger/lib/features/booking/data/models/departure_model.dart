import '../../domain/entities/departure.dart';
import '../../domain/entities/price.dart';
import 'bus_json.dart';
import 'line_model.dart';
import 'stop_model.dart';

/// Couche DATA : un départ, tel que le renvoie
/// `GET /api/v1/bus/departures?date=…&from=…&to=…`.
///
/// Voir `docs/mintlify/guides/bus-search.mdx` pour la réponse complète.
/// Les champs sont repris tels quels ; c'est [toEntity] qui les traduit
/// dans le vocabulaire du domaine.
class DepartureModel {
  const DepartureModel({
    required this.uuid,
    required this.departureDate,
    required this.departureTime,
    required this.status,
    required this.line,
    required this.bus,
    required this.originStop,
    required this.destinationStop,
    required this.availableSeatsCount,
    required this.farePreview,
  });

  factory DepartureModel.fromJson(Map<String, dynamic> json) {
    final fare = BusJson.map(json['fare_preview']);
    final bus = BusJson.map(json['bus']);

    return DepartureModel(
      uuid: BusJson.string(json['uuid']),
      departureDate: BusJson.string(json['departure_date']),
      departureTime: BusJson.string(json['departure_time']),
      status: BusJson.string(json['status']),
      line: LineModel.fromJson(BusJson.map(json['line'])),
      bus: bus.isEmpty ? null : BusModel.fromJson(bus),
      originStop: StopModel.fromJson(BusJson.map(json['origin_stop'])),
      destinationStop: StopModel.fromJson(
        BusJson.map(json['destination_stop']),
      ),
      availableSeatsCount:
          BusJson.intOrNull(json['available_seats_count']) ?? 0,
      farePreview: fare.isEmpty ? null : FarePreviewModel.fromJson(fare),
    );
  }

  final String uuid;

  /// Jour du départ, `aaaa-mm-jj`.
  final String departureDate;

  /// Heure locale du départ, `hh:mm` (parfois `hh:mm:ss`).
  final String departureTime;

  /// `scheduled`, `cancelled`... Non interprété pour l'instant : la
  /// recherche ne renvoie que des départs programmés.
  final String status;

  final LineModel line;

  /// Véhicule affecté, `null` tant qu'aucun bus n'est assigné au départ.
  final BusModel? bus;

  /// Arrêt de montée, correspondant au filtre `from` de la recherche.
  final StopModel originStop;

  /// Arrêt de descente, correspondant au filtre `to`.
  final StopModel destinationStop;

  final int availableSeatsCount;

  /// Aperçu du tarif. La documentation insiste : ce n'est **pas** un devis
  /// figé (celui-ci viendra de `/api/v1/pricing`).
  final FarePreviewModel? farePreview;

  /// Le [Departure] du domaine, ou `null` si le départ n'est pas datable.
  ///
  /// Un départ sans date exploitable n'a rien à faire dans une liste de
  /// résultats triée par heure : le data source l'écarte plutôt que de
  /// lui inventer un horaire. C'est le seul cas où une ligne de la
  /// réponse est ignorée.
  Departure? toEntity() {
    final departsAt = _departsAt();
    if (departsAt == null) return null;

    final price = farePreview?.toPrice() ?? const Price(0);

    return Departure(
      id: uuid,
      line: line.toEntity(
        origin: originStop,
        destination: destinationStop,
        price: price,
      ),
      departsAt: departsAt,
      // L'API n'expose pas encore de gamme de confort (`KORA Confort`,
      // `KORA VIP`...). En attendant, l'écran affiche le véhicule réel
      // via [busLabel] ; cette valeur n'est là que pour les écrans qui
      // n'ont pas encore de repli.
      comfort: BusComfort.classique,
      busLabel: bus?.label,
      price: price,
      seatsTotal: bus?.seatCount ?? 0,
      seatsAvailable: availableSeatsCount,
      // Les services à bord ne figurent pas dans la réponse : mieux vaut
      // une liste vide (l'écran n'affiche alors rien) qu'un Wi-Fi promis
      // par défaut.
      amenities: const [],
    );
  }

  /// Date et heure locales du départ, recomposées depuis les deux champs
  /// de l'API.
  ///
  /// `estimated_arrival_at` n'est pas utilisé ici : voir la note de
  /// [LineModel.toEntity] sur le mélange des fuseaux.
  DateTime? _departsAt() {
    if (departureDate.isEmpty) return null;

    final time = switch (departureTime.length) {
      0 => '00:00:00',
      5 => '$departureTime:00',
      _ => departureTime,
    };

    return DateTime.tryParse('${departureDate}T$time') ??
        DateTime.tryParse(departureDate);
  }
}

/// Couche DATA : le véhicule affecté à un départ.
class BusModel {
  const BusModel({
    required this.uuid,
    required this.registrationNumber,
    required this.brand,
    required this.model,
    required this.seatCount,
  });

  factory BusModel.fromJson(Map<String, dynamic> json) {
    return BusModel(
      uuid: BusJson.string(json['uuid']),
      registrationNumber: BusJson.string(json['registration_number']),
      brand: BusJson.string(json['brand']),
      model: BusJson.string(json['model']),
      seatCount: BusJson.intOrNull(json['seat_count']) ?? 0,
    );
  }

  final String uuid;
  final String registrationNumber;
  final String brand;
  final String model;
  final int seatCount;

  /// `Mercedes O500`, ou `null` si l'API ne dit rien du véhicule.
  String? get label {
    final parts = [brand, model].where((part) => part.isNotEmpty);
    return parts.isEmpty ? null : parts.join(' ');
  }
}

/// Couche DATA : l'aperçu de tarif d'un départ.
class FarePreviewModel {
  const FarePreviewModel({
    required this.amount,
    required this.currency,
    required this.isQuote,
  });

  factory FarePreviewModel.fromJson(Map<String, dynamic> json) {
    return FarePreviewModel(
      // L'API annonce deux montants : `base_amount` est le tarif de la
      // ligne, `final_amount` ce que le passager paiera une fois les
      // règles de remplissage appliquées. C'est ce dernier qui s'affiche
      // — la carte le présente comme « prix final garanti ».
      amount:
          BusJson.intOrNull(json['final_amount']) ??
          BusJson.intOrNull(json['base_amount']) ??
          0,
      currency: BusJson.string(json['currency']),
      isQuote: json['is_quote'] == true,
    );
  }

  final int amount;

  /// Code ISO de la devise (`XOF`).
  final String currency;

  /// Vrai quand le montant reste une estimation susceptible de bouger
  /// d'ici au paiement ; faux quand le serveur le garantit.
  final bool isQuote;

  Price toPrice() => Price(amount, currency: _display(currency));

  /// `XOF` est le code ISO du franc CFA ; les billets et la maquette
  /// écrivent `FCFA`. La conversion se fait ici, pas dans le domaine.
  /// Une devise absente retombe sur `FCFA` : c'est celle de tout le
  /// réseau, et un montant sans devise ne s'affiche pas.
  static String _display(String currency) =>
      currency.isEmpty || currency.toUpperCase() == 'XOF' ? 'FCFA' : currency;
}
