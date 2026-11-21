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
import 'colis_checkout_args.dart';

/// Écran 08 — « Choix du moyen de paiement ».
///
/// **Aucun paiement réel n'est déclenché** : le ticket l'interdit et
/// aucun endpoint n'existe. L'écran compose le montant, laisse choisir
/// entre le solde KORA et un opérateur, puis passe à la confirmation.
///
/// Le Wallet se déduit avant l'opérateur : c'est de l'argent déjà chez
/// KORA, et le faire payer deux fois serait un comble.
class ColisPaymentPage extends StatefulWidget {
  const ColisPaymentPage({super.key});

  @override
  State<ColisPaymentPage> createState() => _ColisPaymentPageState();
}

class _ColisPaymentPageState extends State<ColisPaymentPage> {
  var _useWallet = true;

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
                        ColisBanner(
                          tag: l10n.colisQuoteIssued,
                          tagVariant: KoraTagVariant.success,
                          caption: draft.originHub?.counter ?? '',
                          title: l10n.colisPaymentTitle,
                          text: l10n.colisPaymentSubtitle(
                            draft.originHub?.city ?? '',
                            draft.destinationHub?.city ?? '',
                          ),
                        ),
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
                          Text(
                            l10n.colisOperators.toUpperCase(),
                            style: AppTextStyles.eyebrow(koraColors.textMuted),
                          ),
                          const SizedBox(height: 8),
                          // Choisir l'encaisseur **est** le geste : comme
                          // pour un billet, il n'y a pas de bouton
                          // « Payer » ici — la carte touchée ouvre
                          // l'écran qui encaisse.
                          for (final gateway in kColisGateways) ...[
                            _GatewayTile(
                              gateway: gateway,
                              onTap: () => context.push(
                                colisCheckoutPathOf(gateway),
                                extra: ColisCheckoutArgs(
                                  gatewayId: gateway.id,
                                  due: due,
                                  walletApplied: wallet,
                                ),
                              ),
                            ),
                            const SizedBox(height: 10),
                          ],
                        ],
                        const SizedBox(height: AppSpacing.sm),
                        // Rien ne part vers un opérateur : la maquette
                        // s'arrête au geste, et le ticket aussi.
                        KoraAlert(
                          icon: Icons.lock_outline_rounded,
                          title: l10n.colisPaymentMockTitle,
                          message: l10n.colisPaymentMockText,
                        ),
                      ],
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                    child: KoraButton(
                      label: l10n.colisEditQuote,
                      variant: KoraButtonVariant.ghost,
                      onPressed: () => context.pop(),
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

/// Le montant dû, repris du devis certifié.
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
        borderRadius: BorderRadius.circular(19),
        border: Border.all(color: koraColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            '${draft.originHub?.city}  →  ${draft.destinationHub?.city}',
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 14,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          ColisAmountRow(
            label: l10n.colisTransportFee(
              draft.destinationHub?.city ?? '',
              colisWeightText(draft.billableWeightKg),
            ),
            value: l10n.colisAmount(draft.transportFee),
          ),
          const SizedBox(height: 9),
          ColisAmountRow(
            label: l10n.colisSealFee,
            value: l10n.colisAmount(kColisSealFee),
          ),
          const SizedBox(height: 11),
          Divider(height: 1, color: koraColors.line),
          const SizedBox(height: 11),
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
        borderRadius: BorderRadius.circular(19),
        border: Border.all(color: koraColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Container(
                width: 34,
                height: 34,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: koraColors.background,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  Icons.account_balance_wallet_outlined,
                  size: 18,
                  color: context.koraTitleColor,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.colisWalletTitle,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 13,
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
            const SizedBox(height: 10),
            ColisAmountRow(
              label: l10n.colisWalletApplied,
              value: '− ${l10n.colisAmount(applied)}',
            ),
            const SizedBox(height: 8),
            ColisAmountRow(
              label: l10n.colisRemainingDue,
              value: l10n.colisAmount(due),
              isTotal: true,
            ),
          ],
        ],
      ),
    );
  }
}

/// L'écran qui encaisse pour cette passerelle.
///
/// Trois chemins, comme pour un billet : KKiaPay ouvre sa propre
/// feuille, la carte se saisit dans celle du prestataire, et FedaPay
/// pousse la demande sur le téléphone — il lui faut donc l'opérateur
/// et le numéro d'avance.
String colisCheckoutPathOf(ColisGateway gateway) {
  if (gateway.isCard) return AppRouter.colisCardPath;
  return gateway.collectsInSheet
      ? AppRouter.colisGatewayPath
      : AppRouter.colisMomoPath;
}

/// Un encaisseur : son logo, son nom, sa promesse, et le chevron qui
/// dit qu'un écran suit.
class _GatewayTile extends StatelessWidget {
  const _GatewayTile({required this.gateway, required this.onTap});

  final ColisGateway gateway;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return KoraCard(
      onTap: onTap,
      child: Row(
        children: [
          if (gateway.logo case final asset?)
            Container(
              width: 38,
              height: 38,
              alignment: Alignment.center,
              // Le blanc derrière le logo : la marque porte son
              // propre fond, et le nôtre le salirait.
              decoration: BoxDecoration(
                color: AppColors.white,
                borderRadius: BorderRadius.circular(AppRadius.input),
                border: Border.all(color: koraColors.line),
              ),
              clipBehavior: Clip.antiAlias,
              child: Padding(
                padding: const EdgeInsets.all(3),
                child: Image.asset(asset, fit: BoxFit.contain),
              ),
            )
          else
            Container(
              width: 38,
              height: 38,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: AppColors.gold,
                borderRadius: BorderRadius.circular(AppRadius.input),
              ),
              child: const Icon(
                Icons.credit_card_rounded,
                size: 20,
                color: AppColors.kora,
              ),
            ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  gateway.name,
                  style: AppTextStyles.titleMedium(context.koraTitleColor),
                ),
                const SizedBox(height: 2),
                Text(
                  gateway.isCard
                      ? l10n.paymentMethodCardLead
                      : l10n.colisGatewayMomoLead,
                  style: AppTextStyles.body(koraColors.textMuted),
                ),
                // Les logos des opérateurs sous les deux passerelles
                // Mobile Money : on reconnaît son réseau avant
                // d'avoir lu la ligne.
                if (!gateway.isCard) ...[
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      for (final network in kColisMomoNetworks)
                        Padding(
                          padding: const EdgeInsets.only(right: 6),
                          child: OperatorBadge(network: network, size: 26),
                        ),
                    ],
                  ),
                ],
              ],
            ),
          ),
          // Pas de pastille « Recommandé » : elle prenait sa largeur au
          // milieu de la ligne, et la promesse de FedaPay tombait sur
          // deux lignes quand celle de KKiaPay tenait sur une. L'écran
          // des billets n'en porte pas non plus — deux encaisseurs qui
          // font le même travail n'ont pas à être départagés ici.
          Icon(Icons.chevron_right_rounded, color: koraColors.textMuted),
        ],
      ),
    );
  }
}
