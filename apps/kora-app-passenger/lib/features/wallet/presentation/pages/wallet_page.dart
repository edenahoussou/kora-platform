import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/brand_mini.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../../booking/domain/entities/price.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import '../../domain/entities/wallet_movement.dart';
import '../../domain/repositories/wallet_repository.dart';
import '../bloc/wallet_cubit.dart';

/// Écran `wallet` de la maquette — l'onglet « Wallet » : les soldes, les
/// points de fidélité, les quatre actions et les derniers mouvements.
///
/// **Tout vient du serveur** (`GET /api/v1/wallet`, MEZ-160) : les deux
/// soldes, l'échéance des points, le code de parrainage et les règles.
/// L'écran a longtemps montré « 18 750 FCFA » et « 2 480 points » écrits
/// en dur ; un solde faux est pire qu'un écran qui attend.
class WalletPage extends StatelessWidget {
  const WalletPage({super.key, this.cubit, this.today});

  /// Cubit déjà construit, pour les tests. En vrai, l'écran le crée
  /// lui-même et le ferme en partant.
  final WalletCubit? cubit;

  /// Horloge de référence, figée par les tests : l'alerte d'expiration
  /// dépend du jour où l'écran s'ouvre.
  final DateTime? today;

  @override
  Widget build(BuildContext context) {
    final existing = cubit;

    return BlocProvider<WalletCubit>(
      create: (_) =>
          (existing ?? WalletCubit(getIt<WalletRepository>()))..load(),
      child: _WalletView(today: today),
    );
  }
}

class _WalletView extends StatelessWidget {
  const _WalletView({this.today});

  final DateTime? today;

  void _soon(BuildContext context) =>
      KoraToaster.show(context, context.l10n.walletSoon);

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                showBack: false,
                leading: const BrandMini(),
                eyebrow: l10n.walletEyebrow,
                title: l10n.walletTitle,
                action: KoraIconButton(
                  icon: Icons.north_east_rounded,
                  onTap: () => _soon(context),
                ),
              ),
              Expanded(
                child: BlocBuilder<WalletCubit, WalletState>(
                  builder: (context, state) => _body(context, state, l10n),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _body(BuildContext context, WalletState state, AppLocalization l10n) {
    if (state.isFirstLoad) return const _Skeleton();

    final account = state.account;
    if (account == null) {
      return StateView(
        icon: Icons.account_balance_wallet_outlined,
        title: l10n.walletErrorTitle,
        message: state.failure?.message ?? l10n.walletErrorText,
        primaryLabel: l10n.actionRetry,
        onPrimary: context.read<WalletCubit>().load,
        isPrimaryLoading: state.isLoading,
      );
    }

    final now = today ?? DateTime.now();
    final expiry = account.pointsExpireAt;

    return RefreshIndicator(
      onRefresh: context.read<WalletCubit>().load,
      child: ListView(
        padding: const EdgeInsets.only(bottom: 100),
        children: [
          if (account.isDormant) ...[
            _Dormant(
              title: l10n.walletDormantTitle,
              message: l10n.walletDormantText,
            ),
            const SizedBox(height: 11),
          ],
          _BalanceCard(balance: account.cash),
          const SizedBox(height: 9),
          _PointsStrip(
            points: account.points,
            value: account.pointsValue,
            onTap: () => _soon(context),
          ),
          const SizedBox(height: 6),
          // L'échéance se lit en permanence, pas seulement quand elle
          // approche : des points qui tombent sans prévenir passent pour
          // une erreur de l'application.
          _ExpiryLine(
            label: expiry == null
                ? l10n.walletPointsExpiryNone
                : l10n.walletPointsExpiry(
                    KoraDateFormats.dayMonthYear.format(expiry),
                  ),
          ),
          if (account.needsExpiryWarning(now)) ...[
            const SizedBox(height: 9),
            _ExpiryWarning(
              message: switch (account.daysBeforeExpiry(now) ?? 0) {
                <= 0 => l10n.walletPointsExpiryToday,
                final days => l10n.walletPointsExpirySoon(days),
              },
            ),
          ],
          const SizedBox(height: 11),
          _ReferralCard(code: account.referralCode),
          const SizedBox(height: 11),
          _ActionGrid(onTap: () => _soon(context)),
          const SizedBox(height: 11),
          SectionTitle(
            title: l10n.walletMovements,
            actionLabel: l10n.actionSeeAll,
            onActionTap: () => _soon(context),
          ),
          const SizedBox(height: AppSpacing.sm),
          _Movements(
            movements: state.movements,
            hasFailed: state.movementsFailed,
          ),
        ],
      ),
    );
  }
}

/// `.wallet-card` : la carte navy du solde, avec son biseau doré doublé
/// d'un liseré blanc.
class _BalanceCard extends StatelessWidget {
  const _BalanceCard({required this.balance});

  final Price balance;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final faded = AppColors.white.withValues(alpha: 0.6);

    return Container(
      // Hauteur fixe, et non un minimum : le pied de carte est posé en
      // absolu sur le bas (`.wallet-foot { position: absolute }`), ce qui
      // suppose une hauteur connue. Dans un `ListView`, la hauteur
      // disponible est infinie — un `Spacer` y était impossible à
      // résoudre et faisait disparaître toute la liste.
      height: 192,
      clipBehavior: Clip.antiAlias,
      // Contour en `foregroundDecoration` : dans une `BoxDecoration` il se
      // peindrait derrière le biseau, qui le recouvrirait.
      foregroundDecoration: isDark
          ? BoxDecoration(
              borderRadius: BorderRadius.circular(26),
              border: Border.all(color: koraColors.line),
            )
          : null,
      decoration: BoxDecoration(
        color: AppColors.kora,
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(
            color: AppColors.kora.withValues(alpha: 0.22),
            offset: const Offset(0, 20),
            blurRadius: 45,
          ),
        ],
      ),
      child: Stack(
        children: [
          // `.wallet-card::after` : le biseau doré, surmonté d'un trait
          // blanc translucide (`box-shadow: 0 -15px 0 rgba(255,255,255,.1)`).
          Positioned(
            right: -80,
            bottom: 45,
            child: Transform.rotate(
              angle: -28 * 3.1415926535 / 180,
              child: SizedBox(
                width: 260,
                height: 24,
                child: Stack(
                  children: [
                    Positioned(
                      left: 0,
                      right: 0,
                      top: 0,
                      child: Container(
                        height: 9,
                        color: AppColors.white.withValues(alpha: 0.1),
                      ),
                    ),
                    Positioned(
                      left: 0,
                      right: 0,
                      bottom: 0,
                      child: Container(height: 9, color: AppColors.gold),
                    ),
                  ],
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  l10n.walletBalanceLabel,
                  style: TextStyle(color: faded, fontSize: 12),
                ),
                const SizedBox(height: 19),
                Text(
                  balance.formatted,
                  style: const TextStyle(
                    color: AppColors.white,
                    fontSize: 31,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -1.24,
                  ),
                ),
              ],
            ),
          ),
          // `.wallet-foot { left: 20; right: 20; bottom: 18 }`
          Positioned(
            left: 20,
            right: 20,
            bottom: 18,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Flexible(
                  child: Text(
                    l10n.walletBalanceSource,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: faded, fontSize: 11),
                  ),
                ),
                Text(
                  l10n.walletBalanceAvailable,
                  style: const TextStyle(color: AppColors.white, fontSize: 11),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// `.wallet-points-strip` : les points de fidélité et leur contre-valeur.
class _PointsStrip extends StatelessWidget {
  const _PointsStrip({
    required this.points,
    required this.value,
    required this.onTap,
  });

  final int points;

  /// La contre-valeur en francs, calculée avec le taux du serveur : un
  /// point ne vaut pas forcément un franc, c'est un réglage.
  final Price value;

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final strong = isDark ? koraColors.textPrimary : AppColors.kora;

    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(15),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(15),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(15),
            border: Border.all(color: koraColors.line),
          ),
          child: Row(
            children: [
              _TransactionIcon(icon: Icons.star_rounded, color: strong),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.walletPointsLabel,
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 10,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      // Les deux sont affichés pour que la valeur soit
                      // lisible sans calcul.
                      l10n.walletPointsValue('$points', value.formatted),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: strong,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Icon(
                Icons.chevron_right_rounded,
                size: 18,
                color: koraColors.textMuted,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.wallet-action-grid` : les quatre raccourcis, sur une seule rangée.
class _ActionGrid extends StatelessWidget {
  const _ActionGrid({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    final actions = <(IconData, String)>[
      (Icons.add_rounded, l10n.walletActionTopup),
      (Icons.bolt_rounded, l10n.walletActionUse),
      (Icons.star_rounded, l10n.walletActionBonus),
      (Icons.north_east_rounded, l10n.walletActionHistory),
    ];

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final action in actions) ...[
            if (action != actions.first) const SizedBox(width: 7),
            Expanded(
              child: _ActionTile(
                icon: action.$1,
                label: action.$2,
                onTap: onTap,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final strong = isDark ? koraColors.textPrimary : AppColors.kora;

    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(15),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(15),
        child: Container(
          constraints: const BoxConstraints(minHeight: 64),
          padding: const EdgeInsets.symmetric(horizontal: 3, vertical: 7),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(15),
            border: Border.all(color: koraColors.line),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 28,
                height: 28,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: koraColors.background,
                  borderRadius: BorderRadius.circular(9),
                ),
                child: Icon(icon, size: 15, color: strong),
              ),
              const SizedBox(height: 6),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: strong,
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Les trois derniers mouvements, dans une seule carte.
class _Movements extends StatelessWidget {
  const _Movements({required this.movements, required this.hasFailed});

  final List<WalletMovement> movements;

  /// Le relevé n'a pas répondu alors que les soldes, si : on le dit ici
  /// plutôt que de laisser croire qu'il ne s'est rien passé.
  final bool hasFailed;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    if (movements.isEmpty) {
      return KoraCard(
        variant: KoraCardVariant.mist,
        child: Text(
          hasFailed ? l10n.walletMovementsFailed : l10n.walletMovementsEmpty,
          style: AppTextStyles.body(koraColors.textMuted),
        ),
      );
    }

    return KoraCard(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (final (index, movement) in movements.indexed)
            _Transaction(
              icon: _iconOf(movement.reason),
              title: _titleOf(l10n, movement.reason),
              subtitle: KoraDateFormats.dayMonthYear.format(movement.createdAt),
              amount: _amountOf(l10n, movement),
              isCredit: movement.isCredit,
              isLast: index == movements.length - 1,
            ),
        ],
      ),
    );
  }

  /// Le montant signé, dans l'unité de son solde.
  ///
  /// Le serveur rend une valeur positive et le sens à part : c'est ici
  /// que les deux se rejoignent, pour que « − » et « + » ne dépendent
  /// jamais du signe reçu.
  static String _amountOf(AppLocalization l10n, WalletMovement movement) {
    final sign = movement.isCredit ? '+ ' : '− ';
    final value = movement.kind == WalletBalanceKind.points
        ? l10n.walletPointsUnit('${movement.amount}')
        : Price(movement.amount).formatted;

    return '$sign$value';
  }

  /// Le libellé métier d'un motif. Un motif ajouté côté serveur après
  /// cette version s'affiche sobrement plutôt que sous son code.
  static String _titleOf(AppLocalization l10n, WalletReason reason) =>
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

  static IconData _iconOf(WalletReason reason) => switch (reason) {
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

/// L'échéance des points, lisible en permanence sous la bande.
class _ExpiryLine extends StatelessWidget {
  const _ExpiryLine({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 4),
      child: Text(
        label,
        style: AppTextStyles.caption(context.koraColors.textMuted),
      ),
    );
  }
}

/// L'alerte quand l'échéance approche.
///
/// Elle ne s'affiche que selon les seuils du serveur : l'app n'a pas à
/// décider à partir de quand il est urgent de prévenir.
class _ExpiryWarning extends StatelessWidget {
  const _ExpiryWarning({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(AppRadius.input),
        border: Border.all(color: AppColors.gold.withValues(alpha: 0.45)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.schedule_rounded, size: 18, color: AppColors.kora),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              message,
              style: AppTextStyles.body(context.koraColors.textPrimary),
            ),
          ),
        ],
      ),
    );
  }
}

/// Le code de parrainage, à copier d'un geste.
class _ReferralCard extends StatelessWidget {
  const _ReferralCard({required this.code});

  /// `null` tant que le serveur n'en a pas attribué : on l'écrit en
  /// toutes lettres plutôt que de laisser un vide.
  final String? code;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final value = code;

    return KoraCard(
      variant: KoraCardVariant.mist,
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  l10n.walletReferralLabel,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
                const SizedBox(height: 3),
                Text(
                  value ?? l10n.walletReferralNone,
                  style: AppTextStyles.titleMedium(context.koraTitleColor),
                ),
              ],
            ),
          ),
          if (value != null)
            KoraIconButton(
              icon: Icons.copy_rounded,
              onTap: () async {
                await Clipboard.setData(ClipboardData(text: value));
                if (context.mounted) {
                  KoraToaster.show(context, l10n.walletReferralCopy);
                }
              },
            ),
        ],
      ),
    );
  }
}

/// Le Wallet n'est ouvert sur rien : ni espèces, ni fidélité.
class _Dormant extends StatelessWidget {
  const _Dormant({required this.title, required this.message});

  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return KoraCard(
      variant: KoraCardVariant.mist,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(title, style: AppTextStyles.titleMedium(context.koraTitleColor)),
          const SizedBox(height: 4),
          Text(message, style: AppTextStyles.body(koraColors.textMuted)),
        ],
      ),
    );
  }
}

/// L'attente de la première lecture.
///
/// Des blocs gris à la place des montants, et non un tourniquet centré :
/// l'écran garde sa forme, et aucun chiffre provisoire ne s'affiche.
class _Skeleton extends StatelessWidget {
  const _Skeleton();

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    Widget bar(double height, {double? width}) => Container(
      height: height,
      width: width,
      decoration: BoxDecoration(
        color: koraColors.line,
        borderRadius: BorderRadius.circular(AppRadius.sm),
      ),
    );

    return ListView(
      padding: const EdgeInsets.only(bottom: 100),
      children: [
        bar(192),
        const SizedBox(height: 9),
        bar(58),
        const SizedBox(height: 11),
        bar(74),
        const SizedBox(height: 11),
        bar(96),
      ],
    );
  }
}

/// `.transaction` : pictogramme, libellé, référence et montant.
class _Transaction extends StatelessWidget {
  const _Transaction({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.amount,
    this.isCredit = false,
    this.isLast = false,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final String amount;

  /// Un crédit s'affiche en bleu digital (`.transaction-amount.credit`) ;
  /// un débit garde la couleur du texte fort.
  final bool isCredit;

  final bool isLast;

  @override
  Widget build(BuildContext context) {
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
          _TransactionIcon(icon: icon, color: strong),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: strong,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: koraColors.textMuted, fontSize: 10),
                ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Text(
            amount,
            style: TextStyle(
              color: isCredit ? AppColors.digital : strong,
              fontSize: 12,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

/// `.transaction-icon` : le carré doux qui porte le pictogramme.
class _TransactionIcon extends StatelessWidget {
  const _TransactionIcon({required this.icon, required this.color});

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
