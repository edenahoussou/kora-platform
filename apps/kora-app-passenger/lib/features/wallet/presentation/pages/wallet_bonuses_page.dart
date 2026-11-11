import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import '../../domain/entities/wallet_movement.dart';
import '../../domain/repositories/wallet_repository.dart';
import '../bloc/wallet_bonus_cubit.dart';

/// Écran `wallet-bonuses` de la maquette — les bonus fidélité.
///
/// La maquette affiche un barème — « 3 % », « +500 pts », « +100 pts ».
/// Ce sont des réglages d'administration (`loyaltyEarnRateBasisPoints`,
/// `bonusWelcomePoints`…) que `WalletResource` **n'expose pas**. Plutôt
/// que de les écrire dans l'app, l'écran montre ce qui a réellement été
/// crédité, lu dans le relevé : un bonus déjà obtenu porte son montant,
/// un bonus à venir dit seulement qu'il reste à obtenir.
///
/// Le jour où le serveur rend ce barème, les lignes en attente
/// afficheront leur valeur sans rien republier.
class WalletBonusesPage extends StatelessWidget {
  const WalletBonusesPage({super.key, this.cubit});

  /// Cubit déjà construit, pour les tests.
  final WalletBonusCubit? cubit;

  @override
  Widget build(BuildContext context) {
    final existing = cubit;

    return BlocProvider<WalletBonusCubit>(
      create: (_) =>
          (existing ?? WalletBonusCubit(getIt<WalletRepository>()))..load(),
      child: const _BonusesView(),
    );
  }
}

class _BonusesView extends StatelessWidget {
  const _BonusesView();

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
                eyebrow: l10n.walletBonusEyebrow,
                title: l10n.walletBonusTitle,
              ),
              Expanded(
                child: BlocBuilder<WalletBonusCubit, WalletBonusState>(
                  builder: (context, state) => _body(context, state),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _body(BuildContext context, WalletBonusState state) {
    final l10n = context.l10n;
    final cubit = context.read<WalletBonusCubit>();

    if (state.isFirstLoad) return const _Skeleton();

    if (state.account == null) {
      return StateView(
        icon: Icons.star_outline_rounded,
        title: l10n.walletBonusErrorTitle,
        message: switch (state.failure) {
          null ||
          ServerFailure() ||
          UnexpectedFailure() => l10n.walletBonusErrorText,
          final failure => failure.message,
        },
        primaryLabel: l10n.actionRetry,
        onPrimary: cubit.load,
        isPrimaryLoading: state.isLoading,
      );
    }

    return ListView(
      padding: const EdgeInsets.only(bottom: 110),
      children: [
        _EarnedCard(points: state.earned, hasFailed: state.ledgerFailed),
        const SizedBox(height: 12),
        for (final reason in WalletBonusState.order) ...[
          _BonusRow(reason: reason, earned: state.earnedFor(reason)),
          const SizedBox(height: 8),
        ],
        const SizedBox(height: 6),
        // Pas d'écran de parrainage à part : le code vit déjà sur
        // l'onglet Wallet, et en faire une seconde page obligerait à
        // tenir deux endroits à jour pour une seule chaîne.
        KoraButton(
          label: l10n.walletBonusInvite,
          onPressed: () => _share(context, state.account?.referralCode),
        ),
      ],
    );
  }
}

/// `.card.gold` : le total gagné, lu dans le relevé.
/// Partager son code, depuis l'écran où l'on voit ce qu'il rapporte.
Future<void> _share(BuildContext context, String? code) async {
  final l10n = context.l10n;
  if (code == null || code.isEmpty) {
    KoraToaster.show(context, l10n.walletReferralNone);
    return;
  }

  await Clipboard.setData(ClipboardData(text: code));
  if (context.mounted) KoraToaster.show(context, l10n.walletReferralCopy);
}

class _EarnedCard extends StatelessWidget {
  const _EarnedCard({required this.points, required this.hasFailed});

  final int points;

  /// Le relevé n'a pas répondu : un total de zéro serait alors un
  /// mensonge, pas une information.
  final bool hasFailed;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: AppColors.gold.withValues(alpha: 0.45)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  l10n.walletBonusEarned,
                  style: AppTextStyles.caption(context.koraColors.textMuted),
                ),
                const SizedBox(height: 4),
                Text(
                  hasFailed
                      ? l10n.walletBonusEarnedUnknown
                      : l10n.walletPointsUnit('$points'),
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 22,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.6,
                  ),
                ),
              ],
            ),
          ),
          const Icon(Icons.star_rounded, size: 26, color: AppColors.gold),
        ],
      ),
    );
  }
}

/// `.bonus-row` : le pictogramme, l'intitulé, l'état et le montant.
class _BonusRow extends StatelessWidget {
  const _BonusRow({required this.reason, required this.earned});

  final WalletReason reason;

  /// Ce que ce bonus a rapporté, ou `null` s'il n'a jamais été crédité.
  final int? earned;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final obtained = earned != null;

    return Container(
      constraints: const BoxConstraints(minHeight: 58),
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: koraColors.line),
      ),
      child: Row(
        children: [
          Container(
            width: 32,
            height: 32,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.gold.withValues(alpha: 0.18),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              obtained ? Icons.check_rounded : Icons.star_outline_rounded,
              size: 17,
              color: context.koraTitleColor,
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _title(l10n),
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  obtained ? l10n.walletBonusObtained : l10n.walletBonusPending,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ],
            ),
          ),
          if (obtained)
            Text(
              '+ ${l10n.walletPointsUnit('$earned')}',
              style: const TextStyle(
                color: AppColors.digital,
                fontSize: 12,
                fontWeight: FontWeight.w900,
              ),
            ),
        ],
      ),
    );
  }

  String _title(AppLocalization l10n) => switch (reason) {
    WalletReason.bonusWelcome => l10n.walletReasonBonusWelcome,
    WalletReason.bonusAnniversary => l10n.walletReasonBonusAnniversary,
    WalletReason.bonusUsage => l10n.walletReasonBonusUsage,
    WalletReason.bonusReferral => l10n.walletReasonBonusReferral,
    _ => l10n.walletReasonUnknown,
  };
}

class _Skeleton extends StatelessWidget {
  const _Skeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.only(bottom: 110),
      children: const [
        KoraSkeleton(height: 78, radius: 22),
        SizedBox(height: 12),
        KoraSkeleton(height: 58, radius: 15),
        SizedBox(height: 8),
        KoraSkeleton(height: 58, radius: 15),
        SizedBox(height: 8),
        KoraSkeleton(height: 58, radius: 15),
        SizedBox(height: 8),
        KoraSkeleton(height: 58, radius: 15),
      ],
    );
  }
}
