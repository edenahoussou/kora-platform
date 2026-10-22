import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/phone_number.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/phone_country.dart';
import '../bloc/auth_flow_bloc.dart';
import '../widgets/phone_country_picker.dart';

/// Première étape de la création de compte — écran `phone` de la
/// maquette : l'identifiant KORA, celui qui reçoit le code de
/// vérification.
///
/// Le **téléphone** par défaut, l'adresse e-mail par le lien du bas —
/// l'API accepte les deux depuis qu'elle sait envoyer le code sur
/// WhatsApp, et c'est par leur numéro que les voyageurs se reconnaissent
/// ici. Une inscription par téléphone demande en plus le **nom** : sans
/// adresse, le serveur n'a rien d'où en dériver un et répond
/// `422 name_required`.
///
/// L'écran ne valide que la forme : c'est [AuthFlowBloc] qui demande le
/// code à l'API et décide de la suite. Le message affiché sous le champ
/// vient donc soit d'ici, soit du serveur.
class SignupEmailPage extends StatefulWidget {
  const SignupEmailPage({super.key, this.onSubmit, this.onSignIn});

  /// Notifié après une adresse valide, en plus de l'événement envoyé au
  /// parcours. Sert aux tests, qui montent l'écran sans routeur.
  final ValueChanged<String>? onSubmit;

  /// Où mène « Connectez-vous plutôt », quand le compte existe déjà.
  /// Le routeur le renseigne ; un écran monté sans lui retombe sur un
  /// simple retour en arrière.
  final VoidCallback? onSignIn;

  @override
  State<SignupEmailPage> createState() => _SignupEmailPageState();
}

/// Ce que l'écran demande pour créer le compte.
enum _SignupMode { email, phone }

class _SignupEmailPageState extends State<SignupEmailPage> {
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _name = TextEditingController();

  /// Le téléphone par défaut : c'est le premier identifiant du
  /// voyageur au Bénin, et celui qui reçoit le code sur WhatsApp.
  /// L'adresse reste à un lien de là, pour qui préfère la sienne.
  _SignupMode _mode = _SignupMode.phone;

  PhoneCountry _country = PhoneCountry.benin;

  /// L'erreur du champ « Nom complet », propre au mode téléphone.
  String? _nameError;

  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _phone.dispose();
    _name.dispose();
    super.dispose();
  }

  /// Change d'indicatif et réaccorde le numéro déjà saisi — même règle
  /// que les écrans de connexion, d'autorisations et de paiement.
  void _onCountryChanged(PhoneCountry country) {
    final rebased = PhoneNumberInput.rebase(
      _phone.text,
      from: _country,
      to: country,
    );

    setState(() {
      _country = country;
      if (rebased != _phone.text) _phone.text = rebased;
      _error = null;
    });
  }

  /// Bascule adresse ↔ téléphone, en effaçant ce qui appartenait à
  /// l'autre mode.
  void _switchMode() {
    setState(() {
      _mode = _mode == _SignupMode.email
          ? _SignupMode.phone
          : _SignupMode.email;
      _error = null;
      _nameError = null;
    });
  }

  void _submit() {
    final l10n = context.l10n;

    setState(() {
      _error = _mode == _SignupMode.email
          ? _emailIssue(l10n)
          : _phoneIssue(l10n);
      // Le nom n'est réclamé que par l'inscription par téléphone : à
      // l'adresse, le serveur le dérive lui-même.
      _nameError = _mode == _SignupMode.phone && _name.text.trim().isEmpty
          ? l10n.signupNameRequired
          : null;
    });

    if (_error != null || _nameError != null) return;

    // C'est cet événement qui demande le code à l'API et fait avancer le
    // parcours vers l'écran OTP. Sans lui, le bouton ne fait rien.
    final identifier = _identifier;
    context.read<AuthFlowBloc>().add(
      AuthFlowIdentifierSubmitted(
        identifier: identifier,
        // Porté jusqu'à `register/complete`, où le serveur l'exige pour
        // une inscription par téléphone.
        name: _mode == _SignupMode.phone ? _name.text.trim() : null,
      ),
    );
    widget.onSubmit?.call(identifier);
  }

  /// L'identifiant soumis : l'adresse, ou le numéro au format
  /// international attendu par l'API.
  String get _identifier => switch (_mode) {
    _SignupMode.email => _email.text.trim(),
    _SignupMode.phone =>
      '${_country.code}${_phone.text.replaceAll(RegExp(r'\D'), '')}',
  };

  String? _emailIssue(AppLocalization l10n) {
    final email = _email.text.trim();
    if (email.isEmpty) return l10n.signupEmailRequired;

    return RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)
        ? null
        : l10n.signupEmailInvalid;
  }

  String? _phoneIssue(AppLocalization l10n) =>
      switch (PhoneNumberInput.validate(_phone.text, _country)) {
        null => null,
        PhoneIssue.missing => l10n.signupPhoneRequired,
        PhoneIssue.wrongPrefix => l10n.signupPhonePrefix(
          _country.name,
          _country.prefix,
        ),
        PhoneIssue.tooShort => l10n.signupPhoneIncomplete,
      };

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.signupEyebrow,
                title: _mode == _SignupMode.email
                    ? l10n.signupTitle
                    : l10n.signupPhoneTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    Text(
                      _mode == _SignupMode.email
                          ? l10n.signupLead
                          : l10n.signupPhoneLead,
                      style: AppTextStyles.lead(koraColors.textMuted),
                    ),
                    const SizedBox(height: 18),
                    if (_mode == _SignupMode.email)
                      KoraInput(
                        label: l10n.signupEmailLabel,
                        // Pas de valeur pré-remplie, contrairement à la
                        // maquette : sur un formulaire de création de
                        // compte, une adresse déjà inscrite serait celle
                        // de personne.
                        hint: l10n.signupEmailHint,
                        controller: _email,
                        keyboardType: TextInputType.emailAddress,
                        errorText: _error,
                        onChanged: (_) {
                          if (_error != null) setState(() => _error = null);
                        },
                      )
                    else ...[
                      // Le nom d'abord : c'est lui que le serveur réclame
                      // en plus du numéro, et le demander après le code
                      // ferait échouer la création du compte pour un
                      // champ qu'on n'a jamais montré.
                      KoraInput(
                        label: l10n.signupNameLabel,
                        hint: l10n.signupNameHint,
                        controller: _name,
                        keyboardType: TextInputType.name,
                        errorText: _nameError,
                        onChanged: (_) {
                          if (_nameError != null) {
                            setState(() => _nameError = null);
                          }
                        },
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Text(
                        l10n.signupPhoneLabel,
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _CountryButton(
                            country: _country,
                            onCountry: _onCountryChanged,
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: KoraInput(
                              hint: _country.example,
                              controller: _phone,
                              keyboardType: TextInputType.phone,
                              errorText: _error,
                              onChanged: (_) {
                                if (_error != null) {
                                  setState(() => _error = null);
                                }
                              },
                            ),
                          ),
                        ],
                      ),
                    ],
                    const SizedBox(height: AppSpacing.md),
                    KoraCard(
                      variant: KoraCardVariant.mist,
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(
                            Icons.shield_outlined,
                            size: 18,
                            color: AppColors.gold,
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: Text(
                              _mode == _SignupMode.email
                                  ? l10n.signupEmailProtected
                                  : l10n.signupPhoneProtected,
                              style: AppTextStyles.body(koraColors.textMuted),
                            ),
                          ),
                        ],
                      ),
                    ),
                    // `<div style="margin-top:24px">` : le bouton suit la
                    // card dans le flux, il n'est pas épinglé en bas
                    // d'écran comme ceux du tunnel de réservation.
                    const SizedBox(height: 24),
                    BlocBuilder<AuthFlowBloc, AuthFlowState>(
                      builder: (context, flow) => Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          // Le refus se lit **au-dessus** du bouton, et
                          // c'est tout l'enjeu : sous le bouton, le clavier
                          // le recouvrait. Le voyageur touchait « Recevoir
                          // mon code », voyait tourner, puis retrouvait le
                          // même écran sans un mot — et recommençait. Le
                          // serveur, lui, avait répondu (« Impossible
                          // d'envoyer le code », compte déjà inscrit…) ;
                          // seule la place du message manquait.
                          if (flow.hasError) ...[
                            Text(
                              flow.errorMessage,
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: AppColors.danger,
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 12),
                          ],
                          // Un compte déjà créé n'est pas une saisie à
                          // corriger : rien ne sert de retaper la même
                          // adresse, c'est la connexion qu'il faut. On y
                          // mène, plutôt que de laisser le voyageur
                          // chercher le chemin tout seul.
                          if (flow.accountExists) ...[
                            Text(
                              l10n.signupAccountExistsLead,
                              textAlign: TextAlign.center,
                              style: AppTextStyles.body(koraColors.textMuted),
                            ),
                            TextButton(
                              onPressed: () {
                                final goToSignIn = widget.onSignIn;
                                if (goToSignIn != null) {
                                  goToSignIn();
                                  return;
                                }
                                Navigator.of(context).maybePop();
                              },
                              child: Text(l10n.signupAccountExistsLink),
                            ),
                            const SizedBox(height: 4),
                          ],
                          KoraButton(
                            label: l10n.signupSubmit,
                            isLoading: flow.isSubmitting,
                            onPressed: flow.isSubmitting ? null : _submit,
                          ),
                          const SizedBox(height: 4),
                          // Sous le bouton, comme sur l'écran de
                          // connexion : c'est le même geste, changer la
                          // façon de se nommer.
                          TextButton(
                            onPressed: flow.isSubmitting ? null : _switchMode,
                            child: Text(
                              _mode == _SignupMode.email
                                  ? l10n.signupSwitchToPhone
                                  : l10n.signupSwitchToEmail,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
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

/// L'indicatif, ouvert sur la liste complète des pays.
///
/// Le menu déroulant d'origine ne listait que les onze corridors : on ne
/// pouvait pas créer de compte avec un numéro d'ailleurs. Deux cents
/// entrées ne se parcourent pas dans un menu déroulant, d'où ce champ
/// qui ouvre une feuille avec une recherche.
class _CountryButton extends StatelessWidget {
  const _CountryButton({required this.country, required this.onCountry});

  final PhoneCountry country;
  final ValueChanged<PhoneCountry> onCountry;

  Future<void> _pick(BuildContext context) async {
    final picked = await pickPhoneCountry(context);
    if (picked != null) onCountry(picked);
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return SizedBox(
      width: 124,
      height: 56,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => _pick(context),
          borderRadius: BorderRadius.circular(AppRadius.base),
          child: Ink(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(AppRadius.base),
              border: Border.all(color: koraColors.line),
            ),
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    '${country.flag}  ${country.code}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.body(koraColors.textPrimary),
                  ),
                ),
                Icon(
                  Icons.arrow_drop_down,
                  color: koraColors.textMuted,
                  size: 20,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
