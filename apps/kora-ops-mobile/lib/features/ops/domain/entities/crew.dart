import 'package:equatable/equatable.dart';

/// Un membre d'équipage — nom seul, suffisant pour l'affichage (voir
/// `OpsDepartureResource.crew`).
class CrewMember extends Equatable {
  const CrewMember({required this.uuid, required this.name});

  final String uuid;
  final String name;

  @override
  List<Object?> get props => <Object?>[uuid, name];
}

/// Équipage d'un départ (voir `OpsDepartureResource.crew`) — chaque rôle
/// est optionnel (`null` si non affecté).
class Crew extends Equatable {
  const Crew({
    this.driver,
    this.secondaryDriver,
    this.conductor,
    this.mechanic,
  });

  final CrewMember? driver;
  final CrewMember? secondaryDriver;
  final CrewMember? conductor;
  final CrewMember? mechanic;

  @override
  List<Object?> get props =>
      <Object?>[driver, secondaryDriver, conductor, mechanic];
}
