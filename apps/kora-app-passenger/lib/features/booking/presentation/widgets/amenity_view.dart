import 'package:flutter/material.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../domain/entities/amenity.dart';

/// Comment un [Amenity] se montre : son pictogramme et son libellé.
///
/// Le serveur décide **quels** services existent ; l'app décide seulement
/// comment les dessiner. D'où la séparation : une liste de codes ici, et
/// aucune règle métier — un équipement inconnu ne fait pas d'erreur, il
/// prend l'icône neutre et le libellé que le serveur a rédigé.
extension AmenityView on Amenity {
  /// Pictogramme associé au code, ou une pastille neutre si l'app ne
  /// connaît pas ce service.
  IconData get icon => _icons[code] ?? Icons.check_circle_outline_rounded;

  /// Libellé dans la langue du voyageur.
  ///
  /// Trois niveaux, dans cet ordre : la traduction de l'app, puis le
  /// libellé rédigé par le serveur (en français), puis le code lui-même.
  /// Le dernier n'est pas beau, mais il ne survient que si le serveur
  /// envoie un code sans libellé — et vaut mieux qu'une tuile vide.
  ///
  /// Nommé `displayLabel` et non `label` : une extension ne peut pas
  /// masquer un champ de l'entité, et `Amenity.label` porte déjà le texte
  /// brut du serveur.
  String displayLabel(BuildContext context) {
    final translated = context.l10n.maybeTranslate('amenity_$code');
    if (translated != null) return translated;

    return label.isNotEmpty ? label : code;
  }

  static const Map<String, IconData> _icons = {
    // Confort
    'wifi': Icons.wifi_rounded,
    'air_conditioning': Icons.ac_unit_rounded,
    'reclining_seats': Icons.airline_seat_recline_extra_rounded,
    'extra_legroom': Icons.open_in_full_rounded,
    'individual_seat': Icons.event_seat_rounded,
    'headrest': Icons.airline_seat_recline_normal_rounded,
    'premium_seat': Icons.star_rounded,
    'blanket': Icons.bed_rounded,
    'pillow': Icons.nightlight_round,
    // Énergie et divertissement
    'usb_charging': Icons.usb_rounded,
    'power_outlet': Icons.power_rounded,
    'reading_light': Icons.lightbulb_outline_rounded,
    'tv_screen': Icons.tv_rounded,
    'entertainment': Icons.play_circle_outline_rounded,
    'headphones': Icons.headphones_rounded,
    // À bord
    'onboard_toilet': Icons.wc_rounded,
    'onboard_snack': Icons.local_cafe_rounded,
    'bottled_water': Icons.water_drop_rounded,
    'meal_service': Icons.restaurant_rounded,
    // Bagages
    'priority_luggage': Icons.work_outline_rounded,
    'luggage_storage': Icons.luggage_rounded,
    'extra_luggage': Icons.add_shopping_cart_rounded,
    // Sécurité
    'seat_belt': Icons.verified_user_outlined,
    'gps_tracking': Icons.location_on_rounded,
    'security_camera': Icons.videocam_rounded,
    // Accessibilité
    'wheelchair_accessible': Icons.accessible_rounded,
    'accessible_seat': Icons.accessible_forward_rounded,
    'priority_boarding': Icons.login_rounded,
  };
}
