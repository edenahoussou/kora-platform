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

/// Écran 06 — « Pré-enregistrement validé ».
///
/// Le dossier existe côté escale, et **rien d'autre**. Pas de référence
/// officielle, pas de place réservée en soute, pas de prix ferme : trois
/// absences que l'écran énonce, parce qu'un voyageur qui croit avoir payé
/// se présente au guichet sans son argent.
class ColisPreregisteredPage extends StatelessWidget {
  const ColisPreregisteredPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return BlocBuilder<ColisDraftCubit, ColisDraft>(
      builder: (context, draft) {
        final origin = draft.originHub;
        final destination = draft.destinationHub;
        final (low, high) = draft.indicativeRange;

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
                        _ValidatedBanner(hubCounter: origin?.counter ?? ''),
                        const SizedBox(height: AppSpacing.md),
                        // L'avertissement central du ticket : la
                        // référence n'existe pas encore.
                        KoraAlert(
                          variant: KoraAlertVariant.warning,
                          icon: Icons.receipt_long_outlined,
                          title: l10n.colisNoReferenceTitle,
                          message: l10n.colisNoReferenceText,
                        ),
                        const SizedBox(height: AppSpacing.md),
                        _LinkCard(
                          origin: origin?.city ?? '',
                          originHub: origin?.name ?? '',
                          destination: destination?.city ?? '',
                          destinationHub: destination?.name ?? '',
                          weight: l10n.colisWeightDeclared(
                            l10n.colisWeightWithUnit(
                              colisWeightText(draft.declaredWeightKg),
                            ),
                          ),
                          nature: draft.content,
                          range: l10n.colisRange(
                            l10n.colisAmount(low),
                            l10n.colisAmount(high),
                          ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        Text(
                          l10n.colisProtocolTitle.toUpperCase(),
                          style: AppTextStyles.eyebrow(koraColors.textMuted),
                        ),
                        const SizedBox(height: 10),
                        for (final (index, step) in [
                          (
                            l10n.colisProtocolStep1Title,
                            l10n.colisProtocolStep1Text(origin?.counter ?? ''),
                          ),
                          (
                            l10n.colisProtocolStep2Title,
                            l10n.colisProtocolStep2Text,
                          ),
                          (
                            l10n.colisProtocolStep3Title,
                            l10n.colisProtocolStep3Text,
                          ),
                          (
                            l10n.colisProtocolStep4Title,
                            l10n.colisProtocolStep4Text,
                          ),
                        ].indexed) ...[
                          _ProtocolStep(
                            rank: index + 1,
                            title: step.$1,
                            text: step.$2,
                          ),
                          const SizedBox(height: 8),
                        ],
                      ],
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // Le guichet pèse, puis demande le règlement :
                        // c'est l'étape suivante du parcours.
                        KoraButton(
                          label: l10n.colisPreregisteredCta,
                          icon: Icons.arrow_forward_rounded,
                          iconPosition: KoraButtonIconPosition.edges,
                          // Le guichet pèse, puis le devis s'ouvre.
                          onPressed: () =>
                              context.push(AppRouter.colisQuotePath),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        KoraButton(
                          label: l10n.colisBackToParcels,
                          variant: KoraButtonVariant.ghost,
                          onPressed: () => context.go('/colis'),
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

/// Le bandeau de confirmation : ce qui est acquis, et ce qui ne l'est pas.
class _ValidatedBanner extends StatelessWidget {
  const _ValidatedBanner({required this.hubCounter});

  final String hubCounter;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.kora,
        borderRadius: BorderRadius.circular(19),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              KoraTag(label: l10n.colisToDropOff, variant: KoraTagVariant.gold),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  hubCounter,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.end,
                  style: AppTextStyles.caption(
                    AppColors.white.withValues(alpha: 0.72),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            l10n.colisPreregisteredTitle,
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 20,
              height: 1.15,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            l10n.colisPreregisteredText,
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.72),
              fontSize: 12,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }
}

/// La liaison interurbaine : les deux hubs, le poids déclaré, la
/// fourchette.
class _LinkCard extends StatelessWidget {
  const _LinkCard({
    required this.origin,
    required this.originHub,
    required this.destination,
    required this.destinationHub,
    required this.weight,
    required this.nature,
    required this.range,
  });

  final String origin;
  final String originHub;
  final String destination;
  final String destinationHub;
  final String weight;
  final String nature;
  final String range;

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
              Icon(
                Icons.local_shipping_outlined,
                size: 17,
                color: koraColors.textMuted,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisLinkTitle,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
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
          const SizedBox(height: AppSpacing.md),
          _Point(
            label: l10n.colisSenderHub,
            city: origin,
            hub: originHub,
            color: AppColors.kora,
          ),
          const SizedBox(height: 10),
          _Point(
            label: l10n.colisRecipientHub,
            city: destination,
            hub: destinationHub,
            color: AppColors.gold,
          ),
          const SizedBox(height: AppSpacing.md),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _Box(
                  label: l10n.colisNatureWeight,
                  value: weight,
                  caption: nature,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _Box(
                  label: l10n.colisIndicativeFare,
                  value: range,
                  caption: l10n.colisFinalAtCounter,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Point extends StatelessWidget {
  const _Point({
    required this.label,
    required this.city,
    required this.hub,
    required this.color,
  });

  final String label;
  final String city;
  final String hub;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(top: 5),
          child: Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(shape: BoxShape.circle, color: color),
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
                  fontSize: 15,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(hub, style: AppTextStyles.caption(koraColors.textMuted)),
            ],
          ),
        ),
      ],
    );
  }
}

class _Box extends StatelessWidget {
  const _Box({required this.label, required this.value, required this.caption});

  final String label;
  final String value;
  final String caption;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(
        color: koraColors.background,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(label, style: AppTextStyles.caption(koraColors.textMuted)),
          const SizedBox(height: 3),
          Text(
            value,
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 14,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            caption,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
        ],
      ),
    );
  }
}

/// Une étape du protocole de prise en charge.
class _ProtocolStep extends StatelessWidget {
  const _ProtocolStep({
    required this.rank,
    required this.title,
    required this.text,
  });

  final int rank;
  final String title;
  final String text;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 24,
          height: 24,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: AppColors.gold.withValues(alpha: 0.18),
          ),
          child: Text(
            '$rank',
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 11,
              fontWeight: FontWeight.w900,
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
                title,
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(text, style: AppTextStyles.caption(koraColors.textMuted)),
            ],
          ),
        ),
      ],
    );
  }
}
