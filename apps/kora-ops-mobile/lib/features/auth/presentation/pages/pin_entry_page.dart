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
import '../cubit/auth_cubit.dart';
import '../cubit/login_cubit.dart';

/// Écran PIN — fidèle à `kora-passager`. Deux parcours (voir document.md §
/// Flux d'authentification) :
///   - reconnexion (email déjà activé sur cet appareil) : PIN correct →
///     connecté directement ;
///   - première connexion (venant de [LoginEmailPage]) : PIN correct →
///     envoi d'un OTP d'activation, voir [LoginState.pendingOtpActivation].
/// Fond bleu marine plein écran fixe, indépendant de `ThemeMode` (voir
/// document.md § Dark mode).
class PinEntryPage extends StatefulWidget {
  const PinEntryPage({super.key});

  @override
  State<PinEntryPage> createState() => _PinEntryPageState();
}

class _PinEntryPageState extends State<PinEntryPage> {
  static const int _pinLength = 4;

  String _pin = '';

  @override
  void initState() {
    super.initState();
    // Sans effet si un email est déjà présent dans le state (venant de
    // LoginEmailPage) — voir LoginCubit.loadSavedEmail.
    context.read<LoginCubit>().loadSavedEmail();
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

    if (!success) {
      setState(() => _pin = '');
      final String message = loginCubit.state.errorMessage ?? 'PIN incorrect.';
      await KoraDialogs.error(context,
          title: 'Connexion refusée', description: message);
      return;
    }

    if (loginCubit.state.pendingOtpActivation) {
      final bool otpSent = await loginCubit.requestActivationOtp();
      if (!mounted) return;
      if (otpSent) {
        await context.push(AppRoutes.loginOtp);
      } else {
        setState(() => _pin = '');
        await KoraDialogs.error(
          context,
          title: 'Erreur',
          description: loginCubit.state.errorMessage ?? 'Erreur inattendue.',
        );
      }
    } else {
      final user = loginCubit.state.user;
      if (user != null) context.read<AuthCubit>().authenticate(user);
      if (mounted) context.go(AppRoutes.home);
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
                      _Header(email: state.email ?? ''),
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
    // Logo "clair" (voir échange du 18/08/2026) : pensé pour un fond
    // sombre, plus besoin de carte blanche derrière.
    return const Center(
      child: Image(
        image: AssetImage('assets/images/kora_logo_light.png'),
        width: 220,
        fit: BoxFit.contain,
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
