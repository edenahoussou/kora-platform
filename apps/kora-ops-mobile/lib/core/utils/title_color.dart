import 'package:flutter/material.dart';

import '../../app/theme/app_colors.dart';
import '../../app/theme/kora_colors.dart';

/// Couleur des **titres** de l'app.
///
/// Copie conforme de l'utilitaire `kora-passager` (voir sa docstring) :
/// les titres sont en bleu KORA en thème clair, et retombent sur
/// `koraColors.textPrimary` (blanc) en thème sombre — sans quoi le bleu
/// KORA serait invisible sur le fond navy.
///
/// ```dart
/// Text(title, style: TextStyle(color: context.koraTitleColor))
/// ```
extension KoraTitleColor on BuildContext {
  Color get koraTitleColor => Theme.of(this).brightness == Brightness.dark
      ? koraColors.textPrimary
      : AppColors.kora;
}
