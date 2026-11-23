import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/brand_mini.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../payment/presentation/widgets/operator_badge.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../bloc/colis_draft_cubit.dart';
import '../widgets/colis_banner.dart';
import '../widgets/colis_surface.dart';
import 'colis_checkout_args.dart';

/// Écran 08 — « Paiement de l'expédition ».
///
/// **Aucun paiement réel n'est déclenché** : le ticket l'interdit et
/// aucun endpoint n'existe. L'écran compose le montant, laisse choisir
/// entre le solde KORA et un encaisseur, puis passe à la confirmation.
///
/// Le Wallet se déduit avant l'encaisseur : c'est de l'argent déjà chez
/// KORA, et le faire payer deux fois serait un comble.
///
/// La maquette fait de ce choix une liste à cocher, suivie d'un bouton
/// qui emmène vers l'écran de l'encaisseur retenu — et non d'une suite
/// de cartes qui s'ouvrent au toucher. L'écran suivant reste le même
/// que pour un billet : FedaPay réclame l'opérateur et le numéro,
/// KKiaPay encaisse dans sa propre feuille.
class ColisPaymentPage extends StatefulWidget {
  const ColisPaymentPage({super.key});

  @override
  State<ColisPaymentPage> createState() => _ColisPaymentPageState();
}

class _ColisPaymentPageState extends State<ColisPaymentPage> {
  var _useWallet = true;

  /// L'encaisseur coché. Le premier l'est d'emblée, comme la maquette :
  /// un écran de paiement qui n'a rien de sélectionné laisse croire
  /// qu'il manque une étape.
  var _gatewayId = kColisGateways.first.id;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return BlocBuilder<ColisDraftCubit, ColisDraft>(
      builder: (context, draft) {
        final wallet = _useWallet
            ? (kColisWalletBalance < draft.total
                  ? kColisWalletBalance
                  : draft.total)
            : 0;
        final due = draft.total - wallet;

        return Scaffold(
          body: SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ScreenHeader(
                    leading: const BrandMini(),
                    eyebrow: l10n.parcelsEyebrow,
                    title: l10n.parcelsTitle,
                  ),
                  Expanded(
                    child: ListView(
                      padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                      children: [
                        // L'en-tête de la maquette : trois lignes de
                        // texte, sans carte. Le bandeau qui tenait cette
                        // place pesait autant que le devis qu'il
                        // annonçait.
                        _PaymentHead(draft: draft),
                        const SizedBox(height: AppSpacing.md),
                        _DueCard(draft: draft),
                        const SizedBox(height: AppSpacing.sm),
                        _WalletCard(
                          enabled: _useWallet,
                          applied: wallet,
                          due: due,
                          onChanged: (value) =>
                              setState(() => _useWallet = value),
                        ),
                        if (due > 0) ...[
                          const SizedBox(height: AppSpacing.md),
                          Padding(
                            padding: const EdgeInsets.only(left: 4),
                            child: Text(
                              l10n.colisOperators,
                              style: TextStyle(
                                color: context.koraTitleColor,
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          const SizedBox(height: 8),
                          for (final (index, gateway)
                              in kColisGateways.indexed) ...[
                            _GatewayTile(
                              gateway: gateway,
                              selected: gateway.id == _gatewayId,
                              recommended: index == 0,
                              onTap: () =>
                                  setState(() => _gatewayId = gateway.id),
                            ),
                            const SizedBox(height: 10),
                          ],
                        ],
                        const SizedBox(height: AppSpacing.sm),
                        // Ce que le règlement déclenche, dit avant de
                        // choisir par où payer : la référence officielle
                        // et le code de retrait en dépendent.
                        _RegistrationNote(text: l10n.colisAfterPaymentNote),
                        const SizedBox(height: AppSpacing.sm),
                        // Rien ne part vers un opérateur : la maquette
                        // s'arrête au geste, et le ticket aussi.
                        ColisNote(
                          icon: Icons.lock_outline_rounded,
                          title: l10n.colisPaymentMockTitle,
                          message: l10n.colisPaymentMockText,
                        ),
                        if (due > 0) ...[
                          const SizedBox(height: AppSpacing.md),
                          KoraButton(
                            label: l10n.colisPaymentContinue(
                              l10n.colisAmount(due),
                            ),
                            icon: Icons.arrow_forward_rounded,
                            iconPosition: KoraButtonIconPosition.edges,
                            onPressed: () {
                              final gateway = colisGatewayOf(_gatewayId);

                              context.push(
                                colisCheckoutPathOf(gateway),
                                extra: ColisCheckoutArgs(
                                  gatewayId: gateway.id,
                                  due: due,
                                  walletApplied: wallet,
                                ),
                              );
                            },
                          ),
                        ],
                        const SizedBox(height: AppSpacing.sm),
                        // Revenir au devis se dit en lien, pas en
                        // bouton : c'est la sortie de l'écran, et un
                        // second pavé à côté du premier ferait deux
                        // actions de même poids.
                        Center(
                          child: InkWell(
                            onTap: () => context.pop(),
                            borderRadius: BorderRadius.circular(AppRadius.pill),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 8,
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(
                                    Icons.edit_note_rounded,
                                    size: 17,
                                    color: koraColors.textMuted,
                                  ),
                                  const SizedBox(width: 6),
                                  Flexible(
                                    child: Text(
                                      l10n.colisEditQuote,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        color: koraColors.textMuted,
                                        fontSize: 13,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

/// L'en-tête de l'écran : d'où vient ce montant, et sur quoi il porte.
///
/// Le bleu digital et le bouclier disent en une ligne que le devis a été
/// arrêté au hub, pesée faite — c'est ce qui autorise à encaisser.
class _PaymentHead extends StatelessWidget {
  const _PaymentHead({required this.draft});

  final ColisDraft draft;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            const Icon(
              Icons.verified_user_outlined,
              size: 17,
              color: AppColors.digital,
            ),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                l10n.colisQuoteIssued.toUpperCase(),
                style: AppTextStyles.eyebrow(AppColors.digital),
              ),
            ),
          ],
        ),
        const SizedBox(height: 5),
        Text(
          l10n.colisPaymentTitle,
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: 23,
            height: 1.15,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          l10n.colisPaymentSubtitle(
            draft.originHub?.city ?? '',
            draft.destinationHub?.city ?? '',
          ),
          style: TextStyle(
            color: koraColors.textMuted,
            fontSize: 13,
            height: 1.4,
          ),
        ),
      ],
    );
  }
}

/// Le montant dû, repris du devis certifié.
///
/// Le corridor en tête, le poids facturé en pastille, les deux lignes de
/// frais dans un encart gris, et le total seul au-dessous : c'est la
/// construction de la maquette, et elle fait lire le total en dernier.
class _DueCard extends StatelessWidget {
  const _DueCard({required this.draft});

  final ColisDraft draft;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.colisCorridorTitle.toUpperCase(),
                      style: AppTextStyles.eyebrow(koraColors.textMuted),
                    ),
                    const SizedBox(height: 3),
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            draft.originHub?.city ?? '',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: context.koraTitleColor,
                              fontSize: 15,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Icon(
                          Icons.arrow_forward_rounded,
                          size: 15,
                          color: koraColors.textMuted,
                        ),
                        const SizedBox(width: 6),
                        Flexible(
                          child: Text(
                            draft.destinationHub?.city ?? '',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: context.koraTitleColor,
                              fontSize: 15,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              // Le poids facturé, en pastille grise : c'est lui qui
              // explique le montant, et il doit se lire à côté.
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 5,
                ),
                decoration: BoxDecoration(
                  color: koraColors.background,
                  borderRadius: BorderRadius.circular(AppRadius.pill),
                ),
                child: Text(
                  l10n.colisBalanceTag(colisWeightText(draft.billableWeightKg)),
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(11),
            decoration: colisInsetDecoration(context),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                ColisAmountRow(
                  label: l10n.colisTransportFee(
                    draft.destinationHub?.city ?? '',
                    colisWeightText(draft.billableWeightKg),
                  ),
                  value: l10n.colisAmount(draft.transportFee),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: Row(
                        children: [
                          Flexible(
                            child: Text(
                              l10n.colisSealFee,
                              style: AppTextStyles.body(koraColors.textMuted),
                            ),
                          ),
                          const SizedBox(width: 5),
                          // Le cadenas vert : ce supplément est ce qui
                          // rend le colis inviolable, pas un frais de
                          // dossier.
                          const Icon(
                            Icons.lock_rounded,
                            size: 14,
                            color: AppColors.success,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 10),
                    Text(
                      l10n.colisAmount(kColisSealFee),
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          ColisAmountRow(
            label: l10n.colisTotalDue,
            value: l10n.colisAmount(draft.total),
            isTotal: true,
          ),
        ],
      ),
    );
  }
}

/// Le solde KORA, déductible du montant dû.
///
/// La tuile marine à pictogramme doré de la maquette : c'est le seul
/// moyen de paiement de l'écran qui soit KORA, et il se distingue des
/// encaisseurs par sa couleur avant son libellé.
class _WalletCard extends StatelessWidget {
  const _WalletCard({
    required this.enabled,
    required this.applied,
    required this.due,
    required this.onChanged,
  });

  final bool enabled;

  /// Ce que le solde couvre réellement : jamais plus que le montant dû.
  final int applied;

  final int due;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.kora,
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                ),
                child: const Icon(
                  Icons.account_balance_wallet_outlined,
                  size: 20,
                  color: AppColors.gold,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.colisWalletTitle,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      l10n.colisWalletBalance(kColisWalletBalance),
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ],
                ),
              ),
              Switch(value: enabled, onChanged: onChanged),
            ],
          ),
          if (enabled) ...[
            const SizedBox(height: 11),
            // La déduction en vert, coche comprise : c'est une somme en
            // moins à payer, et la maquette la met du côté des bonnes
            // nouvelles.
            Row(
              children: [
                const Icon(
                  Icons.check_circle_rounded,
                  size: 16,
                  color: AppColors.success,
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    l10n.colisWalletApplied,
                    style: const TextStyle(
                      color: AppColors.success,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                Text(
                  '− ${l10n.colisAmount(applied)}',
                  style: const TextStyle(
                    color: AppColors.success,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 9),
            // Le reste à payer dans son encart : c'est ce chiffre que
            // l'encaisseur va demander, et il ne doit pas se confondre
            // avec le total du devis.
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9),
              decoration: colisInsetDecoration(context),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      l10n.colisRemainingDue,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  Text(
                    l10n.colisAmount(due),
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 15,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// L'écran qui encaisse pour cette passerelle.
///
/// Deux chemins, comme pour un billet : KKiaPay ouvre sa propre feuille,
/// et FedaPay pousse la demande sur le téléphone — il lui faut donc
/// l'opérateur et le numéro d'avance. Le troisième, la fenêtre de
/// saisie carte, ne se propose plus pour une expédition.
String colisCheckoutPathOf(ColisGateway gateway) {
  if (gateway.isCard) return AppRouter.colisCardPath;
  return gateway.collectsInSheet
      ? AppRouter.colisGatewayPath
      : AppRouter.colisMomoPath;
}

/// Un encaisseur à cocher : son logo, son nom, ce qu'il promet, et le
/// bouton radio qui dit qu'un seul sera débité.
///
/// La maquette empile le nom puis une ligne de qualificatifs — une
/// pastille et une phrase courte — et pose le radio à droite. Le logo
/// reste la première chose qu'on voit.
class _GatewayTile extends StatelessWidget {
  const _GatewayTile({
    required this.gateway,
    required this.selected,
    required this.onTap,
    this.recommended = false,
  });

  final ColisGateway gateway;
  final bool selected;
  final VoidCallback onTap;

  /// La pastille verte de la maquette, sur la première option.
  final bool recommended;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Material(
      color: selected ? koraColors.background : koraColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.md),
            boxShadow: selected ? null : kColisCardShadow,
          ),
          child: Row(
            children: [
              // La tuile grise de la maquette : le logo y est posé, et
              // la carte reste blanche. Un encadré blanc sur blanc ne
              // se distinguait pas du fond de la carte.
              //
              // 56 px et non 40 : c'est le logo qu'on cherche du regard
              // avant le nom, et à 40 px il se lisait mal — « FedaPay »
              // et « KKiaPay » se ressemblent trop en petit.
              Container(
                width: 56,
                height: 56,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: selected ? koraColors.surface : koraColors.background,
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                ),
                clipBehavior: Clip.antiAlias,
                child: gateway.logo == null
                    ? Icon(
                        Icons.credit_card_rounded,
                        size: 26,
                        color: context.koraTitleColor,
                      )
                    : Padding(
                        padding: const EdgeInsets.all(6),
                        child: Image.asset(gateway.logo!, fit: BoxFit.contain),
                      ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      gateway.name,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 15,
                        height: 1.15,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        if (recommended) ...[
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 7,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.success.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(AppRadius.sm),
                            ),
                            child: Text(
                              l10n.colisRecommended,
                              style: const TextStyle(
                                color: AppColors.success,
                                fontSize: 10,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          const SizedBox(width: 6),
                        ],
                        Expanded(
                          child: Text(
                            gateway.isCard
                                ? l10n.paymentMethodCardLead
                                : l10n.colisGatewayMomoLead,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTextStyles.caption(koraColors.textMuted),
                          ),
                        ),
                      ],
                    ),
                    // Les logos des opérateurs sous les deux passerelles
                    // Mobile Money : on reconnaît son réseau avant
                    // d'avoir lu la ligne.
                    if (!gateway.isCard) ...[
                      const SizedBox(height: 7),
                      Row(
                        children: [
                          for (final network in kColisMomoNetworks)
                            Padding(
                              padding: const EdgeInsets.only(right: 6),
                              child: OperatorBadge(network: network, size: 30),
                            ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 8),
              // Le bouton radio de la maquette, à la place du chevron :
              // le choix se coche ici, et c'est le bouton du bas qui
              // emmène vers l'écran de l'encaisseur.
              Icon(
                selected
                    ? Icons.radio_button_checked_rounded
                    : Icons.radio_button_unchecked_rounded,
                size: 22,
                color: selected ? context.koraTitleColor : koraColors.textMuted,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Ce que l'enregistrement déclenche, une fois le règlement passé.
///
/// Fond gris et tuile marine à pictogramme doré : la maquette le
/// distingue ainsi des notes blanches de l'écran — c'est la seule
/// promesse que l'app fait ici, et elle ne doit pas se lire comme une
/// mention de bas de page.
class _RegistrationNote extends StatelessWidget {
  const _RegistrationNote({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: koraColors.background,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 28,
            height: 28,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.kora,
              borderRadius: BorderRadius.circular(AppRadius.sm),
            ),
            child: const Icon(
              Icons.lock_clock_outlined,
              size: 17,
              color: AppColors.gold,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: context.koraTitleColor,
                fontSize: 12.5,
                height: 1.45,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
