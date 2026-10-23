import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../booking/presentation/bloc/seat_hold_bloc.dart';
import '../../../booking/presentation/widgets/hold_countdown.dart';
import '../../domain/entities/kkiapay_widget.dart';
import '../../domain/entities/payment_attempt.dart';
import '../../domain/entities/payment_catalog.dart';
import '../bloc/payment_catalog_bloc.dart';
import '../bloc/payment_flow_bloc.dart';
import '../widgets/kkiapay_checkout.dart';
import '../widgets/payment_amount_card.dart';
import 'payment_args.dart';
import 'payment_flow_args.dart';
import 'payment_flow_scope.dart';
import 'payment_method_page.dart';

/// Paiement par la feuille de l'encaisseur, carte ou Mobile Money.
///
/// **Rien n'est saisi ici, et c'est volontaire.** Cet écran demandait
/// autrefois le PAN, la date et le CVV, sans jamais les envoyer :
/// `POST payments/initiate` n'a jamais accepté de données de carte, et
/// faire transiter un numéro par nos serveurs y ferait entrer les
/// obligations PCI-DSS.
///
/// Depuis MEZ-146, KKiaPay encaisse dans sa propre feuille : c'est elle
/// qui recueille le numéro de carte **ou** l'opérateur et le numéro
/// Mobile Money, chez le prestataire, et le serveur vérifie ensuite la
/// transaction. L'écran se réduit donc à ce qu'il doit dire — le
/// montant, le temps qu'il reste, et un bouton qui ouvre la feuille.
///
/// Le même écran sert les deux moyens parce que c'est la même mécanique.
/// L'autre passerelle Mobile Money, FedaPay, pousse au contraire la
/// demande sur le téléphone du payeur : il lui faut l'opérateur et le
/// numéro **avant** l'appel, et c'est [MobileMoneyPage] qui les demande.
class GatewayCheckoutPage extends StatefulWidget {
  const GatewayCheckoutPage({required this.method, super.key, this.args});

  /// `card` ou `mobile_money` : ce que la feuille devra encaisser.
  ///
  /// Porté par la route plutôt que par les arguments : c'est le chemin
  /// emprunté qui le fixe, et un lien profond sans arguments doit quand
  /// même savoir de quel écran il s'agit.
  final String method;

  /// `null` si l'écran est ouvert sans données (lien profond).
  final PaymentArgs? args;

  @override
  State<GatewayCheckoutPage> createState() => _GatewayCheckoutPageState();
}

class _GatewayCheckoutPageState extends State<GatewayCheckoutPage> {
  /// Vrai dès que le voyageur a appuyé sur « Payer » depuis cet écran.
  ///
  /// La feuille KKiaPay ne s'ouvre que là. Un paiement repris au
  /// lancement attend déjà son verdict : rouvrir sa feuille ferait payer
  /// deux fois la même réservation.
  var _requested = false;

  bool get _isCard => widget.method == PaymentCatalog.cardCode;

  @override
  Widget build(BuildContext context) {
    final args = widget.args;
    if (args == null) return const PaymentMissingArgs();

    final l10n = context.l10n;

    return BlocConsumer<PaymentFlowBloc, PaymentFlowState>(
      listenWhen: (previous, current) => previous.status != current.status,
      listener: (context, flow) => _onFlowChanged(context, flow, args),
      builder: (context, flow) => Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                ScreenHeader(
                  eyebrow: _isCard
                      ? l10n.paymentCardEyebrow
                      : l10n.paymentMomoEyebrow(args.total.formatted),
                  title: _isCard
                      ? l10n.paymentCardTitle
                      : l10n.paymentMomoTitle,
                ),
                Expanded(
                  child: ListView(
                    padding: EdgeInsets.zero,
                    children: [
                      PaymentAmountCard(total: args.total.formatted),
                      const SizedBox(height: AppSpacing.md),
                      // Ce que l'écran ne fait pas : demander un numéro.
                      // Le dire évite au voyageur de chercher un
                      // formulaire qui n'existe plus — et, côté Mobile
                      // Money, de chercher la liste des opérateurs qu'il
                      // a vue sur l'autre passerelle.
                      KoraCard(
                        variant: KoraCardVariant.mist,
                        child: Text(
                          _isCard
                              ? l10n.paymentCardLead
                              : l10n.paymentGatewayMomoLead(_gatewayLabel()),
                          style: AppTextStyles.body(
                            context.koraColors.textMuted,
                          ),
                        ),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      const HoldCountdown(),
                      // Même raison que sur l'écran des opérateurs :
                      // le bouton d'assistance flotte au-dessus de cette
                      // liste, et le dernier élément doit pouvoir passer
                      // au-dessus de lui.
                      const SizedBox(height: 104),
                    ],
                  ),
                ),
                KoraButton(
                  label: _isCard
                      ? l10n.paymentCardPay(args.total.formatted)
                      : l10n.paymentMomoPay(args.total.formatted),
                  isLoading: flow.isStarting,
                  onPressed: flow.isStarting ? null : () => _pay(context),
                ),
                const SizedBox(height: AppSpacing.lg),
              ],
            ),
          ),
        ),
      ),
    );
  }

  /// L'encaisseur qui servira ce moyen, ou `null` si le catalogue n'en
  /// annonce aucun d'utilisable.
  ///
  /// Côté Mobile Money, deux passerelles coexistent : celle que le
  /// voyageur a touchée sur l'écran précédent est portée par les
  /// arguments. Sans elle — un lien profond — on prend la première que
  /// le catalogue annonce, faute de mieux.
  PaymentProvider? _provider(BuildContext context) {
    final method = context.read<PaymentCatalogBloc>().state.catalog.methodOf(
      widget.method,
    );
    final enabled = method?.enabledProviders ?? const <PaymentProvider>[];
    final chosen = widget.args?.provider;

    if (chosen != null) {
      for (final provider in enabled) {
        if (provider.code == chosen) return provider;
      }
    }

    return enabled.firstOrNull;
  }

  /// Le nom de la passerelle, pour la phrase qui annonce sa fenêtre.
  String _gatewayLabel() => _provider(context)?.label ?? '';

  void _pay(BuildContext context) {
    _requested = true;

    // L'encaisseur vient du catalogue : c'est le serveur qui dit lequel
    // sert ce moyen, et l'app n'en code aucun en dur.
    final provider = _provider(context);

    if (provider == null) {
      KoraToaster.show(context, context.l10n.paymentMethodNone);
      return;
    }

    final hold = context.read<SeatHoldBloc>().state.hold;
    if (hold == null) {
      context.go(AppRouter.holdExpiredPath);
      return;
    }

    // Aucun réseau n'est envoyé : la feuille de l'encaisseur demandera
    // l'opérateur elle-même. Le serveur l'apprendra par la vérification
    // de la transaction, pas par nous.
    context.read<PaymentFlowBloc>().add(
      PaymentFlowStarted(
        reservationId: hold.id,
        method: widget.method,
        provider: provider.code,
      ),
    );
  }

  void _onFlowChanged(
    BuildContext context,
    PaymentFlowState flow,
    PaymentArgs args,
  ) {
    // Le paiement repris au lancement peut être celui d'une autre
    // réservation : il ne pilote pas cet écran-ci.
    if (!paymentFlowMatchesHold(context, flow)) return;

    if (flow.hasFailed) {
      context.push(
        AppRouter.paymentFailedPath,
        extra: _flowArgs(flow.attempt, args, context),
      );
      return;
    }

    if (flow.isPending) {
      final attempt = flow.attempt;
      // La feuille ne s'ouvre que sur appui : un paiement repris attend
      // déjà son verdict, et la rouvrir ferait payer deux fois.
      if (_requested && attempt != null && attempt.needsWidget) {
        unawaited(_collect(context, attempt, args));
        return;
      }

      context.push(
        AppRouter.paymentPendingPath,
        extra: _flowArgs(flow.attempt, args, context),
      );
      return;
    }

    if (flow.isConfirmed) {
      context.push(
        AppRouter.paymentSuccessPath,
        extra: _flowArgs(flow.attempt, args, context),
      );
    }
  }

  /// Ouvre la feuille KKiaPay, limitée au moyen choisi.
  ///
  /// La feuille sait tout encaisser ; lui laisser les deux portes
  /// ramènerait le voyageur à ce qu'il vient d'écarter un écran plus
  /// tôt, et l'écran d'attente annoncerait ensuite un règlement qui n'en
  /// serait pas un.
  Future<void> _collect(
    BuildContext context,
    PaymentAttempt attempt,
    PaymentArgs args,
  ) async {
    final flow = context.read<PaymentFlowBloc>();
    final user = context.read<AuthBloc>().state.session?.user;
    final sheet = attempt.widget!;

    final result = await openKkiapayCheckout(
      context,
      widget: _isCard ? sheet.restrictedToCard() : sheet.restrictedToMomo(),
      name: user?.name,
      email: user?.email,
    );

    if (!context.mounted) return;

    switch (result.outcome) {
      case KkiapayOutcome.succeeded:
        flow.add(
          PaymentFlowVerifyRequested(transactionId: result.transactionId),
        );
        await context.push(
          AppRouter.paymentPendingPath,
          extra: _flowArgs(attempt, args, context),
        );
      case KkiapayOutcome.cancelled:
        break;
      case KkiapayOutcome.failed:
        KoraToaster.show(context, context.l10n.paymentWidgetFailed);
    }
  }

  PaymentFlowArgs _flowArgs(
    PaymentAttempt? attempt,
    PaymentArgs args,
    BuildContext context,
  ) {
    final l10n = context.l10n;

    return PaymentFlowArgs(
      total: args.total,
      providerLabel: _isCard ? l10n.paymentMethodCard : l10n.paymentMethodMomo,
      reference: attempt?.reference,
      expiresAt: attempt?.expiresAt,
      pollingInterval: attempt?.pollingInterval,
      departure: args.departure,
      seatLabel: args.seatLabel,
    );
  }
}
