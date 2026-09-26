import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/widgets/screen_header.dart';
import '../bloc/auth_flow_bloc.dart';
import '../widgets/pin_pad.dart';

/// Choix du code PIN — écran `create-pin` de la maquette : consigne,
/// quatre pastilles et le pavé numérique.
///
/// La maquette construit cet écran et sa confirmation avec la même
/// fonction (`pinScreen`), à un titre et un enchaînement près : les deux
/// temps se jouent donc ici, sans seconde route.
///
/// La confirmation n'est pas cosmétique : le serveur valide le PIN avec la
/// règle `confirmed` de Laravel et refuse l'inscription si les deux codes
/// diffèrent. Les comparer avant l'appel évite un aller-retour pour une
/// faute de frappe.
///
/// Le PIN part ensuite vers [AuthFlowBloc], qui termine l'inscription ou
/// réinitialise le code selon le motif du parcours.
class CreatePinPage extends StatefulWidget {
  const CreatePinPage({
    super.key,
    this.title = 'Créez votre PIN',
    this.subtitle =
        'Choisissez 4 chiffres faciles à retenir pour vous, difficiles à '
        'deviner pour les autres.',
    this.confirmTitle = 'Confirmez votre PIN',
    this.confirmSubtitle =
        'Saisissez à nouveau vos 4 chiffres pour éviter toute faute de '
        'frappe.',
    this.onSubmit,
    this.onSignIn,
  });

  final String title;
  final String subtitle;

  /// Textes du second temps, une fois le premier code saisi.
  final String confirmTitle;
  final String confirmSubtitle;

  /// Reçoit le code saisi. Injecté par la route pour que l'écran reste
  /// testable sans routeur.
  final ValueChanged<String>? onSubmit;

  /// Où mène « Connectez-vous plutôt ».
  ///
  /// C'est **ici** que le compte déjà existant se découvre : le serveur
  /// ne refuse pas la demande de code, il refuse la création du compte,
  /// au tout dernier appel. Le voyageur a donc traversé le code et choisi
  /// un PIN avant de l'apprendre — raison de plus pour lui ouvrir le
  /// chemin plutôt que de le laisser devant un mur.
  final VoidCallback? onSignIn;

  @override
  State<CreatePinPage> createState() => _CreatePinPageState();
}

class _CreatePinPageState extends State<CreatePinPage> {
  static const int _pinLength = 4;

  String _pin = '';
  String? _error;

  /// Code du premier temps, retenu le temps de la confirmation.
  String? _chosen;

  bool get _isConfirming => _chosen != null;

  void _press(String key) {
    setState(() {
      _error = null;

      switch (key) {
        case PinKeypad.backspace:
          if (_pin.isNotEmpty) _pin = _pin.substring(0, _pin.length - 1);
        case PinKeypad.validate:
          _submit();
        default:
          if (_pin.length < _pinLength) _pin += key;
      }
    });
  }

  void _submit() {
    if (_pin.length < _pinLength) {
      setState(() => _error = 'Choisissez vos $_pinLength chiffres');
      return;
    }

    // Premier temps : on retient le code et on redemande le même.
    if (!_isConfirming) {
      setState(() {
        _chosen = _pin;
        _pin = '';
        _error = null;
      });
      return;
    }

    // Second temps : à la moindre différence, on repart du choix — laisser
    // corriger la seule confirmation ferait valider un code que
    // l'utilisateur n'a peut-être pas voulu.
    if (_pin != _chosen) {
      setState(() {
        _chosen = null;
        _pin = '';
        _error = 'Les deux codes ne correspondent pas';
      });
      return;
    }

    setState(() => _error = null);

    context.read<AuthFlowBloc>().add(AuthFlowPinSubmitted(_pin));
    widget.onSubmit?.call(_pin);
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
              ScreenHeader(
                eyebrow: 'Sécurité',
                title: _isConfirming ? widget.confirmTitle : widget.title,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    Text(
                      _isConfirming ? widget.confirmSubtitle : widget.subtitle,
                      style: AppTextStyles.lead(koraColors.textMuted),
                    ),
                    PinDots(filled: _pin.length, total: _pinLength),
                    if (_error != null) ...[
                      Center(
                        child: Text(
                          _error!,
                          style: const TextStyle(
                            color: AppColors.danger,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                    PinKeypad(onKey: _press),
                    const SizedBox(height: 18),
                    BlocBuilder<AuthFlowBloc, AuthFlowState>(
                      builder: (context, flow) => Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          KoraButton(
                            label: 'Continuer',
                            isLoading: flow.isSubmitting,
                            onPressed: flow.isSubmitting ? null : _submit,
                          ),
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
                          if (flow.accountExists) ...[
                            const SizedBox(height: 4),
                            Text(
                              context.l10n.signupAccountExistsLead,
                              textAlign: TextAlign.center,
                              style: AppTextStyles.body(
                                context.koraColors.textMuted,
                              ),
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
                              child: Text(context.l10n.signupAccountExistsLink),
                            ),
                          ],
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
