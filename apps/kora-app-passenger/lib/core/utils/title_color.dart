import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

/// Couleur des **titres** de l'app.
///
/// La maquette distingue deux couleurs de texte que le design system ne
/// modélise pas séparément :
/// - les titres sont en bleu KORA (`.app-header h2`, `.section-title h3`,
///   `.home-quick-item`, toutes en `color: var(--kora)`) ;
/// - le texte courant est en graphite, ce que porte déjà
///   `KoraColors.textPrimary`.
///
/// Utiliser `textPrimary` pour un titre le rend presque noir en thème
/// clair, au lieu du bleu de la marque. En thème sombre en revanche, le
/// bleu KORA serait invisible sur le fond navy : `textPrimary` (blanc) y
/// reste le bon choix.
///
/// ```dart
/// Text(title, style: AppTextStyles.heading2(context.koraTitleColor))
/// ```
///
/// À supprimer si le design system finit par exposer ce jeton lui-même
/// (un `KoraColors.textTitle`), ce qui en ferait le bon endroit — il
/// profiterait alors aussi à OPS Mobile.
extension KoraTitleColor on BuildContext {
  Color get koraTitleColor => Theme.of(this).brightness == Brightness.dark
      ? koraColors.textPrimary
      : AppColors.kora;
}
