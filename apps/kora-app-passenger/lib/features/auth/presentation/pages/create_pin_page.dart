import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/widgets/screen_header.dart';
import '../widgets/pin_pad.dart';

/// Choix du code PIN — écran `create-pin` de la maquette : consigne,
/// quatre pastilles et le pavé numérique.
///
/// La maquette construit cet écran et sa confirmation avec la même
/// fonction (`pinScreen`), à un titre et un enchaînement près : ce widget
/// est donc paramétré de la même façon, prêt à servir aussi à l'écran de
/// confirmation.
class CreatePinPage extends StatefulWidget {
  const CreatePinPage({
    super.key,
    this.title = 'Créez votre PIN',
    this.subtitle =
        'Choisissez 4 chiffres faciles à retenir pour vous, difficiles à '
        'deviner pour les autres.',
    this.onSubmit,
  });

  final String title;
  final String subtitle;

  /// Reçoit le code saisi. Injecté par la route pour que l'écran reste
  /// testable sans routeur.
  final ValueChanged<String>? onSubmit;

  @override
  State<CreatePinPage> createState() => _CreatePinPageState();
}

class _CreatePinPageState extends State<CreatePinPage> {
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

    setState(() => _error = null);
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
              ScreenHeader(eyebrow: 'Sécurité', title: widget.title),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    Text(
                      widget.subtitle,
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
                    KoraButton(label: 'Continuer', onPressed: _submit),
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
