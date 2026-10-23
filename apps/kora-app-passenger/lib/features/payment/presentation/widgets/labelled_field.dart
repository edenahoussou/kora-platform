import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/title_color.dart';

/// Un intitulé au-dessus d'un champ qui n'en porte pas lui-même.
///
/// [KoraInput] gère le sien ; les listes déroulantes et les cases à
/// cocher, non. Sans ce wrapper, deux champs voisins n'auraient pas leur
/// libellé à la même hauteur ni dans la même graisse.
class LabelledField extends StatelessWidget {
  const LabelledField({
    super.key,
    required this.label,
    required this.child,
    this.error,
  });

  final String label;
  final Widget child;

  /// La raison d'un refus, sous le champ et sur toute la largeur.
  ///
  /// Le message appartient au bloc, pas au champ : « Un numéro Côte
  /// d'Ivoire compte 10 chiffres. » ne tient pas sous un champ qui
  /// partage sa ligne avec l'indicatif — il s'y empilait sur trois
  /// lignes et le bouton de l'écran en rognait la fin.
  final String? error;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: 13,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: AppSpacing.sm),
        child,
        if (error != null) ...[
          const SizedBox(height: 6),
          Text(error!, style: AppTextStyles.caption(AppColors.danger)),
        ],
      ],
    );
  }
}
