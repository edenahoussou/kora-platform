import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/title_color.dart';

/// `stateView` de la maquette (`js/app.js`) : le grand médaillon rond, un
/// titre, un texte d'explication, une barre de chargement optionnelle et
/// jusqu'à deux actions.
///
/// Sert aux trois écrans « pleine page » du parcours : recherche en cours,
/// aucun départ, et erreur.
class StateView extends StatelessWidget {
  const StateView({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    this.showLoader = false,
    this.primaryLabel,
    this.onPrimary,
    this.secondaryLabel,
    this.onSecondary,
  });

  final IconData icon;
  final String title;
  final String message;

  /// Barre de progression indéterminée (`.loader-route`), affichée pendant
  /// une recherche.
  final bool showLoader;

  final String? primaryLabel;
  final VoidCallback? onPrimary;
  final String? secondaryLabel;
  final VoidCallback? onSecondary;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 38),
        _StateVisual(icon: icon),
        const SizedBox(height: 25),
        Text(
          title,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: 25,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.875, // -.035em × 25
          ),
        ),
        const SizedBox(height: 10),
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 285),
            child: Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: koraColors.textMuted,
                fontSize: 13,
                height: 1.5,
              ),
            ),
          ),
        ),
        if (showLoader) ...[
          const SizedBox(height: 25),
          const Center(child: _RouteLoader()),
        ],
        const SizedBox(height: 24),
        if (primaryLabel != null)
          KoraButton(label: primaryLabel!, onPressed: onPrimary),
        if (secondaryLabel != null) ...[
          const SizedBox(height: 10),
          KoraButton(
            label: secondaryLabel!,
            variant: KoraButtonVariant.ghost,
            onPressed: onSecondary,
          ),
        ],
      ],
    );
  }
}

/// `.state-visual` : disque clair de 150px, traversé d'un trait doré
/// incliné, avec le pictogramme au centre (`.state-icon`).
class _StateVisual extends StatelessWidget {
  const _StateVisual({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Center(
      child: SizedBox(
        width: 150,
        height: 150,
        child: Stack(
          alignment: Alignment.center,
          children: [
            DecoratedBox(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: koraColors.textPrimary.withValues(alpha: 0.05),
              ),
              child: const SizedBox.expand(),
            ),
            Transform.rotate(
              angle: -28 * math.pi / 180,
              child: Container(
                width: 112,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.gold,
                  borderRadius: BorderRadius.circular(AppRadius.pill),
                ),
              ),
            ),
            Container(
              width: 65,
              height: 65,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: AppColors.kora,
                borderRadius: BorderRadius.circular(22),
                boxShadow: AppShadows.base,
              ),
              child: Icon(icon, size: 27, color: AppColors.white),
            ),
          ],
        ),
      ),
    );
  }
}

/// `.loader-route` : piste claire de 180×5 parcourue par un segment doré.
class _RouteLoader extends StatelessWidget {
  const _RouteLoader();

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return SizedBox(
      width: 180,
      height: 5,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.pill),
        child: LinearProgressIndicator(
          backgroundColor: koraColors.textPrimary.withValues(alpha: 0.09),
          color: AppColors.gold,
        ),
      ),
    );
  }
}
