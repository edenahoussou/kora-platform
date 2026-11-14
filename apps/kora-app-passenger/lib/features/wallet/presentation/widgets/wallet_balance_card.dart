import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../booking/domain/entities/price.dart';

/// `.wallet-card` : la carte navy du solde, avec son biseau doré doublé
/// d'un liseré blanc.
class WalletBalanceCard extends StatelessWidget {
  const WalletBalanceCard({
    super.key,
    required this.balance,
    this.compact = false,
  });

  final Price balance;

  /// `.compact-wallet` de la maquette : la carte de l'écran de recharge,
  /// plus basse et sans pied — elle rappelle le solde, elle n'est pas le
  /// sujet de l'écran.
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final faded = AppColors.white.withValues(alpha: 0.6);

    return Container(
      // Hauteur fixe, et non un minimum : le pied de carte est posé en
      // absolu sur le bas (`.wallet-foot { position: absolute }`), ce qui
      // suppose une hauteur connue. Dans un `ListView`, la hauteur
      // disponible est infinie — un `Spacer` y était impossible à
      // résoudre et faisait disparaître toute la liste.
      height: compact ? 125 : 192,
      clipBehavior: Clip.antiAlias,
      // Contour en `foregroundDecoration` : dans une `BoxDecoration` il se
      // peindrait derrière le biseau, qui le recouvrirait.
      foregroundDecoration: isDark
          ? BoxDecoration(
              borderRadius: BorderRadius.circular(26),
              border: Border.all(color: koraColors.line),
            )
          : null,
      decoration: BoxDecoration(
        color: AppColors.kora,
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(
            color: AppColors.kora.withValues(alpha: 0.22),
            offset: const Offset(0, 20),
            blurRadius: 45,
          ),
        ],
      ),
      child: Stack(
        children: [
          // `.wallet-card::after` : le biseau doré, surmonté d'un trait
          // blanc translucide (`box-shadow: 0 -15px 0 rgba(255,255,255,.1)`).
          Positioned(
            right: -80,
            bottom: 45,
            child: Transform.rotate(
              angle: -28 * 3.1415926535 / 180,
              child: SizedBox(
                width: 260,
                height: 24,
                child: Stack(
                  children: [
                    Positioned(
                      left: 0,
                      right: 0,
                      top: 0,
                      child: Container(
                        height: 9,
                        color: AppColors.white.withValues(alpha: 0.1),
                      ),
                    ),
                    Positioned(
                      left: 0,
                      right: 0,
                      bottom: 0,
                      child: Container(height: 9, color: AppColors.gold),
                    ),
                  ],
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  compact ? l10n.walletTopUpCurrent : l10n.walletBalanceLabel,
                  style: TextStyle(color: faded, fontSize: 12),
                ),
                SizedBox(height: compact ? 10 : 19),
                Text(
                  balance.formatted,
                  style: const TextStyle(
                    color: AppColors.white,
                    fontSize: 31,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -1.24,
                  ),
                ),
              ],
            ),
          ),
          // `.wallet-foot { left: 20; right: 20; bottom: 18 }`
          if (!compact)
            Positioned(
              left: 20,
              right: 20,
              bottom: 18,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Flexible(
                    child: Text(
                      l10n.walletBalanceSource,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: faded, fontSize: 11),
                    ),
                  ),
                  Text(
                    l10n.walletBalanceAvailable,
                    style: const TextStyle(
                      color: AppColors.white,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
