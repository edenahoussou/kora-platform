import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/phone_number.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../auth/domain/entities/phone_country.dart';
import '../../../booking/presentation/widgets/hold_countdown.dart';
import '../widgets/labelled_field.dart';
import 'payment_args.dart';
import 'payment_flow_args.dart';
import 'payment_method_page.dart';

/// Les deux encaisseurs proposés au voyageur.
///
/// **Écrits en dur, et provisoirement.** Cette liste doit venir de
/// `GET /api/v1/payments/methods` : toute la chaîne existe et est testée
/// (voir `PaymentCatalogBloc` et `payment_catalog_test.dart`). Mais
/// l'endpoint répond aujourd'hui **500** sur le staging — le serveur route
/// `/payments/methods` vers le handler `/payments/{uuid}` et prend le mot
/// `methods` pour un identifiant :
///
/// ```
/// SQLSTATE[22P02]: invalid input syntax for type uuid: "methods"
/// ```
///
/// Tant que cette route n'est pas corrigée côté back, l'écran resterait
/// bloqué sur son message d'erreur et personne ne pourrait payer.
///
/// **Pour rebrancher l'API**, deux gestes et rien d'autre : rétablir le
/// `BlocBuilder<PaymentCatalogBloc, …>` autour du sélecteur, et remettre
/// le `PaymentCatalogRequested` dans le `ShellRoute` du tunnel
/// (`AppRouter`).
const Map<String, String> kMobileMoneyProviders = {
  'fedapay': 'FedaPay',
  'mtn_momo': 'MTN MoMo',
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
    final l10n = context.l10n;

    // Le préfixe imposé dépend du pays, et de lui seul : le Bénin fait
    // commencer ses numéros par `01`, le Togo n'impose rien. Exiger le
    // `01` partout — ou nulle part — rendrait l'un des deux cas
    // invérifiable. La règle vit dans [PhoneNumberInput], partagée avec
    // les deux autres écrans qui demandent un numéro.
    return switch (PhoneNumberInput.validate(_phone.text, _country)) {
      PhoneIssue.missing => l10n.paymentMomoPhoneRequired,
      // Message générique déjà traduit pour l'écran des permissions :
      // « Un numéro Bénin commence par 01. »
      PhoneIssue.wrongPrefix => l10n.permissionsPhonePrefix(
        _country.name,
        _country.prefix,
      ),
      PhoneIssue.tooShort => l10n.paymentMomoPhoneShort,
      null => null,
    };
  }

  /// Change d'indicatif **et** réaccorde le numéro déjà saisi — voir
  /// [PhoneNumberInput.rebase] pour la règle.
  void _onCountryChanged(PhoneCountry? value) {
    final next = value ?? PhoneCountry.benin;
    if (next == _country) return;

    final rebased = PhoneNumberInput.rebase(
      _phone.text,
      from: _country,
      to: next,
    );

    setState(() {
      _country = next;
      if (rebased != _phone.text) _phone.text = rebased;
    });
  }

  void _pay() {
    setState(() => _showErrors = true);
    if (_operatorError != null || _phoneError != null) return;

    // Le provider choisi part tel quel : `POST payments/initiate` attend
    // `method: mobile_money`, ce `provider`, le `phone`, le
    // `mobile_money_network` et une clé d'idempotence. Il reste à
    // brancher, et rendra la référence et l'échéance que les écrans
    // suivants savent déjà afficher.
    context.push(
      AppRouter.paymentValidationPath,
      extra: PaymentFlowArgs(
        total: widget.args!.total,
        providerLabel: kMobileMoneyProviders[_operator] ?? '',
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
                            for (final entry in kMobileMoneyProviders.entries)
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
                              onChanged: _onCountryChanged,
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
