import 'package:flutter/material.dart';

import '../../colors/app_colors.dart';
import '../../colors/kora_colors.dart';
import '../../dimens/app_dimens.dart';
import '../kora_skeleton.dart';

/// Le squelette de l'accueil (`home` du prototype), de la carte de
/// recherche au rappel de départ.
///
/// Ne contient pas l'en-tête bleu : le logo, la salutation et le titre ne
/// dépendent d'aucune donnée chargée, ils sont affichés tout de suite.
/// Esquisser ce qu'on sait déjà afficher ne fait que retarder la page.
///
/// Chaque bloc reprend la géométrie de ce qu'il remplace — rayon, hauteur,
/// nombre de colonnes — pour que le contenu s'y substitue sans décaler le
/// reste de l'écran.
///
/// Exemple d'utilisation :
/// ```dart
/// const HomeSkeleton()
///
/// // L'utilisateur n'a encore aucun billet : inutile de promettre une
/// // carte « Prochain voyage » qui n'arrivera pas.
/// const HomeSkeleton(showNextTrip: false)
/// ```
class HomeSkeleton extends StatelessWidget {
  const HomeSkeleton({super.key, this.showNextTrip = true});

  /// Esquisse la carte « Prochain voyage » et son rappel. À désactiver
  /// quand on sait déjà qu'il n'y a pas de voyage à venir.
  final bool showNextTrip;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const _SearchCardSkeleton(),
        const SizedBox(height: AppSpacing.xl),
        const _SectionTitleSkeleton(width: 128),
        const SizedBox(height: AppSpacing.md),
        const _QuickGridSkeleton(),
        const SizedBox(height: AppSpacing.lg),
        // `.home-ride-banner` : bandeau navy pleine largeur.
        const KoraSkeleton(height: 92, radius: 19),
        if (showNextTrip) ...[
          const SizedBox(height: AppSpacing.xl),
          const _SectionTitleSkeleton(width: 142, withAction: true),
          const SizedBox(height: AppSpacing.md),
          // `.card.navy` du prochain voyage.
          const KoraSkeleton(height: 132, radius: 19),
          const SizedBox(height: 10),
          // `.travel-reminder { min-height: 58px; border-radius: 17px }`.
          const KoraSkeleton(height: 58, radius: 17),
        ],
      ],
    );
  }
}

/// `.floating-search` : le bandeau bleu du libellé, la carte blanche des
/// deux villes, la bande Date/Passagers, puis le bouton de recherche.
///
/// Reproduit en un seul bloc blanc — c'est ainsi que la carte se lit à
/// l'écran — avec ses éléments internes esquissés à leur place.
class _SearchCardSkeleton extends StatelessWidget {
  const _SearchCardSkeleton();

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(19),
        boxShadow: koraColors.cardShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // `.search-card-label` : bandeau dégradé, arrondi en haut
          // seulement et légèrement plus étroit que la carte.
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 10),
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 22),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [AppColors.digital, Color(0xFF0069D7)],
              ),
              borderRadius: BorderRadius.vertical(
                bottom: Radius.circular(AppRadius.lg),
              ),
            ),
            child: const Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                _OnBlueSkeleton(width: 152, height: 11),
                _OnBlueSkeleton(width: 58, height: 9),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(15, 12, 15, 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Départ et arrivée : la pastille, puis le libellé
                // au-dessus de la ville.
                const _RouteFieldSkeleton(),
                Divider(height: 17, color: koraColors.line),
                const _RouteFieldSkeleton(),
                const SizedBox(height: AppSpacing.lg),
                // `.home-search-meta` : Date | Passagers, séparés par un
                // trait vertical.
                Row(
                  children: [
                    const Expanded(child: _MetaFieldSkeleton()),
                    Container(width: 1, height: 34, color: koraColors.line),
                    const Expanded(child: _MetaFieldSkeleton()),
                  ],
                ),
                const SizedBox(height: AppSpacing.lg),
                // `.home-search-button` : bouton pleine largeur, 50px.
                const KoraSkeleton(height: 50, radius: AppRadius.md),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// `.route-field` : pastille ronde, puis le libellé et la ville.
class _RouteFieldSkeleton extends StatelessWidget {
  const _RouteFieldSkeleton();

  @override
  Widget build(BuildContext context) {
    return const Row(
      children: [
        KoraSkeleton(width: 13, height: 13, radius: AppRadius.pill),
        SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              KoraSkeleton(width: 52, height: 8),
              SizedBox(height: 6),
              KoraSkeleton(width: 148, height: 13),
            ],
          ),
        ),
      ],
    );
  }
}

/// Une moitié de la bande Date/Passagers : le libellé, puis la valeur.
class _MetaFieldSkeleton extends StatelessWidget {
  const _MetaFieldSkeleton();

  @override
  Widget build(BuildContext context) {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        KoraSkeleton(width: 46, height: 8),
        SizedBox(height: 7),
        KoraSkeleton(width: 96, height: 12),
      ],
    );
  }
}

/// `.home-quick-grid` : quatre tuiles de même largeur, 76px de haut.
class _QuickGridSkeleton extends StatelessWidget {
  const _QuickGridSkeleton();

  @override
  Widget build(BuildContext context) {
    return const Row(
      children: [
        Expanded(child: KoraSkeleton(height: 76, radius: 16)),
        SizedBox(width: AppSpacing.sm),
        Expanded(child: KoraSkeleton(height: 76, radius: 16)),
        SizedBox(width: AppSpacing.sm),
        Expanded(child: KoraSkeleton(height: 76, radius: 16)),
        SizedBox(width: AppSpacing.sm),
        Expanded(child: KoraSkeleton(height: 76, radius: 16)),
      ],
    );
  }
}

/// `.section-title` : le titre de section, avec ou sans lien à droite.
class _SectionTitleSkeleton extends StatelessWidget {
  const _SectionTitleSkeleton({required this.width, this.withAction = false});

  final double width;
  final bool withAction;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        KoraSkeleton(width: width, height: 16),
        if (withAction) const KoraSkeleton(width: 58, height: 11),
      ],
    );
  }
}

/// Un bloc posé sur le bandeau bleu du libellé : le blanc translucide y
/// remplace le gris habituel, invisible sur cette couleur.
class _OnBlueSkeleton extends StatelessWidget {
  const _OnBlueSkeleton({required this.width, required this.height});

  final double width;
  final double height;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: AppColors.white.withValues(alpha: 0.28),
        borderRadius: BorderRadius.circular(6),
      ),
    );
  }
}
