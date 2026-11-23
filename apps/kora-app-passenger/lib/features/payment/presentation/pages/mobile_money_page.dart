import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/phone_number.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../../auth/domain/entities/phone_country.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../booking/presentation/bloc/seat_hold_bloc.dart';
import '../../../booking/presentation/widgets/hold_countdown.dart';
import '../../domain/entities/kkiapay_widget.dart';
import '../../domain/entities/payment_attempt.dart';
import '../../domain/entities/payment_catalog.dart';
import '../bloc/payment_catalog_bloc.dart';
import '../bloc/payment_flow_bloc.dart';
import '../widgets/kkiapay_checkout.dart';
import '../widgets/labelled_field.dart';
import '../widgets/operator_tile.dart';
import '../widgets/payment_amount_card.dart';
import 'payment_args.dart';
import 'payment_flow_args.dart';
import 'payment_flow_scope.dart';
import 'payment_method_page.dart';

/// Le nom d'affichage des réseaux Mobile Money.
///
/// **La liste des réseaux, elle, vient du serveur** (`GET
/// /payments/methods`) : seule leur graphie est écrite ici. Des marques ne
/// se traduisent pas — « MTN MoMo » s'écrit pareil en français et en
/// anglais — mais elles se capitalisent, et le serveur renvoie des codes
/// (`mtn_momo`) qu'on ne montre pas à un voyageur.
///
/// Un réseau inconnu de cette table n'est pas masqué : il s'affiche sous
/// son code remis en forme, ce qui reste choisissable.
const Map<String, String> kMobileMoneyNetworks = {
  // Bénin : les codes historiques, sans suffixe.
  'mtn_momo': 'MTN MoMo',
  'moov_flooz': 'Moov Flooz',
  'celtiis_cash': 'Celtiis Cash',
  // Côte d'Ivoire et Togo. Le serveur suffixe le pays pour éviter un
  // champ séparé et les divergences réseau/pays (voir ADR 0019) ; le
  // voyageur, lui, n'a que faire du suffixe — il reconnaît sa marque.
  'mtn_momo_ci': 'MTN MoMo',
  'moov_flooz_ci': 'Moov Flooz',
  'wave_ci': 'Wave',
  'orange_money_ci': 'Orange Money',
  'moov_flooz_tg': 'Moov Flooz',
  'tmoney_tg': 'T-Money',
  // Les marques sans pays : c'est sous cette forme que l'écran liste les
  // opérateurs, le suffixe n'étant recollé qu'au moment de payer. Sans
  // ces trois-là, « tmoney » s'affichait « Tmoney ».
  'wave': 'Wave',
  'orange_money': 'Orange Money',
  'tmoney': 'T-Money',
};

/// Le nom lisible d'un réseau : sa marque, ou son code remis en forme.
String mobileMoneyLabel(String network) {
  final known = kMobileMoneyNetworks[network];
  if (known != null) return known;

  return network
      .split('_')
      .where((word) => word.isNotEmpty)
      .map((word) => word[0].toUpperCase() + word.substring(1))
      .join(' ');
}

/// Écran `mobile-money` de la maquette : opérateur, numéro à débiter, et
/// le rappel de sécurité.
///
/// Aucun code secret n'est demandé ici, et c'est le message qui le dit :
/// la validation se fait dans la fenêtre de l'opérateur, sur le téléphone.
/// Un écran qui réclamerait ce code apprendrait au voyageur à le taper
/// ailleurs que chez son opérateur — exactement ce que cherchent ceux qui
/// l'appellent pour le lui soutirer.
class MobileMoneyPage extends StatefulWidget {
  const MobileMoneyPage({super.key, this.args});

  final PaymentArgs? args;

  @override
  State<MobileMoneyPage> createState() => _MobileMoneyPageState();
}

class _MobileMoneyPageState extends State<MobileMoneyPage> {
  late final ({PhoneCountry country, String national}) _initial = _split(
    widget.args?.phone,
  );

  late final TextEditingController _phone = TextEditingController(
    text: _initial.national,
  );

  late PhoneCountry _country = _initial.country;

  /// La **marque** choisie (`mtn_momo`, `wave`…), sans suffixe de pays.
  ///
  /// Le réseau exact — celui que l'API attend — se résout au moment de
  /// payer, en croisant cette marque avec l'indicatif composé : le même
  /// Moov s'appelle `moov_flooz` au Bénin, `moov_flooz_ci` en Côte
  /// d'Ivoire et `moov_flooz_tg` au Togo.
  String? _operator;

  var _showErrors = false;

  /// Vrai dès que le voyageur a appuyé sur « Payer » depuis cet écran.
  ///
  /// La feuille KKiaPay ne s'ouvre que là. Sans ce drapeau, elle
  /// s'ouvrait aussi à la reprise : [PaymentFlowBloc] vit au niveau du
  /// parcours, il redemande au serveur l'état du paiement laissé en
  /// attente au lancement précédent, et ce changement de statut
  /// atteignait ce listener. Le voyageur venu payer *cette* réservation
  /// voyait alors la feuille d'une autre, avec son montant à elle.
  var _requested = false;

  /// La passerelle choisie sur l'écran précédent.
  ///
  /// `null` si l'écran est ouvert sans elle — un lien profond : on
  /// retombe alors sur celle que le catalogue préfère pour la marque.
  PaymentProvider? _gateway(BuildContext context) {
    final catalog = context.read<PaymentCatalogBloc>().state.catalog;
    final chosen = widget.args?.provider;

    if (chosen != null) {
      for (final provider in catalog.mobileMoneyProviders) {
        if (provider.code == chosen) return provider;
      }
    }

    return catalog.mobileMoneyProviders.firstOrNull;
  }

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  /// Le pays desservi que désigne l'indicatif composé.
  ///
  /// Le catalogue ne connaît que le Bénin, la Côte d'Ivoire et le Togo :
  /// un indicatif hors de ces trois-là ne correspond à aucun réseau, et
  /// l'écran le dit plutôt que d'envoyer une demande que le serveur
  /// refuserait.
  String get _countryCode => switch (_country.code) {
    '+229' => 'BJ',
    '+225' => 'CI',
    '+228' => 'TG',
    _ => '',
  };

  /// L'indicatif d'un pays desservi, dans l'autre sens.
  static PhoneCountry? _countryOf(String code) {
    final dialling = switch (code) {
      'BJ' => '+229',
      'CI' => '+225',
      'TG' => '+228',
      _ => null,
    };
    if (dialling == null) return null;

    for (final country in PhoneCountry.all) {
      if (country.code == dialling) return country;
    }
    return null;
  }

  /// Retient une marque, et accorde l'indicatif avec elle.
  ///
  /// Wave et Orange Money sont ivoiriens, T-Money togolais : les choisir
  /// sous l'indicatif béninois ne menait nulle part — l'écran refusait
  /// au moment de payer et demandait de changer d'indicatif. Il le fait
  /// donc lui-même, et ne laisse le choix au voyageur que lorsque la
  /// marque est servie dans plusieurs pays.
  ///
  /// Qui est servi où vient du serveur, pas d'ici : MTN est annoncé au
  /// Bénin, l'indicatif béninois reste donc en place.
  void _chooseOperator(String brand) {
    final served = _gateway(context)?.countriesServing(brand) ?? const [];
    final country = served.contains(_countryCode)
        ? null
        : _countryOf(served.firstOrNull ?? '');

    setState(() => _operator = brand);
    if (country != null) _onCountryChanged(country);
  }

  String? get _operatorError =>
      _operator == null ? context.l10n.paymentMomoOperatorRequired : null;

  /// Vrai quand la saisie suffit à lancer la demande.
  ///
  /// C'est elle qui décide si « Payer » est vif. Le réseau exact, lui,
  /// ne se résout qu'à l'appui : la marque peut ne pas être servie dans
  /// le pays de l'indicatif, et ce cas-là mérite sa phrase plutôt qu'un
  /// bouton éteint sans explication.
  bool get _canPay => _operatorError == null && _phoneError == null;

  String? get _phoneError {
    final l10n = context.l10n;

    // Le préfixe imposé dépend du pays, et de lui seul : le Bénin fait
    // commencer ses numéros par `01`, le Togo n'impose rien. Exiger le
    // `01` partout — ou nulle part — rendrait l'un des deux cas
    // invérifiable. La règle vit dans [PhoneNumberInput], partagée avec
    // les deux autres écrans qui demandent un numéro.
    return switch (PhoneNumberInput.validate(_phone.text, _country)) {
      PhoneIssue.missing => l10n.paymentMomoPhoneRequired,
      // Message générique déjà traduit pour l'écran des permissions :
      // « Un numéro Bénin commence par 01. »
      PhoneIssue.wrongPrefix => l10n.permissionsPhonePrefix(
        _country.name,
        _country.prefix,
      ),
      // « Numéro trop court » laissait deviner de combien : le message
      // dit maintenant la longueur attendue, et le pays qui l'impose —
      // un numéro ivoirien n'a pas le compte d'un numéro togolais.
      PhoneIssue.tooShort => l10n.paymentMomoPhoneShort(
        _country.name,
        _country.digits,
      ),
      null => null,
    };
  }

  /// Change d'indicatif **et** réaccorde le numéro déjà saisi — voir
  /// [PhoneNumberInput.rebase] pour la règle.
  void _onCountryChanged(PhoneCountry? value) {
    final next = value ?? PhoneCountry.benin;
    if (next == _country) return;

    final rebased = PhoneNumberInput.rebase(
      _phone.text,
      from: _country,
      to: next,
    );

    setState(() {
      _country = next;
      if (rebased != _phone.text) _phone.text = rebased;
    });
  }

  /// L'invite du sélecteur : « Sélectionner un opérateur » quand il y a
  /// quelque chose à sélectionner, et la raison du vide sinon.
  String _hint(BuildContext context, PaymentCatalogState catalog) {
    final l10n = context.l10n;

    if (catalog.hasMobileMoney) return l10n.paymentMomoOperatorHint;
    if (catalog.isLoading) return l10n.paymentMethodLoading;

    return catalog.hasFailed
        ? l10n.paymentMethodFailed
        : l10n.paymentMethodNone;
  }

  void _pay() {
    setState(() => _showErrors = true);
    if (_operatorError != null || _phoneError != null) return;
    _requested = true;

    // Un paiement de cet écran est déjà en vol : le Bloc refuse d'en
    // relancer un, à juste titre, et le bouton n'émettait alors plus
    // rien du tout. Le voyageur retapait sur un écran mort. On le mène
    // au suivi, qui interroge le serveur jusqu'à l'issue.
    final flow = context.read<PaymentFlowBloc>().state;
    final pending = flow.attempt;
    if (pending != null &&
        pending.isPending &&
        paymentFlowMatchesHold(context, flow, widget.args)) {
      context.push(AppRouter.paymentPendingPath, extra: _flowArgs(pending));
      return;
    }

    // La passerelle vient de la carte touchée sur l'écran précédent :
    // FedaPay et KKiaPay servent les mêmes réseaux, et ce n'est donc pas
    // le réseau qui peut la désigner.
    final provider = _gateway(context);
    if (provider == null) {
      KoraToaster.show(context, context.l10n.paymentMethodNone);
      return;
    }

    // Le réseau exact se croise ici : la marque choisie plus le pays de
    // l'indicatif composé. Envoyer `moov_flooz` avec un numéro ivoirien
    // ferait débiter un compte dans le mauvais pays — le serveur déduit
    // le pays du réseau, pas du numéro.
    final network = provider.networkFor(_operator!, _countryCode);
    if (network == null) {
      setState(() => _showErrors = true);
      KoraToaster.show(
        context,
        context.l10n.paymentMomoNetworkCountry(
          mobileMoneyLabel(_operator!),
          _country.name,
        ),
      );
      return;
    }

    // Une recharge n'a pas de réservation : le blocage de sièges ne la
    // concerne pas.
    final topUp = widget.args?.topUpAmount;
    final hold = topUp == null ? context.read<SeatHoldBloc>().state.hold : null;
    if (topUp == null && hold == null) {
      // Sans blocage, il n'y a pas de réservation à payer — un lien
      // profond, ou un retour après expiration.
      context.go(AppRouter.holdExpiredPath);
      return;
    }

    context.read<PaymentFlowBloc>().add(
      PaymentFlowStarted(
        reservationId: hold?.id,
        topUpAmount: topUp,
        walletAmount: widget.args?.walletAmount,
        pointsAmount: widget.args?.pointsAmount,
        provider: provider.code,
        network: network,
        phone: '${_country.code}${_phone.text.replaceAll(RegExp(r'\D'), '')}',
      ),
    );
  }

  /// Ce que devient l'écran une fois la demande résolue.
  ///
  /// Rien tant qu'elle vole : l'écran reste en place, sous son voile
  /// d'attente, et dit au voyageur de confirmer sur son téléphone. Les
  /// deux écrans intermédiaires d'avant — « confirmez » puis « vérification
  /// en cours » — n'apprenaient rien de plus, et le second offrait un
  /// bouton « Actualiser » qui menait au succès **sans rien vérifier**.
  void _onFlowChanged(BuildContext context, PaymentFlowState flow) {
    // Le paiement repris au lancement peut être celui d'une autre
    // réservation : il ne pilote pas cet écran-ci.
    if (!paymentFlowMatchesHold(context, flow, widget.args)) return;

    // Cet écran ne réagit qu'au paiement **qu'il a lancé**. Sans cette
    // condition, un paiement déjà en cours — une recharge du même
    // montant laissée en attente — faisait sauter à l'écran de suivi
    // dès l'ouverture : le voyageur n'avait jamais la main pour
    // choisir son opérateur ni saisir son numéro. Le bouton, lui,
    // propose déjà de suivre ce paiement-là.
    if (!_requested) return;

    if (flow.hasFailed) {
      context.push(AppRouter.paymentFailedPath, extra: _flowArgs(flow.attempt));
      return;
    }

    // La demande est partie : l'écran d'attente prend le relais et suivra
    // le paiement jusqu'au verdict.
    if (flow.isPending) {
      final attempt = flow.attempt;

      // KKiaPay n'a pas d'API de poussée : `initiate` n'a contacté
      // personne et rend de quoi ouvrir sa feuille. C'est elle qui
      // recueille le code du payeur, pas l'opérateur sur son téléphone.
      if (attempt != null && attempt.needsWidget) {
        unawaited(_collect(context, attempt));
        return;
      }

      context.push(
        AppRouter.paymentPendingPath,
        extra: _flowArgs(flow.attempt),
      );
      return;
    }

    // Confirmé dès la première réponse — rare, mais possible.
    if (flow.isConfirmed) {
      context.push(
        AppRouter.paymentSuccessPath,
        extra: _flowArgs(flow.attempt),
      );
    }
  }

  /// Ouvre la feuille KKiaPay, puis laisse l'écran d'attente conclure.
  ///
  /// **L'app ne tranche jamais le paiement.** La feuille peut annoncer un
  /// succès ; seul le serveur fait foi, en interrogeant KKiaPay avec
  /// l'identifiant de transaction. On se contente donc de lui transmettre
  /// cet identifiant, et l'écran d'attente suit le verdict comme pour
  /// n'importe quel autre encaisseur.
  ///
  /// Une feuille refermée sans payer ne mène nulle part : le paiement
  /// reste en attente côté serveur, le voyageur revient sur le formulaire
  /// et peut recommencer. Mieux vaut ça qu'un écran d'attente qui
  /// guetterait un paiement que personne n'a lancé.
  Future<void> _collect(BuildContext context, PaymentAttempt attempt) async {
    final flow = context.read<PaymentFlowBloc>();
    final user = context.read<AuthBloc>().state.session?.user;

    final result = await openKkiapayCheckout(
      context,
      widget: attempt.widget!.restrictedToMomo(),
      name: user?.name,
      email: user?.email,
    );

    if (!context.mounted) return;

    switch (result.outcome) {
      case KkiapayOutcome.succeeded:
        // L'identifiant part au serveur, qui seul conclura.
        flow.add(
          PaymentFlowVerifyRequested(transactionId: result.transactionId),
        );
        await context.push(
          AppRouter.paymentPendingPath,
          extra: _flowArgs(attempt),
        );
      case KkiapayOutcome.cancelled:
        // Le voyageur a fermé la feuille : rien à lui dire qu'il ne
        // sache déjà.
        break;
      case KkiapayOutcome.failed:
        KoraToaster.show(context, context.l10n.paymentWidgetFailed);
    }
  }

  PaymentFlowArgs _flowArgs(PaymentAttempt? attempt) {
    return PaymentFlowArgs(
      total: widget.args!.total,
      providerLabel: mobileMoneyLabel(_operator ?? ''),
      reference: attempt?.reference,
      expiresAt: attempt?.expiresAt,
      pollingInterval: attempt?.pollingInterval,
      departure: widget.args!.departure,
      seatLabel: widget.args!.seatLabel,
      isTopUp: widget.args!.isTopUp,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = widget.args;

    if (args == null) return const PaymentMissingArgs();

    return BlocConsumer<PaymentFlowBloc, PaymentFlowState>(
      // Seuls les changements d'état comptent : sans ce filtre, le drapeau
      // de vérification manuelle rejouerait la navigation.
      listenWhen: (previous, current) => previous.status != current.status,
      listener: _onFlowChanged,
      // Le bouton porte déjà le chargement de l'appel ; dès qu'il répond,
      // l'écran d'attente prend la suite.
      builder: (context, flow) => _form(context, args, l10n),
    );
  }

  /// Le bas de l'écran : « Payer », ou la sortie qui le remplace.
  ///
  /// Un paiement déjà ouvert ne se relance pas — [PaymentFlowBloc] refuse
  /// la seconde demande pour ne pas débiter deux fois. Le bouton « Payer »
  /// n'aurait donc plus aucun effet, et c'est ce qui arrivait au voyageur
  /// revenu ici par le geste « retour » depuis l'écran d'échec : il
  /// appuyait dans le vide. On le retire, et on met à sa place ce qui,
  /// lui, mène quelque part.
  ///
  /// Les deux boutons de l'écran d'échec continuent de fonctionner : ils
  /// remettent le paiement à zéro avant de revenir, si bien qu'on retrouve
  /// ici un « Payer » vivant.
  /// Clôt la demande en cours, puis rend la main.
  void _abandon(BuildContext context) {
    _requested = false;
    context.read<PaymentFlowBloc>().add(const PaymentFlowAbandoned());
    KoraToaster.show(context, context.l10n.paymentAbandonDone);
  }

  Widget _payButton(BuildContext context, PaymentFlowState flow) {
    final l10n = context.l10n;

    // Le paiement suit son cours ailleurs : on y ramène plutôt que de
    // laisser croire qu'il faut recommencer. Celui d'une autre
    // réservation — ou d'un billet, quand on recharge le Wallet — ne
    // doit rien verrouiller ici : sans `args`, un vieux paiement
    // remplaçait « Payer » par « Suivre le paiement en cours » et la
    // recharge ne pouvait plus partir.
    if (flow.isPending && paymentFlowMatchesHold(context, flow, widget.args)) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          KoraButton(
            label: l10n.paymentMomoResume,
            variant: KoraButtonVariant.ghost,
            onPressed: () => context.push(
              AppRouter.paymentPendingPath,
              extra: _flowArgs(flow.attempt),
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          // Sans cette porte, une demande en attente verrouillait son
          // montant : la clé d'idempotence rendait la même à chaque
          // essai, et le voyageur ne pouvait ni la payer ni en ouvrir
          // une autre.
          KoraButton(
            label: l10n.paymentAbandon,
            variant: KoraButtonVariant.ghost,
            onPressed: () => _abandon(context),
          ),
        ],
      );
    }

    // Un paiement refusé ne verrouille plus l'écran : le voyageur corrige
    // son numéro et réessaie ici même. C'est ce qu'il fait naturellement,
    // et le renvoyer au choix du moyen pour ça n'apportait rien — le
    // bouton « Payer » ci-dessous remet la demande à zéro avant de
    // repartir.

    return KoraButton(
      label: l10n.paymentMomoPay(widget.args!.total.formatted),
      // Le bouton se verrouille le temps de l'appel : deux appuis pendant
      // que la demande vole ouvriraient deux paiements, et la clé
      // d'idempotence n'est qu'un filet — mieux vaut ne pas avoir à s'en
      // servir.
      //
      // Il reste inerte, aussi, tant que l'opérateur n'est pas choisi ou
      // que le numéro ne tient pas : un bouton vif qui ne fait
      // qu'afficher deux reproches se lit comme une panne.
      isLoading: flow.isStarting,
      onPressed: flow.isStarting || !_canPay ? null : _pay,
    );
  }

  Widget _form(BuildContext context, PaymentArgs args, AppLocalization l10n) {
    final koraColors = context.koraColors;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                // Une recharge n'achète pas de billet : le surtitre le
                // dit, comme sur la feuille de la passerelle.
                eyebrow: args.isTopUp
                    ? l10n.paymentTopUpEyebrow
                    : l10n.paymentMomoEyebrow(args.total.formatted),
                title: l10n.paymentMomoTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    const HoldCountdown(margin: EdgeInsets.only(bottom: 11)),
                    // Le montant d'abord, comme sur un terminal de
                    // paiement : c'est ce que le voyageur vérifie avant
                    // de choisir par où il paie.
                    PaymentAmountCard(total: args.total.formatted),
                    const SizedBox(height: AppSpacing.md),
                    SectionTitle(title: l10n.paymentMomoOperator),
                    BlocBuilder<PaymentCatalogBloc, PaymentCatalogState>(
                      builder: (context, catalog) {
                        if (!catalog.hasMobileMoney) {
                          return KoraCard(
                            variant: KoraCardVariant.mist,
                            child: Text(
                              _hint(context, catalog),
                              style: AppTextStyles.body(koraColors.textMuted),
                            ),
                          );
                        }

                        // Les marques de **cette** passerelle, chacune une
                        // fois : ses réseaux comptent trois Moov — un par
                        // pays desservi — et les lister tels quels
                        // afficherait trois fois le même opérateur.
                        final brands =
                            _gateway(context)?.networkBrands ??
                            const <String>[];

                        // Deux par ligne : en colonne, les six
                        // opérateurs repoussaient le champ du numéro
                        // sous le pli, et le voyageur appuyait sur
                        // « Payer » sans l'avoir vu.
                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            for (var row = 0; row < brands.length; row += 2)
                              Padding(
                                padding: const EdgeInsets.only(
                                  bottom: AppSpacing.sm,
                                ),
                                // `IntrinsicHeight` donne aux deux
                                // vignettes la hauteur de la plus haute :
                                // « Orange Money » tient sur deux lignes
                                // là où « Wave » n'en prend qu'une. Sans
                                // lui, `stretch` n'aurait aucune hauteur
                                // à étirer dans une liste qui défile.
                                child: IntrinsicHeight(
                                  child: Row(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.stretch,
                                    children: [
                                      for (var col = row; col < row + 2; col++)
                                        Expanded(
                                          // La ligne impaire garde sa
                                          // moitié vide : sans elle, le
                                          // dernier opérateur s'étirerait
                                          // sur toute la largeur et ne
                                          // ressemblerait plus aux autres.
                                          child: col < brands.length
                                              ? Padding(
                                                  padding: EdgeInsets.only(
                                                    left: col == row
                                                        ? 0
                                                        : AppSpacing.sm / 2,
                                                    right: col == row
                                                        ? AppSpacing.sm / 2
                                                        : 0,
                                                  ),
                                                  child: OperatorTile(
                                                    network: brands[col],
                                                    selected:
                                                        _operator ==
                                                        brands[col],
                                                    onTap: () =>
                                                        _chooseOperator(
                                                          brands[col],
                                                        ),
                                                  ),
                                                )
                                              : const SizedBox.shrink(),
                                        ),
                                    ],
                                  ),
                                ),
                              ),
                          ],
                        );
                      },
                    ),
                    const SizedBox(height: AppSpacing.md),
                    LabelledField(
                      label: l10n.paymentMomoPhone,
                      // Le message sous le bloc entier, pas sous le seul
                      // champ : celui-ci partage sa ligne avec
                      // l'indicatif, et une phrase y tombait sur trois
                      // lignes dont le bouton de l'écran rognait la fin.
                      error: _showErrors || _phone.text.isNotEmpty
                          ? _phoneError
                          : null,
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Même paire indicatif + numéro que l'écran des
                          // permissions : le voyageur qui débite un compte
                          // togolais depuis le Bénin doit pouvoir le dire.
                          SizedBox(
                            width: 124,
                            child: DropdownButtonFormField<PhoneCountry>(
                              initialValue: _country,
                              isExpanded: true,
                              onChanged: _onCountryChanged,
                              items: [
                                for (final item in PhoneCountry.all)
                                  DropdownMenuItem(
                                    value: item,
                                    child: Text(
                                      '${item.flag}  ${item.code}',
                                      style: AppTextStyles.body(
                                        koraColors.textPrimary,
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: KoraInput(
                              hint: _country.example,
                              controller: _phone,
                              keyboardType: TextInputType.phone,
                              // Le liseré rouge seulement : la raison
                              // s'écrit sous le bloc. Elle n'attend plus
                              // l'appui sur « Payer », qui est désormais
                              // inerte tant que la saisie ne tient pas.
                              invalid:
                                  (_showErrors || _phone.text.isNotEmpty) &&
                                  _phoneError != null,
                              onChanged: (_) => setState(() {}),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    // Le message qui protège le voyageur : personne ne
                    // doit lui demander son code secret. Il se lisait en
                    // gris, dans une carte de la même couleur que le
                    // reste — autant dire qu'il ne se lisait pas. Il
                    // reprend donc la forme du décompte, la seule que
                    // l'œil retient dans ce tunnel.
                    _SecurityNotice(
                      title: l10n.paymentMomoNoticeTitle,
                      message: l10n.paymentMomoNotice,
                    ),
                    // Le bouton d'assistance flotte au-dessus de cette
                    // liste, 84 px au-dessus du bord : sans cet espace,
                    // le dernier élément restait sous lui une fois la
                    // liste arrivée en bout de course — et c'était le
                    // rappel de sécurité, la seule chose que le voyageur
                    // doive lire avant d'appuyer.
                    const SizedBox(height: 104),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: BlocBuilder<PaymentFlowBloc, PaymentFlowState>(
                  builder: _payButton,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Le rappel de sécurité, posé comme le décompte des sièges : une
/// pastille dorée, un surtitre, et la phrase en toutes lettres.
///
/// C'est la seule information de l'écran que le voyageur doit lire
/// **avant** d'appuyer : aucune fenêtre KORA ne lui demandera jamais son
/// code Mobile Money, et c'est exactement ce qu'on lui soutire au
/// téléphone.
class _SecurityNotice extends StatelessWidget {
  const _SecurityNotice({required this.title, required this.message});

  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(AppRadius.input),
        border: Border.all(color: AppColors.gold.withValues(alpha: 0.45)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: const BoxDecoration(
              color: AppColors.gold,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.shield_rounded,
              size: 18,
              color: AppColors.kora,
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title.toUpperCase(),
                  style: AppTextStyles.eyebrow(koraColors.textMuted),
                ),
                const SizedBox(height: 3),
                Text(
                  message,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 12,
                    height: 1.4,
                    fontWeight: FontWeight.w700,
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

/// Un opérateur, présenté comme on le choisit à une caisse : sa
/// vignette, son nom, et une pastille qui dit lequel est retenu.
///
/// La liste des réseaux vient du serveur ; un code inconnu garde donc sa
/// place, avec une vignette neutre plutôt qu'un trou.
/// Sépare un numéro enregistré en indicatif et partie nationale.
///
/// Le compte KORA stocke le numéro complet (`+229 0152235689`) ; l'écran,
/// lui, sépare les deux pour pouvoir proposer un autre pays. À défaut de
/// reconnaître l'indicatif, on retombe sur le Bénin et on garde les
/// chiffres tels quels : mieux vaut un pays à corriger qu'un champ vide.
({PhoneCountry country, String national}) _split(String? stored) {
  final raw = (stored ?? '').trim();
  if (raw.isEmpty) return (country: PhoneCountry.benin, national: '');

  for (final country in PhoneCountry.all) {
    if (raw.startsWith(country.code)) {
      return (
        country: country,
        national: raw.substring(country.code.length).trim(),
      );
    }
  }

  return (country: PhoneCountry.benin, national: raw);
}
