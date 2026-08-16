import 'package:equatable/equatable.dart';

/// Un départ (course) que l'agent peut avoir à gérer — sélectionné depuis
/// [SelectionDepartPage], affiché en résumé sur `AccueilPage` et utilisé
/// pour filtrer le Manifeste / la Descente anticipée.
class Depart extends Equatable {
  const Depart({
    required this.id,
    required this.route,
    required this.date,
    required this.heure,
    required this.vehiculeImmat,
    required this.totalPlaces,
    required this.placesOccupees,
  });

  final String id;

  /// Ex : "Cotonou → Porto-Novo"
  final String route;

  /// Ex : "14/08/2026"
  final String date;

  /// Ex : "07:30"
  final String heure;

  /// Ex : "AB-1234-RB"
  final String vehiculeImmat;

  final int totalPlaces;
  final int placesOccupees;

  @override
  List<Object?> get props => <Object?>[
        id,
        route,
        date,
        heure,
        vehiculeImmat,
        totalPlaces,
        placesOccupees
      ];
}
