import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/presentation/widgets/hold_countdown.dart';
import '../widgets/card_number_formatter.dart';
import 'payment_args.dart';
import 'payment_flow_args.dart';
import 'payment_method_page.dart';

/// Écran `card-payment-bus` de la maquette : le paiement par carte.
///
/// **Rien de ce qui est saisi ici ne quitte l'écran ni n'est conservé.**
/// `POST payments/initiate` n'accepte aucune donnée de carte — il prend un
/// `method` et un `provider`, jamais un numéro — et c'est volontaire :
/// faire transiter un PAN et un CVV par nos serveurs y ferait entrer les
/// obligations PCI-DSS. Le jour où ce parcours sera branché, ces champs
/// devront être remplacés par le formulaire hébergé du prestataire (SDK ou
/// page 3-D Secure), qui rend un jeton à la place du numéro.
///
/// D'ici là l'écran existe, il valide la saisie, et il ne l'envoie nulle
/// part. Les champs ne sont pas non plus pré-remplis, contrairement à la
/// maquette : un numéro de carte affiché d'avance apprend à ne pas
/// vérifier ce qu'on paie.
class CardPaymentPage extends StatefulWidget {
  const CardPaymentPage({super.key, this.args});

  final PaymentArgs? args;

  @override
  State<CardPaymentPage> createState() => _CardPaymentPageState();
}

class _CardPaymentPageState extends State<CardPaymentPage> {
  final _holder = TextEditingController();
  final _number = TextEditingController();
  final _expiry = TextEditingController();
  final _cvv = TextEditingController();

  var _save = false;
  var _showErrors = false;

  @override
  void dispose() {
    _holder.dispose();
    _number.dispose();
    _expiry.dispose();
    _cvv.dispose();
    super.dispose();
  }

  String? get _holderError => _holder.text.trim().isEmpty
      ? context.l10n.paymentCardHolderRequired
      : null;

  String? get _numberError {
    final digits = _number.text.replaceAll(RegExp(r'\D'), '');
    if (digits.isEmpty) return context.l10n.paymentCardNumberRequired;
    // Longueur et clé de Luhn : de quoi écarter une faute de frappe sans
    // prétendre dire si la carte est provisionnée.
    if (digits.length < 13 || digits.length > 19 || !_luhn(digits)) {
      return context.l10n.paymentCardNumberInvalid;
    }
    return null;
  }

  String? get _expiryError {
    final match = RegExp(
      r'^(\d{2})\s*/?\s*(\d{2})$',
    ).firstMatch(_expiry.text.trim());
    if (match == null) return context.l10n.paymentCardExpiryInvalid;

    final month = int.parse(match.group(1)!);
    final year = 2000 + int.parse(match.group(2)!);
    if (month < 1 || month > 12) return context.l10n.paymentCardExpiryInvalid;

    // Une carte expire à la fin de son mois : on compare au premier jour
    // du mois suivant.
    final now = DateTime.now();
    if (DateTime(year, month + 1).isBefore(DateTime(now.year, now.month))) {
      return context.l10n.paymentCardExpiryInvalid;
    }
    return null;
  }

  String? get _cvvError {
    final digits = _cvv.text.replaceAll(RegExp(r'\D'), '');
    // Trois chiffres, quatre chez American Express.
    return digits.length < 3 || digits.length > 4
        ? context.l10n.paymentCardCvvInvalid
        : null;
  }

  void _pay() {
    setState(() => _showErrors = true);

    final invalid =
        _holderError != null ||
        _numberError != null ||
        _expiryError != null ||
        _cvvError != null;
    if (invalid) return;

    // Le prestataire de carte confirme ou refuse dans la foulée : pas de
    // fenêtre d'opérateur à attendre, on va droit à la confirmation.
    context.push(
      AppRouter.paymentSuccessPath,
      extra: PaymentFlowArgs(
        total: widget.args!.total,
        providerLabel: context.l10n.paymentMethodCard,
        departure: widget.args!.departure,
        seatLabel: widget.args!.seatLabel,
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
                eyebrow: l10n.paymentCardEyebrow,
                title: l10n.paymentCardTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    const HoldCountdown(margin: EdgeInsets.only(bottom: 11)),
                    const _BrandRow(),
                    const SizedBox(height: AppSpacing.md),
                    KoraInput(
                      label: l10n.paymentCardHolder,
                      controller: _holder,
                      errorText: _showErrors ? _holderError : null,
                      onChanged: (_) => setState(() {}),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    KoraInput(
                      label: l10n.paymentCardNumber,
                      controller: _number,
                      keyboardType: TextInputType.number,
                      inputFormatters: const [CardNumberFormatter()],
                      errorText: _showErrors ? _numberError : null,
                      onChanged: (_) => setState(() {}),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    // `.grid-2` : expiration et CVV sur une seule ligne.
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: KoraInput(
                            label: l10n.paymentCardExpiry,
                            hint: l10n.paymentCardExpiryHint,
                            controller: _expiry,
                            keyboardType: TextInputType.number,
                            inputFormatters: const [CardExpiryFormatter()],
                            errorText: _showErrors ? _expiryError : null,
                            onChanged: (_) => setState(() {}),
                          ),
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        Expanded(
                          child: KoraInput(
                            label: l10n.paymentCardCvv,
                            controller: _cvv,
                            keyboardType: TextInputType.number,
                            obscureText: true,
                            // Quatre chiffres au plus : trois partout,
                            // quatre chez American Express.
                            inputFormatters: [
                              FilteringTextInputFormatter.digitsOnly,
                              LengthLimitingTextInputFormatter(4),
                            ],
                            errorText: _showErrors ? _cvvError : null,
                            onChanged: (_) => setState(() {}),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.md),
                    _SaveCardChoice(
                      value: _save,
                      onChanged: (value) => setState(() => _save = value),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    KoraCard(
                      variant: KoraCardVariant.mist,
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            l10n.paymentCardTotal,
                            style: AppTextStyles.body(koraColors.textMuted),
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
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    KoraButton(
                      label: l10n.paymentCardPay(args.total.formatted),
                      onPressed: _pay,
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    KoraButton(
                      label: l10n.paymentCardSwitch,
                      variant: KoraButtonVariant.ghost,
                      onPressed: () => context.pop(),
                    ),
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

/// Vérifie la clé de Luhn — le chiffre de contrôle que porte tout numéro
/// de carte. Attrape la faute de frappe, rien de plus.
bool _luhn(String digits) {
  var sum = 0;
  var alternate = false;

  for (var i = digits.length - 1; i >= 0; i--) {
    var value = digits.codeUnitAt(i) - 48;
    if (alternate) {
      value *= 2;
      if (value > 9) value -= 9;
    }
    sum += value;
    alternate = !alternate;
  }

  return sum % 10 == 0;
}

/// `.card-brand-row` : les réseaux acceptés et la mention de sécurité.
class _BrandRow extends StatelessWidget {
  const _BrandRow();

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    // `Wrap` et non `Row` : sur un écran étroit la mention de sécurité
    // passe à la ligne, là où un `Row` la tronquait en « Paiement séc… ».
    return Wrap(
      crossAxisAlignment: WrapCrossAlignment.center,
      spacing: AppSpacing.sm,
      runSpacing: AppSpacing.sm,
      children: [
        for (final brand in ['VISA', 'mastercard']) ...[
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              border: Border.all(color: koraColors.line),
              borderRadius: BorderRadius.circular(AppRadius.sm),
            ),
            child: Text(
              brand,
              style: AppTextStyles.caption(koraColors.textPrimary),
            ),
          ),
        ],
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.lock_rounded, size: 14, color: koraColors.textMuted),
            const SizedBox(width: 4),
            Text(
              context.l10n.paymentCardSecure,
              style: AppTextStyles.caption(koraColors.textMuted),
            ),
          ],
        ),
      ],
    );
  }
}

/// `.card-save-choice` : la case à cocher et son explication.
class _SaveCardChoice extends StatelessWidget {
  const _SaveCardChoice({required this.value, required this.onChanged});

  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return KoraCard(
      onTap: () => onChanged(!value),
      child: Row(
        children: [
          Checkbox(
            value: value,
            activeColor: AppColors.gold,
            checkColor: AppColors.kora,
            onChanged: (next) => onChanged(next ?? false),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  l10n.paymentCardSave,
                  style: AppTextStyles.titleMedium(context.koraTitleColor),
                ),
                const SizedBox(height: 2),
                Text(
                  l10n.paymentCardSaveLead,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
