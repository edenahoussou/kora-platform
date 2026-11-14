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
import '../../domain/entities/wallet_account.dart';
import '../../domain/repositories/wallet_repository.dart';
import '../bloc/wallet_cubit.dart';

/// Écran `wallet-services` de la maquette — où dépenser son Wallet.
///
/// La maquette annonce quatre services ; le MVP n'en sert qu'un. KORA
/// Ride, Colis et Premium sont donc montrés sans être cliquables, comme
/// les tuiles de l'accueil : les masquer laisserait croire que le
/// Wallet ne vaut que pour le bus, et les rendre cliquables ouvrirait
/// une page vide.
///
/// Le seuil minimum vient du serveur (`points_min_spend`) : l'écrire
/// ici ferait annoncer une règle que l'administration peut changer.
class WalletServicesPage extends StatelessWidget {
  const WalletServicesPage({super.key, this.cubit});

  /// Cubit déjà construit, pour les tests.
  final WalletCubit? cubit;

  @override
  Widget build(BuildContext context) {
    final existing = cubit;

    return BlocProvider<WalletCubit>(
      create: (_) =>
          (existing ?? WalletCubit(getIt<WalletRepository>()))..load(),
      child: const _ServicesView(),
    );
  }
}

class _ServicesView extends StatelessWidget {
  const _ServicesView();

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
                eyebrow: l10n.walletServicesEyebrow,
                title: l10n.walletServicesTitle,
              ),
              Expanded(
                child: BlocBuilder<WalletCubit, WalletState>(
                  builder: (context, state) => ListView(
                    padding: const EdgeInsets.only(bottom: 100),
                    children: [
                      _ServiceTile(
                        label: l10n.walletServiceBus,
                        lead: l10n.walletServiceBusLead,
                        onTap: () => context.push(AppRouter.busSearchPath),
                      ),
                      const SizedBox(height: 8),
                      _ServiceTile(
                        label: l10n.walletServiceRide,
                        lead: l10n.walletServiceRideLead,
                      ),
                      const SizedBox(height: 8),
                      _ServiceTile(
                        label: l10n.walletServiceParcels,
                        lead: l10n.walletServiceParcelsLead,
                      ),
                      const SizedBox(height: 8),
                      _ServiceTile(
                        label: l10n.walletServicePremium,
                        lead: l10n.walletServicePremiumLead,
                      ),
                      const SizedBox(height: 11),
                      // `.card.mist` : la règle d'usage, avec le seuil
                      // que le serveur impose — jamais un chiffre à nous.
                      KoraCard(
                        variant: KoraCardVariant.mist,
                        child: Text(
                          _rule(context, state.account?.rules),
                          style: AppTextStyles.body(
                            context.koraColors.textMuted,
                          ),
                        ),
                      ),
                      const SizedBox(height: 10),
                      KoraButton(
                        label: l10n.walletServicesRules,
                        variant: KoraButtonVariant.ghost,
                        onPressed: () =>
                            context.push(AppRouter.walletRulesPath),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// La phrase d'usage. Sans les règles du serveur, on dit l'essentiel
  /// sans avancer de seuil.
  String _rule(BuildContext context, WalletRules? rules) {
    final l10n = context.l10n;
    final minimum = rules?.pointsMinSpend ?? 0;
    if (minimum <= 0) return l10n.walletServicesRuleUnknown;

    return l10n.walletServicesRule(l10n.walletPointsUnit('$minimum'));
  }
}

/// `.wallet-service-grid button` : le carré doré, l'intitulé, la
/// précision et le chevron.
class _ServiceTile extends StatelessWidget {
  const _ServiceTile({required this.label, required this.lead, this.onTap});

  final String label;
  final String lead;

  /// `null` pour un service que le MVP ne sert pas encore : la tuile
  /// reste lisible mais ne s'enfonce pas.
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;
    final enabled = onTap != null;

    return Opacity(
      opacity: enabled ? 1 : 0.55,
      child: Material(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: Container(
            constraints: const BoxConstraints(minHeight: 64),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: koraColors.line),
            ),
            child: Row(
              children: [
                Container(
                  width: 36,
                  height: 36,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: AppColors.gold,
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: const Text(
                    'K',
                    style: TextStyle(
                      color: AppColors.kora,
                      fontSize: 15,
                      fontStyle: FontStyle.italic,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        label,
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        enabled ? lead : l10n.walletServiceSoon,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                    ],
                  ),
                ),
                if (enabled)
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

/// Écran `wallet-rules` de la maquette — ce que le Wallet autorise.
///
/// Les quatre règles sont structurelles (voir
/// `docs/mez-49-compte-voyageur-wallet-fidelite.md`) ; seule la valeur
/// d'un point et le seuil d'usage sont des réglages, et ils viennent du
/// serveur.
class WalletRulesPage extends StatelessWidget {
  const WalletRulesPage({super.key, this.cubit});

  final WalletCubit? cubit;

  @override
  Widget build(BuildContext context) {
    final existing = cubit;

    return BlocProvider<WalletCubit>(
      create: (_) =>
          (existing ?? WalletCubit(getIt<WalletRepository>()))..load(),
      child: const _RulesView(),
    );
  }
}

class _RulesView extends StatelessWidget {
  const _RulesView();

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
                eyebrow: l10n.walletRulesEyebrow,
                title: l10n.walletRulesTitle,
              ),
              Expanded(
                child: BlocBuilder<WalletCubit, WalletState>(
                  builder: (context, state) {
                    final rules = state.account?.rules;

                    return ListView(
                      padding: const EdgeInsets.only(bottom: 100),
                      children: [
                        KoraCard(
                          padding: const EdgeInsets.symmetric(horizontal: 14),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              _Rule(
                                symbol: '1',
                                title: _value(context, rules),
                                lead: _spend(context, rules),
                              ),
                              _Rule(
                                symbol: '×',
                                title: l10n.walletRuleNoCashOut,
                                lead: l10n.walletRuleNoCashOutLead,
                              ),
                              _Rule(
                                symbol: '⇄',
                                title: l10n.walletRuleNoTransfer,
                                lead: l10n.walletRuleNoTransferLead,
                              ),
                              _Rule(
                                symbol: '+',
                                title: l10n.walletRuleNoBonus,
                                lead: l10n.walletRuleNoBonusLead,
                                isLast: true,
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 12),
                        _GoldNote(message: _expiry(context, rules)),
                      ],
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _value(BuildContext context, WalletRules? rules) {
    final l10n = context.l10n;
    if (rules == null) return l10n.walletRuleValueUnknown;

    return l10n.walletRuleValue(
      l10n.walletPointsUnit('1'),
      Price(rules.pointValue).formatted,
    );
  }

  String _spend(BuildContext context, WalletRules? rules) {
    final l10n = context.l10n;
    final minimum = rules?.pointsMinSpend ?? 0;
    if (minimum <= 0) return l10n.walletRuleSpendUnknown;

    return l10n.walletRuleSpend(l10n.walletPointsUnit('$minimum'));
  }

  /// La règle d'expiration, avec les seuils du serveur quand il les
  /// donne. Sans eux, on dit la prolongation sans promettre d'alerte :
  /// `points_expiry_reminder_days` n'est pas encore exposé.
  String _expiry(BuildContext context, WalletRules? rules) {
    final l10n = context.l10n;
    final months = rules?.pointsExpiryMonths ?? 0;
    final reminders = rules?.expiryReminderDays ?? const <int>[];

    if (months <= 0) return l10n.walletRuleExpiryUnknown;
    if (reminders.isEmpty) return l10n.walletRuleExpiry('$months');

    return l10n.walletRuleExpiryWithAlerts(
      '$months',
      reminders.map((days) => 'J−$days').join(', '),
    );
  }
}

class _Rule extends StatelessWidget {
  const _Rule({
    required this.symbol,
    required this.title,
    required this.lead,
    this.isLast = false,
  });

  final String symbol;
  final String title;
  final String lead;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(
        border: isLast
            ? null
            : Border(bottom: BorderSide(color: koraColors.line)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: koraColors.background,
              borderRadius: BorderRadius.circular(11),
            ),
            child: Text(
              symbol,
              style: TextStyle(
                color: context.koraTitleColor,
                fontSize: 14,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(lead, style: AppTextStyles.caption(koraColors.textMuted)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// `.card.gold` : l'encadré doré qui porte la règle d'expiration.
class _GoldNote extends StatelessWidget {
  const _GoldNote({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: AppColors.gold.withValues(alpha: 0.45)),
      ),
      child: Text(message, style: AppTextStyles.body(context.koraTitleColor)),
    );
  }
}
