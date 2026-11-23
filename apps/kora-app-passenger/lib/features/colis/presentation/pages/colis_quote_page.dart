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
                          icon: Icons.scale_rounded,
                          tag: l10n.colisWeightCertifiedTag,
                          tagVariant: KoraTagVariant.success,
                          caption: origin?.counter ?? '',
                          title: l10n.colisWeighedTitle,
                          text: l10n.colisWeighedText,
                        ),
                        const SizedBox(height: AppSpacing.md),
                        // Le devis chiffre un trajet : sans lui à
                        // l'écran, le montant ne se vérifie pas.
                        _AxisCard(draft: draft),
                        const SizedBox(height: AppSpacing.sm),
                        _WeighingCard(
                          nature: _natureLabel(l10n, draft.nature),
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
                        ColisNote(
                          icon: Icons.receipt_long_outlined,
                          title: l10n.colisReferenceOnPaymentTitle,
                          message: l10n.colisReferenceOnPaymentText,
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        // Par quoi on pourra payer : la question se
                        // pose avant d'appuyer, pas après.
                        const _AcceptedMethods(),
                        const SizedBox(height: AppSpacing.sm),
                        // Le pied défile avec le contenu : épinglé en
                        // bas, il recouvrait la fin de la liste.
                        const SizedBox(height: AppSpacing.md),
                        // Le montant dans le libellé : la maquette ne
                        // fait pas appuyer sur « payer » sans redire
                        // combien.
                        KoraButton(
                          label: l10n.colisPayFinalQuote(
                            l10n.colisAmount(draft.total),
                          ),
                          icon: Icons.arrow_forward_rounded,
                          iconPosition: KoraButtonIconPosition.edges,
                          onPressed: () =>
                              context.push(AppRouter.colisPaymentPath),
                        ),
                        const SizedBox(height: 10),
                        // L'assistance du guichet où le colis est
                        // déposé : un bouton, parce qu'on l'appelle,
                        // on ne se contente pas de la lire. Sur fond
                        // blanc — le doré ne se donne qu'une fois.
                        if (origin case final hub?) ...[
                          ColisFlatAction(
                            label: l10n.colisCounterSupport(hub.counter),
                            icon: Icons.support_agent_rounded,
                            onTap: () => KoraToaster.show(context, hub.phone),
                          ),
                          const SizedBox(height: 10),
                        ],
                        Text(
                          l10n.colisQuoteHint,
                          textAlign: TextAlign.center,
                          style: AppTextStyles.caption(koraColors.textMuted),
                        ),
                        const SizedBox(height: 6),
                        // Repartir sans payer reste possible : le devis
                        // attend au guichet. En lien, pas en bouton —
                        // deux boutons suffisent en bas d'écran.
                        Center(
                          child: InkWell(
                            onTap: () => context.go('/colis'),
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
                                    Icons.chevron_left_rounded,
                                    size: 18,
                                    color: koraColors.textMuted,
                                  ),
                                  const SizedBox(width: 4),
                                  Flexible(
                                    child: Text(
                                      l10n.colisBackHome,
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
    required this.nature,
    required this.declared,
    required this.certified,
    required this.gap,
    required this.reclassified,
  });

  /// La nature déclarée, telle que l'agent l'a vérifiée au guichet.
  final String nature;

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
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
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
          Container(
            padding: const EdgeInsets.all(10),
            decoration: colisInsetDecoration(context),
            child: Row(
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
                const SizedBox(width: 10),
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
          // L'écart de pesée s'explique : emballage renforcé, et non
          // une balance qui dérive.
          const SizedBox(height: 10),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.info_outline_rounded,
                size: 15,
                color: koraColors.textMuted,
              ),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  l10n.colisWeighingGapNote,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Divider(height: 1, color: koraColors.line),
          const SizedBox(height: 10),
          // Ce que l'agent a vu dans le colis : le poids seul ne dit
          // pas que le contenu déclaré a été vérifié.
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.colisNatureChecked,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      nature,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              KoraTag(
                label: l10n.colisAgentRef(kColisWeighingAgent),
                variant: KoraTagVariant.outline,
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              const Icon(
                Icons.check_rounded,
                size: 15,
                color: AppColors.success,
              ),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  l10n.colisVisualCompliance,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ),
            ],
          ),
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
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
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
            label: l10n.colisPackagingFee,
            value: l10n.colisAmount(kColisSealFee),
          ),
          const SizedBox(height: 9),
          ColisAmountRow(
            label: l10n.colisInsuranceFee,
            value: l10n.colisInsuranceFree,
            valueColor: AppColors.success,
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

/// Le libellé d'une nature de colis.
///
/// Un `switch` plutôt qu'une clé lue dynamiquement : `translate` ne
/// s'emploie que depuis la couche de traduction, et une clé passée en
/// donnée échappe de toute façon au compilateur.
String _natureLabel(AppLocalization l10n, ColisNature nature) =>
    switch (nature) {
      ColisNature.clothing => l10n.colisNatureClothing,
      ColisNature.documents => l10n.colisNatureDocuments,
      ColisNature.electronics => l10n.colisNatureElectronics,
      ColisNature.other => l10n.colisNatureOther,
    };

/// L'axe emprunté : d'où part le colis, où il est attendu.
class _AxisCard extends StatelessWidget {
  const _AxisCard({required this.draft});

  final ColisDraft draft;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final origin = draft.originHub;
    final destination = draft.destinationHub;

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
              Expanded(
                child: Text(
                  l10n.colisAxisTitle,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              KoraTag(
                label: l10n.colisDirectDeparture,
                variant: KoraTagVariant.outline,
              ),
            ],
          ),
          const SizedBox(height: 11),
          _AxisPoint(
            label: l10n.colisOriginHubLabel,
            city: origin?.city ?? '',
            hub: origin == null ? '' : '${origin.name} — ${origin.counter}',
            isOrigin: true,
          ),
          const SizedBox(height: 10),
          _AxisPoint(
            label: l10n.colisDestinationHubLabel,
            city: destination?.city ?? '',
            hub: destination == null
                ? ''
                : '${destination.name} — ${destination.counter}',
            isOrigin: false,
          ),
        ],
      ),
    );
  }
}

/// Un bout de l'axe : sa pastille, son intitulé, sa ville, son guichet.
class _AxisPoint extends StatelessWidget {
  const _AxisPoint({
    required this.label,
    required this.city,
    required this.hub,
    required this.isOrigin,
  });

  final String label;
  final String city;
  final String hub;
  final bool isOrigin;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(top: 5),
          child: Container(
            width: 9,
            height: 9,
            decoration: BoxDecoration(
              // Marine au départ, doré à l'arrivée : la même grammaire
              // que les champs d'itinéraire de l'étape 1.
              color: isOrigin ? AppColors.kora : AppColors.gold,
              shape: BoxShape.circle,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label.toUpperCase(),
                style: AppTextStyles.eyebrow(koraColors.textMuted),
              ),
              const SizedBox(height: 2),
              Text(
                city,
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 1),
              Text(hub, style: AppTextStyles.caption(koraColors.textMuted)),
            ],
          ),
        ),
      ],
    );
  }
}

/// Les moyens de règlement acceptés au guichet comme dans l'app.
class _AcceptedMethods extends StatelessWidget {
  const _AcceptedMethods();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    // Deux moyens, et non trois : la carte bancaire ne se propose plus
    // pour une expédition, et l'annoncer ici ferait attendre au guichet
    // un règlement que l'app ne sait pas prendre.
    // Quatre pavés : le solde KORA, les deux passerelles qui
    // encaissent, et le réseau Mobile Money le plus demandé sur le
    // corridor. Les deux premiers portent leur logo — c'est à lui qu'on
    // les reconnaît au guichet comme dans l'app.
    final methods = <_Method>[
      _Method(
        label: l10n.colisMethodWallet,
        icon: Icons.account_balance_wallet_outlined,
      ),
      for (final gateway in kColisGateways)
        _Method(label: gateway.name, logo: gateway.logo),
      _Method(label: l10n.colisMethodMtnMomo, network: 'mtn_momo'),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          l10n.colisAcceptedMethods.toUpperCase(),
          style: AppTextStyles.eyebrow(koraColors.textMuted),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            for (final (index, method) in methods.indexed) ...[
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 4,
                    vertical: 10,
                  ),
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: koraColors.surface,
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    boxShadow: kColisCardShadow,
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      SizedBox(height: 22, child: method.mark(context)),
                      const SizedBox(height: 6),
                      Text(
                        method.label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              if (index != methods.length - 1) const SizedBox(width: 6),
            ],
          ],
        ),
      ],
    );
  }
}

/// Un moyen de règlement annoncé : son logo, son réseau ou son
/// pictogramme, et son nom.
class _Method {
  const _Method({required this.label, this.icon, this.logo, this.network});

  final String label;

  /// Le pictogramme, quand le moyen n'a pas de marque — le solde KORA.
  final IconData? icon;

  /// Le logo de la passerelle, tel que le catalogue le porte.
  final String? logo;

  /// Le réseau Mobile Money, dont la vignette vit avec les opérateurs.
  final String? network;

  Widget mark(BuildContext context) {
    if (network case final network?) {
      return OperatorBadge(network: network, size: 22);
    }
    if (logo case final logo?) {
      return Image.asset(logo, fit: BoxFit.contain);
    }

    return Icon(icon, size: 20, color: context.koraTitleColor);
  }
}
