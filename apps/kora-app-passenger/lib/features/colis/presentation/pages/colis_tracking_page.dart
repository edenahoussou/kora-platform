import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../../data/datasources/colis_tracking_mock.dart';
import '../widgets/colis_status_chip.dart';
import 'colis_shipments_page.dart';

/// Écran 11 — le suivi détaillé d'un envoi.
///
/// Les jalons sont l'objet de l'écran : ils répondent à la seule question
/// que se pose qui attend un colis — où est-il, et quand puis-je y aller.
/// Chacun porte son heure **observée**, pas une estimation déguisée.
class ColisTrackingPage extends StatelessWidget {
  const ColisTrackingPage({super.key, this.reference});

  /// La référence suivie. `null` si l'écran est ouvert sans elle : on
  /// retombe alors sur le premier envoi plutôt que sur une page vide.
  final String? reference;

  Future<void> _copy(BuildContext context, String value) async {
    final confirmation = context.l10n.parcelsReferenceCopied;
    await Clipboard.setData(ClipboardData(text: value));
    if (context.mounted) KoraToaster.show(context, confirmation);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final parcel = colisParcelByReference(reference ?? '');
    final ready = parcel.status == ColisTrackingStatus.readyForPickup;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeaderless(title: l10n.colisTrackingTitle),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                  children: [
                    _ReferenceHeader(
                      parcel: parcel,
                      onCopy: () => _copy(context, parcel.reference),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    _RouteCard(parcel: parcel),
                    const SizedBox(height: AppSpacing.md),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            l10n.colisMilestonesTitle,
                            style: TextStyle(
                              color: context.koraTitleColor,
                              fontSize: 15,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        Text(
                          l10n.colisStepOfTotal(parcel.step, parcel.totalSteps),
                          style: AppTextStyles.caption(koraColors.textMuted),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    for (final (index, milestone) in parcel.milestones.indexed)
                      _MilestoneRow(
                        milestone: milestone,
                        isLast: index == parcel.milestones.length - 1,
                      ),
                  ],
                ),
              ),
              if (ready)
                Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                  child: KoraButton(
                    label: l10n.colisPickupCta,
                    icon: Icons.arrow_forward_rounded,
                    iconPosition: KoraButtonIconPosition.edges,
                    onPressed: () => context.push(
                      AppRouter.colisPickupPath,
                      extra: parcel.reference,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// La référence officielle et l'état, en tête d'écran.
class _ReferenceHeader extends StatelessWidget {
  const _ReferenceHeader({required this.parcel, required this.onCopy});

  final ColisTrackedParcel parcel;
  final VoidCallback onCopy;

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
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  l10n.colisOfficialReference.toUpperCase(),
                  style: AppTextStyles.eyebrow(koraColors.textMuted),
                ),
              ),
              ColisStatusChip(status: parcel.status),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              Expanded(
                child: Text(
                  parcel.reference,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                ),
              ),
              InkWell(
                onTap: onCopy,
                borderRadius: BorderRadius.circular(8),
                child: Padding(
                  padding: const EdgeInsets.all(5),
                  child: Icon(
                    Icons.copy_rounded,
                    size: 17,
                    color: koraColors.textMuted,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Le trajet, sa ligne, et l'heure d'arrivée attendue.
class _RouteCard extends StatelessWidget {
  const _RouteCard({required this.parcel});

  final ColisTrackedParcel parcel;

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
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _Endpoint(
                  label: l10n.colisDeparture,
                  city: parcel.originCity,
                  hub: parcel.originHub,
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: KoraTag(
                  label: l10n.colisLineTag(parcel.line),
                  variant: KoraTagVariant.blue,
                ),
              ),
              Expanded(
                child: _Endpoint(
                  label: l10n.colisArrival,
                  city: parcel.destinationCity,
                  hub: parcel.destinationHub,
                  alignEnd: true,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
            decoration: BoxDecoration(
              color: koraColors.background,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  l10n.colisEtaLabel,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
                const SizedBox(height: 3),
                Text(
                  l10n.colisEtaToday(parcel.etaTime),
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                // L'heure n'est pas un engagement : elle sort de la
                // grille de rotation du corridor, pas d'un GPS.
                Text(
                  l10n.colisEtaSource(parcel.line),
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          Text(
            l10n.colisCertifiedAt(colisWeightText(parcel.certifiedWeightKg)),
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
        ],
      ),
    );
  }
}

class _Endpoint extends StatelessWidget {
  const _Endpoint({
    required this.label,
    required this.city,
    required this.hub,
    this.alignEnd = false,
  });

  final String label;
  final String city;
  final String hub;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: alignEnd
          ? CrossAxisAlignment.end
          : CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          label.toUpperCase(),
          style: AppTextStyles.eyebrow(koraColors.textMuted),
        ),
        const SizedBox(height: 3),
        Text(
          city,
          textAlign: alignEnd ? TextAlign.end : TextAlign.start,
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: 15,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          hub,
          textAlign: alignEnd ? TextAlign.end : TextAlign.start,
          style: AppTextStyles.caption(koraColors.textMuted),
        ),
      ],
    );
  }
}

/// Un jalon : sa pastille, son libellé, son heure et son détail.
class _MilestoneRow extends StatelessWidget {
  const _MilestoneRow({required this.milestone, required this.isLast});

  final ColisMilestone milestone;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final done = milestone.done;

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Column(
            children: [
              Container(
                width: 18,
                height: 18,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: done ? AppColors.success : koraColors.surface,
                  border: done
                      ? null
                      : Border.all(color: koraColors.line, width: 2),
                ),
                child: done
                    ? const Icon(
                        Icons.check_rounded,
                        size: 11,
                        color: Colors.white,
                      )
                    : null,
              ),
              // Le fil qui relie les jalons : il s'arrête au dernier,
              // sinon il pendrait dans le vide sous la liste.
              if (!isLast)
                Expanded(
                  child: Container(
                    width: 2,
                    color: done ? AppColors.success : koraColors.line,
                  ),
                ),
            ],
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          _milestoneLabel(l10n, milestone.titleKey),
                          style: TextStyle(
                            color: done
                                ? context.koraTitleColor
                                : koraColors.textMuted,
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        milestone.time,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    milestone.detail,
                    style: AppTextStyles.caption(koraColors.textMuted),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Le libellé d'un jalon.
///
/// Un `switch` plutôt qu'une clé lue dynamiquement : les traductions se
/// nomment à la compilation, sinon une clé renommée ne casse rien avant
/// l'exécution.
String _milestoneLabel(AppLocalization l10n, String key) => switch (key) {
  'colis_milestone_loaded' => l10n.colisMilestoneLoaded,
  'colis_milestone_transit' => l10n.colisMilestoneTransit,
  'colis_milestone_arrived' => l10n.colisMilestoneArrived,
  'colis_milestone_collected' => l10n.colisMilestoneCollected,
  _ => l10n.colisMilestoneSealed,
};
