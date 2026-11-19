import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/domain/entities/price.dart';
import '../../../payment/domain/entities/payment_catalog.dart';
import '../../../payment/domain/repositories/payment_repository.dart';
import '../../../payment/presentation/pages/payment_args.dart';
import '../../domain/repositories/wallet_repository.dart';
import '../bloc/wallet_top_up_cubit.dart';

/// Écran `wallet-recharge-method` de la maquette — par où recharger.
///
/// **La liste vient du serveur**, comme pour l'achat d'un billet : c'est
/// `GET /payments/methods` qui dit ce qui est encaissable ici et
/// maintenant. Une liste écrite dans l'app finirait par proposer une
/// passerelle dont les identifiants ont expiré.
///
/// Les écrans qui suivent sont **ceux du billet**, repris tels quels :
/// la feuille KKiaPay, la saisie FedaPay, la carte. Recharger n'est pas
/// un paiement différent, c'est le même paiement sans réservation — et
/// en refaire une copie laisserait les deux parcours diverger à la
/// première correction.
class WalletTopUpMethodPage extends StatelessWidget {
  const WalletTopUpMethodPage({super.key, required this.amount, this.cubit});

  /// Le montant arrêté à l'écran précédent. `null` si l'écran est ouvert
  /// sans données — un lien profond, ou un retour après redémarrage.
  final int? amount;

  final WalletTopUpCubit? cubit;

  @override
  Widget build(BuildContext context) {
    final existing = cubit;
    final value = amount;

    if (value == null || value <= 0) return const _MissingAmount();

    return BlocProvider<WalletTopUpCubit>(
      create: (_) =>
          (existing ??
                WalletTopUpCubit(
                  getIt<WalletRepository>(),
                  getIt<PaymentRepository>(),
                ))
            ..load(),
      child: _MethodView(amount: value),
    );
  }
}

class _MethodView extends StatelessWidget {
  const _MethodView({required this.amount});

  final int amount;

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
              // Le montant en exergue : c'est la somme qui décide du
              // moyen, et elle doit rester lisible pendant le choix.
              ScreenHeader(
                eyebrow: Price(amount).formatted,
                title: l10n.walletTopUpMethodTitle,
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

    if (state.isFirstLoad) {
      return ListView(
        padding: const EdgeInsets.only(bottom: 100),
        children: const [
          KoraSkeleton(height: 72, radius: 17),
          SizedBox(height: 9),
          KoraSkeleton(height: 72, radius: 17),
        ],
      );
    }

    if (state.methods.isEmpty) {
      return KoraCard(
        variant: KoraCardVariant.mist,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              state.catalogFailed
                  ? l10n.walletTopUpMethodsFailed
                  : l10n.walletTopUpMethodsEmpty,
              style: AppTextStyles.body(context.koraColors.textMuted),
            ),
            if (state.catalogFailed) ...[
              const SizedBox(height: AppSpacing.sm),
              KoraButton(
                label: l10n.actionRetry,
                variant: KoraButtonVariant.ghost,
                onPressed: cubit.load,
              ),
            ],
          ],
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.only(bottom: 100),
      children: [
        for (final method in state.methods) ...[
          ..._tilesFor(context, method),
          const SizedBox(height: 9),
        ],
      ],
    );
  }

  /// Les pavés d'un moyen.
  ///
  /// Le Mobile Money se décline par passerelle, comme sur l'écran de
  /// paiement d'un billet : FedaPay pousse la demande sur le téléphone,
  /// KKiaPay ouvre sa feuille dans l'app. Les confondre sous une seule
  /// carte promettrait une expérience que le voyageur n'aura pas.
  List<Widget> _tilesFor(BuildContext context, PaymentMethod method) {
    final l10n = context.l10n;

    if (method.code == PaymentCatalog.mobileMoneyCode) {
      return [
        for (final provider in method.enabledProviders) ...[
          _MethodTile(
            icon: Icons.smartphone_rounded,
            // La marque de la passerelle, pas un pictogramme générique :
            // deux cartes Mobile Money se suivent, et c'est le logo qui
            // les distingue au premier coup d'œil.
            logoAsset: _logoOf(provider),
            title: provider.label,
            lead: l10n.walletTopUpMethodMomoLead,
            onTap: () => _open(
              context,
              path: provider.collectsInApp
                  ? AppRouter.paymentGatewayMomoPath
                  : AppRouter.paymentMobileMoneyPath,
              provider: provider.code,
            ),
          ),
          if (provider != method.enabledProviders.last)
            const SizedBox(height: 9),
        ],
      ];
    }

    if (method.code == PaymentCatalog.cardCode) {
      final provider = method.enabledProviders.firstOrNull;

      return [
        _MethodTile(
          icon: Icons.credit_card_rounded,
          logoAsset: provider == null ? null : _logoOf(provider),
          title: l10n.walletTopUpMethodCard,
          // La passerelle qui encaisse la carte, sous le libellé : le
          // voyageur verra sa fenêtre s'ouvrir, autant la nommer.
          lead: provider == null
              ? l10n.walletTopUpMethodCardLead
              : '${l10n.walletTopUpMethodCardLead} · ${provider.label}',
          onTap: provider == null
              ? null
              : () => _open(
                  context,
                  path: AppRouter.paymentCardPath,
                  provider: provider.code,
                ),
        ),
      ];
    }

    return const [];
  }

  /// Le logo de la passerelle, quand il est au dépôt. Mêmes fichiers que
  /// l'écran de paiement d'un billet : une seule source par marque.
  String? _logoOf(PaymentProvider provider) => switch (provider.code) {
    'kkiapay' => 'assets/images/kkiapay.png',
    'fedapay' => 'assets/images/fedapay.jpg',
    _ => null,
  };

  /// Ouvre l'écran d'encaissement, en lui disant que la somme alimente
  /// le Wallet et non une réservation.
  ///
  /// Ce sont les écrans du billet, repris tels quels : ils
  /// reconnaissent une recharge à son `topUpAmount` et ouvrent alors
  /// `POST /wallet/topups` au lieu de `payments/initiate`. Le reste —
  /// l'attente, la relecture, le verdict — est identique, parce que
  /// c'est le même paiement provider.
  void _open(
    BuildContext context, {
    required String path,
    required String provider,
  }) {
    context.push(
      path,
      extra: PaymentArgs(
        total: Price(amount),
        provider: provider,
        topUpAmount: amount,
      ),
    );
  }
}

/// `.delivery-option` de la maquette : pictogramme, intitulé, précision
/// et chevron.
class _MethodTile extends StatelessWidget {
  const _MethodTile({
    required this.icon,
    required this.title,
    required this.lead,
    required this.onTap,
    this.logoAsset,
  });

  final IconData icon;
  final String title;
  final String lead;
  final VoidCallback? onTap;

  /// Le logo de la passerelle, à la place du pictogramme.
  final String? logoAsset;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final enabled = onTap != null;
    final strong = enabled ? context.koraTitleColor : koraColors.textMuted;

    return Opacity(
      opacity: enabled ? 1 : 0.55,
      child: Material(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(17),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(17),
          child: Container(
            constraints: const BoxConstraints(minHeight: 72),
            padding: const EdgeInsets.all(11),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(17),
              border: Border.all(color: koraColors.line),
            ),
            child: Row(
              children: [
                if (logoAsset case final asset?)
                  Container(
                    width: 34,
                    height: 34,
                    alignment: Alignment.center,
                    // Le blanc derrière le logo : la marque porte son
                    // propre fond, et le gris de la tuile le salirait.
                    decoration: BoxDecoration(
                      color: AppColors.white,
                      borderRadius: BorderRadius.circular(11),
                      border: Border.all(color: koraColors.line),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: Padding(
                      padding: const EdgeInsets.all(3),
                      child: Image.asset(asset, fit: BoxFit.contain),
                    ),
                  )
                else
                  Container(
                    width: 34,
                    height: 34,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: koraColors.background,
                      borderRadius: BorderRadius.circular(11),
                    ),
                    child: Icon(icon, size: 18, color: strong),
                  ),
                const SizedBox(width: 9),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        title,
                        style: TextStyle(
                          color: strong,
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        lead,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                    ],
                  ),
                ),
                Icon(
                  Icons.chevron_right_rounded,
                  size: 20,
                  color: koraColors.textMuted,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// L'écran ouvert sans montant : on ne devine pas une somme à débiter.
class _MissingAmount extends StatelessWidget {
  const _MissingAmount();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.walletTopUpEyebrow,
                title: l10n.walletTopUpMethodTitle,
              ),
              KoraCard(
                variant: KoraCardVariant.mist,
                child: Text(
                  l10n.walletTopUpNoAmount,
                  style: AppTextStyles.body(context.koraColors.textMuted),
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              KoraButton(
                label: l10n.walletTopUpAction,
                onPressed: () => context.go(AppRouter.walletTopUpPath),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
