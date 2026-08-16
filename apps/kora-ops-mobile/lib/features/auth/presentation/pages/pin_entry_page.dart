import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/app_text_styles.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/pin_pad.dart';
import '../cubit/login_cubit.dart';

/// Écran de connexion — entrée **unique** de l'app (voir document.md §
/// Flux d'authentification : les agents KORA OPS sont provisionnés par un
/// administrateur, il n'y a pas d'auto-inscription). Fond bleu marine
/// plein écran fidèle à `kora-passager`, indépendant de `ThemeMode` (voir
/// document.md § Dark mode).
class PinEntryPage extends StatefulWidget {
  const PinEntryPage({super.key});

  @override
  State<PinEntryPage> createState() => _PinEntryPageState();
}

class _PinEntryPageState extends State<PinEntryPage> {
  static const int _pinLength = 4;

  String _pin = '';
  String? _savedEmail;

  @override
  void initState() {
    super.initState();
    _loadSavedEmail();
  }

  Future<void> _loadSavedEmail() async {
    final String? email = await context.read<LoginCubit>().loadSavedEmail();
    if (mounted) setState(() => _savedEmail = email);
  }

  void _onKey(String key) {
    switch (key) {
      case PinKeypad.backspace:
        if (_pin.isNotEmpty) {
          setState(() => _pin = _pin.substring(0, _pin.length - 1));
        }
      case PinKeypad.validate:
        if (_pin.length == _pinLength) _submit();
      default:
        if (_pin.length < _pinLength) {
          setState(() => _pin += key);
          if (_pin.length == _pinLength) _submit();
        }
    }
  }

  Future<void> _submit() async {
    final LoginCubit loginCubit = context.read<LoginCubit>();
    final bool success = await loginCubit.submitPin(_pin);
    if (!mounted) return;

    if (success) {
      await context.push(AppRoutes.loginNewDevice);
    } else {
      setState(() => _pin = '');
      final String message = loginCubit.state.errorMessage ?? 'PIN incorrect.';
      await KoraDialogs.error(context,
          title: 'Connexion refusée', description: message);
    }
  }

  @override
  Widget build(BuildContext context) {
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
              child: BlocBuilder<LoginCubit, LoginState>(
                builder: (BuildContext context, LoginState state) {
                  return ListView(
                    padding: const EdgeInsets.symmetric(
                        horizontal: AppSpacing.lg, vertical: AppSpacing.xl),
                    children: <Widget>[
                      const _Logo(),
                      const SizedBox(height: AppSpacing.lg),
                      _Header(email: _savedEmail ?? ''),
                      PinDots(
                          filled: _pin.length, total: _pinLength, onNavy: true),
                      PinKeypad(onKey: _onKey, onNavy: true),
                      const SizedBox(height: AppSpacing.md),
                      Center(
                        child: TextButton(
                          onPressed: () => KoraDialogs.info(
                            context,
                            title: 'PIN oublié ?',
                            description:
                                'Contactez votre superviseur d\'agence pour réinitialiser '
                                'votre PIN KORA.',
                          ),
                          style: TextButton.styleFrom(
                              foregroundColor: AppColors.white),
                          child: const Text('PIN oublié ?'),
                        ),
                      ),
                    ],
                  );
                },
              ),
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
    return Center(
      child: Container(
        width: 150,
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
            color: AppColors.white, borderRadius: BorderRadius.circular(13)),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(AppRadius.sm),
          child: Image.asset('assets/images/kora_logo.png',
              height: 62, fit: BoxFit.cover),
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
      children: <Widget>[
        Text('BON RETOUR PARMI VOUS',
            style:
                AppTextStyles.eyebrow(AppColors.white.withValues(alpha: 0.65))),
        const SizedBox(height: 6),
        const Text(
          'Votre PIN KORA OPS',
          style: TextStyle(
              color: AppColors.white,
              fontSize: 23,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.575),
        ),
        const SizedBox(height: 6),
        Text(email,
            style: TextStyle(
                color: AppColors.white.withValues(alpha: 0.65), fontSize: 13)),
      ],
    );
  }
}
