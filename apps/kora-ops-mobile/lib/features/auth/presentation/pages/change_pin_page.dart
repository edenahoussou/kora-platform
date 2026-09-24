import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';

import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/app_text_styles.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/result/result.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/pin_pad.dart';
import '../../../../injection/injection.dart';
import '../../domain/repositories/auth_repository.dart';

enum _ChangePinStep { current, chosen, confirm }

/// "Modifier mon PIN" (Réglages → Sécurité) — saisie en trois temps (PIN
/// actuel, nouveau PIN, confirmation) puis `POST /auth/pin/change`
/// (`AuthRepository.changePin`, déjà câblé côté backend — voir
/// `ChangePinRequest`). Pas d'OTP ici (contrairement au PIN oublié,
/// `NewPinPage`) : connaître le PIN actuel suffit, le serveur le
/// revérifie de toute façon (`AuthSessionService::changePin`).
class ChangePinPage extends StatefulWidget {
  const ChangePinPage({super.key});

  @override
  State<ChangePinPage> createState() => _ChangePinPageState();
}

class _ChangePinPageState extends State<ChangePinPage> {
  static const int _pinLength = 4;

  _ChangePinStep _step = _ChangePinStep.current;
  String _currentPin = '';
  String _newPin = '';
  String _confirmPin = '';
  bool _isSubmitting = false;

  String get _activeValue => switch (_step) {
        _ChangePinStep.current => _currentPin,
        _ChangePinStep.chosen => _newPin,
        _ChangePinStep.confirm => _confirmPin,
      };

  void _onKey(String key) {
    if (_isSubmitting) return;

    switch (key) {
      case PinKeypad.backspace:
        if (_activeValue.isNotEmpty) {
          setState(() => _setActiveValue(
              _activeValue.substring(0, _activeValue.length - 1)));
        }
      case PinKeypad.validate:
        if (_activeValue.length == _pinLength) _advance();
      default:
        if (_activeValue.length < _pinLength) {
          final String next = _activeValue + key;
          setState(() => _setActiveValue(next));
          if (next.length == _pinLength) _advance();
        }
    }
  }

  void _setActiveValue(String value) {
    switch (_step) {
      case _ChangePinStep.current:
        _currentPin = value;
      case _ChangePinStep.chosen:
        _newPin = value;
      case _ChangePinStep.confirm:
        _confirmPin = value;
    }
  }

  Future<void> _advance() async {
    switch (_step) {
      case _ChangePinStep.current:
        setState(() => _step = _ChangePinStep.chosen);
      case _ChangePinStep.chosen:
        if (_newPin == _currentPin) {
          await KoraDialogs.error(
            context,
            title: 'PIN identique',
            description: 'Choisissez un PIN différent de l\'actuel.',
          );
          if (mounted) setState(() => _newPin = '');
          return;
        }
        setState(() => _step = _ChangePinStep.confirm);
      case _ChangePinStep.confirm:
        if (_confirmPin != _newPin) {
          await KoraDialogs.error(
            context,
            title: 'Les codes ne correspondent pas',
            description: 'Ressaisissez votre nouveau PIN.',
          );
          if (mounted) {
            setState(() {
              _newPin = '';
              _confirmPin = '';
              _step = _ChangePinStep.chosen;
            });
          }
          return;
        }
        await _submit();
    }
  }

  Future<void> _submit() async {
    setState(() => _isSubmitting = true);

    final Result<void> result = await getIt<AuthRepository>().changePin(
      currentPin: _currentPin,
      newPin: _newPin,
    );

    if (!mounted) return;
    setState(() => _isSubmitting = false);

    await result.when(
      onSuccess: (_) async {
        await KoraDialogs.success(
          context,
          title: 'PIN modifié',
          description: 'Votre nouveau PIN est actif dès maintenant.',
        );
        if (mounted) context.pop();
      },
      onFailure: (Failure failure) async {
        await KoraDialogs.error(
          context,
          title: 'Échec de la modification',
          description: failure.message,
        );
        // Le PIN actuel est peut-être erroné — on fait tout ressaisir
        // plutôt que de deviner quelle étape a échoué.
        if (mounted) {
          setState(() {
            _currentPin = '';
            _newPin = '';
            _confirmPin = '';
            _step = _ChangePinStep.current;
          });
        }
      },
    );
  }

  (String eyebrow, String title) get _copy => switch (_step) {
        _ChangePinStep.current => (
            'SÉCURITÉ',
            'Saisissez votre PIN actuel',
          ),
        _ChangePinStep.chosen => (
            'NOUVEAU PIN',
            'Choisissez un code à 4 chiffres',
          ),
        _ChangePinStep.confirm => (
            'CONFIRMEZ',
            'Ressaisissez le même code',
          ),
      };

  @override
  Widget build(BuildContext context) {
    final (String eyebrow, String title) = _copy;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      sized: false,
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: AppColors.kora,
        body: SafeArea(
          child: Center(
            child: ConstrainedBox(
              constraints: BoxConstraints(
                maxWidth: Responsive.value<double>(context,
                    mobile: double.infinity, tablet: 420),
              ),
              child: Stack(
                children: <Widget>[
                  ListView(
                    padding: const EdgeInsets.symmetric(
                        horizontal: AppSpacing.lg, vertical: AppSpacing.xl),
                    children: <Widget>[
                      IconButton(
                        onPressed: _isSubmitting ? null : () => context.pop(),
                        icon: const Icon(Icons.arrow_back,
                            color: AppColors.white),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Text(
                        eyebrow,
                        style: AppTextStyles.eyebrow(
                            AppColors.white.withValues(alpha: 0.65)),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 6),
                      Text(
                        title,
                        style: const TextStyle(
                            color: AppColors.white,
                            fontSize: 20,
                            fontWeight: FontWeight.w800),
                        textAlign: TextAlign.center,
                      ),
                      PinDots(
                          filled: _activeValue.length,
                          total: _pinLength,
                          onNavy: true),
                      IgnorePointer(
                        ignoring: _isSubmitting,
                        child: Opacity(
                          opacity: _isSubmitting ? 0.4 : 1,
                          child: PinKeypad(onKey: _onKey, onNavy: true),
                        ),
                      ),
                    ],
                  ),
                  if (_isSubmitting)
                    const Positioned.fill(
                      child: IgnorePointer(
                        child: Center(
                          child:
                              CircularProgressIndicator(color: AppColors.gold),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
