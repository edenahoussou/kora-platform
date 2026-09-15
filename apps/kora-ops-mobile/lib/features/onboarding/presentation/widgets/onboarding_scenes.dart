import 'package:flutter/material.dart';

import '../../../../app/theme/app_colors.dart';
import '../../../splash/presentation/widgets/kora_wordmark.dart';

/// Les trois scènes navy de l'onboarding OPS.
///
/// Copie conforme du décor de `kora-passager`
/// (`onboarding_scenes.dart` : `_Backdrop`, `_ScenePill`) — seuls
/// l'icône, le pictogramme central et les textes changent, pour coller au
/// métier de l'agent plutôt qu'à celui du voyageur : la tournée du jour,
/// le scan à l'embarquement, la synchronisation hors-ligne.

/// Le décor commun : un cercle filaire et le trait doré incliné.
class _Backdrop extends StatelessWidget {
  const _Backdrop({
    this.circle,
    this.slash = true,
    this.slashTop,
    required this.child,
  });

  final Alignment? circle;
  final bool slash;
  final double? slashTop;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Stack(
      clipBehavior: Clip.none,
      children: [
        if (circle != null)
          Positioned(
            right: circle == Alignment.topRight ? -120 : null,
            left: circle == Alignment.bottomLeft ? -130 : null,
            top: circle == Alignment.topRight ? 20 : null,
            bottom: circle == Alignment.bottomLeft ? -30 : null,
            child: Container(
              width: 250,
              height: 250,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: AppColors.white.withValues(alpha: 0.10),
                  width: 2,
                ),
              ),
            ),
          ),
        if (slash)
          Positioned(
            right: -120,
            top: slashTop ?? 190,
            child: Transform.rotate(
              angle: -24 * 3.1415926535 / 180,
              child: Container(
                width: 430,
                height: 7,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(999),
                  gradient: const LinearGradient(
                    colors: [
                      Colors.transparent,
                      AppColors.gold,
                      AppColors.gold,
                    ],
                    stops: [0, 0.2, 1],
                  ),
                ),
              ),
            ),
          ),
        Positioned.fill(child: child),
      ],
    );
  }
}

/// La pastille d'information, en bas de chaque scène.
class _ScenePill extends StatelessWidget {
  const _ScenePill({
    required this.glyph,
    required this.title,
    required this.lead,
  });

  final IconData glyph;
  final String title;
  final String lead;

  @override
  Widget build(BuildContext context) {
    return Positioned(
      left: 20,
      right: 20,
      bottom: 22,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 11),
        decoration: BoxDecoration(
          color: AppColors.white.withValues(alpha: 0.10),
          borderRadius: BorderRadius.circular(17),
          border: Border.all(color: AppColors.white.withValues(alpha: 0.16)),
        ),
        child: Row(
          children: [
            Container(
              width: 32,
              height: 32,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: AppColors.gold,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(glyph, size: 17, color: AppColors.kora),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: AppColors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    lead,
                    style: TextStyle(
                      color: AppColors.white.withValues(alpha: 0.6),
                      fontSize: 10,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Premier temps : la tournée du jour, résumée par une course assignée.
class OnboardingTourneeScene extends StatelessWidget {
  const OnboardingTourneeScene({super.key});

  @override
  Widget build(BuildContext context) {
    return const _Backdrop(
      circle: Alignment.topRight,
      slashTop: 196,
      child: Stack(
        children: [
          Positioned(left: 20, top: 26, child: KoraWordmark()),
          _ScenePill(
            glyph: Icons.route_rounded,
            title: 'Cotonou → Abidjan',
            lead: 'Départ 20:00 · 32 places',
          ),
        ],
      ),
    );
  }
}

/// Deuxième temps : le scan à l'embarquement, avec un billet validé.
class OnboardingScanScene extends StatelessWidget {
  const OnboardingScanScene({super.key});

  @override
  Widget build(BuildContext context) {
    return const _Backdrop(
      circle: Alignment.bottomLeft,
      slash: false,
      child: Stack(
        children: [
          Align(
            alignment: Alignment(0, -0.18),
            child: _ScanFrame(),
          ),
          _ScenePill(
            glyph: Icons.qr_code_scanner_rounded,
            title: 'Billet valide',
            lead: 'Passager embarqué',
          ),
        ],
      ),
    );
  }
}

/// Le viseur de scan, réduit à sa silhouette — mêmes proportions que le
/// plan de sièges du passager (128 de large), pour garder le même rythme
/// visuel d'un slide à l'autre.
class _ScanFrame extends StatelessWidget {
  const _ScanFrame();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 128,
      height: 128,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Container(
            width: 96,
            height: 96,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: AppColors.white.withValues(alpha: 0.18),
                width: 2,
              ),
            ),
          ),
          for (final alignment in [
            Alignment.topLeft,
            Alignment.topRight,
            Alignment.bottomLeft,
            Alignment.bottomRight,
          ])
            Align(
              alignment: alignment,
              child: Container(
                width: 22,
                height: 22,
                margin: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  border: Border(
                    top: alignment.y < 0
                        ? const BorderSide(color: AppColors.gold, width: 3)
                        : BorderSide.none,
                    bottom: alignment.y > 0
                        ? const BorderSide(color: AppColors.gold, width: 3)
                        : BorderSide.none,
                    left: alignment.x < 0
                        ? const BorderSide(color: AppColors.gold, width: 3)
                        : BorderSide.none,
                    right: alignment.x > 0
                        ? const BorderSide(color: AppColors.gold, width: 3)
                        : BorderSide.none,
                  ),
                  borderRadius: BorderRadius.circular(4),
                ),
              ),
            ),
          const Icon(
            Icons.check_circle_rounded,
            color: AppColors.gold,
            size: 30,
          ),
        ],
      ),
    );
  }
}

/// Troisième temps : la synchronisation, prête même hors-ligne.
class OnboardingSynchroScene extends StatelessWidget {
  const OnboardingSynchroScene({super.key});

  @override
  Widget build(BuildContext context) {
    return const _Backdrop(
      slashTop: 176,
      child: Stack(
        children: [
          Align(
            alignment: Alignment(0, -0.2),
            child: _SyncBadge(),
          ),
          _ScenePill(
            glyph: Icons.sync_rounded,
            title: 'File d\'attente vidée',
            lead: 'Synchronisé au retour du réseau',
          ),
        ],
      ),
    );
  }
}

/// Le badge de synchronisation : un nuage barré qui redevient plein,
/// silhouette du bandeau `SyncStatusBanner` de l'app.
class _SyncBadge extends StatelessWidget {
  const _SyncBadge();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 96,
      height: 96,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(14),
      ),
      child: const Icon(
        Icons.cloud_sync_rounded,
        color: AppColors.kora,
        size: 46,
      ),
    );
  }
}
