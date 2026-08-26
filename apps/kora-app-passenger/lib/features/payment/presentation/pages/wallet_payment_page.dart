import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../../booking/domain/entities/price.dart';
import '../../../booking/presentation/widgets/hold_countdown.dart';
import 'payment_args.dart';
import 'payment_flow_args.dart';
import 'payment_method_page.dart';

/// Écran `wallet-payment` de la maquette : payer tout ou partie du billet
/// avec le solde et les points.
///
/// Le Wallet couvre rarement la totalité : l'écran calcule donc en
/// permanence ce qu'il restera à régler en Mobile Money, et n'envoie vers
/// ce complément que s'il est non nul. Cacher ce reste aurait fait
/// découvrir un second paiement au moment de valider.
class WalletPaymentPage extends StatefulWidget {
  const WalletPaymentPage({super.key, this.args});

  final PaymentArgs? args;

  /// Solde de démonstration, le même que l'écran Wallet : l'API n'expose
  /// pas encore de solde.
  static const Price balance = Price(18750);

  /// Points disponibles, et le pas du curseur — repris de la maquette
  /// (`min=100 max=2400 step=100`).
  static const int maxPoints = 2400;
  static const int minPoints = 100;
  static const int pointsStep = 100;

  /// Le pas des boutons `−` / `+` du crédit Wallet.
  static const int creditStep = 1000;

  @override
  State<WalletPaymentPage> createState() => _WalletPaymentPageState();
}

class _WalletPaymentPageState extends State<WalletPaymentPage> {
  int _credit = 0;
  int _points = WalletPaymentPage.minPoints;

  /// Ce que le Wallet et les points couvrent, plafonné au prix du billet :
  /// verser plus que le total n'aurait aucun sens, et le complément
  /// deviendrait négatif.
  int get _covered {
    final total = widget.args?.total.amount ?? 0;
    final used = _credit + _points;
    return used > total ? total : used;
  }

  int get _remainder => (widget.args?.total.amount ?? 0) - _covered;

  /// Le crédit ne dépasse ni le solde, ni ce qu'il reste à payer une fois
  /// les points déduits.
  int get _maxCredit {
    final total = widget.args?.total.amount ?? 0;
    final room = total - _points;
    final ceiling = room < 0 ? 0 : room;
    return ceiling > WalletPaymentPage.balance.amount
        ? WalletPaymentPage.balance.amount
        : ceiling;
  }

  void _shiftCredit(int delta) {
    setState(() {
      final next = _credit + delta;
      _credit = next.clamp(0, _maxCredit);
    });
  }

  void _confirm() {
    final args = widget.args;
    if (args == null) return;

    // Un reste à payer bascule sur Mobile Money, comme la maquette ; sinon
    // le Wallet solde le billet et il n'y a plus rien à demander.
    if (_remainder > 0) {
      context.push(
        AppRouter.paymentMobileMoneyPath,
        extra: PaymentArgs(
          total: Price(_remainder, currency: args.total.currency),
          phone: args.phone,
          departure: args.departure,
          seatLabel: args.seatLabel,
        ),
      );
      return;
    }

    context.push(
      AppRouter.paymentSuccessPath,
      extra: PaymentFlowArgs(
        total: args.total,
        providerLabel: context.l10n.paymentMethodWallet,
        departure: args.departure,
        seatLabel: args.seatLabel,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final args = widget.args;

    if (args == null) return const PaymentMissingArgs();

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.paymentWalletEyebrow,
                title: l10n.paymentWalletTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    const HoldCountdown(margin: EdgeInsets.only(bottom: 11)),
                    _BalanceCard(balance: WalletPaymentPage.balance),
                    SectionTitle(title: l10n.paymentWalletAmountTitle),
                    _CreditStepper(
                      label: l10n.paymentWalletAmountLabel,
                      value: Price(
                        _credit,
                        currency: args.total.currency,
                      ).formatted,
                      onMinus: _credit > 0
                          ? () => _shiftCredit(-WalletPaymentPage.creditStep)
                          : null,
                      onPlus: _credit < _maxCredit
                          ? () => _shiftCredit(WalletPaymentPage.creditStep)
                          : null,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    _PointsSlider(
                      points: _points,
                      onChanged: (value) => setState(() {
                        _points = value;
                        // Le crédit peut devenir excédentaire quand les
                        // points montent : on le ramène dans les clous.
                        if (_credit > _maxCredit) _credit = _maxCredit;
                      }),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    KoraCard(
                      child: Column(
                        children: [
                          _BreakdownRow(
                            label: l10n.paymentWalletTicketTotal,
                            value: args.total.formatted,
                          ),
                          const Divider(height: 26),
                          _BreakdownRow(
                            label: l10n.paymentWalletCredit,
                            value:
                                '− ${Price(_covered, currency: args.total.currency).formatted}',
                          ),
                          const Divider(height: 26),
                          _BreakdownRow(
                            label: l10n.paymentWalletRemainder,
                            value: Price(
                              _remainder,
                              currency: args.total.currency,
                            ).formatted,
                            highlight: true,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    KoraCard(
                      variant: KoraCardVariant.mist,
                      child: Text(
                        l10n.paymentWalletNotice,
                        style: AppTextStyles.body(koraColors.textMuted),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xl),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: KoraButton(
                  label: l10n.paymentWalletConfirm,
                  onPressed: _confirm,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.wallet-card` : le solde, en grand, sur le navy KORA.
class _BalanceCard extends StatelessWidget {
  const _BalanceCard({required this.balance});

  final Price balance;

  @override
  Widget build(BuildContext context) {
    return KoraCard(
      variant: KoraCardVariant.navy,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.paymentWalletBalance,
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.7),
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            balance.formatted,
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 28,
              fontWeight: FontWeight.w900,
              letterSpacing: -1,
            ),
          ),
        ],
      ),
    );
  }
}

/// `.wallet-amount-control` : le montant encadré de ses deux boutons.
class _CreditStepper extends StatelessWidget {
  const _CreditStepper({
    required this.label,
    required this.value,
    required this.onMinus,
    required this.onPlus,
  });

  final String label;
  final String value;
  final VoidCallback? onMinus;
  final VoidCallback? onPlus;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return KoraCard(
      child: Row(
        children: [
          _StepButton(icon: Icons.remove_rounded, onTap: onMinus),
          Expanded(
            child: Column(
              children: [
                Text(label, style: AppTextStyles.caption(koraColors.textMuted)),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: AppTextStyles.titleMedium(context.koraTitleColor),
                ),
              ],
            ),
          ),
          _StepButton(icon: Icons.add_rounded, onTap: onPlus),
        ],
      ),
    );
  }
}

class _StepButton extends StatelessWidget {
  const _StepButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final enabled = onTap != null;

    return Material(
      color: enabled ? AppColors.gold : koraColors.background,
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 38,
          height: 38,
          child: Icon(
            icon,
            size: 20,
            color: enabled ? AppColors.kora : koraColors.textMuted,
          ),
        ),
      ),
    );
  }
}

/// `.range-field` : les points fidélité, au curseur.
class _PointsSlider extends StatelessWidget {
  const _PointsSlider({required this.points, required this.onChanged});

  final int points;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              // `Expanded` des deux côtés : « Points fidélité utilisés »
              // et son compteur ne tiennent pas ensemble sur une ligne
              // étroite, et un `spaceBetween` déborderait au lieu de
              // rendre la main.
              Expanded(
                child: Text(
                  l10n.paymentWalletPoints,
                  style: AppTextStyles.body(koraColors.textMuted),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Text(
                l10n.paymentWalletPointsValue(points),
                style: AppTextStyles.titleMedium(context.koraTitleColor),
              ),
            ],
          ),
          Slider(
            value: points.toDouble(),
            min: WalletPaymentPage.minPoints.toDouble(),
            max: WalletPaymentPage.maxPoints.toDouble(),
            divisions:
                (WalletPaymentPage.maxPoints - WalletPaymentPage.minPoints) ~/
                WalletPaymentPage.pointsStep,
            activeColor: AppColors.gold,
            onChanged: (value) => onChanged(value.round()),
          ),
        ],
      ),
    );
  }
}

class _BreakdownRow extends StatelessWidget {
  const _BreakdownRow({
    required this.label,
    required this.value,
    this.highlight = false,
  });

  final String label;
  final String value;

  /// Le complément à régler ailleurs, en bleu : c'est la seule ligne qui
  /// engage encore le voyageur.
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(
            child: Text(label, style: AppTextStyles.body(koraColors.textMuted)),
          ),
          const SizedBox(width: AppSpacing.sm),
          Text(
            value,
            style: AppTextStyles.titleMedium(
              highlight ? AppColors.digital : context.koraTitleColor,
            ),
          ),
        ],
      ),
    );
  }
}
