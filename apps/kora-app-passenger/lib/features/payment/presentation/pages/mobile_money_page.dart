import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../auth/domain/entities/phone_country.dart';
import '../../../booking/presentation/widgets/hold_countdown.dart';
import '../widgets/labelled_field.dart';
import 'payment_args.dart';
import 'payment_flow_args.dart';
import 'payment_method_page.dart';

/// Les opérateurs Mobile Money acceptés, dans l'ordre de la maquette.
///
/// La clé est celle qu'attend `POST payments/initiate` (`provider`) ; le
/// libellé est ce que lit le voyageur.
const Map<String, String> kMobileMoneyOperators = {
  'mtn_momo': 'MTN MoMo',
  'moov_money': 'Moov Money',
  'celtiis_cash': 'Celtiis Cash',
  'wave': 'Wave',
};

/// Écran `mobile-money` de la maquette : opérateur, numéro à débiter, et
/// le rappel de sécurité.
///
/// Aucun code secret n'est demandé ici, et c'est le message qui le dit :
/// la validation se fait dans la fenêtre de l'opérateur, sur le téléphone.
/// Un écran qui réclamerait ce code apprendrait au voyageur à le taper
/// ailleurs que chez son opérateur — exactement ce que cherchent ceux qui
/// l'appellent pour le lui soutirer.
class MobileMoneyPage extends StatefulWidget {
  const MobileMoneyPage({super.key, this.args});

  final PaymentArgs? args;

  @override
  State<MobileMoneyPage> createState() => _MobileMoneyPageState();
}

class _MobileMoneyPageState extends State<MobileMoneyPage> {
  late final ({PhoneCountry country, String national}) _initial = _split(
    widget.args?.phone,
  );

  late final TextEditingController _phone = TextEditingController(
    text: _initial.national,
  );

  late PhoneCountry _country = _initial.country;

  String? _operator;
  var _showErrors = false;

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  String? get _operatorError =>
      _operator == null ? context.l10n.paymentMomoOperatorRequired : null;

  String? get _phoneError {
    final digits = _phone.text.replaceAll(RegExp(r'\D'), '');
    if (digits.isEmpty) return context.l10n.paymentMomoPhoneRequired;
    // La longueur attendue vient du pays : huit chiffres au Togo, dix au
    // Bénin depuis 2024. Un seuil unique laisserait passer un numéro
    // béninois amputé de deux chiffres.
    if (digits.length < _country.digits) {
      return context.l10n.paymentMomoPhoneShort;
    }
    return null;
  }

  void _pay() {
    setState(() => _showErrors = true);
    if (_operatorError != null || _phoneError != null) return;

    // `POST payments/initiate` reste à brancher : il attend
    // `method: mobile_money`, le `provider` choisi ci-dessus et une clé
    // d'idempotence, et rendra la référence, l'échéance et la cadence de
    // vérification que les écrans suivants savent déjà afficher.
    context.push(
      AppRouter.paymentValidationPath,
      extra: PaymentFlowArgs(
        total: widget.args!.total,
        providerLabel: kMobileMoneyOperators[_operator] ?? '',
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
                eyebrow: l10n.paymentMomoEyebrow(args.total.formatted),
                title: l10n.paymentMomoTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    const HoldCountdown(margin: EdgeInsets.only(bottom: 11)),
                    LabelledField(
                      label: l10n.paymentMomoOperator,
                      // `DropdownMenu` et non `DropdownButtonFormField` :
                      // le second aligne l'élément choisi sur le champ,
                      // donc recouvre le champ et son intitulé. Celui-ci
                      // s'ancre **sous** le champ, comme le `<select>` de
                      // la maquette — on garde sous les yeux ce qu'on est
                      // en train de renseigner.
                      child: LayoutBuilder(
                        builder: (context, constraints) => DropdownMenu<String>(
                          width: constraints.maxWidth,
                          initialSelection: _operator,
                          hintText: l10n.paymentMomoOperatorHint,
                          errorText: _showErrors ? _operatorError : null,
                          textStyle: AppTextStyles.body(koraColors.textPrimary),
                          menuStyle: MenuStyle(
                            backgroundColor: WidgetStatePropertyAll(
                              koraColors.surface,
                            ),
                          ),
                          onSelected: (value) =>
                              setState(() => _operator = value),
                          dropdownMenuEntries: [
                            for (final entry in kMobileMoneyOperators.entries)
                              DropdownMenuEntry(
                                value: entry.key,
                                label: entry.value,
                              ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    LabelledField(
                      label: l10n.paymentMomoPhone,
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Même paire indicatif + numéro que l'écran des
                          // permissions : le voyageur qui débite un compte
                          // togolais depuis le Bénin doit pouvoir le dire.
                          SizedBox(
                            width: 124,
                            child: DropdownButtonFormField<PhoneCountry>(
                              initialValue: _country,
                              isExpanded: true,
                              onChanged: (value) => setState(
                                () => _country = value ?? PhoneCountry.benin,
                              ),
                              items: [
                                for (final item in PhoneCountry.all)
                                  DropdownMenuItem(
                                    value: item,
                                    child: Text(
                                      '${item.flag}  ${item.code}',
                                      style: AppTextStyles.body(
                                        koraColors.textPrimary,
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: KoraInput(
                              hint: _country.example,
                              controller: _phone,
                              keyboardType: TextInputType.phone,
                              errorText: _showErrors ? _phoneError : null,
                              onChanged: (_) => setState(() {}),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    KoraCard(
                      variant: KoraCardVariant.mist,
                      child: Text(
                        l10n.paymentMomoNotice,
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
                  label: l10n.paymentMomoPay(args.total.formatted),
                  onPressed: _pay,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Sépare un numéro enregistré en indicatif et partie nationale.
///
/// Le compte KORA stocke le numéro complet (`+229 0152235689`) ; l'écran,
/// lui, sépare les deux pour pouvoir proposer un autre pays. À défaut de
/// reconnaître l'indicatif, on retombe sur le Bénin et on garde les
/// chiffres tels quels : mieux vaut un pays à corriger qu'un champ vide.
({PhoneCountry country, String national}) _split(String? stored) {
  final raw = (stored ?? '').trim();
  if (raw.isEmpty) return (country: PhoneCountry.benin, national: '');

  for (final country in PhoneCountry.all) {
    if (raw.startsWith(country.code)) {
      return (
        country: country,
        national: raw.substring(country.code.length).trim(),
      );
    }
  }

  return (country: PhoneCountry.benin, national: raw);
}
