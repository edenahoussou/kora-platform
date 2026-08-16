import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';
import '../../app/theme/app_radius.dart';
import '../../app/theme/app_spacing.dart';
import '../../app/theme/kora_colors.dart';

/// Enveloppe shimmer commune — colore l'effet selon `context.koraColors`
/// pour rester cohérent en clair comme en sombre. Toujours préférer les
/// widgets `Skeleton*` ci-dessous à un usage direct de `Shimmer.fromColors`
/// dans un écran.
class KoraShimmer extends StatelessWidget {
  const KoraShimmer({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;
    final bool isDark = Theme.of(context).brightness == Brightness.dark;

    return Shimmer.fromColors(
      baseColor:
          isDark ? koraColors.surface : koraColors.line.withValues(alpha: 1),
      highlightColor: isDark
          ? koraColors.textMuted.withValues(alpha: 0.2)
          : koraColors.background,
      period: const Duration(milliseconds: 1400),
      child: child,
    );
  }
}

/// Bloc rectangulaire de base pour composer un squelette (une ligne de
/// texte, une pastille, une vignette...).
class SkeletonBox extends StatelessWidget {
  const SkeletonBox({
    required this.width,
    required this.height,
    this.borderRadius = AppRadius.sm,
    super.key,
  });

  final double width;
  final double height;
  final double borderRadius;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(borderRadius),
      ),
    );
  }
}

/// Squelette d'une ligne de liste type `PassengerListTile` (avatar rond +
/// deux lignes de texte + pastille de statut à droite).
class SkeletonListTile extends StatelessWidget {
  const SkeletonListTile({super.key});

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Row(
        children: <Widget>[
          SkeletonBox(width: 44, height: 44, borderRadius: AppRadius.pill),
          SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                SkeletonBox(width: 140, height: 14),
                SizedBox(height: 8),
                SkeletonBox(width: 90, height: 11, borderRadius: AppRadius.sm),
              ],
            ),
          ),
          SizedBox(width: AppSpacing.md),
          SkeletonBox(width: 64, height: 22, borderRadius: AppRadius.pill),
        ],
      ),
    );
  }
}

/// Liste de [SkeletonListTile] enveloppée dans un seul [KoraShimmer] —
/// utilisée pour Manifeste, Descente anticipée, Synchro pendant le
/// chargement des données mockées.
class SkeletonList extends StatelessWidget {
  const SkeletonList({this.itemCount = 6, super.key});

  final int itemCount;

  @override
  Widget build(BuildContext context) {
    return KoraShimmer(
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
        itemCount: itemCount,
        itemBuilder: (BuildContext context, int index) =>
            const SkeletonListTile(),
      ),
    );
  }
}

/// Squelette d'une carte (KPI de l'accueil, résumé de départ...).
class SkeletonCard extends StatelessWidget {
  const SkeletonCard({this.height = 90, super.key});

  final double height;

  @override
  Widget build(BuildContext context) {
    return KoraShimmer(
      child: Container(
        width: double.infinity,
        height: height,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
      ),
    );
  }
}
