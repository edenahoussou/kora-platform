import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import '../../domain/entities/wallet_movement.dart';
import '../../domain/repositories/wallet_repository.dart';
import '../bloc/wallet_history_cubit.dart';
import '../widgets/wallet_movement_tile.dart';

/// Écran `wallet-history` de la maquette — le relevé complet.
///
/// Les deux soldes vivent dans **une seule** liste, dans l'ordre où les
/// écritures sont arrivées. Le voyageur n'a pas à recoller deux relevés
/// pour comprendre ce qui s'est passé après un paiement mixte : il voit
/// les points dépensés et le cash débité l'un sous l'autre, à la même
/// seconde. Les onglets servent à chercher une ligne, pas à séparer
/// deux comptes.
class WalletHistoryPage extends StatelessWidget {
  const WalletHistoryPage({super.key, this.cubit});

  /// Cubit déjà construit, pour les tests. En vrai, l'écran le crée
  /// lui-même et le ferme en partant.
  final WalletHistoryCubit? cubit;

  @override
  Widget build(BuildContext context) {
    final existing = cubit;

    return BlocProvider<WalletHistoryCubit>(
      create: (_) =>
          (existing ?? WalletHistoryCubit(getIt<WalletRepository>()))..load(),
      child: const _HistoryView(),
    );
  }
}

class _HistoryView extends StatefulWidget {
  const _HistoryView();

  @override
  State<_HistoryView> createState() => _HistoryViewState();
}

class _HistoryViewState extends State<_HistoryView> {
  final ScrollController _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scroll
      ..removeListener(_onScroll)
      ..dispose();
    super.dispose();
  }

  /// Charge la suite avant d'arriver au bout plutôt qu'une fois dedans :
  /// un relevé qui s'arrête net pendant qu'on fait défiler donne
  /// l'impression d'être terminé.
  void _onScroll() {
    if (!_scroll.hasClients) return;

    final remaining = _scroll.position.maxScrollExtent - _scroll.offset;
    if (remaining > 320) return;

    context.read<WalletHistoryCubit>().loadMore();
  }

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
                eyebrow: l10n.walletHistoryEyebrow,
                title: l10n.walletHistoryTitle,
              ),
              BlocBuilder<WalletHistoryCubit, WalletHistoryState>(
                buildWhen: (before, after) =>
                    before.direction != after.direction,
                builder: (context, state) => _Tabs(current: state.direction),
              ),
              const SizedBox(height: 13),
              Expanded(
                child: BlocBuilder<WalletHistoryCubit, WalletHistoryState>(
                  builder: (context, state) => _body(context, state),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _body(BuildContext context, WalletHistoryState state) {
    final l10n = context.l10n;
    final cubit = context.read<WalletHistoryCubit>();

    if (state.isFirstLoad) return const _Skeleton();

    if (state.movements.isEmpty && state.failure != null) {
      return StateView(
        icon: Icons.receipt_long_outlined,
        title: l10n.walletHistoryErrorTitle,
        // Une panne serveur rend son propre texte, écrit pour un
        // journal. Le voyageur lit le nôtre.
        message: switch (state.failure) {
          null ||
          ServerFailure() ||
          UnexpectedFailure() => l10n.walletHistoryErrorText,
          final failure => failure.message,
        },
        primaryLabel: l10n.actionRetry,
        onPrimary: cubit.load,
        isPrimaryLoading: state.isLoading,
      );
    }

    if (state.isEmpty) {
      return StateView(
        icon: Icons.receipt_long_outlined,
        title: l10n.walletHistoryEmptyTitle,
        message: state.direction == null
            ? l10n.walletHistoryEmptyText
            : l10n.walletHistoryEmptyFiltered,
      );
    }

    final visible = state.visible;

    return RefreshIndicator(
      onRefresh: cubit.load,
      child: ListView(
        controller: _scroll,
        padding: const EdgeInsets.only(bottom: 100),
        children: [
          // `.card` de la maquette : toutes les écritures dans une seule
          // carte, séparées par un filet.
          KoraCard(
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                for (final (index, movement) in visible.indexed)
                  WalletMovementTile(
                    movement: movement,
                    withTime: true,
                    isLast: index == visible.length - 1,
                  ),
              ],
            ),
          ),
          _Footer(state: state, onRetry: cubit.loadMore),
        ],
      ),
    );
  }
}

/// Les trois vues du relevé : tout, les crédits, les utilisations.
///
/// Le tri se fait dans l'app : `GET /wallet/transactions` n'accepte que
/// `balance_type`, il n'a pas de paramètre de sens.
class _Tabs extends StatelessWidget {
  const _Tabs({required this.current});

  final WalletDirection? current;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final cubit = context.read<WalletHistoryCubit>();

    final tabs = <(WalletDirection?, String)>[
      (null, l10n.walletHistoryFilterAll),
      (WalletDirection.credit, l10n.walletHistoryFilterCredits),
      (WalletDirection.debit, l10n.walletHistoryFilterUses),
    ];

    return SizedBox(
      height: 34,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: [
          for (final (direction, label) in tabs) ...[
            if (direction != null) const SizedBox(width: 7),
            KoraFilterChip(
              label: label,
              isSelected: direction == current,
              // La puce sert d'onglet : il n'y a rien à dérouler.
              showIcon: false,
              onTap: () => cubit.filter(direction),
            ),
          ],
        ],
      ),
    );
  }
}

/// Le pied de liste : il dit ce qui se passe au bout du relevé.
///
/// Sans lui, une liste qui s'arrête ressemble à une liste terminée —
/// que la suite soit en train d'arriver ou qu'elle ait échoué.
class _Footer extends StatelessWidget {
  const _Footer({required this.state, required this.onRetry});

  final WalletHistoryState state;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    if (state.moreFailed) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 18),
        child: Column(
          children: [
            Text(
              l10n.walletHistoryMoreFailed,
              textAlign: TextAlign.center,
              style: AppTextStyles.body(koraColors.textMuted),
            ),
            const SizedBox(height: AppSpacing.sm),
            KoraButton(
              label: l10n.actionRetry,
              variant: KoraButtonVariant.ghost,
              onPressed: onRetry,
            ),
          ],
        ),
      );
    }

    if (state.isLoadingMore) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 22),
        child: Center(child: KoraLoadingIndicator()),
      );
    }

    if (state.hasMore) return const SizedBox(height: 22);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 18),
      child: Text(
        l10n.walletHistoryEnd,
        textAlign: TextAlign.center,
        style: AppTextStyles.caption(koraColors.textMuted),
      ),
    );
  }
}

class _Skeleton extends StatelessWidget {
  const _Skeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.only(bottom: 100),
      children: const [KoraSkeleton(height: 320, radius: 22)],
    );
  }
}
