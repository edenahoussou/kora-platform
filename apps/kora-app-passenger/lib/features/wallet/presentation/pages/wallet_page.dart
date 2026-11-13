import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/brand_mini.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../../booking/domain/entities/price.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import '../../domain/entities/wallet_account.dart';
import '../../domain/entities/wallet_movement.dart';
import '../../domain/repositories/wallet_repository.dart';
import '../bloc/wallet_cubit.dart';
import '../bloc/wallet_refresh.dart';
import '../widgets/wallet_balance_card.dart';
import '../widgets/wallet_movement_tile.dart';

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
      child: _WalletGate(today: today),
    );
  }
}

/// Ce qu'on écrit sous le titre quand les soldes ne viennent pas.
///
/// Une panne serveur rend son propre texte, écrit pour un journal :
/// « The route api/v1/wallet could not be found. » le jour où le module
/// n'est pas déployé. Le voyageur lit le nôtre. Les autres échecs — le
/// réseau, la session expirée — portent une phrase écrite pour lui, et
/// celle-là vaut mieux que la nôtre.
String _failureText(AppLocalization l10n, Failure? failure) =>
    switch (failure) {
      null || ServerFailure() || UnexpectedFailure() => l10n.walletErrorText,
      _ => failure.message,
    };

/// Relit le Wallet quand quelque chose l'a fait bouger ailleurs.
///
/// L'onglet naît une fois et son cubit lit le solde à la création. Après
/// une recharge confirmée, le voyageur y revenait sur l'ancien solde et
/// devait tirer pour rafraîchir.
class _WalletGate extends StatefulWidget {
  const _WalletGate({this.today});

  final DateTime? today;

  @override
  State<_WalletGate> createState() => _WalletGateState();
}

class _WalletGateState extends State<_WalletGate> {
  StreamSubscription<void>? _watch;

  @override
  void initState() {
    super.initState();

    if (!getIt.isRegistered<WalletRefresh>()) return;

    _watch = getIt<WalletRefresh>().changes.listen((_) {
      if (mounted) unawaited(context.read<WalletCubit>().load());
    });
  }

  @override
  void dispose() {
    unawaited(_watch?.cancel());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => _WalletView(today: widget.today);
}

class _WalletView extends StatelessWidget {
  const _WalletView({this.today});

  final DateTime? today;

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
                  // La flèche de l'en-tête ouvre les Mouvements : elle
                  // ne promettait rien d'autre, et un bouton qui
                  // répond « bientôt » use la patience pour rien.
                  onTap: () => context.push(AppRouter.walletHistoryPath),
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
        message: _failureText(l10n, state.failure),
        primaryLabel: l10n.actionRetry,
        onPrimary: context.read<WalletCubit>().load,
        isPrimaryLoading: state.isLoading,
      );
    }

    final now = today ?? DateTime.now();
    final expiry = account.pointsExpireAt;
    // Les deux poches s'ouvrent séparément côté serveur. Afficher un
    // solde cash et un bouton « Recharger » quand `wallet_cash_enabled`
    // est à faux promet une fonctionnalité que l'administration a
    // fermée : le voyageur appuie, et rien ne peut aboutir.
    final rules = account.rules;

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
          if (rules.cashEnabled) ...[
            WalletBalanceCard(balance: account.cash),
            const SizedBox(height: 9),
          ],
          if (rules.pointsEnabled) ...[
            // Sans destination, la carte ne réagit pas : un appui qui
            // ne fait qu'afficher « bientôt » donne l'impression d'un
            // écran cassé.
            _PointsStrip(points: account.points, value: account.pointsValue),
            const SizedBox(height: 6),
            // L'échéance se lit en permanence, pas seulement quand elle
            // approche : des points qui tombent sans prévenir passent
            // pour une erreur de l'application.
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
            // Le parrainage se paie en points : sans la poche fidélité,
            // partager un code ne rapporterait rien.
            _ReferralCard(code: account.referralCode),
          ],
          const SizedBox(height: 11),
          _ActionGrid(
            rules: rules,
            onUse: () => context.push(AppRouter.walletServicesPath),
            onBonus: () => context.push(AppRouter.walletBonusesPath),
            onHistory: () => context.push(AppRouter.walletHistoryPath),
            // Recharger relit le Wallet au retour : le solde a pu
            // bouger pendant que le voyageur payait.
            onTopUp: () async {
              await context.push(AppRouter.walletTopUpPath);
              if (context.mounted) await context.read<WalletCubit>().load();
            },
          ),
          const SizedBox(height: 11),
          SectionTitle(
            title: l10n.walletMovements,
            actionLabel: l10n.actionSeeAll,
            onActionTap: () => context.push(AppRouter.walletHistoryPath),
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
/// `.wallet-points-strip` : les points de fidélité et leur contre-valeur.
class _PointsStrip extends StatelessWidget {
  const _PointsStrip({required this.points, required this.value});

  final int points;

  /// La contre-valeur en francs, calculée avec le taux du serveur : un
  /// point ne vaut pas forcément un franc, c'est un réglage.
  final Price value;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final strong = isDark ? koraColors.textPrimary : AppColors.kora;

    // Une carte qui se lit, pas un bouton : rien ne s'ouvre derrière
    // les points pour l'instant, et un appui qui ne répond que
    // « bientôt » fait passer l'écran pour cassé.
    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(15),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(15),
          border: Border.all(color: koraColors.line),
        ),
        child: Row(
          children: [
            WalletRoundIcon(icon: Icons.star_rounded, color: strong),
            const SizedBox(width: 9),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    l10n.walletPointsLabel,
                    style: TextStyle(color: koraColors.textMuted, fontSize: 10),
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
          ],
        ),
      ),
    );
  }
}

/// `.wallet-action-grid` : les quatre raccourcis, sur une seule rangée.
class _ActionGrid extends StatelessWidget {
  const _ActionGrid({
    required this.rules,
    required this.onUse,
    required this.onBonus,
    required this.onHistory,
    required this.onTopUp,
  });

  /// Ce que le serveur autorise : une action fermée n'est pas grisée,
  /// elle n'est pas proposée. Un bouton gris invite quand même à
  /// appuyer, et laisse croire à une panne.
  final WalletRules rules;

  final VoidCallback onUse;
  final VoidCallback onBonus;
  final VoidCallback onHistory;
  final VoidCallback onTopUp;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    final actions = <(IconData, String, VoidCallback)>[
      if (rules.cashEnabled)
        (Icons.add_rounded, l10n.walletActionTopup, onTopUp),
      (Icons.bolt_rounded, l10n.walletActionUse, onUse),
      if (rules.pointsEnabled)
        (Icons.star_rounded, l10n.walletActionBonus, onBonus),
      (Icons.north_east_rounded, l10n.walletActionHistory, onHistory),
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
                onTap: action.$3,
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
            WalletMovementTile(
              movement: movement,
              isLast: index == movements.length - 1,
            ),
        ],
      ),
    );
  }
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
