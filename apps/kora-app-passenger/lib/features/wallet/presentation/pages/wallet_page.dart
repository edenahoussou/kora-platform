import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/widgets/brand_mini.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../../booking/domain/entities/price.dart';

/// Écran `wallet` de la maquette — l'onglet « Wallet » : le solde, les
/// points de fidélité, les quatre actions et les derniers mouvements.
///
/// Montants figés : la feature `wallet` n'existe pas encore. Ils passent
/// tout de même par [Price], qui porte déjà le format monétaire de l'app —
/// un « 18 750 FCFA » écrit en dur ici se séparerait autrement des prix du
/// tunnel Bus le jour où le format changera.
class WalletPage extends StatelessWidget {
  const WalletPage({super.key});

  static const _balance = Price(18750);
  static const _points = 2480;

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
                child: ListView(
                  padding: const EdgeInsets.only(bottom: 100),
                  children: [
                    const _BalanceCard(balance: _balance),
                    const SizedBox(height: 9),
                    _PointsStrip(points: _points, onTap: () => _soon(context)),
                    const SizedBox(height: 11),
                    _ActionGrid(onTap: () => _soon(context)),
                    const SizedBox(height: 11),
                    SectionTitle(
                      title: l10n.walletMovements,
                      actionLabel: l10n.actionSeeAll,
                      onActionTap: () => _soon(context),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    const _Movements(),
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
  const _PointsStrip({required this.points, required this.onTap});

  final int points;
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
                      // Un point vaut un franc : la maquette affiche les
                      // deux pour que la valeur soit lisible sans calcul.
                      l10n.walletPointsValue(
                        '$points',
                        Price(points).formatted,
                      ),
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
  const _Movements();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return KoraCard(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          _Transaction(
            icon: Icons.add_rounded,
            title: l10n.walletTxRefund,
            subtitle: 'KE-…78342',
            amount: '+ ${const Price(9375).formatted}',
            isCredit: true,
          ),
          _Transaction(
            icon: Icons.star_rounded,
            title: l10n.walletTxPoints,
            subtitle: l10n.walletTxPointsDetail,
            amount: '+ 375 pts',
            isCredit: true,
          ),
          _Transaction(
            icon: Icons.remove_rounded,
            title: l10n.walletTxPayment,
            subtitle: 'KE-…51802',
            amount: '− ${const Price(6000).formatted}',
            isLast: true,
          ),
        ],
      ),
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
