import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/title_color.dart';

/// Un intitulé au-dessus d'un champ qui n'en porte pas lui-même.
///
/// [KoraInput] gère le sien ; les listes déroulantes et les cases à
/// cocher, non. Sans ce wrapper, deux champs voisins n'auraient pas leur
/// libellé à la même hauteur ni dans la même graisse.
class LabelledField extends StatelessWidget {
  const LabelledField({super.key, required this.label, required this.child});

  final String label;
  final Widget child;

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
      ],
    );
  }
}
