import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/app_text_styles.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/pin_pad.dart';
import '../bloc/auth_flow_bloc.dart';

/// Écran "Choisissez un nouveau PIN" — dernière étape du parcours PIN
/// oublié (email → OTP → ce choix), atteint après vérification du code.
/// Saisie en deux temps (PIN puis confirmation) : la comparaison a lieu
/// ici, avant l'appel réseau, plutôt que de compter sur la validation
/// serveur pour un aller-retour évitable.
class NewPinPage extends StatefulWidget {
  const NewPinPage({super.key});

  @override
  State<NewPinPage> createState() => _NewPinPageState();
}

class _NewPinPageState extends State<NewPinPage> {
  static const int _pinLength = 4;

  String _pin = '';
  String _confirmPin = '';
  bool _isConfirming = false;

  void _onKey(String key) {
    final String current = _isConfirming ? _confirmPin : _pin;

    switch (key) {
      case PinKeypad.backspace:
        if (current.isNotEmpty) {
          setState(() {
            if (_isConfirming) {
              _confirmPin = _confirmPin.substring(0, _confirmPin.length - 1);
            } else {
              _pin = _pin.substring(0, _pin.length - 1);
            }
          });
        }
      case PinKeypad.validate:
        if (current.length == _pinLength) _advance();
      default:
        if (current.length < _pinLength) {
          setState(() {
            if (_isConfirming) {
              _confirmPin += key;
            } else {
              _pin += key;
            }
          });
          if (current.length + 1 == _pinLength) _advance();
        }
    }
  }

  void _advance() {
    if (!_isConfirming) {
      setState(() => _isConfirming = true);
      return;
    }

    if (_pin != _confirmPin) {
      KoraDialogs.error(
        context,
        title: 'Les codes ne correspondent pas',
        description: 'Saisissez à nouveau votre nouveau PIN.',
      );
      setState(() {
        _pin = '';
        _confirmPin = '';
        _isConfirming = false;
      });
      return;
    }

    _submit();
  }

  Future<void> _submit() async {
    final AuthFlowBloc flowBloc = context.read<AuthFlowBloc>();
    flowBloc.add(AuthFlowNewPinSubmitted(_pin));
    final AuthFlowState next = await flowBloc.stream
        .firstWhere((AuthFlowState state) => !state.isSubmitting);

    if (!mounted) return;

    if (next.hasError) {
      setState(() {
        _pin = '';
        _confirmPin = '';
        _isConfirming = false;
      });
      await KoraDialogs.error(
        context,
        title: 'Échec de la réinitialisation',
        description: next.errorMessage,
      );
      return;
    }

    await KoraDialogs.success(
      context,
      title: 'PIN mis à jour',
      description: 'Connectez-vous avec votre nouveau PIN.',
    );
    if (mounted) context.go(AppRoutes.loginPin);
  }

  @override
  Widget build(BuildContext context) {
    final String current = _isConfirming ? _confirmPin : _pin;

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
              child: ListView(
                padding: const EdgeInsets.symmetric(
                    horizontal: AppSpacing.lg, vertical: AppSpacing.xl),
                children: <Widget>[
                  const SizedBox(height: AppSpacing.lg),
                  Text(
                    _isConfirming ? 'CONFIRMEZ VOTRE PIN' : 'NOUVEAU PIN',
                    style: AppTextStyles.eyebrow(
                        AppColors.white.withValues(alpha: 0.65)),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    _isConfirming
                        ? 'Ressaisissez le même code'
                        : 'Choisissez un code à 4 chiffres',
                    style: const TextStyle(
                        color: AppColors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w800),
                    textAlign: TextAlign.center,
                  ),
                  PinDots(
                      filled: current.length, total: _pinLength, onNavy: true),
                  PinKeypad(onKey: _onKey, onNavy: true),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
