import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../injection/injection.dart';
import '../cubit/login_cubit.dart';
import '../widgets/numeric_keypad.dart';

/// Écran "Votre PIN KORA" — connexion rapide pour un utilisateur déjà
/// enregistré sur l'appareil, fidèle à la maquette : fond bleu marine
/// plein écran (identité de marque, indépendant du thème clair/sombre de
/// l'app — voir document.md § Dark mode), logo, 4 points de progression,
/// clavier numérique. Route : `/login/pin`.
class PinEntryPage extends StatefulWidget {
  const PinEntryPage({super.key});

  @override
  State<PinEntryPage> createState() => _PinEntryPageState();
}

class _PinEntryPageState extends State<PinEntryPage>
    with SingleTickerProviderStateMixin {
  static const int _pinLength = 4;

  String _pin = '';
  String? _savedEmail;
  late final AnimationController _shakeController;

  @override
  void initState() {
    super.initState();
    _shakeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
    );
    _loadSavedEmail();
  }

  @override
  void dispose() {
    _shakeController.dispose();
    super.dispose();
  }

  Future<void> _loadSavedEmail() async {
    final String? email = await context.read<LoginCubit>().loadSavedEmail();
    if (mounted) setState(() => _savedEmail = email);
  }

  void _onDigit(String digit) {
    if (_pin.length >= _pinLength) return;
    setState(() => _pin += digit);
    if (_pin.length == _pinLength) _submit();
  }

  void _onBackspace() {
    if (_pin.isEmpty) return;
    setState(() => _pin = _pin.substring(0, _pin.length - 1));
  }

  Future<void> _submit() async {
    final LoginCubit loginCubit = context.read<LoginCubit>();
    final bool success = await loginCubit.submitPin(_pin);
    if (!mounted) return;

    if (success) {
      await context.push(AppRoutes.loginNewDevice);
    } else {
      await _shakeController.forward(from: 0);
      setState(() => _pin = '');
      if (mounted) {
        final String message =
            loginCubit.state.errorMessage ?? 'PIN incorrect.';
        await getIt.dialogService.showError(context, message: message);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // Fond marine fixe, quel que soit ThemeMode — voir docstring de la classe.
      backgroundColor: AppColors.primary,
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: Responsive.value<double>(context,
                  mobile: double.infinity, tablet: 420),
            ),
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.lg, vertical: AppSpacing.xl),
              child: Column(
                children: <Widget>[
                  const _KoraLogoCard(),
                  const SizedBox(height: AppSpacing.lg),
                  Text(
                    'BON RETOUR PARMI NOUS',
                    style: TextStyle(
                      color: AppColors.white.withValues(alpha: 0.7),
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 0.8,
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Votre PIN KORA',
                    style: TextStyle(
                        color: AppColors.white,
                        fontSize: 24,
                        fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _savedEmail ?? '',
                    style: TextStyle(
                        color: AppColors.white.withValues(alpha: 0.7),
                        fontSize: 13),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  AnimatedBuilder(
                    animation: _shakeController,
                    builder: (BuildContext context, Widget? child) {
                      final double value = _shakeController.value;
                      final double offset =
                          8 * math.sin(value * math.pi * 6) * (1 - value);
                      return Transform.translate(
                          offset: Offset(offset, 0), child: child);
                    },
                    child: _PinDots(filled: _pin.length, length: _pinLength),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  NumericKeypad(
                      onDigitPressed: _onDigit,
                      onBackspacePressed: _onBackspace),
                  const SizedBox(height: AppSpacing.md),
                  TextButton(
                    onPressed: () {},
                    child: Text(
                      'PIN oublié ?',
                      style: TextStyle(
                          color: AppColors.white.withValues(alpha: 0.85)),
                    ),
                  ),
                  TextButton(
                    onPressed: () {
                      context.read<LoginCubit>().reset();
                      context.go(AppRoutes.loginEmail);
                    },
                    child: Text(
                      'Connexion sur un nouvel appareil',
                      style: TextStyle(
                          color: AppColors.white.withValues(alpha: 0.85)),
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

class _PinDots extends StatelessWidget {
  const _PinDots({required this.filled, required this.length});

  final int filled;
  final int length;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List<Widget>.generate(length, (int index) {
        final bool isFilled = index < filled;
        return Container(
          margin: const EdgeInsets.symmetric(horizontal: 8),
          width: 14,
          height: 14,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: isFilled ? AppColors.accent : Colors.transparent,
            border: Border.all(
                color: AppColors.white.withValues(alpha: 0.6), width: 1.5),
          ),
        );
      }),
    );
  }
}

class _KoraLogoCard extends StatelessWidget {
  const _KoraLogoCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 96,
      height: 120,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(Icons.local_shipping_rounded,
                color: AppColors.primary, size: 32),
            SizedBox(height: 4),
            Text(
              'KORA',
              style: TextStyle(
                  color: AppColors.primary,
                  fontWeight: FontWeight.w800,
                  fontSize: 16),
            ),
            Text(
              'EXPRESS',
              style: TextStyle(
                  color: AppColors.accent,
                  fontWeight: FontWeight.w700,
                  fontSize: 10),
            ),
          ],
        ),
      ),
    );
  }
}
