import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import 'kora_wordmark.dart';

/// Les trois scènes navy de l'onboarding.
///
/// Chacune montre l'écran dont parle son slide, réduit à sa silhouette :
/// la carte de départ, le plan des sièges, le billet. Une illustration
/// générique aurait été plus jolie et moins utile — ici, l'utilisateur
/// reconnaît l'écran quand il y arrive pour de bon.

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

/// Premier temps : la recherche, résumée par une liaison et son départ.
class OnboardingSearchScene extends StatelessWidget {
  const OnboardingSearchScene({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return _Backdrop(
      circle: Alignment.topRight,
      slashTop: 196,
      child: Stack(
        children: [
          const Positioned(left: 20, top: 26, child: KoraWordmark()),
          _ScenePill(
            glyph: Icons.my_location_rounded,
            title: l10n.onboardingSearchPill,
            lead: l10n.onboardingSearchPillLead,
          ),
        ],
      ),
    );
  }
}

/// Deuxième temps : le plan des sièges, avec une place retenue.
class OnboardingSeatsScene extends StatelessWidget {
  const OnboardingSeatsScene({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    // Trois rangées de quatre : assez pour lire un plan de bus, assez peu
    // pour rester une silhouette.
    const taken = {2, 8};
    const chosen = 5;

    return _Backdrop(
      circle: Alignment.bottomLeft,
      slash: false,
      child: Stack(
        children: [
          Align(
            alignment: const Alignment(0, -0.18),
            // 4 × 26 + 3 × 8 : sans cette largeur le `Wrap` étale les
            // douze sièges sur une seule ligne, et le plan ne ressemble
            // plus à un bus.
            child: SizedBox(
              width: 128,
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (var i = 0; i < 12; i++)
                    Container(
                      width: 26,
                      height: 26,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(7),
                        color: i == chosen
                            ? AppColors.gold
                            : AppColors.white.withValues(
                                alpha: taken.contains(i) ? 0.07 : 0.16,
                              ),
                      ),
                    ),
                ],
              ),
            ),
          ),
          _ScenePill(
            glyph: Icons.event_seat_rounded,
            title: l10n.onboardingSeatsPill,
            lead: l10n.onboardingSeatsPillLead,
          ),
        ],
      ),
    );
  }
}

/// Troisième temps : le billet et son code d'embarquement.
class OnboardingTicketScene extends StatelessWidget {
  const OnboardingTicketScene({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return _Backdrop(
      slashTop: 176,
      child: Stack(
        children: [
          Align(
            alignment: const Alignment(0, -0.2),
            child: Container(
              width: 96,
              height: 96,
              padding: const EdgeInsets.all(9),
              decoration: BoxDecoration(
                color: AppColors.white,
                borderRadius: BorderRadius.circular(14),
              ),
              child: GridView.count(
                crossAxisCount: 7,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: 2,
                crossAxisSpacing: 2,
                children: [
                  // Motif figé : décoratif, comme sur le billet tant que
                  // l'API n'expose pas le `qr_code`.
                  for (final on in _pattern)
                    ColoredBox(color: on ? AppColors.kora : AppColors.white),
                ],
              ),
            ),
          ),
          _ScenePill(
            glyph: Icons.check_rounded,
            title: l10n.onboardingTicketPill,
            lead: l10n.onboardingTicketPillLead,
          ),
        ],
      ),
    );
  }

  static const List<bool> _pattern = [
    true, true, false, true, false, true, true, //
    true, false, true, true, true, false, true, //
    false, true, true, false, true, true, false, //
    true, true, false, true, true, false, true, //
    true, false, true, true, false, true, true, //
    false, true, true, false, true, true, true, //
    true, true, false, true, true, false, true, //
  ];
}
