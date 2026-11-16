import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/referral_code.dart';
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
                child: BlocConsumer<WalletBonusCubit, WalletBonusState>(
                  // Le rattachement réussi se dit une fois : la carte de
                  // saisie laisse ensuite place à la ligne « parrain
                  // rattaché », qui ne raconte plus l'événement.
                  listenWhen: (before, after) =>
                      !before.referralAttached && after.referralAttached,
                  listener: (context, state) => KoraToaster.show(
                    context,
                    context.l10n.walletReferralEntryDone,
                  ),
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
          _BonusRow(
            reason: reason,
            earned: state.earnedFor(reason),
            // Ce que la ligne rapporte, quand le serveur le dit.
            worth: state.account?.rules.bonuses.pointsFor(reason),
          ),
          const SizedBox(height: 8),
        ],
        const SizedBox(height: 6),
        // Le parrainage se joue dans les deux sens sur cet écran : on y
        // partage son code, et on y rattache celui d'un autre. En faire
        // une page à part obligerait à tenir deux endroits à jour.
        _ReferralEntry(
          attached: state.account?.hasReferrer ?? false,
          isSending: state.isAttaching,
          failure: state.referralFailure,
        ),
        const SizedBox(height: 12),
        KoraButton(
          label: l10n.walletBonusInvite,
          onPressed: () => _share(context, state.account?.referralCode),
        ),
      ],
    );
  }
}

/// Rattacher le code d'un parrain — point 5 du ticket MEZ-160.
///
/// Le serveur tranche seul : son propre code, un parrain déjà
/// rattaché, un compte qui a déjà voyagé, un code inconnu. L'app ne
/// rejoue aucune de ces règles — elle envoie ce qui est tapé et montre
/// la raison du refus. Rejouer les règles ici, c'est promettre un
/// refus que le serveur n'aurait pas fait, ou l'inverse.
class _ReferralEntry extends StatefulWidget {
  const _ReferralEntry({
    required this.attached,
    required this.isSending,
    required this.failure,
  });

  /// Un parrain est déjà rattaché : le champ n'a plus lieu d'être.
  final bool attached;

  final bool isSending;

  /// Le refus du serveur, ou `null`.
  final Failure? failure;

  @override
  State<_ReferralEntry> createState() => _ReferralEntryState();
}

class _ReferralEntryState extends State<_ReferralEntry> {
  final _code = TextEditingController();

  @override
  void didUpdateWidget(_ReferralEntry old) {
    super.didUpdateWidget(old);

    // Rattaché : le champ disparaît, et ce qu'il contenait avec lui.
    if (widget.attached && !old.attached) _code.clear();
  }

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    if (widget.attached) {
      return KoraAlert(
        icon: Icons.verified_rounded,
        title: l10n.walletReferralAttached,
      );
    }

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            l10n.walletReferralEntryTitle,
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            l10n.walletReferralEntryText,
            style: AppTextStyles.caption(context.koraColors.textMuted),
          ),
          const SizedBox(height: 10),
          KoraInput(
            controller: _code,
            hint: l10n.walletReferralEntryHint,
            enabled: !widget.isSending,
            // Majuscules et longueur maximale : les mêmes règles qu'à
            // l'inscription, parce que c'est le même champ côté serveur.
            inputFormatters: referralCodeFormatters(),
            onChanged: (_) => setState(() {}),
          ),
          if (_errorText(l10n) case final error?) ...[
            const SizedBox(height: 6),
            Text(error, style: AppTextStyles.caption(AppColors.danger)),
          ],
          const SizedBox(height: 10),
          KoraButton(
            label: l10n.walletReferralEntryAction,
            isLoading: widget.isSending,
            onPressed: _code.text.trim().isEmpty
                ? null
                : () => context.read<WalletBonusCubit>().attachReferral(
                    _code.text,
                  ),
          ),
        ],
      ),
    );
  }

  /// Ce qu'on dit du refus.
  ///
  /// Les raisons du serveur sont écrites pour le voyageur (« Ce code de
  /// parrainage est inconnu. ») : on les montre telles quelles. Une
  /// panne, elle, n'a rien à lui apprendre.
  String? _errorText(AppLocalization l10n) => switch (widget.failure) {
    null => null,
    ServerFailure() || UnexpectedFailure() => l10n.walletReferralEntryFailed,
    final failure => failure.message,
  };
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
  const _BonusRow({
    required this.reason,
    required this.earned,
    required this.worth,
  });

  final WalletReason reason;

  /// Ce que ce bonus a rapporté, ou `null` s'il n'a jamais été crédité.
  final int? earned;

  /// Ce qu'il rapporte selon le barème du serveur, ou `null` quand il
  /// n'en dit rien. Un bonus à venir le montre pour qu'on sache ce qu'on
  /// gagne ; un bonus obtenu montre plutôt ce qu'il a vraiment rapporté.
  final int? worth;

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
            )
          else if (worth != null)
            Text(
              '+ ${l10n.walletPointsUnit('$worth')}',
              style: AppTextStyles.caption(context.koraColors.textMuted),
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
