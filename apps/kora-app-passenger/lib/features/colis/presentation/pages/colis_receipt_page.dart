import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../bloc/colis_draft_cubit.dart';
import '../widgets/colis_surface.dart';

/// Écran 09 — « Paiement confirmé ».
///
/// C'est le seul écran du parcours qui porte une **référence officielle**.
/// Elle n'existait pas une seconde plus tôt : le pré-enregistrement
/// l'annonçait comme une promesse, le devis la redisait, et c'est le
/// règlement qui la crée. Tout le ticket tient dans cette séquence.
class ColisReceiptPage extends StatelessWidget {
  const ColisReceiptPage({super.key});

  Future<void> _copy(BuildContext context, String reference) async {
    final confirmation = context.l10n.parcelsReferenceCopied;
    await Clipboard.setData(ClipboardData(text: reference));
    if (context.mounted) KoraToaster.show(context, confirmation);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return BlocBuilder<ColisDraftCubit, ColisDraft>(
      builder: (context, draft) {
        final reference = draft.reference ?? '';

        return Scaffold(
          body: SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18),
              child: ListView(
                padding: const EdgeInsets.only(
                  top: AppSpacing.sm,
                  bottom: AppSpacing.lg,
                ),
                children: [
                  // La pastille flottante de la maquette : ce qui est
                  // acquis, dit en trois mots avant tout le reste.
                  Center(
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 6,
                      ),
                      decoration: BoxDecoration(
                        color: koraColors.background,
                        borderRadius: BorderRadius.circular(AppRadius.pill),
                        boxShadow: kColisCardShadow,
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.verified_rounded,
                            size: 16,
                            color: AppColors.success,
                          ),
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              l10n.colisShipmentValidated.toUpperCase(),
                              textAlign: TextAlign.center,
                              style: AppTextStyles.eyebrow(
                                context.koraTitleColor,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  _Hero(
                    city: draft.originHub?.city ?? '',
                    reference: reference,
                    onCopy: () => _copy(context, reference),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  _AssignedRoute(draft: draft),
                  const SizedBox(height: AppSpacing.sm),
                  // La preuve de paiement, opposable : montant, moyen,
                  // horodatage, identifiant.
                  if (draft.settlement case final settlement?) ...[
                    _Settlement(settlement: settlement),
                    const SizedBox(height: AppSpacing.sm),
                  ],
                  ColisNote(
                    icon: Icons.sms_outlined,
                    title: l10n.colisPickupCodeTitle,
                    message: l10n.colisPickupCodeText,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  // Ce qu'on fait juste après avoir payé : on regarde
                  // où en est le colis.
                  KoraButton(
                    label: l10n.colisTrackCta,
                    icon: Icons.near_me_outlined,
                    iconPosition: KoraButtonIconPosition.edges,
                    onPressed: () => context.push(
                      AppRouter.colisTrackingPath,
                      extra: reference,
                    ),
                  ),
                  const SizedBox(height: 10),
                  // Le reçu se télécharge sur fond blanc, pas sur le
                  // marine plein : deux pavés de même poids se
                  // disputaient l'attention, alors que la maquette n'en
                  // met qu'un en avant.
                  ColisFlatAction(
                    label: l10n.colisReceiptDownload,
                    icon: Icons.qr_code_2_rounded,
                    onTap: () =>
                        KoraToaster.show(context, l10n.colisReceiptDownload),
                  ),
                  const SizedBox(height: 10),
                  Center(
                    child: InkWell(
                      // Le dossier est clos côté app : revenir en
                      // arrière rouvrirait un paiement déjà réglé.
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
                              Icons.arrow_back_rounded,
                              size: 17,
                              color: koraColors.textMuted,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              l10n.colisBackToParcels,
                              style: TextStyle(
                                color: koraColors.textMuted,
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
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
          ),
        );
      },
    );
  }
}

/// L'annonce du paiement, et la référence qu'il vient de créer.
///
/// La maquette tient les deux dans une seule carte blanche centrée : la
/// coche, le titre, la phrase, puis la référence dans un encart gris
/// avec son bouton de copie, et enfin le hub qui a pris le colis en
/// charge. La référence sur une carte marine à part faisait un second
/// héros, et on ne savait plus lequel regarder.
class _Hero extends StatelessWidget {
  const _Hero({
    required this.city,
    required this.reference,
    required this.onCopy,
  });

  final String city;
  final String reference;
  final VoidCallback onCopy;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Le disque vert et son halo : la coche de la maquette, pas
          // le médaillon doré des autres succès de l'app — ici c'est un
          // paiement vérifié, et le vert le dit mieux.
          Container(
            width: 64,
            height: 64,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.success.withValues(alpha: 0.12),
            ),
            child: Container(
              width: 48,
              height: 48,
              alignment: Alignment.center,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.success,
              ),
              child: const Icon(
                Icons.check_rounded,
                size: 28,
                color: AppColors.white,
              ),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            l10n.colisPaidTitle,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 23,
              height: 1.15,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            l10n.colisPaidText,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: koraColors.textMuted,
              fontSize: 13,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 14),
          // La référence et son bouton de copie : c'est le numéro qu'on
          // dicte au guichet, et il ne se retient pas de mémoire.
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
            decoration: colisInsetDecoration(context),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  l10n.colisRefShort.toUpperCase(),
                  style: AppTextStyles.eyebrow(koraColors.textMuted),
                ),
                const SizedBox(width: 8),
                Flexible(
                  child: Text(
                    reference,
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 15,
                      letterSpacing: 0.3,
                      fontWeight: FontWeight.w900,
                      fontFeatures: const [FontFeature.tabularFigures()],
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Material(
                  color: koraColors.surface,
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                  child: InkWell(
                    onTap: onCopy,
                    borderRadius: BorderRadius.circular(AppRadius.sm),
                    child: SizedBox(
                      width: 28,
                      height: 28,
                      child: Icon(
                        Icons.content_copy_rounded,
                        size: 15,
                        color: context.koraTitleColor,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          // Le hub qui a pris le colis en charge : c'est là qu'il est à
          // cette seconde, et le point vert dit que c'est l'état vivant.
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
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
                const SizedBox(width: 7),
                Flexible(
                  child: Text(
                    l10n.colisRegisteredAt(city),
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: AppColors.success,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
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

/// L'itinéraire assigné, scellé au hub de départ.
///
/// La maquette le dessine en rail vertical : un point marine à cœur
/// doré pour le départ, un point gris à épingle pour l'arrivée, et sous
/// chacun ce qui s'y passe. Les deux horaires ferment la carte dans un
/// encart gris — ils appartiennent au trajet, et faisaient carte à part
/// sans raison.
class _AssignedRoute extends StatelessWidget {
  const _AssignedRoute({required this.draft});

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
              Icon(
                Icons.route_rounded,
                size: 19,
                color: context.koraTitleColor,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisAssignedRoute,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              // La maquette porte ici le nom de l'axe (« RN1 Express »).
              // Aucune donnée ne le dit côté app : on garde ce qui est
              // vrai à cette seconde, le hub qui a scellé le colis.
              KoraTag(
                label: l10n.colisSealedAt(draft.originHub?.city ?? ''),
                variant: KoraTagVariant.success,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          _RailStop(
            city: draft.originHub?.city ?? '',
            hub: draft.originHub?.name ?? '',
            note: l10n.colisPaidWeight(colisWeightText(draft.billableWeightKg)),
            isDeparture: true,
          ),
          // Le filet qui relie les deux points, aligné sur leur centre.
          Row(
            children: [
              SizedBox(
                width: 20,
                child: Center(
                  child: Container(
                    width: 2,
                    height: 20,
                    color: koraColors.line,
                  ),
                ),
              ),
            ],
          ),
          _RailStop(
            city: draft.destinationHub?.city ?? '',
            hub: draft.destinationHub?.name ?? '',
            note: l10n.colisSecureCustody,
            isDeparture: false,
          ),
          const SizedBox(height: 14),
          // Quand le car part, et quand le destinataire peut se
          // déplacer. Sans ces deux heures, le reçu ne dit pas ce qu'il
          // faut faire ensuite.
          Container(
            padding: const EdgeInsets.all(11),
            decoration: colisInsetDecoration(context),
            child: Row(
              children: [
                const Icon(
                  Icons.schedule_rounded,
                  size: 19,
                  color: AppColors.digital,
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        l10n.colisNextDeparture,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                      const SizedBox(height: 1),
                      Text(
                        l10n.colisTodayAt(kColisNextDeparture),
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.colisEtaLabel,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                    const SizedBox(height: 1),
                    Text(
                      l10n.colisTomorrowAt(kColisPlannedArrival),
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
        ],
      ),
    );
  }
}

/// Un bout du rail : son point, sa ville, sa gare, et ce qui s'y passe.
class _RailStop extends StatelessWidget {
  const _RailStop({
    required this.city,
    required this.hub,
    required this.note,
    required this.isDeparture,
  });

  final String city;
  final String hub;
  final String note;

  /// Le départ porte le point marine à cœur doré — le colis y est déjà
  /// passé. L'arrivée porte l'épingle grise : elle est à venir.
  final bool isDeparture;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 20,
          child: Center(
            child: Container(
              width: 20,
              height: 20,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: isDeparture ? AppColors.kora : koraColors.background,
              ),
              child: isDeparture
                  ? Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        shape: BoxShape.circle,
                        color: AppColors.gold,
                      ),
                    )
                  : Icon(
                      Icons.pin_drop_rounded,
                      size: 13,
                      color: context.koraTitleColor,
                    ),
            ),
          ),
        ),
        const SizedBox(width: 11),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    city,
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 15,
                      height: 1.2,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Flexible(
                    child: Text(
                      hub,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 2),
              Text(note, style: AppTextStyles.caption(koraColors.textMuted)),
            ],
          ),
        ),
      ],
    );
  }
}

/// La preuve de paiement : combien, par quoi, quand, sous quel numéro.
///
/// C'est la partie du reçu qu'on ressort en cas de litige. Elle lit le
/// règlement figé au paiement, jamais l'état courant du brouillon.
class _Settlement extends StatelessWidget {
  const _Settlement({required this.settlement});

  final ColisSettlement settlement;

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
                child: Text(
                  l10n.colisSettlementTitle,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              KoraTag(
                label: l10n.colisPaymentVerified,
                variant: KoraTagVariant.success,
              ),
            ],
          ),
          const SizedBox(height: 11),
          _SettlementRow(
            label: l10n.colisAmountPaid,
            value: l10n.colisAmount(settlement.amount),
            strong: true,
          ),
          const SizedBox(height: 9),
          _SettlementRow(
            label: l10n.colisDebitMethod,
            value: settlement.method,
          ),
          const SizedBox(height: 9),
          _SettlementRow(
            label: l10n.colisOfficialTimestamp,
            value: l10n.colisTodayAt(_clock(settlement.settledAt)),
          ),
          const SizedBox(height: 9),
          _SettlementRow(
            label: l10n.colisTransactionLabel,
            value: settlement.transactionId,
          ),
        ],
      ),
    );
  }

  static String _clock(DateTime time) {
    final hour = time.hour.toString().padLeft(2, '0');
    final minute = time.minute.toString().padLeft(2, '0');
    return '$hour:$minute';
  }
}

/// Une ligne du règlement : son intitulé à gauche, sa valeur à droite.
class _SettlementRow extends StatelessWidget {
  const _SettlementRow({
    required this.label,
    required this.value,
    this.strong = false,
  });

  final String label;
  final String value;
  final bool strong;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            label,
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
        ),
        const SizedBox(width: 10),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: strong ? 17 : 12,
              fontWeight: strong ? FontWeight.w900 : FontWeight.w800,
            ),
          ),
        ),
      ],
    );
  }
}
