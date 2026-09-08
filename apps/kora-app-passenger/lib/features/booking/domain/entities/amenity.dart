import 'package:equatable/equatable.dart';

/// Couche DOMAIN : un service à bord (`wifi`, `air_conditioning`, `usb_charging`…).
///
/// Le serveur en envoie deux choses utiles : un [code] stable, et un
/// [label] déjà rédigé. C'est [code] qui fait autorité pour l'affichage —
/// il permet de choisir un pictogramme et un libellé dans la langue du
/// voyageur. Le [label] du serveur, lui, est rédigé en français : il ne
/// sert que de **repli** pour un code que l'app ne connaît pas encore.
///
/// Sans ce repli, un équipement ajouté côté back après la sortie de cette
/// version s'afficherait au voyageur sous sa forme brute
/// (`reclining_seats`). Avec, il s'affiche « Sièges inclinables » — en
/// français, mais lisible.
class Amenity extends Equatable {
  const Amenity({required this.code, this.label = ''});

  /// Identifiant stable côté serveur, en `snake_case`.
  final String code;

  /// Libellé rédigé par le serveur, en français. Peut être vide.
  final String label;

  @override
  List<Object?> get props => [code, label];
}
