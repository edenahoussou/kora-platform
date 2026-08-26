import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/domain/entities/price.dart';
import '../../../booking/presentation/bloc/seat_hold_bloc.dart';
import 'payment_args.dart';

/// Écran `payment-method` de la maquette : le choix du moyen de paiement.
///
/// Trois pavés, et le rappel du délai de protection juste au-dessus du
/// total. Ce rappel n'est pas décoratif : c'est le dernier écran où
/// changer d'avis coûte encore quelque chose, et le voyageur doit savoir
/// combien de temps il lui reste avant que ses sièges repartent.
class PaymentMethodPage extends StatelessWidget {
  const PaymentMethodPage({super.key, this.args});

  /// `null` si l'écran est ouvert sans données (lien profond).
  final PaymentArgs? args;

  /// Le solde du Wallet, repris tel quel de l'écran Wallet — la valeur de
  /// démonstration de la maquette, l'API n'exposant pas encore de solde.
  static const Price _walletBalance = Price(18750);

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = this.args;

    if (args == null) return const PaymentMissingArgs();

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.paymentMethodEyebrow,
                title: l10n.paymentMethodTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    _MethodTile(
                      symbol: 'M',
                      title: l10n.paymentMethodMomo,
                      lead: l10n.paymentMethodMomoLead,
                      onTap: () => context.push(
                        AppRouter.paymentMobileMoneyPath,
                        extra: args,
                      ),
                    ),
                    const SizedBox(height: 10),
                    _MethodTile(
                      symbol: '◫',
                      title: l10n.paymentMethodWallet,
                      lead: l10n.paymentMethodWalletLead(
                        _walletBalance.formatted,
                      ),
                      onTap: () => context.push(
                        AppRouter.paymentWalletPath,
                        extra: args,
                      ),
                    ),
                    const SizedBox(height: 10),
                    _MethodTile(
                      symbol: '▭',
                      title: l10n.paymentMethodCard,
                      lead: l10n.paymentMethodCardLead,
                      onTap: () =>
                          context.push(AppRouter.paymentCardPath, extra: args),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    const _HoldReminder(),
                    const SizedBox(height: AppSpacing.md),
                    KoraCard(
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            l10n.paymentMethodTotal,
                            style: AppTextStyles.body(
                              context.koraColors.textMuted,
                            ),
                          ),
                          Text(
                            args.total.formatted,
                            style: AppTextStyles.titleMedium(
                              context.koraTitleColor,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xl),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `<button class="card clickable">` : un moyen de paiement, son sous-titre
/// et le chevron d'entrée.
class _MethodTile extends StatelessWidget {
  const _MethodTile({
    required this.symbol,
    required this.title,
    required this.lead,
    required this.onTap,
  });

  /// Le pictogramme de `.transaction-icon`, repris de la maquette.
  final String symbol;
  final String title;
  final String lead;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return KoraCard(
      onTap: onTap,
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.gold,
              borderRadius: BorderRadius.circular(AppRadius.input),
            ),
            child: Text(
              symbol,
              style: const TextStyle(
                color: AppColors.kora,
                fontSize: 16,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  style: AppTextStyles.titleMedium(context.koraTitleColor),
                ),
                const SizedBox(height: 2),
                Text(lead, style: AppTextStyles.body(koraColors.textMuted)),
              ],
            ),
          ),
          Icon(Icons.chevron_right_rounded, color: koraColors.textMuted),
        ],
      ),
    );
  }
}

/// `Vos sièges restent réservés pendant 06:42.`
///
/// Lit le même blocage que le décompte des écrans précédents ; disparaît
/// avec lui, parce qu'annoncer une protection qui n'existe plus serait
/// pire que de ne rien dire.
class _HoldReminder extends StatelessWidget {
  const _HoldReminder();

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<SeatHoldBloc, SeatHoldState>(
      builder: (context, state) {
        if (!state.isHeld) return const SizedBox.shrink();

        return KoraCard(
          variant: KoraCardVariant.mist,
          child: Text(
            context.l10n.paymentMethodHold(state.formatted),
            style: AppTextStyles.body(context.koraColors.textMuted),
          ),
        );
      },
    );
  }
}

/// Écran ouvert sans montant — un lien profond, ou un retour après
/// expiration. On ne devine pas un total à payer.
class PaymentMissingArgs extends StatelessWidget {
  const PaymentMissingArgs({super.key});

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
                eyebrow: l10n.paymentMethodEyebrow,
                title: l10n.paymentMethodTitle,
              ),
              const Spacer(),
              KoraButton(
                label: l10n.holdExpiredSecondary,
                onPressed: () => context.go('/home'),
              ),
              const SizedBox(height: AppSpacing.lg),
            ],
          ),
        ),
      ),
    );
  }
}
