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

  /// Vrai tant que la feuille de l'encaisseur est à l'écran.
  ///
  /// Elle est poussée comme une page ordinaire : tout ce que l'app
  /// pousse ensuite se pose **par-dessus**. Pendant qu'elle attend le
  /// numéro, la relecture du paiement continue et le serveur finit par
  /// répondre « en attente, vérifie » — ce que l'écoute traduisait par
  /// un saut vers le suivi, posé sur la feuille. Le voyageur voyait
  /// « Paiement en cours de vérification » sans avoir rien saisi, et ne
  /// retrouvait la feuille qu'en revenant en arrière.
  var _sheetOpen = false;

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
                  // Une recharge n'achète pas de billet : le surtitre le
                  // dit, sans quoi l'écran annonce « Billet KORA BUS »
                  // au-dessus d'un montant qui alimente le Wallet.
                  eyebrow: args.isTopUp
                      ? l10n.paymentTopUpEyebrow
                      : _isCard
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
                      KoraAlert(
                        icon: _isCard
                            ? Icons.lock_rounded
                            : Icons.open_in_new_rounded,
                        title: _isCard
                            ? l10n.paymentCardSecureTitle
                            : l10n.paymentSecureWindowTitle,
                        message: _isCard
                            ? l10n.paymentCardLead
                            : _leadForGateway(l10n),
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
                // Une demande en attente verrouille son montant : sans
                // cette porte, le voyageur ne peut ni la payer ni en
                // ouvrir une autre.
                if (flow.isPending &&
                    paymentFlowMatchesHold(context, flow, args)) ...[
                  const SizedBox(height: AppSpacing.sm),
                  KoraButton(
                    label: l10n.paymentAbandon,
                    variant: KoraButtonVariant.ghost,
                    onPressed: () {
                      _requested = false;
                      context.read<PaymentFlowBloc>().add(
                        const PaymentFlowAbandoned(),
                      );
                      KoraToaster.show(context, l10n.paymentAbandonDone);
                    },
                  ),
                ],
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

  /// La phrase qui annonce la fenêtre de la passerelle.
  ///
  /// Le catalogue n'est pas toujours lu quand l'écran se peint la
  /// première fois : sans nom, la phrase se lisait « dans la fenêtre ,
  /// qui s'ouvre ». On dit alors « la fenêtre sécurisée », ce qui reste
  /// vrai et se lit.
  String _leadForGateway(AppLocalization l10n) {
    final label = _gatewayLabel();

    return label.isEmpty
        ? l10n.paymentGatewayMomoLeadPlain
        : l10n.paymentGatewayMomoLead(label);
  }

  void _pay(BuildContext context) {
    _requested = true;

    // La feuille a déjà été ouverte et refermée sans payer : il n'y a
    // rien de neuf à demander au serveur, il faut la rouvrir.
    //
    // Le verrou anti-double-débit refuse de relancer un paiement encore
    // vivant — à juste titre. Mais il refusait aussi d'en rouvrir la
    // fenêtre : le bouton n'émettait plus rien, et le voyageur restait
    // devant une recharge qu'il ne pouvait ni payer ni abandonner.
    final flow = context.read<PaymentFlowBloc>().state;
    final pending = flow.attempt;
    final args = widget.args;
    if (pending != null &&
        pending.isPending &&
        args != null &&
        paymentFlowMatchesHold(context, flow, widget.args)) {
      if (pending.needsWidget) {
        unawaited(_collect(context, pending, args));
        return;
      }

      // Plus de fenêtre à rouvrir : le serveur a pris la main. Il
      // vérifie une transaction que la feuille lui a déjà rendue, ou
      // l'initiation n'a pas abouti — dans les deux cas il répond
      // « attends », sans configuration de widget.
      //
      // Le bouton devenait alors muet : le Bloc refuse de relancer un
      // paiement encore vivant, et il n'y avait rien d'autre à faire.
      // L'écran était un cul-de-sac — ni payer, ni comprendre. On mène
      // au suivi, qui interroge le serveur jusqu'à l'issue, et
      // « Abandonner et recommencer » reste la porte de sortie.
      context.push(
        AppRouter.paymentPendingPath,
        extra: _flowArgs(pending, args, context),
      );
      return;
    }

    // L'encaisseur vient du catalogue : c'est le serveur qui dit lequel
    // sert ce moyen, et l'app n'en code aucun en dur.
    final provider = _provider(context);

    if (provider == null) {
      KoraToaster.show(context, context.l10n.paymentMethodNone);
      return;
    }

    // Une recharge n'a pas de réservation : le blocage de sièges ne la
    // concerne pas, et le renvoyer vers « réservation expirée » dirait
    // au voyageur une chose qui n'a rien à voir avec ce qu'il fait.
    final topUp = widget.args?.topUpAmount;
    final hold = topUp == null ? context.read<SeatHoldBloc>().state.hold : null;
    if (topUp == null && hold == null) {
      context.go(AppRouter.holdExpiredPath);
      return;
    }

    // Aucun réseau n'est envoyé : la feuille de l'encaisseur demandera
    // l'opérateur elle-même. Le serveur l'apprendra par la vérification
    // de la transaction, pas par nous.
    context.read<PaymentFlowBloc>().add(
      PaymentFlowStarted(
        reservationId: hold?.id,
        topUpAmount: topUp,
        walletAmount: widget.args?.walletAmount,
        pointsAmount: widget.args?.pointsAmount,
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
    if (!paymentFlowMatchesHold(context, flow, args)) return;

    // Comme sur l'écran Mobile Money : on ne réagit qu'au paiement que
    // cet écran a lancé. Un paiement déjà en cours fait sinon sauter au
    // suivi avant que le voyageur ait touché quoi que ce soit.
    if (!_requested) return;

    // La feuille de l'encaisseur est ouverte : elle mène le jeu jusqu'à
    // son issue. Pousser quoi que ce soit maintenant le poserait
    // par-dessus elle.
    if (_sheetOpen) return;

    if (flow.hasFailed) {
      context.push(
        AppRouter.paymentFailedPath,
        extra: _flowArgs(flow.attempt, args, context),
      );
      return;
    }

    if (flow.isPending) {
      final attempt = flow.attempt;
      if (attempt != null && attempt.needsWidget) {
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
    if (_sheetOpen) return;

    final flow = context.read<PaymentFlowBloc>();
    final user = context.read<AuthBloc>().state.session?.user;
    final sheet = attempt.widget!;

    _sheetOpen = true;

    try {
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
    } finally {
      // Même si l'écran a disparu entre-temps : sans cette remise à
      // zéro, un retour sur la page garderait l'écoute muette.
      _sheetOpen = false;
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
      isTopUp: args.isTopUp,
    );
  }
}
