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
import '../../../booking/presentation/bloc/seat_hold_bloc.dart';
import '../../../booking/presentation/widgets/hold_countdown.dart';
import '../../domain/entities/payment_attempt.dart';
import '../bloc/payment_catalog_bloc.dart';
import '../bloc/payment_flow_bloc.dart';
import '../widgets/labelled_field.dart';
import '../widgets/operator_badge.dart';
import '../widgets/payment_amount_card.dart';
import 'payment_args.dart';
import 'payment_flow_args.dart';
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
  'mtn_momo': 'MTN MoMo',
  'moov_flooz': 'Moov Flooz',
  'celtiis_cash': 'Celtiis Cash',
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

  String? _operator;
  var _showErrors = false;

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  String? get _operatorError =>
      _operator == null ? context.l10n.paymentMomoOperatorRequired : null;

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
      PhoneIssue.tooShort => l10n.paymentMomoPhoneShort,
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

    final network = _operator!;

    // Le voyageur choisit un **réseau** (`mtn_momo`), pas un encaisseur :
    // il a un compte MTN, pas un compte FedaPay. Le provider s'en déduit
    // par le catalogue, qui range ses encaisseurs par ordre de préférence.
    final provider = context
        .read<PaymentCatalogBloc>()
        .state
        .catalog
        .providerForNetwork(network);

    // Aucun encaisseur pour ce réseau : le serveur refuserait l'appel.
    // Le sélecteur ne propose que des réseaux servis, donc ce cas ne
    // survient qu'entre deux rechargements du catalogue.
    if (provider == null) {
      KoraToaster.show(context, context.l10n.paymentMethodNone);
      return;
    }

    final hold = context.read<SeatHoldBloc>().state.hold;
    if (hold == null) {
      // Sans blocage, il n'y a pas de réservation à payer — un lien
      // profond, ou un retour après expiration.
      context.go(AppRouter.holdExpiredPath);
      return;
    }

    context.read<PaymentFlowBloc>().add(
      PaymentFlowStarted(
        reservationId: hold.id,
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
    if (flow.hasFailed) {
      context.push(AppRouter.paymentFailedPath, extra: _flowArgs(flow.attempt));
      return;
    }

    // La demande est partie : l'écran d'attente prend le relais et suivra
    // le paiement jusqu'au verdict.
    if (flow.isPending) {
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

  PaymentFlowArgs _flowArgs(PaymentAttempt? attempt) {
    return PaymentFlowArgs(
      total: widget.args!.total,
      providerLabel: mobileMoneyLabel(_operator ?? ''),
      reference: attempt?.reference,
      expiresAt: attempt?.expiresAt,
      pollingInterval: attempt?.pollingInterval,
      departure: widget.args!.departure,
      seatLabel: widget.args!.seatLabel,
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
  Widget _payButton(BuildContext context, PaymentFlowState flow) {
    final l10n = context.l10n;

    // Le paiement suit son cours ailleurs : on y ramène plutôt que de
    // laisser croire qu'il faut recommencer.
    if (flow.isPending) {
      return KoraButton(
        label: l10n.paymentMomoResume,
        variant: KoraButtonVariant.ghost,
        onPressed: () => context.push(
          AppRouter.paymentPendingPath,
          extra: _flowArgs(flow.attempt),
        ),
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
      isLoading: flow.isStarting,
      onPressed: flow.isStarting ? null : _pay,
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
                eyebrow: l10n.paymentMomoEyebrow(args.total.formatted),
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

                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            for (final network in catalog.mobileMoneyNetworks)
                              Padding(
                                padding: const EdgeInsets.only(
                                  bottom: AppSpacing.sm,
                                ),
                                child: _OperatorTile(
                                  network: network,
                                  selected: _operator == network,
                                  onTap: () =>
                                      setState(() => _operator = network),
                                ),
                              ),
                            if (_showErrors && _operatorError != null)
                              Text(
                                _operatorError!,
                                style: AppTextStyles.caption(AppColors.danger),
                              ),
                          ],
                        );
                      },
                    ),
                    const SizedBox(height: AppSpacing.md),
                    LabelledField(
                      label: l10n.paymentMomoPhone,
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
                              errorText: _showErrors ? _phoneError : null,
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
                    const SizedBox(height: AppSpacing.xl),
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
class _OperatorTile extends StatelessWidget {
  const _OperatorTile({
    required this.network,
    required this.selected,
    required this.onTap,
  });

  final String network;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.sm),
        decoration: BoxDecoration(
          color: koraColors.surface,
          borderRadius: BorderRadius.circular(AppRadius.md),
          border: Border.all(
            color: selected ? AppColors.gold : koraColors.line,
            width: selected ? 2 : 1,
          ),
        ),
        child: Row(
          children: [
            OperatorBadge(network: network),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Text(
                mobileMoneyLabel(network),
                style: AppTextStyles.titleMedium(context.koraTitleColor),
              ),
            ),
            Icon(
              selected
                  ? Icons.check_circle_rounded
                  : Icons.radio_button_unchecked_rounded,
              size: 21,
              color: selected ? AppColors.gold : koraColors.line,
            ),
          ],
        ),
      ),
    );
  }
}

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
