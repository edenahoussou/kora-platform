import 'package:flutter/material.dart';

import '../kora_tag.dart';

/// À quel point un départ est rempli, du point de vue du voyageur.
///
/// Trois niveaux et pas davantage : le badge sert à décider *maintenant ou
/// plus tard*, pas à connaître le taux de remplissage au pourcent près. Un
/// dégradé continu de couleurs obligerait à comparer deux nuances entre
/// deux cartes pour comprendre laquelle presse le plus.
///
/// Les seuils portent sur les places **libres**, pas sur les places
/// vendues : c'est ce qui reste qui intéresse celui qui réserve.
enum KoraSeatsLevel {
  /// Au moins 30 % du bus est libre : rien ne presse.
  plenty(KoraTagVariant.success),

  /// Entre 10 % et 30 % : ça se remplit.
  limited(KoraTagVariant.gold),

  /// Moins de 10 % : dernières places.
  last(KoraTagVariant.danger);

  const KoraSeatsLevel(this.variant);

  /// La variante de [KoraTag] qui porte la couleur du niveau.
  final KoraTagVariant variant;

  /// Le niveau correspondant à [available] places libres sur [total].
  ///
  /// Un [total] nul ou négatif (capacité inconnue — l'API ne renvoie pas
  /// toujours le véhicule) rend [plenty] : sans dénominateur il n'y a
  /// aucun remplissage à qualifier, et alarmer au hasard serait pire que
  /// de ne rien dire.
  static KoraSeatsLevel of({required int available, required int total}) {
    if (total <= 0) return plenty;

    final free = available / total;
    if (free >= 0.30) return plenty;
    if (free >= 0.10) return limited;
    return last;
  }
}

/// Le badge « 12 places » des cartes de départ, coloré selon le
/// remplissage (vert / orange / rouge — voir [KoraSeatsLevel]).
///
/// Il ne gère pas le cas « complet » : un départ sans place n'annonce pas
/// « 0 places » mais un badge « Complet », que porte l'appelant
/// ([DepartureCard]).
///
/// Exemple d'utilisation :
/// ```dart
/// KoraSeatsBadge(available: 12, total: 55)
/// ```
class KoraSeatsBadge extends StatelessWidget {
  const KoraSeatsBadge({
    super.key,
    required this.available,
    required this.total,
  });

  final int available;

  /// Capacité du véhicule. `0` quand elle est inconnue : le badge reste
  /// affiché, en vert, sans prétendre à une urgence qu'il ne peut pas
  /// mesurer.
  final int total;

  @override
  Widget build(BuildContext context) {
    return KoraTag(
      label: available > 1 ? '$available places' : '$available place',
      variant: KoraSeatsLevel.of(available: available, total: total).variant,
    );
  }
}
