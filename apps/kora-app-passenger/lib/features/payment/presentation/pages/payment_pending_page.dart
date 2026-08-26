import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import 'payment_flow_args.dart';
import 'payment_method_page.dart';

/// Écran `payment-pending` de la maquette : le paiement est parti, sa
/// confirmation se fait attendre.
///
/// Le siège reste protégé pendant ce temps, et c'est le premier chose que
/// dit l'écran : sans cette phrase, l'attente ressemble à une perte de la
/// réservation, et le voyageur relance un second paiement.
///
/// La vérification automatique annoncée n'est pas encore branchée : elle
/// suivra `polling_interval_seconds`, que rend `POST payments/initiate`.
/// L'écran ne l'annonce donc que lorsque cette cadence est connue — dire
/// « vérification toutes les 10 secondes » sans rien vérifier serait une
/// promesse creuse.
class PaymentPendingPage extends StatelessWidget {
  const PaymentPendingPage({super.key, this.args});

  final PaymentFlowArgs? args;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = this.args;

    if (args == null) return const PaymentMissingArgs();

    final polling = args.pollingInterval;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: ListView(
            children: [
              const SizedBox(height: AppSpacing.lg),
              StateView(
                icon: Icons.autorenew_rounded,
                title: l10n.paymentPendingTitle,
                message: l10n.paymentPendingText,
                showLoader: true,
                primaryLabel: l10n.paymentPendingRefresh,
                onPrimary: () => context.pushReplacement(
                  AppRouter.paymentSuccessPath,
                  extra: args,
                ),
                secondaryLabel: l10n.paymentPendingReport,
                onSecondary: () => context.push(AppRouter.contactPath),
              ),
              if (polling != null) ...[
                const SizedBox(height: AppSpacing.md),
                Text(
                  l10n.paymentPendingPolling(polling.inSeconds),
                  textAlign: TextAlign.center,
                  style: AppTextStyles.caption(context.koraColors.textMuted),
                ),
              ],
              if (args.reference != null) ...[
                const SizedBox(height: AppSpacing.md),
                KoraCard(
                  variant: KoraCardVariant.mist,
                  child: Column(
                    children: [
                      Text(
                        l10n.paymentReference,
                        style: AppTextStyles.caption(
                          context.koraColors.textMuted,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        args.reference!,
                        style: AppTextStyles.titleMedium(
                          context.koraColors.textPrimary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.xl),
            ],
          ),
        ),
      ),
    );
  }
}
