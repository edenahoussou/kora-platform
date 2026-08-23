import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';

/// `.home-ride-banner` : bandeau navy qui pousse KORA RIDE depuis
/// l'accueil.
///
/// **Tailles de texte volontairement différentes de la maquette** : le CSS
/// descend jusqu'à `font-size: 6px` sur ce bloc, lisible sur une maquette
/// affichée en grand dans un navigateur, pas sur un vrai téléphone. On
/// reprend l'échelle typographique du design system à la place, la
/// composition (grille, couleurs, trait doré incliné) restant identique.
class HomeRideBanner extends StatefulWidget {
  const HomeRideBanner({super.key, required this.onTap});

  final VoidCallback onTap;

  @override
  State<HomeRideBanner> createState() => _HomeRideBannerState();
}

class _HomeRideBannerState extends State<HomeRideBanner>
    with SingleTickerProviderStateMixin {
  /// Rebond de la pastille véhicule — `@keyframes reminderFloat`
  /// (`translateY(-3px)` à mi-parcours). La maquette le déclenche au
  /// survol (`.home-ride-banner:hover .home-ride-car`) : sur mobile il n'y
  /// a pas de survol, l'équivalent le plus proche est l'appui maintenu.
  ///
  /// 600 ms = un demi-cycle : le CSS annonce 1,2 s pour l'aller-retour
  /// complet, que `repeat(reverse: true)` rejoue ici.
  late final AnimationController _bounce = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 600),
  );

  @override
  void dispose() {
    _bounce.dispose();
    super.dispose();
  }

  void _startBounce() {
    // Respecte « réduire les animations » du système, comme le
    // `@media (prefers-reduced-motion: reduce)` de la maquette.
    if (MediaQuery.disableAnimationsOf(context)) return;
    _bounce.repeat(reverse: true);
  }

  void _stopBounce() {
    _bounce.stop();
    _bounce.animateTo(
      0,
      duration: const Duration(milliseconds: 180),
      curve: Curves.easeOut,
    );
  }

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      // En thème sombre, le fond d'écran est lui aussi bleu KORA : sans
      // contour, la bannière n'a plus de bords visibles. Même filet que
      // les cartes voisines (« Prochain voyage », « Rappel utile »), donc
      // même couleur, et dessiné par-dessus l'aplat navy qui, sinon, le
      // recouvrirait.
      position: DecorationPosition.foreground,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(19),
        border: Border.all(color: context.koraColors.line),
      ),
      child: Material(
        color: AppColors.kora,
        borderRadius: BorderRadius.circular(19),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: widget.onTap,
          onTapDown: (_) => _startBounce(),
          onTapUp: (_) => _stopBounce(),
          onTapCancel: _stopBounce,
          child: Stack(
            children: [
              // `radial-gradient(circle at 92% 0, rgba(0,87,184,.48), transparent 38%)`
              const Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: RadialGradient(
                      center: Alignment(0.84, -1),
                      radius: 0.75,
                      colors: [Color(0x7A0057B8), Colors.transparent],
                    ),
                  ),
                ),
              ),
              // `.home-ride-banner::after` — le même trait doré incliné que
              // l'en-tête, en plus fin.
              Positioned(
                right: -39,
                bottom: 17,
                child: Transform.rotate(
                  angle: -23 * math.pi / 180,
                  child: Container(
                    width: 145,
                    height: 3,
                    decoration: BoxDecoration(
                      color: AppColors.gold,
                      borderRadius: BorderRadius.circular(AppRadius.pill),
                    ),
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: Row(
                  children: [
                    AnimatedBuilder(
                      animation: _bounce,
                      builder: (context, child) => Transform.translate(
                        offset: Offset(
                          0,
                          -3 * Curves.easeInOut.transform(_bounce.value),
                        ),
                        child: child,
                      ),
                      child: const _RideCarBadge(),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            context.l10n.homeRideEyebrow,
                            style: AppTextStyles.eyebrow(AppColors.gold),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            context.l10n.homeRideTitle,
                            style: TextStyle(
                              color: AppColors.white,
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            context.l10n.homeRideText,
                            style: TextStyle(
                              color: AppColors.white.withValues(alpha: 0.57),
                              fontSize: 10,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Container(
                      width: 27,
                      height: 27,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: AppColors.gold,
                        borderRadius: BorderRadius.circular(9),
                      ),
                      child: const Icon(
                        Icons.arrow_forward_rounded,
                        size: 15,
                        color: AppColors.kora,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.home-ride-car` : la pastille dorée en forme de voiture vue de face.
class _RideCarBadge extends StatelessWidget {
  const _RideCarBadge();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 52,
      height: 35,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: AppColors.gold,
        borderRadius: const BorderRadius.only(
          topLeft: Radius.circular(16),
          topRight: Radius.circular(18),
          bottomLeft: Radius.circular(8),
          bottomRight: Radius.circular(8),
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.gold.withValues(alpha: 0.24),
            offset: const Offset(0, 8),
            blurRadius: 18,
          ),
        ],
      ),
      child: const KoraVehicleIcon(
        type: KoraVehicleType.standard,
        size: 38,
        // Le SVG a des traits blancs en dur ; sur la pastille dorée il
        // faut du navy (même besoin que le badge de `DepartureCard`).
        colorFilter: ColorFilter.mode(AppColors.kora, BlendMode.srcIn),
      ),
    );
  }
}
