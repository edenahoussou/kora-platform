import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/presentation/bloc/seat_hold_bloc.dart';
import '../../../booking/presentation/widgets/hold_countdown.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import '../bloc/payment_flow_bloc.dart';
import 'payment_flow_args.dart';
import 'payment_method_page.dart';

/// Le paiement n'a pas abouti.
///
/// Un écran, et non un simple message : le refus arrête le parcours, et il
/// faut dire au voyageur ce qui reste possible. Or **ce qui reste
/// possible dépend du serveur** : un refus d'opérateur fait aujourd'hui
/// expirer la réservation et relâche les sièges, si bien que « réessayer »
/// ne pouvait qu'échouer — le serveur répondait « Cette réservation doit
/// d'abord être reprise ». La réponse de paiement porte le statut de la
/// réservation : l'écran s'en sert pour ne proposer que ce qui peut
/// aboutir, et pour couper un décompte qui tournait sur des places déjà
/// rendues.
///
/// Le message du serveur est repris tel quel quand il y en a un : « solde
/// insuffisant » vaut mieux qu'un refus sans raison.
class PaymentFailedPage extends StatefulWidget {
  const PaymentFailedPage({super.key, this.args});

  final PaymentFlowArgs? args;

  @override
  State<PaymentFailedPage> createState() => _PaymentFailedPageState();
}

class _PaymentFailedPageState extends State<PaymentFailedPage> {
  @override
  void initState() {
    super.initState();

    // Les sièges sont déjà repartis à la vente : on arrête le décompte,
    // sans rien libérer — il n'y a plus rien à rendre.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;

      final attempt = context.read<PaymentFlowBloc>().state.attempt;
      if (attempt != null && attempt.needsNewBooking) {
        context.read<SeatHoldBloc>().add(const SeatHoldSettled());
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = widget.args;

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
                // Sans sièges, il n'y a plus de paiement à reprendre :
                // l'écran renvoie à la recherche, et ne propose ni un
                // nouvel essai ni un autre moyen — les deux buteraient sur
                // la même réservation morte.
                if (flow.attempt?.needsNewBooking ?? false)
                  StateView(
                    icon: Icons.error_outline_rounded,
                    title: l10n.paymentFailedTitle,
                    message: l10n.paymentFailedText,
                    primaryLabel: l10n.paymentFailedRetry,
                    onPrimary: () {
                      context.read<PaymentFlowBloc>().add(
                        const PaymentFlowReset(),
                      );
                      context.go('/home');
                    },
                  )
                else
                  StateView(
                    icon: Icons.error_outline_rounded,
                    title: l10n.paymentFailedTitle,
                    // Ce que dit le serveur, s'il dit quelque chose.
                    message: flow.errorMessage ?? l10n.paymentFailedHeldText,
                    primaryLabel: l10n.paymentFailedRetryPayment,
                    // Le paiement refusé ne se relance pas tel quel : le
                    // bloc garde la demande en mémoire pour éviter le
                    // double débit. On la remet à zéro, puis on revient à
                    // la saisie.
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
