import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/services/oauth_sign_in.dart';
import '../../../../core/widgets/screen_header.dart';
import '../bloc/auth_flow_bloc.dart';

/// Première étape de la création de compte — écran `phone` de la maquette
/// (qui, malgré son nom, demande bien une **adresse e-mail**) : l'adresse
/// sert d'identifiant KORA, et reçoit le code de vérification.
///
/// L'écran ne valide que le format : c'est [AuthFlowBloc] qui demande le
/// code à l'API et décide de la suite. Le message d'erreur affiché sous le
/// champ vient donc soit d'ici (adresse mal formée), soit du serveur.
class SignupEmailPage extends StatefulWidget {
  const SignupEmailPage({super.key, this.onSubmit, this.oauth});

  /// Notifié après une adresse valide, en plus de l'événement envoyé au
  /// parcours. Sert aux tests, qui montent l'écran sans routeur.
  final ValueChanged<String>? onSubmit;

  /// Injectable pour les tests, qui n'ont ni Google ni Facebook à
  /// interroger.
  final OAuthSignIn? oauth;

  @override
  State<SignupEmailPage> createState() => _SignupEmailPageState();
}

class _SignupEmailPageState extends State<SignupEmailPage> {
  final _email = TextEditingController();

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
    super.dispose();
  }

  void _submit() {
    final l10n = context.l10n;
    final email = _email.text.trim();

    setState(() {
      if (email.isEmpty) {
        _error = l10n.signupEmailRequired;
      } else if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
        _error = l10n.signupEmailInvalid;
      } else {
        _error = null;
      }
    });

    if (_error != null) return;

    // C'est cet événement qui demande le code à l'API et fait avancer le
    // parcours vers l'écran OTP. Sans lui, le bouton ne fait rien.
    context.read<AuthFlowBloc>().add(AuthFlowEmailSubmitted(email: email));
    widget.onSubmit?.call(email);
  }

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
                title: l10n.signupTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    Text(
                      l10n.signupLead,
                      style: AppTextStyles.lead(koraColors.textMuted),
                    ),
                    const SizedBox(height: 18),
                    KoraInput(
                      label: l10n.signupEmailLabel,
                      // Pas de valeur pré-remplie, contrairement à la
                      // maquette : sur un formulaire de création de compte,
                      // une adresse déjà inscrite serait celle de personne.
                      hint: l10n.signupEmailHint,
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      errorText: _error,
                      onChanged: (_) {
                        if (_error != null) setState(() => _error = null);
                      },
                    ),
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
                              l10n.signupEmailProtected,
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
