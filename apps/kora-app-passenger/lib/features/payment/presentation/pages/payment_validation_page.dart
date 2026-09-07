import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import '../bloc/payment_flow_bloc.dart';
import 'payment_flow_args.dart';
import 'payment_method_page.dart';

/// Écran `mobile-validation` de la maquette : la demande est partie, il
/// reste à la confirmer chez l'opérateur.
///
/// Le code secret se compose **dans la fenêtre de l'opérateur**, jamais
/// ici. L'écran ne fait donc que dire où regarder, et attendre.
class PaymentValidationPage extends StatefulWidget {
  const PaymentValidationPage({super.key, this.args});

  final PaymentFlowArgs? args;

  @override
  State<PaymentValidationPage> createState() => _PaymentValidationPageState();
}

class _PaymentValidationPageState extends State<PaymentValidationPage> {
  Timer? _ticker;

  @override
  void initState() {
    super.initState();
    // Une seconde, seulement s'il y a une échéance à égrener.
    if (widget.args?.expiresAt != null) {
      _ticker = Timer.periodic(
        const Duration(seconds: 1),
        (_) => setState(() {}),
      );
    }
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  /// Ce qu'il reste avant que la demande de l'opérateur se ferme, ou
  /// `null` quand le serveur n'annonce pas d'échéance.
  Duration? get _remaining {
    final expiry = widget.args?.expiresAt;
    if (expiry == null) return null;

    final left = expiry.difference(DateTime.now().toUtc());
    return left.isNegative ? Duration.zero : left;
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = widget.args;

    if (args == null) return const PaymentMissingArgs();

    final remaining = _remaining;

    return BlocListener<PaymentFlowBloc, PaymentFlowState>(
      listenWhen: (previous, current) => previous.status != current.status,
      listener: (context, flow) {
        // Le serveur a tranché : c'est lui qui interroge le provider, et
        // l'app ne fait que suivre. Le voyageur n'a rien à confirmer de
        // plus — l'écran s'efface de lui-même.
        if (flow.isConfirmed) {
          context.pushReplacement(AppRouter.paymentSuccessPath, extra: args);
          return;
        }

        if (flow.hasFailed) {
          KoraToaster.show(
            context,
            flow.errorMessage ?? context.l10n.paymentMethodFailed,
          );
        }
      },
      child: Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: ListView(
              children: [
                ScreenHeader(
                  eyebrow: args.providerLabel,
                  title: l10n.paymentValidationTitle,
                ),
                StateView(
                  icon: Icons.smartphone_rounded,
                  title: l10n.paymentValidationLead,
                  message: l10n.paymentValidationText,
                  primaryLabel: l10n.paymentValidationConfirmed,
                  // « J'ai payé » ne conclut rien : il demande au serveur
                  // d'interroger le provider maintenant, sans attendre le
                  // prochain tour de relecture. Si la réponse tarde, l'écran
                  // d'attente prend le relais.
                  onPrimary: () {
                    context.read<PaymentFlowBloc>().add(
                      const PaymentFlowVerifyRequested(),
                    );
                    context.push(AppRouter.paymentPendingPath, extra: args);
                  },
                  secondaryLabel: l10n.paymentSwitchMethod,
                  onSecondary: () => context.go(AppRouter.paymentMethodPath),
                ),
                if (remaining != null) ...[
                  const SizedBox(height: AppSpacing.md),
                  KoraCard(
                    variant: KoraCardVariant.mist,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          l10n.paymentValidationExpiresIn,
                          style: AppTextStyles.body(
                            context.koraColors.textMuted,
                          ),
                        ),
                        Text(
                          _mmss(remaining),
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
      ),
    );
  }
}

String _mmss(Duration duration) {
  final minutes = duration.inMinutes.toString().padLeft(2, '0');
  final seconds = (duration.inSeconds % 60).toString().padLeft(2, '0');
  return '$minutes:$seconds';
}
