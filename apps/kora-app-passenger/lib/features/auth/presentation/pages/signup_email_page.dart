import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/services/oauth_sign_in.dart';
import '../../../../core/utils/phone_number.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/phone_country.dart';
import '../bloc/auth_flow_bloc.dart';

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
  const SignupEmailPage({super.key, this.onSubmit, this.onSignIn, this.oauth});

  /// Notifié après une adresse valide, en plus de l'événement envoyé au
  /// parcours. Sert aux tests, qui montent l'écran sans routeur.
  final ValueChanged<String>? onSubmit;

  /// Où mène « Connectez-vous plutôt », quand le compte existe déjà.
  /// Le routeur le renseigne ; un écran monté sans lui retombe sur un
  /// simple retour en arrière.
  final VoidCallback? onSignIn;

  /// Injectable pour les tests, qui n'ont ni Google ni Facebook à
  /// interroger.
  final OAuthSignIn? oauth;

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

  late final OAuthSignIn _oauth = widget.oauth ?? getIt<OAuthSignIn>();

  String? _error;

  /// Fournisseur dont la feuille est ouverte : les trois boutons se
  /// neutralisent le temps de l'aller-retour.
  String? _pending;

  /// Message d'un échec côté fournisseur — avant même que l'API soit
  /// appelée, donc sans rapport avec `flow.errorMessage`.
  String? _oauthError;

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

  /// « Continuer avec … » : le SDK du fournisseur authentifie
  /// l'utilisateur, et c'est son **jeton d'accès** que l'API échange
  /// contre une session KORA (`POST auth/oauth/token`, validé côté
  /// serveur par `Socialite::userFromToken()`).
  Future<void> _social(String provider, String label) async {
    final l10n = context.l10n;

    setState(() {
      _pending = provider;
      _oauthError = null;
    });

    try {
      final token = await _oauth.signIn(provider);
      if (!mounted) return;

      setState(() => _pending = null);
      context.read<AuthFlowBloc>().add(
        AuthFlowProviderSubmitted(provider: provider, providerToken: token),
      );
    } on OAuthSignInException catch (error) {
      if (!mounted) return;

      setState(() {
        _pending = null;
        // Une annulation n'est pas une erreur : l'utilisateur a fermé la
        // feuille, il n'a pas besoin qu'on le lui rappelle.
        _oauthError = switch (error.reason) {
          OAuthFailure.cancelled => null,
          OAuthFailure.notConfigured => l10n.oauthNotConfigured(label),
          OAuthFailure.unsupported => l10n.oauthUnsupported,
          OAuthFailure.failed => l10n.oauthFailed(label),
        };
      });
    }
  }

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
                          SizedBox(
                            width: 124,
                            child: DropdownButtonFormField<PhoneCountry>(
                              initialValue: _country,
                              isExpanded: true,
                              onChanged: (value) => _onCountryChanged(
                                value ?? PhoneCountry.benin,
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
                          // Adresse déjà inscrite, envoi impossible : le
                          // message vient du serveur et se lit ici, sous le
                          // bouton, comme sur les autres écrans du parcours.
                          if (flow.hasError) ...[
                            const SizedBox(height: 12),
                            Text(
                              flow.errorMessage,
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: AppColors.danger,
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                          // Un compte déjà créé n'est pas une saisie à
                          // corriger : rien ne sert de retaper la même
                          // adresse, c'est la connexion qu'il faut. On y
                          // mène, plutôt que de laisser le voyageur
                          // chercher le chemin tout seul.
                          if (flow.accountExists) ...[
                            const SizedBox(height: 4),
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
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 24),
                    KoraOrDivider(label: l10n.signupOr),
                    const SizedBox(height: 18),
                    // Les trois fournisseurs, dans l'ordre de leur usage
                    // attendu au Bénin.
                    KoraSocialButton(
                      provider: KoraSocialProvider.google,
                      label: l10n.signupWithGoogle,
                      isLoading: _pending == OAuthSignInImpl.google,
                      onPressed: _pending != null
                          ? null
                          : () => _social(OAuthSignInImpl.google, 'Google'),
                    ),
                    const SizedBox(height: 10),
                    KoraSocialButton(
                      provider: KoraSocialProvider.facebook,
                      label: l10n.signupWithFacebook,
                      isLoading: _pending == OAuthSignInImpl.facebook,
                      onPressed: _pending != null
                          ? null
                          : () => _social(OAuthSignInImpl.facebook, 'Facebook'),
                    ),
                    const SizedBox(height: 10),
                    KoraSocialButton(
                      provider: KoraSocialProvider.tiktok,
                      label: l10n.signupWithTiktok,
                      isLoading: _pending == OAuthSignInImpl.tiktok,
                      onPressed: _pending != null
                          ? null
                          : () => _social(OAuthSignInImpl.tiktok, 'TikTok'),
                    ),
                    if (_oauthError != null) ...[
                      const SizedBox(height: 12),
                      Text(
                        _oauthError!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: AppColors.danger,
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
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
