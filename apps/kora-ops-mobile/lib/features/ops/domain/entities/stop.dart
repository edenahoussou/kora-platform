import 'package:equatable/equatable.dart';

/// Un arrêt d'une ligne (voir `OpsDepartureResource.stops`, renvoyé
/// uniquement par `GET /ops/departures/{uuid}` — jamais par la liste).
class Stop extends Equatable {
  const Stop({
    required this.uuid,
    required this.name,
    this.city,
    this.country,
    this.order,
  });

  final String uuid;
  final String name;
  final String? city;
  final String? country;
  final int? order;

  @override
  List<Object?> get props => <Object?>[uuid, name, city, country, order];
}
