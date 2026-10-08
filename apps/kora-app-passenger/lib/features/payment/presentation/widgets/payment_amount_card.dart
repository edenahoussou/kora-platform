import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';

/// Ce que le voyageur s'apprête à payer, mis en avant comme sur un
/// terminal : le montant d'abord, la mention de sécurité ensuite.
///
/// L'intitulé de l'écran porte déjà la somme, mais en petit, dans la
/// ligne de surtitre — là où l'œil ne la cherche pas au moment de choisir
/// un opérateur.
class PaymentAmountCard extends StatelessWidget {
  const PaymentAmountCard({super.key, required this.total});

  final String total;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return KoraCard(
      variant: KoraCardVariant.navy,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Text(
            l10n.paymentMomoAmount,
            style: AppTextStyles.caption(
              AppColors.white.withValues(alpha: 0.75),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            total,
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 27,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.5,
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          // Le cadenas et sa mention tiennent sur une ligne, quelle que
          // soit la langue : en anglais, « Secure payment · KORA
          // EXPRESS » débordait d'une ligne centrée non contrainte.
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.lock_rounded, size: 13, color: AppColors.gold),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  l10n.paymentMomoSecure,
                  textAlign: TextAlign.center,
                  style: AppTextStyles.caption(
                    AppColors.white.withValues(alpha: 0.75),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
