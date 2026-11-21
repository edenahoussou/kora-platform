import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/brand_mini.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../bloc/colis_draft_cubit.dart';
import '../widgets/colis_banner.dart';

/// Écran 07 — « Poids confirmé & devis final ».
///
/// Le guichet a pesé. Tout ce que l'app annonçait jusqu'ici était une
/// estimation ; à partir d'ici, c'est un montant dû. L'écran met les deux
/// poids côte à côte plutôt que de remplacer l'un par l'autre en silence :
/// un voyageur qui paie 500 F de plus qu'annoncé doit voir pourquoi.
class ColisQuotePage extends StatefulWidget {
  const ColisQuotePage({super.key});

  @override
  State<ColisQuotePage> createState() => _ColisQuotePageState();
}

class _ColisQuotePageState extends State<ColisQuotePage> {
  @override
  void initState() {
    super.initState();
    // La pesée du guichet, simulée : la balance trouve 400 g de plus que
    // le déclaratif, ce qui est l'ordinaire d'un emballage oublié dans
    // l'estimation. Le jour où l'API existe, c'est elle qui donnera ce
    // chiffre.
    final cubit = context.read<ColisDraftCubit>();
    if (cubit.state.certifiedWeightKg == null) {
      cubit.confirmWeight(cubit.state.declaredWeightKg + 0.4);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return BlocBuilder<ColisDraftCubit, ColisDraft>(
      builder: (context, draft) {
        final origin = draft.originHub;
        final destination = draft.destinationHub;

        // Le reclassement tarifaire : l'écart de poids n'a d'importance
        // que s'il change de tranche. Sinon, c'est un détail de balance.
        final declaredFee = colisTransportFee(
          originCity: origin?.city ?? '',
          destinationCity: destination?.city ?? '',
          weightKg: draft.declaredWeightKg,
        );
        final reclassified = draft.transportFee > declaredFee;

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
                          tag: l10n.colisWeightUpdated,
                          caption: origin?.counter ?? '',
                          title: l10n.colisWeighedTitle,
                          text: l10n.colisWeighedText,
                        ),
                        const SizedBox(height: AppSpacing.md),
                        _WeighingCard(
                          declared: draft.declaredWeightKg,
                          certified: draft.certifiedWeightKg ?? 0,
                          gap: draft.weightGapKg,
                          reclassified: reclassified,
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        _PricingCard(draft: draft),
                        const SizedBox(height: AppSpacing.sm),
                        // Le reçu et l'étiquette naissent du paiement,
                        // pas de la pesée : l'écran le redit avant de
                        // demander l'argent.
                        KoraAlert(
                          icon: Icons.receipt_long_outlined,
                          title: l10n.colisReferenceOnPaymentTitle,
                          message: l10n.colisReferenceOnPaymentText,
                        ),
                      ],
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        KoraButton(
                          label: l10n.colisQuoteCta,
                          icon: Icons.arrow_forward_rounded,
                          iconPosition: KoraButtonIconPosition.edges,
                          onPressed: () =>
                              context.push(AppRouter.colisPaymentPath),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          l10n.colisQuoteHint,
                          textAlign: TextAlign.center,
                          style: AppTextStyles.caption(koraColors.textMuted),
                        ),
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

/// Les deux poids, face à face : ce qui était annoncé, ce qui a été lu.
class _WeighingCard extends StatelessWidget {
  const _WeighingCard({
    required this.declared,
    required this.certified,
    required this.gap,
    required this.reclassified,
  });

  final double declared;
  final double certified;
  final double gap;

  /// Le colis a changé de tranche tarifaire à cause de l'écart.
  final bool reclassified;

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
              Icon(Icons.scale_outlined, size: 17, color: koraColors.textMuted),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisWeighingCardTitle,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              KoraTag(
                label: l10n.colisWeightGap(colisWeightText(gap)),
                variant: KoraTagVariant.gold,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _WeightBox(
                  label: l10n.colisDeclaredWeightLabel,
                  weight: declared,
                  caption: l10n.colisDeclaredOnline,
                  strong: false,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _WeightBox(
                  label: l10n.colisCertifiedWeightLabel,
                  weight: certified,
                  caption: l10n.colisCertifiedScale,
                  strong: true,
                ),
              ),
            ],
          ),
          if (reclassified) ...[
            const SizedBox(height: 10),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(
                  Icons.info_outline_rounded,
                  size: 15,
                  color: AppColors.gold,
                ),
                const SizedBox(width: 7),
                Expanded(
                  child: Text(
                    l10n.colisReclassifiedText,
                    style: AppTextStyles.caption(koraColors.textMuted),
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 10),
          Row(
            children: [
              const Icon(
                Icons.verified_outlined,
                size: 15,
                color: AppColors.success,
              ),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  l10n.colisControlledBy,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Un poids encadré : son intitulé, sa valeur, son origine.
class _WeightBox extends StatelessWidget {
  const _WeightBox({
    required this.label,
    required this.weight,
    required this.caption,
    required this.strong,
  });

  final String label;
  final double weight;
  final String caption;

  /// Le poids qui fait foi se détache ; l'autre n'est plus qu'un
  /// souvenir de l'estimation.
  final bool strong;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
      decoration: BoxDecoration(
        color: strong
            ? AppColors.gold.withValues(alpha: 0.12)
            : koraColors.background,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(label, style: AppTextStyles.caption(koraColors.textMuted)),
          const SizedBox(height: 4),
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(
                colisWeightText(weight),
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(width: 4),
              Text(
                l10n.colisWeightUnit,
                style: AppTextStyles.caption(koraColors.textMuted),
              ),
            ],
          ),
          const SizedBox(height: 2),
          Text(caption, style: AppTextStyles.caption(koraColors.textMuted)),
        ],
      ),
    );
  }
}

/// Le détail du montant dû, ligne à ligne.
class _PricingCard extends StatelessWidget {
  const _PricingCard({required this.draft});

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
          Row(
            children: [
              Icon(Icons.sell_outlined, size: 17, color: koraColors.textMuted),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisPricingTitle,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              KoraTag(
                label: l10n.colisPaymentExpected,
                variant: KoraTagVariant.outline,
              ),
            ],
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
          const SizedBox(height: 9),
          ColisAmountRow(
            label: l10n.colisInsuranceFee,
            value: l10n.colisInsuranceFree,
          ),
          const SizedBox(height: 11),
          Divider(height: 1, color: koraColors.line),
          const SizedBox(height: 11),
          ColisAmountRow(
            label: l10n.colisNetDue,
            value: l10n.colisAmount(draft.total),
            isTotal: true,
          ),
          const SizedBox(height: 3),
          Text(
            l10n.colisTaxesIncluded,
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
        ],
      ),
    );
  }
}
