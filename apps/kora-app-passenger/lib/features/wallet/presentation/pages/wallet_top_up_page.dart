import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/domain/entities/price.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import '../../../payment/domain/repositories/payment_repository.dart';
import '../../../payment/presentation/widgets/labelled_field.dart';
import '../../domain/entities/wallet_account.dart';
import '../../domain/repositories/wallet_repository.dart';
import '../bloc/wallet_top_up_cubit.dart';
import '../widgets/wallet_balance_card.dart';

/// Écran `wallet-recharge` de la maquette — recharger le solde Cash.
///
/// **Les bornes viennent du serveur** : les montants proposés, le
/// minimum, le maximum et le plafond sont des réglages d'administration.
/// Les écrire ici ferait refuser un jour un montant que le serveur
/// accepte — et le voyageur ne l'apprendrait qu'au paiement.
///
/// L'écran ne débite rien : il arrête un montant, puis passe la main au
/// choix du moyen de recharge, exactement comme le récapitulatif d'un
/// billet passe la main au choix du moyen de paiement.
class WalletTopUpPage extends StatelessWidget {
  const WalletTopUpPage({super.key, this.cubit});

  /// Cubit déjà construit, pour les tests.
  final WalletTopUpCubit? cubit;

  @override
  Widget build(BuildContext context) {
    final existing = cubit;

    return BlocProvider<WalletTopUpCubit>(
      create: (_) =>
          (existing ??
                WalletTopUpCubit(
                  getIt<WalletRepository>(),
                  getIt<PaymentRepository>(),
                ))
            ..load(),
      child: const _TopUpView(),
    );
  }
}

class _TopUpView extends StatefulWidget {
  const _TopUpView();

  @override
  State<_TopUpView> createState() => _TopUpViewState();
}

class _TopUpViewState extends State<_TopUpView> {
  final TextEditingController _amount = TextEditingController();

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  /// Un montant proposé remplit le champ : le voyageur voit ce qu'il a
  /// choisi là où il l'aurait tapé, et peut le corriger.
  void _choosePreset(int value) {
    _amount.text = '$value';
    _amount.selection = TextSelection.collapsed(offset: _amount.text.length);
    context.read<WalletTopUpCubit>().chooseAmount(value);
  }

  void _onTyped(String raw) {
    final digits = raw.replaceAll(RegExp(r'\D'), '');
    context.read<WalletTopUpCubit>().chooseAmount(
      digits.isEmpty ? null : int.tryParse(digits),
    );
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
                eyebrow: l10n.walletTopUpEyebrow,
                title: l10n.walletTopUpTitle,
              ),
              Expanded(
                child: BlocBuilder<WalletTopUpCubit, WalletTopUpState>(
                  builder: (context, state) => _body(context, state),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _body(BuildContext context, WalletTopUpState state) {
    final l10n = context.l10n;
    final cubit = context.read<WalletTopUpCubit>();
    final account = state.account;

    if (state.isFirstLoad) return const _Skeleton();

    if (account == null) {
      return StateView(
        icon: Icons.account_balance_wallet_outlined,
        title: l10n.walletTopUpErrorTitle,
        // Une panne serveur rend son propre texte, écrit pour un
        // journal. Le voyageur lit le nôtre.
        message: switch (state.failure) {
          null ||
          ServerFailure() ||
          UnexpectedFailure() => l10n.walletTopUpErrorText,
          final failure => failure.message,
        },
        primaryLabel: l10n.actionRetry,
        onPrimary: cubit.load,
        isPrimaryLoading: state.isLoading,
      );
    }

    // Le serveur refuse la recharge tant que la poche cash est fermée
    // (`walletCashEnabled`). On le dit avant de faire saisir un montant
    // plutôt qu'après.
    if (!account.rules.cashEnabled) {
      return StateView(
        icon: Icons.lock_outline_rounded,
        title: l10n.walletTopUpClosedTitle,
        message: l10n.walletTopUpClosedText,
      );
    }

    return ListView(
      padding: const EdgeInsets.only(bottom: 120),
      children: [
        WalletBalanceCard(balance: account.cash, compact: true),
        const SizedBox(height: 13),
        LabelledField(
          label: l10n.walletTopUpAmountLabel,
          error: _issueText(l10n, account.rules, state),
          child: KoraInput(
            controller: _amount,
            hint: l10n.walletTopUpAmountHint,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            invalid: state.amount != null && state.amountIssue != null,
            onChanged: _onTyped,
          ),
        ),
        if (state.presets.isNotEmpty) ...[
          const SizedBox(height: 8),
          _Presets(
            presets: state.presets,
            current: state.amount,
            onTap: _choosePreset,
          ),
        ],
        const SizedBox(height: 12),
        // La règle qui surprend le plus — recharger ne rapporte aucun
        // point — mérite mieux que la card `mist` de la maquette : sur
        // un fond clair elle se confondait avec la page, et personne ne
        // lisait la seule phrase qui corrige une attente fausse.
        KoraAlert(
          icon: Icons.info_outline_rounded,
          title: l10n.walletTopupNoteTitle,
          message: l10n.walletTopUpNote,
        ),
        const SizedBox(height: 14),
        KoraButton(
          label: l10n.walletTopUpAction,
          onPressed: state.canSubmit
              ? () => context.push(
                  AppRouter.walletTopUpMethodPath,
                  extra: state.amount,
                )
              : null,
        ),
      ],
    );
  }

  /// La raison d'un refus, dite avec les chiffres du serveur.
  String? _issueText(
    AppLocalization l10n,
    WalletRules rules,
    WalletTopUpState state,
  ) {
    // Tant que rien n'est saisi, il n'y a rien à reprocher : un champ
    // vide n'est pas une erreur, c'est un début.
    if (state.amount == null) return null;

    return switch (state.amountIssue) {
      null || TopUpAmountIssue.empty => null,
      TopUpAmountIssue.tooSmall => l10n.walletTopUpTooSmall(
        Price(rules.topupMin ?? 0).formatted,
      ),
      TopUpAmountIssue.tooLarge => l10n.walletTopUpTooLarge(
        Price(rules.topupMax ?? 0).formatted,
      ),
      TopUpAmountIssue.overCap => l10n.walletTopUpOverCap(
        Price(rules.cashBalanceCap ?? 0).formatted,
      ),
    };
  }
}

/// `.wallet-presets` : les montants réglés par l'administration, sur
/// trois colonnes.
class _Presets extends StatelessWidget {
  const _Presets({
    required this.presets,
    required this.current,
    required this.onTap,
  });

  final List<int> presets;
  final int? current;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        const gap = 7.0;
        final width = (constraints.maxWidth - gap * 2) / 3;

        return Wrap(
          spacing: gap,
          runSpacing: gap,
          children: [
            for (final preset in presets)
              SizedBox(
                width: width,
                child: _PresetTile(
                  label: Price(preset).formatted,
                  isSelected: preset == current,
                  onTap: () => onTap(preset),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _PresetTile extends StatelessWidget {
  const _PresetTile({
    required this.label,
    required this.isSelected,
    required this.onTap,
  });

  final String label;
  final bool isSelected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final strong = isDark ? koraColors.textPrimary : AppColors.kora;

    return Semantics(
      button: true,
      selected: isSelected,
      child: Material(
        color: isSelected ? AppColors.gold : koraColors.surface,
        borderRadius: BorderRadius.circular(11),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(11),
          child: Container(
            height: 38,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(11),
              border: Border.all(
                color: isSelected ? AppColors.gold : koraColors.line,
              ),
            ),
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: isSelected ? AppColors.kora : strong,
                fontSize: 12,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Skeleton extends StatelessWidget {
  const _Skeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.only(bottom: 120),
      children: const [
        KoraSkeleton(height: 125, radius: 26),
        SizedBox(height: 13),
        KoraSkeleton(height: 72),
        SizedBox(height: 8),
        KoraSkeleton(height: 38),
        SizedBox(height: 12),
        KoraSkeleton(height: 64),
      ],
    );
  }
}
