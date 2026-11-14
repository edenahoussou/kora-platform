import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../booking/domain/entities/price.dart';
import '../../domain/entities/wallet_movement.dart';

/// Une écriture du relevé, telle qu'elle se lit.
///
/// La même ligne sert à l'aperçu de l'onglet Wallet et à l'historique
/// complet : la dupliquer laisserait les deux écrans diverger sur le
/// signe, le libellé ou l'unité — et c'est précisément ce qui ferait
/// douter un voyageur qui compare les deux.
class WalletMovementTile extends StatelessWidget {
  const WalletMovementTile({
    super.key,
    required this.movement,
    this.withTime = false,
    this.isLast = false,
  });

  final WalletMovement movement;

  /// L'heure en plus de la date — « 28 juil. · 11:42 ».
  ///
  /// L'aperçu de l'onglet Wallet s'en passe : trois lignes n'ont pas
  /// besoin d'être départagées. Le relevé complet, si — deux écritures
  /// du même jour s'y suivent, et c'est l'heure qui les ordonne.
  final bool withTime;

  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final strong = isDark ? koraColors.textPrimary : AppColors.kora;

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(
        border: isLast
            ? null
            : Border(bottom: BorderSide(color: koraColors.line)),
      ),
      child: Row(
        children: [
          WalletRoundIcon(icon: iconOf(movement.reason), color: strong),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  titleOf(l10n, movement.reason),
                  style: TextStyle(
                    color: strong,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  _when(movement.createdAt),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: koraColors.textMuted, fontSize: 10),
                ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Text(
            amountOf(l10n, movement),
            style: TextStyle(
              // Un crédit s'affiche en bleu digital
              // (`.transaction-amount.credit`) ; un débit garde la
              // couleur du texte fort.
              color: movement.isCredit ? AppColors.digital : strong,
              fontSize: 12,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }

  /// La date de l'écriture, avec l'heure quand l'écran la demande.
  String _when(DateTime at) => withTime
      ? '${KoraDateFormats.dayMonthShort.format(at)} · '
            '${KoraDateFormats.time.format(at)}'
      : KoraDateFormats.dayMonthYear.format(at);

  /// Le montant signé, dans l'unité de son solde.
  ///
  /// Le serveur rend une valeur positive et le sens à part : c'est ici
  /// que les deux se rejoignent, pour que « − » et « + » ne dépendent
  /// jamais du signe reçu.
  static String amountOf(AppLocalization l10n, WalletMovement movement) {
    final sign = movement.isCredit ? '+ ' : '− ';
    final value = movement.kind == WalletBalanceKind.points
        ? l10n.walletPointsUnit('${movement.amount}')
        : Price(movement.amount).formatted;

    return '$sign$value';
  }

  /// Le libellé métier d'un motif. Un motif ajouté côté serveur après
  /// cette version s'affiche sobrement plutôt que sous son code.
  static String titleOf(AppLocalization l10n, WalletReason reason) =>
      switch (reason) {
        WalletReason.topUp => l10n.walletReasonTopup,
        WalletReason.purchase => l10n.walletReasonPurchase,
        WalletReason.refundCash => l10n.walletReasonRefundCash,
        WalletReason.refundPoints => l10n.walletReasonRefundPoints,
        WalletReason.pointsEarn => l10n.walletReasonPointsEarn,
        WalletReason.bonusWelcome => l10n.walletReasonBonusWelcome,
        WalletReason.bonusReferral => l10n.walletReasonBonusReferral,
        WalletReason.bonusAnniversary => l10n.walletReasonBonusAnniversary,
        WalletReason.bonusUsage => l10n.walletReasonBonusUsage,
        WalletReason.pointsExpire => l10n.walletReasonPointsExpire,
        WalletReason.pointsCorrection => l10n.walletReasonPointsCorrection,
        WalletReason.compensationOffset => l10n.walletReasonCompensationOffset,
        WalletReason.restitution => l10n.walletReasonRestitution,
        WalletReason.adjustment => l10n.walletReasonAdjustment,
        WalletReason.unknown => l10n.walletReasonUnknown,
      };

  static IconData iconOf(WalletReason reason) => switch (reason) {
    WalletReason.topUp => Icons.add_rounded,
    WalletReason.purchase => Icons.remove_rounded,
    WalletReason.refundCash || WalletReason.refundPoints => Icons.undo_rounded,
    WalletReason.pointsEarn => Icons.star_rounded,
    WalletReason.bonusWelcome ||
    WalletReason.bonusReferral ||
    WalletReason.bonusAnniversary ||
    WalletReason.bonusUsage => Icons.card_giftcard_rounded,
    WalletReason.pointsExpire => Icons.schedule_rounded,
    WalletReason.pointsCorrection ||
    WalletReason.adjustment => Icons.tune_rounded,
    WalletReason.compensationOffset => Icons.handshake_outlined,
    WalletReason.restitution => Icons.account_balance_outlined,
    WalletReason.unknown => Icons.circle_outlined,
  };
}

/// Le pictogramme rond du relevé, repris par la bande des points pour
/// que les deux s'alignent au pixel près.
class WalletRoundIcon extends StatelessWidget {
  const WalletRoundIcon({super.key, required this.icon, required this.color});

  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 40,
      height: 40,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: context.koraColors.background,
        borderRadius: BorderRadius.circular(13),
      ),
      child: Icon(icon, size: 19, color: color),
    );
  }
}
