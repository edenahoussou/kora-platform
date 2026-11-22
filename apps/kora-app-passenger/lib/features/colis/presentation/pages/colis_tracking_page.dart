import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../../data/datasources/colis_tracking_mock.dart';
import '../widgets/colis_surface.dart';

/// Écran 11 — le suivi détaillé d'un envoi.
///
/// Les jalons sont l'objet de l'écran : ils répondent à la seule question
/// que se pose qui attend un colis — où est-il, et quand puis-je y aller.
/// Chacun porte son heure **observée**, pas une estimation déguisée.
///
/// L'en-tête ne porte pas de titre : il nomme l'écran d'où l'on vient.
/// Le titre, ici, c'est la référence — elle est en tête de la première
/// carte, et c'est elle qu'on dicte au guichet.
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
              const _TrackingHeader(),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                  children: [
                    _ShipmentCard(
                      parcel: parcel,
                      onCopy: () => _copy(context, parcel.reference),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    _MilestonesCard(parcel: parcel),
                    const SizedBox(height: AppSpacing.sm),
                    _RecipientCard(parcel: parcel),
                    const SizedBox(height: AppSpacing.sm),
                    KoraButton(
                      label: l10n.colisShowLabel,
                      icon: Icons.qr_code_2_rounded,
                      onPressed: () =>
                          KoraToaster.show(context, l10n.colisShowLabel),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    KoraButton(
                      label: l10n.colisShareTracking,
                      variant: KoraButtonVariant.secondary,
                      icon: Icons.share_outlined,
                      onPressed: () =>
                          KoraToaster.show(context, l10n.colisLinkCopied),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    _LineSupport(parcel: parcel),
                    const SizedBox(height: 104),
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

/// L'en-tête : le retour nommé, et la pastille de session.
///
/// Pas de grand titre : l'écran s'ouvre par-dessus la liste des envois,
/// et c'est son nom qu'on remet sous les yeux pour savoir où l'on
/// retombera en revenant.
class _TrackingHeader extends StatelessWidget {
  const _TrackingHeader();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        children: [
          InkWell(
            onTap: () => context.pop(),
            borderRadius: BorderRadius.circular(AppRadius.pill),
            child: Padding(
              padding: const EdgeInsets.all(4),
              child: Icon(
                Icons.arrow_back_rounded,
                size: 20,
                color: context.koraTitleColor,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              l10n.colisShipmentsTitle,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: context.koraTitleColor,
                fontSize: 15,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          // La pastille de session : une puce verte et un mot, posés
          // sur le fond plutôt que dans une carte.
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(
              color: koraColors.background,
              borderRadius: BorderRadius.circular(AppRadius.pill),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 6,
                  height: 6,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.success,
                  ),
                ),
                const SizedBox(width: 6),
                Text(
                  l10n.colisSecureSession,
                  style: TextStyle(
                    color: koraColors.textMuted,
                    fontSize: 11,
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

/// La carte d'expédition : la référence, l'état, la fraîcheur du suivi,
/// le trajet et l'heure d'arrivée.
///
/// Une seule carte pour tout ce qui identifie l'envoi. La maquette les
/// réunit, et c'est juste : la référence sans le trajet ne dit rien, et
/// le trajet sans l'heure d'arrivée non plus.
class _ShipmentCard extends StatelessWidget {
  const _ShipmentCard({required this.parcel, required this.onCopy});

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
                child: Text(
                  l10n.colisOfficialShipmentNo,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ),
              _StatusPill(status: parcel.status),
            ],
          ),
          const SizedBox(height: 3),
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
                borderRadius: BorderRadius.circular(AppRadius.base),
                child: Padding(
                  padding: const EdgeInsets.all(5),
                  child: Icon(
                    Icons.content_copy_rounded,
                    size: 17,
                    color: koraColors.textMuted,
                  ),
                ),
              ),
            ],
          ),
          // La fraîcheur du suivi, et de quoi la rafraîchir : sans
          // elle, on ne sait pas si l'écran dit l'instant ou la veille.
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
            decoration: colisInsetDecoration(context),
            child: Row(
              children: [
                Icon(Icons.sync_rounded, size: 15, color: koraColors.textMuted),
                const SizedBox(width: 7),
                Expanded(
                  child: Text(
                    l10n.colisRefreshedAgo(kColisRefreshedMinutes),
                    style: AppTextStyles.caption(koraColors.textMuted),
                  ),
                ),
                InkWell(
                  onTap: () => KoraToaster.show(context, l10n.colisRefresh),
                  child: Text(
                    l10n.colisRefresh,
                    style: const TextStyle(
                      color: AppColors.digital,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          _RouteStrip(parcel: parcel),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: colisInsetDecoration(context),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.schedule_rounded,
                  size: 17,
                  color: koraColors.textMuted,
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Row(
                        children: [
                          Text(
                            '${l10n.colisEtaLabel} : ',
                            style: AppTextStyles.caption(koraColors.textMuted),
                          ),
                          Flexible(
                            child: Text(
                              l10n.colisEtaToday(parcel.etaTime),
                              style: TextStyle(
                                color: context.koraTitleColor,
                                fontSize: 13,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 3),
                      Text(
                        l10n.colisEtaSource(parcel.line),
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                    ],
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

/// Le trajet : les deux bouts, et entre eux la ligne et le convoi.
///
/// Le convoi est au milieu du trait, pas sous le départ : c'est lui qui
/// porte le colis d'un bout à l'autre, et c'est ce qu'on cite au
/// guichet de régulation.
class _RouteStrip extends StatelessWidget {
  const _RouteStrip({required this.parcel});

  final ColisTrackedParcel parcel;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: _Endpoint(
            label: l10n.colisDeparture,
            city: parcel.originCity,
            hub: parcel.originHub,
            alignEnd: false,
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(top: 14),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  '${parcel.line} EXPRESS',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppColors.digital,
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Row(
                  children: [
                    Container(
                      width: 7,
                      height: 7,
                      decoration: const BoxDecoration(
                        shape: BoxShape.circle,
                        color: AppColors.digital,
                      ),
                    ),
                    Expanded(
                      child: Container(height: 1.5, color: koraColors.line),
                    ),
                    const Icon(
                      Icons.directions_bus_rounded,
                      size: 15,
                      color: AppColors.digital,
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  l10n.colisBusNumber(parcel.busNumber),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _Endpoint(
            label: l10n.colisArrival,
            city: parcel.destinationCity,
            hub: parcel.destinationHub,
            alignEnd: true,
          ),
        ),
      ],
    );
  }
}

/// Un bout du trajet : son intitulé, sa ville, son hub.
class _Endpoint extends StatelessWidget {
  const _Endpoint({
    required this.label,
    required this.city,
    required this.hub,
    required this.alignEnd,
  });

  final String label;
  final String city;
  final String hub;

  /// L'arrivée s'aligne à droite : les deux bouts encadrent la ligne.
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
        const SizedBox(height: 2),
        Text(
          city,
          textAlign: alignEnd ? TextAlign.end : TextAlign.start,
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: 17,
            height: 1.15,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          hub,
          textAlign: alignEnd ? TextAlign.end : TextAlign.start,
          style: AppTextStyles.caption(koraColors.textMuted),
        ),
      ],
    );
  }
}

/// La pastille d'état, posée en tête de la carte d'expédition.
class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status});

  final ColisTrackingStatus status;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    final (label, color) = switch (status) {
      ColisTrackingStatus.sealed => (l10n.colisStatusSealed, AppColors.kora),
      ColisTrackingStatus.inTransit => (
        l10n.parcelsStatusTransit,
        AppColors.digital,
      ),
      ColisTrackingStatus.readyForPickup => (
        l10n.colisStatusReady,
        AppColors.gold,
      ),
      ColisTrackingStatus.collected => (
        l10n.colisStatusCollected,
        AppColors.success,
      ),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(AppRadius.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(shape: BoxShape.circle, color: color),
          ),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

/// Les jalons d'acheminement, dans leur carte.
class _MilestonesCard extends StatelessWidget {
  const _MilestonesCard({required this.parcel});

  final ColisTrackedParcel parcel;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    // Le jalon en cours est le dernier franchi : ce qui suit n'a pas
    // encore eu lieu, ce qui précède est acquis.
    final active = parcel.step - 1;

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
                  l10n.colisMilestonesTitle,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 16,
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
          const SizedBox(height: 14),
          for (final (index, milestone) in parcel.milestones.indexed)
            _MilestoneRow(
              milestone: milestone,
              checkpoint: index == active ? parcel.checkpoint : null,
              state: index < active
                  ? _MilestoneState.done
                  : index == active
                  ? _MilestoneState.active
                  : _MilestoneState.upcoming,
              isLast: index == parcel.milestones.length - 1,
            ),
          const SizedBox(height: 4),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: colisInsetDecoration(context),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.info_outline_rounded,
                  size: 15,
                  color: koraColors.textMuted,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    l10n.colisMilestonesSource,
                    style: AppTextStyles.caption(koraColors.textMuted),
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

/// Où en est un jalon : acquis, en cours, ou encore à venir.
enum _MilestoneState { done, active, upcoming }

/// Un jalon : sa pastille, son libellé, son heure et son détail.
class _MilestoneRow extends StatelessWidget {
  const _MilestoneRow({
    required this.milestone,
    required this.state,
    required this.isLast,
    this.checkpoint,
  });

  final ColisMilestone milestone;
  final _MilestoneState state;
  final bool isLast;

  /// Le dernier poste de contrôle franchi, sur le jalon en cours.
  final String? checkpoint;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    final color = switch (state) {
      _MilestoneState.done => AppColors.success,
      _MilestoneState.active => AppColors.digital,
      _MilestoneState.upcoming => koraColors.textMuted,
    };
    final titleColor = switch (state) {
      _MilestoneState.active => AppColors.digital,
      _MilestoneState.done => context.koraTitleColor,
      _MilestoneState.upcoming => koraColors.textMuted,
    };

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Column(
            children: [
              Container(
                width: 26,
                height: 26,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  // Plein pour l'acquis, cerclé pour le reste : un jalon
                  // franchi ne se discute pas, les autres attendent.
                  color: state == _MilestoneState.done
                      ? color
                      : color.withValues(alpha: 0.12),
                ),
                child: Icon(
                  switch (state) {
                    _MilestoneState.done => Icons.check_rounded,
                    _MilestoneState.active => Icons.rotate_right_rounded,
                    _MilestoneState.upcoming =>
                      isLast
                          ? Icons.lock_outline_rounded
                          : Icons.schedule_rounded,
                  },
                  size: 15,
                  color: state == _MilestoneState.done
                      ? AppColors.white
                      : color,
                ),
              ),
              // Le fil qui relie les jalons : il s'arrête au dernier,
              // sinon il pendrait dans le vide sous la liste.
              if (!isLast)
                Expanded(
                  child: Container(
                    width: 2,
                    color: color.withValues(
                      alpha: state == _MilestoneState.upcoming ? 0.3 : 1,
                    ),
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
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Text(
                          _milestoneLabel(l10n, milestone.titleKey),
                          style: TextStyle(
                            color: titleColor,
                            fontSize: 14,
                            height: 1.25,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      // Le jalon en cours porte son état, pas une heure
                      // qui n'existe pas encore ; le dernier annonce
                      // qu'il reste à venir.
                      if (state == _MilestoneState.active)
                        _SmallPill(
                          label: l10n.colisActive,
                          color: AppColors.digital,
                        )
                      else if (state == _MilestoneState.upcoming && isLast)
                        _SmallPill(
                          label: l10n.colisUpcoming,
                          color: koraColors.textMuted,
                        )
                      else
                        Text(
                          milestone.time,
                          style: AppTextStyles.caption(koraColors.textMuted),
                        ),
                    ],
                  ),
                  const SizedBox(height: 3),
                  Text(
                    milestone.detail,
                    style: AppTextStyles.caption(koraColors.textMuted),
                  ),
                  // Le poste de contrôle : la preuve que le colis
                  // avance, à l'endroit où l'on se demande s'il bouge.
                  if (checkpoint case final checkpoint?) ...[
                    const SizedBox(height: 3),
                    Text(
                      l10n.colisCheckpoint(checkpoint),
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      l10n.colisNoIncident,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Une pastille brève, posée à droite d'un jalon.
class _SmallPill extends StatelessWidget {
  const _SmallPill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(AppRadius.pill),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
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
  'colis_milestone_sealed' => l10n.colisMilestoneSealed,
  'colis_milestone_loaded' => l10n.colisMilestoneLoaded,
  'colis_milestone_transit' => l10n.colisMilestoneTransit,
  'colis_milestone_arrived' => l10n.colisMilestoneArrived,
  _ => l10n.colisMilestoneCollected,
};

/// Le destinataire et les conditions de remise.
///
/// Le numéro est masqué, et le code de retrait absent : le suivi se
/// partage, et une capture d'écran lisible suffirait à usurper un
/// retrait.
class _RecipientCard extends StatelessWidget {
  const _RecipientCard({required this.parcel});

  final ColisTrackedParcel parcel;

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
              Icon(
                Icons.how_to_reg_outlined,
                size: 20,
                color: context.koraTitleColor,
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.colisRecipientSection,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 1),
                    Text(
                      l10n.colisDeliveryProtocol,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          // Qui retire. Le numéro reste masqué : cet écran se partage.
          Container(
            padding: const EdgeInsets.all(12),
            decoration: colisInsetDecoration(context),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        l10n.colisDesignatedRecipient,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                    ),
                    _SmallPill(
                      label: l10n.colisMasked,
                      color: koraColors.textMuted,
                    ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  parcel.recipientName,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  parcel.recipientPhoneMasked,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          // Où le colis attend, et jusqu'à quelle heure.
          Container(
            padding: const EdgeInsets.all(12),
            decoration: colisInsetDecoration(context),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.storefront_outlined,
                  size: 17,
                  color: koraColors.textMuted,
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        l10n.colisPickupPlace,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        parcel.counter.hub,
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        parcel.counter.address,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        l10n.colisCounterUntil(parcel.counter.weekdayHours),
                        style: const TextStyle(
                          color: AppColors.digital,
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          // Pourquoi aucun code n'apparaît ici, et ce qu'il faudra
          // présenter à la place.
          Container(
            padding: const EdgeInsets.all(12),
            decoration: colisInsetDecoration(context),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    Icon(
                      Icons.verified_user_outlined,
                      size: 17,
                      color: context.koraTitleColor,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        l10n.colisAntiImpersonation,
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 13,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  l10n.colisAntiImpersonationText,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
                const SizedBox(height: 10),
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: koraColors.surface,
                    borderRadius: BorderRadius.circular(AppRadius.md),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(
                        Icons.badge_outlined,
                        size: 15,
                        color: AppColors.gold,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          l10n.colisIdentityAtCounter,
                          style: AppTextStyles.caption(koraColors.textMuted),
                        ),
                      ),
                    ],
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

/// L'assistance de la ligne : qui régule le convoi, à quel numéro, et
/// par où ouvrir un litige.
class _LineSupport extends StatelessWidget {
  const _LineSupport({required this.parcel});

  final ColisTrackedParcel parcel;

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
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.colisLineSupport(parcel.line),
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 1),
                    Text(
                      l10n.colisConvoyDesk,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              // Le numéro est une action, pas une mention : on appelle
              // la régulation quand le colis ne bouge plus.
              Material(
                color: koraColors.background,
                borderRadius: BorderRadius.circular(AppRadius.pill),
                child: InkWell(
                  onTap: () => KoraToaster.show(context, kColisSupportPhone),
                  borderRadius: BorderRadius.circular(AppRadius.pill),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 8,
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.call_rounded,
                          size: 15,
                          color: context.koraTitleColor,
                        ),
                        const SizedBox(width: 7),
                        Text(
                          kColisSupportPhone,
                          style: TextStyle(
                            color: context.koraTitleColor,
                            fontSize: 12,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Divider(height: 1, color: koraColors.line),
          const SizedBox(height: 10),
          // En rouge, et seul de sa couleur sur l'écran : ouvrir un
          // litige n'est pas un geste de consultation.
          InkWell(
            onTap: () => context.push(
              AppRouter.colisIncidentPath,
              extra: parcel.reference,
            ),
            borderRadius: BorderRadius.circular(AppRadius.base),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  const Icon(
                    Icons.report_problem_outlined,
                    size: 17,
                    color: AppColors.danger,
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    child: Text(
                      l10n.colisReportIncident,
                      style: const TextStyle(
                        color: AppColors.danger,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
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
