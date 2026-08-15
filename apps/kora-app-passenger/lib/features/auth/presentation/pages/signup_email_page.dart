import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

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
    final email = _email.text.trim();

    setState(() {
      if (email.isEmpty) {
        _error = 'Adresse e-mail requise';
      } else if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
        _error = 'Adresse e-mail invalide';
      } else {
        _error = null;
      }
    });

    if (_error != null) return;
    widget.onSubmit?.call(email);
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const ScreenHeader(
                eyebrow: 'Création de compte',
                title: 'Votre adresse email',
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    Text(
                      'Nous vous enverrons un code de vérification sécurisé '
                      'par email.',
                      style: AppTextStyles.lead(koraColors.textMuted),
                    ),
                    const SizedBox(height: 18),
                    KoraInput(
                      label: 'Adresse email',
                      // Pas de valeur pré-remplie, contrairement à la
                      // maquette : sur un formulaire de création de compte,
                      // une adresse déjà inscrite serait celle de personne.
                      hint: 'vous@exemple.com',
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
                              'Votre email sert d’identifiant KORA et reste '
                              'protégé.',
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
                    KoraButton(label: 'Recevoir mon code', onPressed: _submit),
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
