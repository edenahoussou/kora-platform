import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../bloc/colis_draft_cubit.dart';
import '../widgets/colis_banner.dart';
import '../widgets/colis_surface.dart';
import 'colis_checkout_args.dart';
import 'colis_missing_checkout.dart';

/// La demande est partie, sa confirmation se fait attendre.
///
/// C'est le seul écran d'attente du règlement, comme pour un billet. Mais
/// là où le billet attend le verdict du serveur, celui-ci n'attend rien :
/// aucun endpoint `parcel` n'existe, et le ticket interdit tout paiement
/// réel. Le bouton le dit en toutes lettres — **simuler** la confirmation
/// — plutôt qu'un faux délai qui ferait croire à un vrai opérateur.
///
/// L'écran porte tout ce qu'il faut pour ne pas payer deux fois :
/// l'identifiant de la tentative, la répartition du montant, le numéro
/// débité et l'heure de la demande. Un voyageur qui doute relance un
/// second paiement — et c'est précisément ce qu'on veut lui éviter.
///
/// C'est ici, et nulle part ailleurs, que naît la référence officielle :
/// elle suit le paiement, jamais l'intention de payer.
class ColisPendingPage extends StatelessWidget {
  const ColisPendingPage({super.key, this.args});

  /// `null` si l'écran est ouvert sans données (lien profond).
  final ColisCheckoutArgs? args;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final args = this.args;

    if (args == null) return const ColisMissingCheckout();

    final payer = args.payerLabel ?? args.gateway.name;

    return BlocBuilder<ColisDraftCubit, ColisDraft>(
      builder: (context, draft) => Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: ListView(
              children: [
                const SizedBox(height: AppSpacing.lg),
                // La maquette n'emploie pas le médaillon des succès :
                // rien n'est acquis ici, et un grand disque coché
                // ferait croire au contraire.
                _PendingHeader(
                  payer: payer,
                  due: args.due,
                  transactionId: colisNewTransactionId(
                    args.startedAt ?? DateTime.now(),
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                _SplitCard(args: args, draft: draft),
                const SizedBox(height: AppSpacing.sm),
                _OperatorCard(args: args, payer: payer),
                const SizedBox(height: AppSpacing.sm),
                _ParcelLine(draft: draft),
                const SizedBox(height: AppSpacing.sm),
                // Le message qui évite le double débit, sur le jaune
                // d'information et non sur une note grise : c'est la
                // seule chose que le payeur doive lire avant de toucher
                // quoi que ce soit.
                KoraAlert(
                  variant: KoraAlertVariant.info,
                  icon: Icons.security_outlined,
                  title: l10n.colisNoDoublePayTitle,
                  message: l10n.colisNoDoublePayText,
                ),
                const SizedBox(height: AppSpacing.sm),
                ColisNote(
                  icon: Icons.science_outlined,
                  title: l10n.colisPaymentMockTitle,
                  message: l10n.colisPaymentMockText,
                ),
                const SizedBox(height: AppSpacing.sm),
                // Le levier de démonstration, et non l'action de
                // l'écran : il ne figure pas sur la maquette, et le
                // doré est réservé à la relecture du statut. Sa place
                // est sous la note qui dit que rien n'est encaissé.
                KoraButton(
                  label: l10n.colisPendingSimulate,
                  variant: KoraButtonVariant.secondary,
                  icon: Icons.check_rounded,
                  iconPosition: KoraButtonIconPosition.edges,
                  onPressed: () {
                    // La référence officielle naît ici, et nulle part
                    // ailleurs.
                    final now = DateTime.now();
                    context.read<ColisDraftCubit>().settle(
                      colisNewReference(now),
                      // Ce que le reçu devra redire des mois plus
                      // tard : la somme encaissée, par quoi, quand, et
                      // sous quel identifiant.
                      settlement: ColisSettlement(
                        amount: draft.total,
                        method: payer,
                        transactionId: colisNewTransactionId(
                          args.startedAt ?? now,
                        ),
                        settledAt: now,
                      ),
                    );
                    // `pushReplacement` : le reçu ne doit pas ramener à
                    // une attente déjà close.
                    context.pushReplacement(AppRouter.colisReceiptPath);
                  },
                ),
                const SizedBox(height: AppSpacing.md),
                // Relire le statut sans rien conclure : c'est le geste
                // qu'on fait quand l'attente dure, et il vaut mieux
                // qu'il existe qu'un second paiement. La maquette lui
                // donne le doré — c'est la seule action attendue ici.
                KoraButton(
                  label: l10n.colisCheckStatus,
                  icon: Icons.refresh_rounded,
                  iconPosition: KoraButtonIconPosition.edges,
                  onPressed: () => KoraToaster.show(
                    context,
                    l10n.colisPendingText(payer, args.due),
                  ),
                ),
                const SizedBox(height: 10),
                // Les deux secours, côte à côte et sur fond blanc,
                // comme la maquette : renvoyer le push ne crée pas une
                // seconde dette, changer de moyen n'annule pas la
                // tentative en cours. Empilés en pleine largeur, ils
                // pesaient autant que la relecture du statut.
                Row(
                  children: [
                    if (args.payerPhone != null) ...[
                      Expanded(
                        child: ColisFlatAction(
                          label: l10n.colisResendPush,
                          icon: Icons.send_to_mobile_outlined,
                          tint: context.koraTitleColor,
                          height: 46,
                          onTap: () =>
                              KoraToaster.show(context, l10n.colisPushSent),
                        ),
                      ),
                      const SizedBox(width: 10),
                    ],
                    Expanded(
                      child: ColisFlatAction(
                        label: l10n.colisPendingSwitch,
                        icon: Icons.arrow_back_rounded,
                        tint: koraColors.textMuted,
                        height: 46,
                        onTap: () => context.pop(),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.md),
                // L'assistance, discrète et centrée : on ne la cherche
                // que si l'opérateur ne répond pas.
                Center(
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.headset_mic_outlined,
                        size: 15,
                        color: koraColors.textMuted,
                      ),
                      const SizedBox(width: 7),
                      Flexible(
                        child: Text(
                          l10n.colisOperatorSupport(kColisSupportPhone),
                          textAlign: TextAlign.center,
                          style: AppTextStyles.caption(koraColors.textMuted),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                const _FailurePreview(),
                const SizedBox(height: AppSpacing.xl),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// La répartition du règlement : ce que le solde couvre, ce que
/// l'opérateur encaisse, et le détail du montant.
///
/// Sans elle, le payeur voit un montant partiel sur sa facture
/// d'opérateur et croit à une erreur.
class _SplitCard extends StatelessWidget {
  const _SplitCard({required this.args, required this.draft});

  final ColisCheckoutArgs args;
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
              Expanded(
                child: Text(
                  l10n.colisSplitTitle,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              KoraTag(
                label: l10n.colisSplitSettled,
                variant: KoraTagVariant.outline,
              ),
            ],
          ),
          const SizedBox(height: 11),
          ColisAmountRow(
            label: l10n.colisTotalDue,
            value: l10n.colisAmount(draft.total),
            isTotal: true,
          ),
          if (args.walletApplied > 0) ...[
            const SizedBox(height: 9),
            ColisAmountRow(
              label: l10n.colisWalletTitle,
              value: l10n.colisAmount(args.walletApplied),
            ),
          ],
          const SizedBox(height: 9),
          ColisAmountRow(
            label: args.payerLabel ?? args.gateway.name,
            value: l10n.colisAmount(args.due),
          ),
          const SizedBox(height: 8),
          Text(
            l10n.colisSplitDetail(
              l10n.colisAmount(draft.transportFee),
              l10n.colisAmount(kColisSealFee),
            ),
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
        ],
      ),
    );
  }
}

/// Qui encaisse, sur quel numéro, et depuis quand.
class _OperatorCard extends StatelessWidget {
  const _OperatorCard({required this.args, required this.payer});

  final ColisCheckoutArgs args;
  final String payer;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final startedAt = args.startedAt;

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
                Icons.cell_tower_rounded,
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
                      payer,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    // Le numéro débité : c'est sur ce téléphone-là que
                    // l'invite est partie, et pas un autre.
                    if (args.payerPhone case final phone?) ...[
                      const SizedBox(height: 2),
                      Text(
                        phone,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                    ],
                  ],
                ),
              ),
              KoraTag(
                label: l10n.colisSessionActive(kColisPaymentSessionMinutes),
                variant: KoraTagVariant.outline,
              ),
            ],
          ),
          const SizedBox(height: 11),
          Row(
            children: [
              const Icon(
                Icons.done_rounded,
                size: 15,
                color: AppColors.success,
              ),
              const SizedBox(width: 7),
              Text(
                l10n.colisPushSent,
                style: AppTextStyles.caption(koraColors.textMuted),
              ),
            ],
          ),
          if (startedAt != null) ...[
            const SizedBox(height: 7),
            Row(
              children: [
                Icon(
                  Icons.schedule_rounded,
                  size: 15,
                  color: koraColors.textMuted,
                ),
                const SizedBox(width: 7),
                Text(
                  l10n.colisInitiatedAt(_clock(startedAt)),
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  /// `14:18` — l'heure seule, qui suffit à situer l'attente.
  static String _clock(DateTime time) {
    final hour = time.hour.toString().padLeft(2, '0');
    final minute = time.minute.toString().padLeft(2, '0');
    return '$hour:$minute';
  }
}

/// Ce qu'on est en train de payer, en une ligne.
class _ParcelLine extends StatelessWidget {
  const _ParcelLine({required this.draft});

  final ColisDraft draft;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Row(
      children: [
        Icon(
          Icons.local_shipping_outlined,
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
                '${draft.originHub?.city ?? ''} → '
                '${draft.destinationHub?.city ?? ''}',
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                l10n.colisParcelLine(colisWeightText(draft.billableWeightKg)),
                style: AppTextStyles.caption(koraColors.textMuted),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Ce qui arrive si l'opérateur refuse, replié par défaut.
///
/// Un refus inquiète surtout parce qu'on ne sait pas si le compte a été
/// débité. La réponse se lit ici, avant l'échec plutôt qu'après.
class _FailurePreview extends StatefulWidget {
  const _FailurePreview();

  @override
  State<_FailurePreview> createState() => _FailurePreviewState();
}

class _FailurePreviewState extends State<_FailurePreview> {
  var _open = false;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          InkWell(
            onTap: () => setState(() => _open = !_open),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  Icon(
                    Icons.troubleshoot_rounded,
                    size: 17,
                    color: koraColors.textMuted,
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    child: Text(
                      l10n.colisFailurePreview,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  Icon(
                    _open
                        ? Icons.expand_less_rounded
                        : Icons.expand_more_rounded,
                    color: koraColors.textMuted,
                  ),
                ],
              ),
            ),
          ),
          if (_open)
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      const Icon(
                        Icons.cancel_outlined,
                        size: 15,
                        color: AppColors.danger,
                      ),
                      const SizedBox(width: 7),
                      Text(
                        l10n.colisFailureTitle,
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    l10n.colisFailureReason,
                    style: AppTextStyles.caption(koraColors.textMuted),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    l10n.colisFailureText,
                    style: AppTextStyles.caption(koraColors.textMuted),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// L'en-tête de l'attente : l'état, l'identifiant, et ce qui n'existe
/// pas encore.
///
/// Pas le médaillon des succès : à cette seconde rien n'est acquis, et
/// un grand disque coché ferait croire au contraire. La maquette pose
/// une carte, une pastille d'état, l'identifiant de la tentative, puis
/// l'avertissement sur la référence absente.
class _PendingHeader extends StatelessWidget {
  const _PendingHeader({
    required this.payer,
    required this.due,
    required this.transactionId,
  });

  final String payer;
  final int due;
  final String transactionId;

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
                  color: AppColors.gold.withValues(alpha: 0.18),
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
                        color: AppColors.gold,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      l10n.colisPendingTag,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              // L'identifiant de la tentative : la référence KC-
              // n'existe pas encore, et c'est celui-ci qu'on donne à
              // l'assistance en attendant.
              Expanded(
                child: Text(
                  l10n.colisTransactionId(transactionId),
                  textAlign: TextAlign.end,
                  maxLines: 2,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 46,
                height: 46,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.kora,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: const Icon(
                  Icons.sync_rounded,
                  size: 24,
                  color: AppColors.gold,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.colisPendingTitle,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 17,
                        height: 1.2,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      l10n.colisPendingText(payer, due),
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: colisInsetDecoration(context),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.info_outline_rounded,
                  size: 19,
                  color: koraColors.textMuted,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        l10n.colisNoReferenceTitle,
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        l10n.colisNoReferenceText,
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
