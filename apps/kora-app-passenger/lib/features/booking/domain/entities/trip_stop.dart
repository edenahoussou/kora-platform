import 'package:equatable/equatable.dart';

/// Couche DOMAIN : un arrêt du **trajet retenu**, nommé et daté.
///
/// Vient de `legs[].trip_stops` (`GET bus/itineraries`). Il dit trois
/// choses que [LineStop] ne disait pas :
///
/// - le nom du **point d'arrêt physique** — « Gare de Dantokpa », celui
///   que le voyageur cherche sur place, et non la ville qui le porte ;
/// - l'heure de passage, que l'app ne pouvait jusqu'ici qu'ignorer ;
/// - le fuseau dans lequel cette heure se lit, pour les lignes qui
///   traversent une frontière horaire.
///
/// Les arrêts arrivent dans l'ordre de circulation réel : un retour qui
/// remonte la ligne est déjà retourné côté serveur. L'app n'a donc plus
/// à deviner le sens ni à découper la ligne entière par correspondance
/// de noms.
class TripStop extends Equatable {
  const TripStop({
    required this.name,
    this.city,
    this.kmFromOrigin,
    this.departureAt,
    this.arrivalAt,
    this.timezone,
  });

  /// Le nom du point physique, ou celui de l'arrêt de ligne à défaut :
  /// `physical_stop` est nul sur les arrêts hérités, pas encore
  /// rattachés à un point. Un arrêt sans point reste un arrêt — le
  /// masquer trouerait la frise au milieu du trajet.
  final String name;

  /// La ville qui porte l'arrêt, affichée en sous-titre. Une gare se
  /// cherche par son nom, mais c'est la ville qui situe l'étape.
  final String? city;

  /// `km_from_origin` : la distance depuis le terminus de départ.
  final double? kmFromOrigin;

  /// Heure de départ de cet arrêt, **telle qu'on la lit sur place**.
  ///
  /// Le serveur date déjà chaque passage dans le fuseau de l'arrêt ; la
  /// couche DATA en garde l'heure murale plutôt que l'instant, pour
  /// qu'un bus annoncé à 07:31 devant la gare s'affiche 07:31 quel que
  /// soit le fuseau du téléphone.
  final DateTime? departureAt;

  /// Heure d'arrivée à cet arrêt, même règle que [departureAt].
  final DateTime? arrivalAt;

  /// Le fuseau de l'arrêt (`Africa/Lome`), quand le serveur le donne.
  final String? timezone;

  /// La ville, seulement quand elle apprend quelque chose : sur le réseau
  /// actuel, beaucoup d'arrêts portent le nom de leur ville, et l'écrire
  /// deux fois de suite n'aide personne.
  String? get subtitle {
    final town = city;
    if (town == null || town.isEmpty) return null;

    return town.toLowerCase() == name.toLowerCase() ? null : town;
  }

  @override
  List<Object?> get props => [
    name,
    city,
    kmFromOrigin,
    departureAt,
    arrivalAt,
    timezone,
  ];
}
