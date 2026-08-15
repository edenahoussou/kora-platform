import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../widgets/pin_pad.dart';

/// Connexion par code PIN — écran `login` de la maquette : écran navy,
/// logo, rappel du compte, quatre pastilles de saisie et un pavé numérique.
///
/// **Aucun PIN n'est vérifié** : la feature `auth` n'existe pas encore
/// (voir GUIDE.md §13). Tout code à 4 chiffres ouvre l'app, ce qui permet
/// de parcourir le tunnel Bus mocké. Le jour où l'authentification arrive,
/// seul [onSuccess] change — la saisie, elle, est déjà en place.
class LoginPage extends StatefulWidget {
  const LoginPage({super.key, this.email = 'aicha.hounkpe@example.com', this.onSuccess});

  /// Compte auquel se rattache le PIN. Codé en dur tant qu'aucune session
  /// n'existe.
  final String email;

  final VoidCallback? onSuccess;

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  static const int _pinLength = 4;

  String _pin = '';
  String? _error;

  void _press(String key) {
    setState(() {
      _error = null;

      switch (key) {
        case PinKeypad.backspace:
          if (_pin.isNotEmpty) _pin = _pin.substring(0, _pin.length - 1);
        case PinKeypad.validate:
          if (_pin.length < _pinLength) {
            _error = 'Saisissez vos $_pinLength chiffres';
          } else {
            widget.onSuccess?.call();
          }
        default:
          if (_pin.length < _pinLength) _pin += key;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      // `{ navy: true }` : l'écran est bleu marine dans les deux thèmes,
      // la barre d'état doit donc toujours s'y afficher en clair.
      sized: false,
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: AppColors.kora,
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // La maquette n'a pas de retour sur cet écran ; empilé
                // au-dessus de la bienvenue, il en faut un — le geste
                // système ne suffit pas sur toutes les plateformes.
                Align(
                  alignment: Alignment.centerLeft,
                  child: IconButton(
                    icon: const Icon(
                      Icons.chevron_left_rounded,
                      color: AppColors.white,
                    ),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ),
                Expanded(
                  child: ListView(
                    padding: EdgeInsets.zero,
                    children: [
                      const SizedBox(height: 20),
                      const Center(child: _Logo()),
                      const SizedBox(height: 32),
                      _Header(email: widget.email),
                      const SizedBox(height: 27),
                      PinDots(
                        filled: _pin.length,
                        total: _pinLength,
                        onNavy: true,
                      ),
                      if (_error != null) ...[
                        Text(
                          _error!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: AppColors.gold,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 12),
                      ],
                      PinKeypad(onKey: _press, onNavy: true),
                      const SizedBox(height: 12),
                      _LinkButton(
                        label: 'PIN oublié ?',
                        onPressed: () => _notAvailableYet(
                          context,
                          'La réinitialisation du PIN arrive avec la '
                          'feature auth.',
                        ),
                      ),
                      const SizedBox(height: 8),
                      _LinkButton(
                        label: 'Connexion sur un nouvel appareil',
                        onPressed: () => _notAvailableYet(
                          context,
                          'La vérification d’un nouvel appareil arrive avec '
                          'la feature auth.',
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
      ),
    );
  }
}

class _Logo extends StatelessWidget {
  const _Logo();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 150,
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(13),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: Image.asset(
          'assets/images/kora_logo.png',
          height: 62,
          fit: BoxFit.cover,
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.email});

  final String email;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          'BON RETOUR PARMI NOUS',
          style: AppTextStyles.eyebrow(
            AppColors.white.withValues(alpha: 0.65),
          ),
        ),
        const SizedBox(height: 6),
        const Text(
          'Votre PIN KORA',
          style: TextStyle(
            color: AppColors.white,
            fontSize: 23,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.575,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          email,
          style: TextStyle(
            color: AppColors.white.withValues(alpha: 0.65),
            fontSize: 13,
          ),
        ),
      ],
    );
  }
}

class _LinkButton extends StatelessWidget {
  const _LinkButton({required this.label, required this.onPressed});

  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return TextButton(
      onPressed: onPressed,
      style: TextButton.styleFrom(foregroundColor: AppColors.white),
      child: Text(label),
    );
  }
}

void _notAvailableYet(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}
