import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/widgets/screen_header.dart';

/// Première étape de la création de compte — écran `phone` de la maquette
/// (qui, malgré son nom, demande bien une **adresse e-mail**) : l'adresse
/// sert d'identifiant KORA, et reçoit le code de vérification.
///
/// Écran de saisie pure : il valide le format et transmet l'adresse. Aucun
/// appel réseau, donc pas de Bloc — la vérification du code et la création
/// effective du compte viendront avec la feature `auth`.
class SignupEmailPage extends StatefulWidget {
  const SignupEmailPage({super.key, this.onSubmit});

  /// Reçoit l'adresse validée. Injecté par la route, pour que l'écran
  /// reste testable sans routeur.
  final ValueChanged<String>? onSubmit;

  @override
  State<SignupEmailPage> createState() => _SignupEmailPageState();
}

class _SignupEmailPageState extends State<SignupEmailPage> {
  final _email = TextEditingController();

  String? _error;

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
    widget.onSubmit?.call(email);
  }

  void _social(BuildContext context, String provider) =>
      KoraToaster.show(context, context.l10n.signupSocialSoon(provider));

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
                    KoraButton(label: l10n.signupSubmit, onPressed: _submit),
                    const SizedBox(height: 24),
                    KoraOrDivider(label: l10n.signupOr),
                    const SizedBox(height: 18),
                    // Les trois fournisseurs, dans l'ordre de leur usage
                    // attendu au Bénin. Aucun n'est branché : la feature
                    // `auth` n'existe pas, chacun le dit plutôt que de
                    // simuler une connexion.
                    KoraSocialButton(
                      provider: KoraSocialProvider.google,
                      label: l10n.signupWithGoogle,
                      onPressed: () => _social(context, 'Google'),
                    ),
                    const SizedBox(height: 10),
                    KoraSocialButton(
                      provider: KoraSocialProvider.facebook,
                      label: l10n.signupWithFacebook,
                      onPressed: () => _social(context, 'Facebook'),
                    ),
                    const SizedBox(height: 10),
                    KoraSocialButton(
                      provider: KoraSocialProvider.tiktok,
                      label: l10n.signupWithTiktok,
                      onPressed: () => _social(context, 'TikTok'),
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
