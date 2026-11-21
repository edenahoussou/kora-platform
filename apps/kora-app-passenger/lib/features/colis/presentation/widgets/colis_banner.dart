import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/title_color.dart';

/// Le bandeau navy qui ouvre les écrans de confirmation du parcours.
///
/// Trois écrans le partagent — pré-enregistrement, devis, paiement — et
/// chacun doit annoncer d'un coup d'œil où en est le dossier. Une
/// pastille, un titre, une phrase.
class ColisBanner extends StatelessWidget {
  const ColisBanner({
    super.key,
    required this.tag,
    required this.caption,
    required this.title,
    required this.text,
    this.tagVariant = KoraTagVariant.gold,
  });

  final String tag;

  /// Le guichet concerné, aligné à droite de la pastille.
  final String caption;

  final String title;
  final String text;
  final KoraTagVariant tagVariant;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.kora,
        borderRadius: BorderRadius.circular(19),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              KoraTag(label: tag, variant: tagVariant),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  caption,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.end,
                  style: AppTextStyles.caption(
                    AppColors.white.withValues(alpha: 0.72),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            title,
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 20,
              height: 1.15,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            text,
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.72),
              fontSize: 12,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }
}

/// Une ligne de montant : son intitulé à gauche, sa valeur à droite.
///
/// Le total se distingue par sa graisse et sa taille, comme au
/// récapitulatif Bus : une addition se relit du bas vers le haut.
class ColisAmountRow extends StatelessWidget {
  const ColisAmountRow({
    super.key,
    required this.label,
    required this.value,
    this.isTotal = false,
  });

  final String label;
  final String value;
  final bool isTotal;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Text(
            label,
            style: isTotal
                ? TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  )
                : AppTextStyles.body(koraColors.textMuted),
          ),
        ),
        const SizedBox(width: 10),
        Text(
          value,
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: isTotal ? 20 : 13,
            fontWeight: isTotal ? FontWeight.w900 : FontWeight.w800,
          ),
        ),
      ],
    );
  }
}
