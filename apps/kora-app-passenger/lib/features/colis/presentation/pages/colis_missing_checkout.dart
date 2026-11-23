import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/widgets/screen_header.dart';

/// L'écran d'encaissement ouvert sans règlement à encaisser — un lien
/// profond, ou un redémarrage à chaud.
///
/// Un cul-de-sac visible plutôt qu'une exception : le montant est arrêté
/// sur l'écran des moyens et transporté de là. Sans lui, il n'y a rien à
/// payer, et l'inventer serait pire que de le dire.
class ColisMissingCheckout extends StatelessWidget {
  const ColisMissingCheckout({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.colisCheckoutEyebrow,
                title: l10n.parcelsTitle,
              ),
              const Spacer(),
              KoraButton(
                label: l10n.colisBackToParcels,
                onPressed: () => context.go('/colis'),
              ),
              const SizedBox(height: AppSpacing.lg),
            ],
          ),
        ),
      ),
    );
  }
}
