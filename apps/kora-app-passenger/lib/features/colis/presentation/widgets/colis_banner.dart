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
    this.icon,
  });

  final String tag;

  /// Le guichet concerné, aligné à droite de la pastille.
  final String caption;

  final String title;
  final String text;
  final KoraTagVariant tagVariant;

  /// Le pictogramme posé sur une tuile dorée, à gauche du bandeau — la
  /// balance d'une pesée, par exemple. La maquette s'en sert pour dire
  /// de quel geste l'écran rend compte, avant même son titre.
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.kora,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (icon case final icon?) ...[
            Container(
              width: 40,
              height: 40,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: AppColors.gold,
                borderRadius: BorderRadius.circular(AppRadius.sm),
              ),
              child: Icon(icon, size: 22, color: AppColors.kora),
            ),
            const SizedBox(width: 12),
          ],
          Expanded(child: _Body(this)),
        ],
      ),
    );
  }
}

/// Le contenu du bandeau : sa pastille, son guichet, son titre, sa
/// phrase. Séparé pour que la tuile dorée puisse se poser à côté sans
/// décaler le texte d'une ligne.
class _Body extends StatelessWidget {
  const _Body(this.banner);

  final ColisBanner banner;

  @override
  Widget build(BuildContext context) {
    final tag = banner.tag;
    final caption = banner.caption;
    final title = banner.title;
    final text = banner.text;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            KoraTag(label: tag, variant: banner.tagVariant),
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
        const SizedBox(height: 10),
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
    this.valueColor,
  });

  final String label;
  final String value;
  final bool isTotal;

  /// La couleur de la valeur, quand elle n'est pas un montant — « Offerte »
  /// se dit en vert sur la maquette, et non dans le bleu des sommes dues.
  final Color? valueColor;

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
            color: valueColor ?? context.koraTitleColor,
            fontSize: isTotal ? 20 : 13,
            fontWeight: isTotal ? FontWeight.w900 : FontWeight.w800,
          ),
        ),
      ],
    );
  }
}
