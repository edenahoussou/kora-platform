import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/title_color.dart';

/// `.price-assurance` : le rappel « Prix affichés définitifs » en tête des
/// résultats.
///
/// La maquette pose ce bloc dans son markup mais ne lui donne aucune règle
/// CSS — il s'y affiche en texte brut. On le rend donc comme une carte
/// discrète, cohérente avec le reste de l'écran.
class PriceAssuranceBanner extends StatelessWidget {
  const PriceAssuranceBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return KoraCard(
      variant: KoraCardVariant.mist,
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Row(
        children: [
          Container(
            width: 26,
            height: 26,
            alignment: Alignment.center,
            decoration: const BoxDecoration(
              color: AppColors.gold,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.check_rounded,
              size: 16,
              color: AppColors.kora,
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Prix affichés définitifs',
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Les éventuelles règles de remplissage sont déjà incluses.',
                  style: TextStyle(color: koraColors.textMuted, fontSize: 10),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
