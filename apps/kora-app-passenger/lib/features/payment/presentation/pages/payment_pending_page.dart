import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import '../bloc/payment_flow_bloc.dart';
import 'payment_flow_args.dart';
import 'payment_method_page.dart';

/// Écran `payment-pending` de la maquette : la demande est partie, sa
/// confirmation se fait attendre.
///
/// C'est **le seul écran d'attente du parcours**. On y arrive dès que le
/// paiement est lancé, et on n'en sort que lorsque le serveur a tranché :
/// vers la réservation confirmée, ou vers le refus.
///
/// Le voyageur n'a rien à y faire. Le suivi interroge le fournisseur tout
/// seul, à la cadence que le serveur annonce, et l'écran repart de
/// lui-même dès qu'il a la réponse. **Tant que l'attente est normale,
/// l'écran n'offre donc aucun bouton** : proposer d'actualiser ou de
/// signaler un problème pendant les vingt secondes ordinaires d'un
/// paiement Mobile Money, c'est annoncer une panne qui n'a pas eu lieu, et
/// pousser à intervenir là où il suffisait d'attendre.
///
/// Passé le délai que l'opérateur s'était donné, l'attente cesse d'être
/// normale : les deux boutons apparaissent alors, parce qu'à ce moment-là
/// il y a effectivement quelque chose à tenter.
///
/// Le siège reste protégé pendant ce temps, et c'est la première chose que
/// dit l'écran : sans cette phrase, l'attente ressemble à une perte de la
/// réservation, et le voyageur relance un second paiement.
class PaymentPendingPage extends StatefulWidget {
  const PaymentPendingPage({super.key, this.args});

  final PaymentFlowArgs? args;

  @override
  State<PaymentPendingPage> createState() => _PaymentPendingPageState();
}

class _PaymentPendingPageState extends State<PaymentPendingPage> {
  /// Ce qu'on laisse à un paiement quand le serveur n'annonce aucune
  /// échéance. Une demande Mobile Money aboutit en quelques dizaines de
  /// secondes ; au-delà, le voyageur a fermé la fenêtre de son opérateur,
  /// ou ne l'a jamais vue.
  static const Duration _fallbackWait = Duration(seconds: 75);

  /// Bornes du délai déduit de `expires_at`. Une échéance très courte
  /// ferait surgir les boutons avant que le voyageur ait fini de composer
  /// son code ; une échéance très longue les retiendrait bien après que
  /// l'attente est devenue suspecte.
  static const Duration _minWait = Duration(seconds: 45);
  static const Duration _maxWait = Duration(minutes: 3);

  Timer? _timer;

  /// L'attente dépasse ce que l'opérateur s'était donné.
  var _isLate = false;

  @override
  void initState() {
    super.initState();

    final args = widget.args;
    if (args != null) {
      _timer = Timer(_wait(args), () {
        if (mounted) setState(() => _isLate = true);
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  /// Le temps au bout duquel on cesse de demander au voyageur de patienter.
  Duration _wait(PaymentFlowArgs args) {
    final expiresAt = args.expiresAt;
    if (expiresAt == null) return _fallbackWait;

    final remaining = expiresAt.difference(DateTime.now());
    if (remaining < _minWait) return _minWait;
    if (remaining > _maxWait) return _maxWait;

    return remaining;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = widget.args;

    if (args == null) return const PaymentMissingArgs();

    final polling = args.pollingInterval;

    return BlocConsumer<PaymentFlowBloc, PaymentFlowState>(
      listenWhen: (previous, current) => previous.status != current.status,
      listener: (context, flow) {
        // Le verdict vient du serveur, et de lui seul. C'est ici que le
        // parcours reprend, dans un sens ou dans l'autre.
        if (flow.hasFailed) {
          context.pushReplacement(AppRouter.paymentFailedPath, extra: args);
          return;
        }

        if (flow.isConfirmed) {
          context.pushReplacement(AppRouter.paymentSuccessPath, extra: args);
        }
      },
      builder: (context, flow) => Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: ListView(
              children: [
                const SizedBox(height: AppSpacing.lg),
                StateView(
                  icon: Icons.autorenew_rounded,
                  title: l10n.paymentPendingTitle,
                  // Le texte dit d'abord que le siège est gardé ; il ne
                  // change qu'une fois le délai dépassé, pour expliquer
                  // les boutons qui viennent d'apparaître.
                  message: _isLate
                      ? l10n.paymentPendingSlow
                      : l10n.paymentPendingText,
                  showLoader: true,
                  // Rien à faire tant que l'attente est normale.
                  //
                  // Cette vérification-ci interroge le fournisseur — elle
                  // ne se contente pas de relire la base, et surtout elle
                  // ne pousse pas l'écran de succès de sa propre autorité,
                  // comme le faisait le bouton « Actualiser » d'avant.
                  primaryLabel: _isLate ? l10n.paymentPendingRefresh : null,
                  onPrimary: () => context.read<PaymentFlowBloc>().add(
                    const PaymentFlowVerifyRequested(),
                  ),
                  isPrimaryLoading: flow.isVerifying,
                  secondaryLabel: _isLate ? l10n.paymentPendingReport : null,
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
                const SizedBox(height: AppSpacing.xl),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
