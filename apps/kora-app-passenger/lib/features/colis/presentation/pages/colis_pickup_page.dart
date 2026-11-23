import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../../data/datasources/colis_tracking_mock.dart';
import '../widgets/colis_banner.dart';
import '../widgets/colis_surface.dart';
import 'colis_shipments_page.dart';

/// Écran 12 — « Colis arrivé, prêt à retirer ».
///
/// Le dernier écran du parcours côté app : il dit où aller, quand, et
/// avec quoi. Le code de retrait n'y figure pas — il est arrivé par SMS
/// au destinataire, et c'est ce qui le rend opposable au guichet.
class ColisPickupPage extends StatelessWidget {
  const ColisPickupPage({super.key, this.reference});

  final String? reference;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final parcel = colisParcelByReference(reference ?? '');
    final counter = parcel.counter;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeaderless(
                eyebrow: l10n.colisWaybillRef,
                title: l10n.colisPickupTitle,
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                  children: [
                    _ArrivalBanner(parcel: parcel),
                    const SizedBox(height: AppSpacing.md),
                    _CounterCard(counter: counter),
                    const SizedBox(height: AppSpacing.sm),
                    // Les deux gestes qu'on fait avant de partir : on
                    // regarde où c'est, et on appelle si l'on doute.
                    Row(
                      children: [
                        Expanded(
                          child: KoraButton(
                            label: l10n.colisDirections,
                            variant: KoraButtonVariant.secondary,
                            icon: Icons.directions_rounded,
                            onPressed: () =>
                                KoraToaster.show(context, l10n.colisDirections),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: KoraButton(
                            label: l10n.colisCounterCall,
                            variant: KoraButtonVariant.secondary,
                            icon: Icons.phone_in_talk_outlined,
                            onPressed: () =>
                                KoraToaster.show(context, kColisSupportPhone),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    // Les deux choses à apporter. Sans elles, le guichet
                    // ne remet rien, et le voyage était pour rien.
                    ColisNote(
                      icon: Icons.key_outlined,
                      title: l10n.colisBringTitle,
                      message: l10n.colisBringText,
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Container(
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
                          Text(
                            l10n.colisParcelTitle,
                            style: TextStyle(
                              color: context.koraTitleColor,
                              fontSize: 14,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          ColisAmountRow(
                            label: l10n.colisDeparture,
                            value: parcel.originCity,
                          ),
                          const SizedBox(height: 9),
                          ColisAmountRow(
                            label: l10n.colisArrival,
                            value: parcel.destinationCity,
                          ),
                          const SizedBox(height: 9),
                          ColisAmountRow(
                            label: l10n.colisCertifiedWeightLabel,
                            value: l10n.colisWeightWithUnit(
                              parcel.certifiedWeightKg
                                  .toStringAsFixed(1)
                                  .replaceAll('.', ','),
                            ),
                          ),
                          const SizedBox(height: 9),
                          ColisAmountRow(
                            label: l10n.colisSender,
                            value: parcel.senderName,
                          ),
                          const SizedBox(height: 9),
                          // Le scellé : c'est lui qu'on vérifie avant
                          // de signer la décharge. Un numéro qui ne
                          // correspond pas arrête la remise.
                          ColisAmountRow(
                            label: l10n.colisSealNumber,
                            value: parcel.sealNumber,
                          ),
                          const SizedBox(height: 10),
                          Row(
                            children: [
                              const Icon(
                                Icons.verified_rounded,
                                size: 15,
                                color: AppColors.success,
                              ),
                              const SizedBox(width: 7),
                              Expanded(
                                child: Text(
                                  l10n.colisArrivalCheckOk,
                                  style: AppTextStyles.caption(
                                    koraColors.textMuted,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    // Les trois conditions, numérotées : le guichet ne
                    // remet rien s'il en manque une, et mieux vaut le
                    // lire avant de se déplacer.
                    const _Conditions(),
                    const SizedBox(height: AppSpacing.sm),
                    // Où le code a été envoyé, sans jamais le montrer.
                    _CodeDestination(parcel: parcel),
                    const SizedBox(height: AppSpacing.sm),
                    // Jusqu'à quand le colis attend sans frais.
                    if (parcel.keepUntil case final until?)
                      _KeepingPeriod(parcel: parcel, until: until),
                    const SizedBox(height: AppSpacing.sm),
                    const _PublicShare(),
                    const SizedBox(height: AppSpacing.md),
                    Row(
                      children: [
                        Icon(
                          Icons.help_outline_rounded,
                          size: 15,
                          color: koraColors.textMuted,
                        ),
                        const SizedBox(width: 7),
                        Expanded(
                          child: Text(
                            l10n.colisNeedHelp(kColisSupportPhone),
                            style: AppTextStyles.caption(koraColors.textMuted),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Les trois conditions obligatoires au guichet.
///
/// Numérotées, parce qu'il en faut trois et pas deux : le guichet refuse
/// la remise s'il en manque une, et c'est au départ de chez soi qu'on
/// doit le savoir.
class _Conditions extends StatelessWidget {
  const _Conditions();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    final conditions = <(String, String)>[
      (l10n.colisCondition1Title, l10n.colisCondition1Text),
      (l10n.colisCondition2Title, l10n.colisCondition2Text),
      (l10n.colisCondition3Title, l10n.colisCondition3Text),
    ];

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
                Icons.verified_user_outlined,
                size: 17,
                color: context.koraTitleColor,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.colisDeliveryConditions,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 1),
                    Text(
                      l10n.colisConditionsCount(conditions.length),
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (var index = 0; index < conditions.length; index++) ...[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 24,
                  height: 24,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: koraColors.background,
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    '${index + 1}',
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
                        conditions[index].$1,
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 12,
                          height: 1.3,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        conditions[index].$2,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            if (index != conditions.length - 1) const SizedBox(height: 11),
          ],
        ],
      ),
    );
  }
}

/// Où le code de retrait a été envoyé — jamais le code lui-même.
class _CodeDestination extends StatelessWidget {
  const _CodeDestination({required this.parcel});

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
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Icon(
                Icons.lock_outline_rounded,
                size: 17,
                color: context.koraTitleColor,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisCodeSentTo,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Icon(Icons.chat_outlined, size: 15, color: koraColors.textMuted),
              const SizedBox(width: 7),
              Text(
                parcel.recipientPhoneMasked,
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            l10n.colisNoCodeHere,
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
        ],
      ),
    );
  }
}

/// Le délai de garde gratuit, et ce qu'il en coûte de le dépasser.
class _KeepingPeriod extends StatelessWidget {
  const _KeepingPeriod({required this.parcel, required this.until});

  final ColisTrackedParcel parcel;
  final String until;

  /// La part du délai déjà écoulée.
  ///
  /// Figée, faute de dates manipulables dans les données de
  /// démonstration : le jour où le serveur donnera la mise en consigne
  /// et l'échéance, c'est leur écart qui la calculera.
  static const double _elapsed = 0.2;

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
                Icons.hourglass_top_rounded,
                size: 17,
                color: context.koraTitleColor,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisFreeKeeping,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              KoraTag(
                label: l10n.colisFreeKeepingDays(kColisFreeKeepingDays),
                variant: KoraTagVariant.outline,
              ),
            ],
          ),
          const SizedBox(height: 9),
          Text(
            l10n.colisFreeKeepingText(
              until,
              l10n.colisAmount(kColisStorageFeePerDay),
            ),
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
          // Ce qui reste du délai, d'un coup d'œil : deux dates se
          // comparent mal, une barre se lit sans calculer.
          const SizedBox(height: 11),
          ClipRRect(
            borderRadius: BorderRadius.circular(AppRadius.pill),
            child: LinearProgressIndicator(
              value: _elapsed,
              minHeight: 6,
              backgroundColor: koraColors.line,
              valueColor: const AlwaysStoppedAnimation(AppColors.gold),
            ),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              if (parcel.receivedOn case final received?)
                Expanded(
                  child: Text(
                    l10n.colisReceivedOn(received),
                    style: AppTextStyles.caption(koraColors.textMuted),
                  ),
                ),
              Text(
                l10n.colisKeepUntil(until),
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Le partage d'un statut public, et ce qu'il laisse voir.
class _PublicShare extends StatelessWidget {
  const _PublicShare();

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
                Icons.share_outlined,
                size: 17,
                color: context.koraTitleColor,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisSharePublic,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          // Ce que le tiers verra, et surtout ce qu'il ne verra pas.
          Text(
            l10n.colisSharePublicText,
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
          const SizedBox(height: 12),
          KoraButton(
            label: l10n.colisShareLink,
            variant: KoraButtonVariant.secondary,
            icon: Icons.link_rounded,
            onPressed: () => KoraToaster.show(context, l10n.colisLinkCopied),
          ),
        ],
      ),
    );
  }
}

/// Le guichet de remise : son adresse et ses horaires.
class _CounterCard extends StatelessWidget {
  const _CounterCard({required this.counter});

  final ColisPickupCounter counter;

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
                Icons.storefront_outlined,
                size: 17,
                color: koraColors.textMuted,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisCounterTitle,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Text(
            counter.hub,
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.place_outlined, size: 15, color: koraColors.textMuted),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  counter.address,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _Hours(
                  label: l10n.colisWeekdays,
                  value: counter.weekdayHours,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _Hours(
                  label: l10n.colisWeekend,
                  value: counter.weekendHours,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Hours extends StatelessWidget {
  const _Hours({required this.label, required this.value});

  final String label;
  final String value;

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
              fontSize: 13,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

/// Le bandeau d'arrivée : l'état, le bordereau, et le trajet accompli.
///
/// Clair, et non marine : la maquette réserve le fond sombre aux écrans
/// où quelque chose est dû — un devis, un paiement. Ici le colis est
/// arrivé, il n'y a plus qu'à venir le chercher.
class _ArrivalBanner extends StatelessWidget {
  const _ArrivalBanner({required this.parcel});

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
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 5,
                ),
                decoration: BoxDecoration(
                  color: AppColors.success.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadius.pill),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 7,
                      height: 7,
                      decoration: const BoxDecoration(
                        shape: BoxShape.circle,
                        color: AppColors.success,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      l10n.colisStatusReady.toUpperCase(),
                      style: const TextStyle(
                        color: AppColors.success,
                        fontSize: 10,
                        letterSpacing: 0.5,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              const Spacer(),
              // Le bordereau : c'est ce numéro qu'on annonce au
              // comptoir, et il se lit avant le reste.
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    l10n.colisWaybillRef,
                    style: AppTextStyles.caption(koraColors.textMuted),
                  ),
                  const SizedBox(height: 1),
                  Text(
                    parcel.reference,
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 13,
                      fontWeight: FontWeight.w900,
                      fontFeatures: const [FontFeature.tabularFigures()],
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            l10n.colisArrivedTitle,
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 18,
              height: 1.2,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            l10n.colisArrivedText,
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
          const SizedBox(height: 12),
          // Le trajet accompli, en bande : d'où il vient, où il attend.
          Container(
            padding: const EdgeInsets.all(10),
            decoration: colisInsetDecoration(context),
            child: Row(
              children: [
                Expanded(
                  child: _RouteEnd(
                    icon: Icons.pin_drop_outlined,
                    tint: AppColors.kora,
                    label: l10n.colisDeparture,
                    value: '${parcel.originCity} (${parcel.originHub})',
                    alignEnd: false,
                  ),
                ),
                const SizedBox(width: 6),
                Icon(
                  Icons.arrow_forward_rounded,
                  size: 17,
                  color: koraColors.textMuted,
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: _RouteEnd(
                    icon: Icons.location_on_outlined,
                    tint: AppColors.gold,
                    label: l10n.colisArrival,
                    value:
                        '${parcel.destinationCity} '
                        '(${parcel.destinationHub})',
                    alignEnd: true,
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

/// Un bout du trajet dans la bande : sa pastille, son intitulé, son hub.
class _RouteEnd extends StatelessWidget {
  const _RouteEnd({
    required this.icon,
    required this.tint,
    required this.label,
    required this.value,
    required this.alignEnd,
  });

  final IconData icon;
  final Color tint;
  final String label;
  final String value;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    final badge = Container(
      width: 28,
      height: 28,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: tint.withValues(alpha: 0.14),
      ),
      child: Icon(icon, size: 16, color: tint),
    );

    final text = Column(
      crossAxisAlignment: alignEnd
          ? CrossAxisAlignment.end
          : CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(label, style: AppTextStyles.caption(koraColors.textMuted)),
        const SizedBox(height: 1),
        Text(
          value,
          maxLines: 2,
          textAlign: alignEnd ? TextAlign.end : TextAlign.start,
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: 12,
            height: 1.25,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: alignEnd
          ? [Expanded(child: text), const SizedBox(width: 8), badge]
          : [badge, const SizedBox(width: 8), Expanded(child: text)],
    );
  }
}
