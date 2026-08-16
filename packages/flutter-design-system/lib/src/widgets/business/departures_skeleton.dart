import 'package:flutter/material.dart';

import '../../colors/app_colors.dart';
import '../../dimens/app_dimens.dart';
import '../kora_skeleton.dart';

/// Le fantôme d'une [DepartureCard] pendant la recherche.
///
/// Reprend sa géométrie exacte — même fond blanc, même padding, même
/// rayon, même ombre, et un bloc à l'emplacement de chaque information —
/// pour que la carte réelle se substitue au fantôme sans que la page
/// bouge. Les trois `.skeleton` génériques du prototype annonçaient un
/// simple bloc de 86px : la liste sautait au moment du remplissage.
///
/// Fond blanc dans les deux thèmes, comme [DepartureCard] elle-même.
class DepartureCardSkeleton extends StatelessWidget {
  const DepartureCardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        boxShadow: AppShadows.card,
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          // `.trip-head` : badge véhicule à gauche, places à droite.
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              KoraSkeleton(width: 104, height: 22, radius: 8, onWhite: true),
              KoraSkeleton(width: 76, height: 22, radius: 8, onWhite: true),
            ],
          ),
          SizedBox(height: AppSpacing.lg),
          // `.trip-route` : heure + ville de part et d'autre de la ligne.
          Row(
            children: [
              _TimePointSkeleton(),
              Expanded(
                child: Padding(
                  padding: EdgeInsets.symmetric(horizontal: AppSpacing.md),
                  child: Column(
                    children: [
                      KoraSkeleton(width: 58, height: 9, onWhite: true),
                      SizedBox(height: 6),
                      KoraSkeleton(height: 2, radius: 2, onWhite: true),
                    ],
                  ),
                ),
              ),
              _TimePointSkeleton(alignEnd: true),
            ],
          ),
          SizedBox(height: AppSpacing.lg),
          // `.trip-foot` : services à gauche, prix à droite.
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              KoraSkeleton(width: 138, height: 9, onWhite: true),
              KoraSkeleton(width: 82, height: 17, onWhite: true),
            ],
          ),
        ],
      ),
    );
  }
}

/// Une heure (18px) surmontant sa ville (9px), comme `_TimePoint`.
class _TimePointSkeleton extends StatelessWidget {
  const _TimePointSkeleton({this.alignEnd = false});

  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: alignEnd
          ? CrossAxisAlignment.end
          : CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: const [
        KoraSkeleton(width: 54, height: 18, onWhite: true),
        SizedBox(height: 4),
        KoraSkeleton(width: 42, height: 9, onWhite: true),
      ],
    );
  }
}

/// Le squelette complet de l'écran de résultats (`results` du prototype) :
/// bandeau de garantie des prix, filtres, puis [count] cartes de départ.
///
/// Ne contient pas l'en-tête de l'écran : celui-ci affiche déjà le trajet
/// et la date cherchés, qui sont connus avant la réponse — les masquer
/// derrière un fantôme ferait perdre à l'utilisateur ce qu'il vient de
/// saisir.
///
/// Exemple d'utilisation :
/// ```dart
/// const DeparturesSkeleton()          // 3 cartes, comme le prototype
/// const DeparturesSkeleton(count: 5)  // liste plus longue
/// ```
class DeparturesSkeleton extends StatelessWidget {
  const DeparturesSkeleton({super.key, this.count = 3});

  /// Nombre de cartes esquissées. Trois par défaut : assez pour remplir
  /// l'écran, pas au point de promettre une liste qui n'arrivera
  /// peut-être pas.
  final int count;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        // `.price-assurance` : pastille + deux lignes de texte.
        const KoraSkeleton(height: 58, radius: AppRadius.lg),
        const SizedBox(height: AppSpacing.md),
        // `.row` des trois filtres (`Heure ⌄`, `Prix ⌄`, `Services`).
        const Row(
          children: [
            KoraSkeleton(width: 74, height: 30, radius: AppRadius.pill),
            SizedBox(width: AppSpacing.sm),
            KoraSkeleton(width: 66, height: 30, radius: AppRadius.pill),
            SizedBox(width: AppSpacing.sm),
            KoraSkeleton(width: 88, height: 30, radius: AppRadius.pill),
          ],
        ),
        const SizedBox(height: AppSpacing.lg),
        for (var i = 0; i < count; i++) ...[
          if (i != 0) const SizedBox(height: 10),
          const DepartureCardSkeleton(),
        ],
      ],
    );
  }
}
