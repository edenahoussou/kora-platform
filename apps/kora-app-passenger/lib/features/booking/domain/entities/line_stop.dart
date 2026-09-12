import 'package:equatable/equatable.dart';

/// Couche DOMAIN : un arrêt desservi par une ligne, dans l'ordre du
/// trajet.
///
/// Vient de `GET bus/lines/{uuid}`, qui rend la ligne avec ses arrêts
/// actifs triés par `stop_order`. À ne pas confondre avec [TripSegment],
/// qui découpe un trajet en tronçons tarifés : ici il ne s'agit que de
/// dire où le bus passe.
///
/// **Le serveur ne publie aucune heure de passage** — seulement la
/// distance depuis l'origine. L'écran n'en invente donc pas : il date le
/// premier et le dernier arrêt, qu'il connaît par le départ, et laisse
/// les autres à leur kilométrage.
class LineStop extends Equatable {
  const LineStop({
    required this.order,
    required this.name,
    this.city,
    this.kmFromOrigin,
  });

  /// `stop_order` : le rang dans le trajet, à partir de 1.
  final int order;

  /// Le nom de l'arrêt physique (`Gare de Bohicon`).
  final String name;

  /// La ville qui le porte. Le plus souvent identique à [name] sur le
  /// réseau actuel, où chaque ville n'a qu'un arrêt.
  final String? city;

  /// `km_from_origin` : la distance depuis le terminus de départ.
  final double? kmFromOrigin;

  /// Ce qu'on affiche : la ville quand elle est connue, l'arrêt sinon.
  String get label => (city == null || city!.isEmpty) ? name : city!;

  @override
  List<Object?> get props => [order, name, city, kmFromOrigin];
}
