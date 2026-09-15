import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/presentation/widgets/hold_countdown.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import '../bloc/payment_flow_bloc.dart';
import 'payment_flow_args.dart';
import 'payment_method_page.dart';

/// Le paiement n'a pas abouti.
///
/// Un écran, et non un simple message : le refus arrête le parcours, et il
/// faut dire au voyageur ce qui reste possible. Ses sièges sont encore
/// retenus — le décompte le montre — donc réessayer a un sens tant qu'il
/// court.
///
/// Le message du serveur est repris tel quel quand il y en a un : « solde
/// insuffisant » vaut mieux qu'un refus sans raison.
class PaymentFailedPage extends StatelessWidget {
  const PaymentFailedPage({super.key, this.args});

  final PaymentFlowArgs? args;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = this.args;

    if (args == null) return const PaymentMissingArgs();

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: BlocBuilder<PaymentFlowBloc, PaymentFlowState>(
            builder: (context, flow) => ListView(
              children: [
                ScreenHeader(
                  eyebrow: l10n.paymentFailedEyebrow,
                  title: l10n.paymentFailedTitle,
                ),
                const HoldCountdown(margin: EdgeInsets.only(bottom: 11)),
                StateView(
                  icon: Icons.error_outline_rounded,
                  title: l10n.paymentFailedTitle,
                  // Ce que dit le serveur, s'il dit quelque chose.
                  message: flow.errorMessage ?? l10n.paymentFailedText,
                  primaryLabel: l10n.paymentFailedRetry,
                  // Le paiement refusé ne se relance pas tel quel : le bloc
                  // garde la demande en mémoire pour éviter le double
                  // débit. On la remet à zéro, puis on revient à la saisie.
                  onPrimary: () {
                    context.read<PaymentFlowBloc>().add(
                      const PaymentFlowReset(),
                    );
                    context.pop();
                  },
                  secondaryLabel: l10n.paymentFailedSwitch,
                  onSecondary: () {
                    context.read<PaymentFlowBloc>().add(
                      const PaymentFlowReset(),
                    );
                    backToPaymentMethods(context);
                  },
                ),
                const SizedBox(height: AppSpacing.xl),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
