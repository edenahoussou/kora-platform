import 'package:flutter/material.dart';

/// Badge Material 3 (petit indicateur superposé sur une icône/avatar) —
/// équivalent des points de notification du prototype
/// (`.icon-btn .dot`, `.notification-button::after`). Wrapper direct
/// autour du widget [Badge] de Material 3 : la couleur vient du thème
/// (`ThemeData.badgeTheme`, voir `AppTheme`), jamais d'une valeur en dur
/// ici — pour changer la couleur du badge dans toute l'app, on modifie
/// `AppTheme.badgeTheme`, pas ce fichier.
///
/// À ne pas confondre avec [KoraTag] : [KoraTag] est un badge de statut
/// texte inline ("Livré", "En cours"), [KoraBadge] est un point/compteur
/// superposé sur un autre widget (icône, avatar...).
///
/// Exemple d'utilisation :
/// ```dart
/// // Simple point (notification non lue, sans compteur) :
/// KoraBadge(
///   child: KoraIconButton(icon: Icons.notifications_outlined, onTap: () {}),
/// )
///
/// // Avec compteur (troncature "999+" automatique au-delà de 999) :
/// KoraBadge(
///   count: 3,
///   child: Icon(Icons.mail_outline),
/// )
/// ```
class KoraBadge extends StatelessWidget {
  const KoraBadge({
    super.key,
    required this.child,
    this.count,
    this.isVisible = true,
  });

  final Widget child;

  /// `null` = simple point, sans chiffre. Sinon, affiché avec troncature
  /// "999+" au-delà de 999 (comportement standard de [Badge.count]).
  final int? count;

  /// Permet de masquer le badge sans démonter le widget (ex: passer à
  /// `false` une fois la notification lue), avec l'animation d'apparition
  /// standard de Material 3.
  final bool isVisible;

  @override
  Widget build(BuildContext context) {
    if (count == null) {
      return Badge(isLabelVisible: isVisible, child: child);
    }

    return Badge.count(count: count!, isLabelVisible: isVisible, child: child);
  }
}
