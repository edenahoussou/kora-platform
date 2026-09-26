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
import '../cubit/auth_cubit.dart';

/// Écran PIN — email + PIN envoyés ensemble à `login` (contrat réel de
/// l'API, voir AuthRepository.login). Deux issues possibles :
///   - connecté directement (appareil déjà reconnu) ;
///   - le serveur répond 202 (appareil inconnu) → `AuthFlowBloc` passe à
///     l'étape OTP, cet écran navigue vers `/login/otp`.
/// Fond bleu marine plein écran fixe, indépendant de `ThemeMode`.
class PinEntryPage extends StatefulWidget {
  const PinEntryPage({super.key});

  @override
  State<PinEntryPage> createState() => _PinEntryPageState();
}

class _PinEntryPageState extends State<PinEntryPage> {
  static const int _pinLength = 4;

  String _pin = '';
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    // Si cet écran est atteint directement (appareil déjà reconnu, sans
    // passer par LoginEmailPage), AuthFlowBloc n'a pas encore l'email —
    // on le reprend de AuthCubit, seule source qui l'a lu du stockage
    // sécurisé à ce stade (voir AuthGuard.redirect).
    final AuthFlowBloc flowBloc = context.read<AuthFlowBloc>();
    if (flowBloc.state.email.isEmpty) {
      final AuthState authState = context.read<AuthCubit>().state;
      final String? savedEmail = switch (authState) {
        AuthUnauthenticated(:final savedEmail) => savedEmail,
        AuthCheckFailed(:final savedEmail) => savedEmail,
        _ => null,
      };
      if (savedEmail != null) {
        flowBloc.add(AuthFlowEmailEntered(savedEmail));
      }
    }
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
    final AuthFlowBloc flowBloc = context.read<AuthFlowBloc>();
    final String email = flowBloc.state.email;

    // Même loader qu'sur ChangePinPage (superposition dans la page, pas
    // un KoraDialogs.showLoading) — échange du 24/09/2026.
    setState(() => _isSubmitting = true);

    flowBloc.add(AuthFlowLoginSubmitted(email: email, pin: _pin));
    final AuthFlowState next = await flowBloc.stream
        .firstWhere((AuthFlowState state) => !state.isSubmitting);

    if (!mounted) return;
    setState(() => _isSubmitting = false);

    if (next.hasError) {
      setState(() => _pin = '');
      if (next.isAccountLocked) {
        await KoraDialogs.error(
          context,
          title: 'Compte verrouillé',
          description: next.errorMessage,
        );
        return;
      }
      await KoraDialogs.error(context,
          title: 'Connexion refusée', description: next.errorMessage);
      return;
    }

    switch (next.step) {
      case AuthFlowStep.code:
        // Appareil inconnu : le serveur a déjà envoyé le code.
        if (mounted) await context.push(AppRoutes.loginOtp);
      case AuthFlowStep.done:
        final session = next.session;
        if (session != null) {
          context.read<AuthCubit>().authenticate(session.user);
        }
        if (mounted) context.go(AppRoutes.home);
      case AuthFlowStep.email:
      case AuthFlowStep.pin:
      case AuthFlowStep.newPin:
        // Ne devrait pas arriver depuis une soumission de PIN réussie —
        // filet de sécurité silencieux plutôt qu'un crash.
        setState(() => _pin = '');
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
              child: BlocBuilder<AuthFlowBloc, AuthFlowState>(
                builder: (BuildContext context, AuthFlowState state) {
                  return Stack(
                    children: <Widget>[
                      ListView(
                        padding: const EdgeInsets.symmetric(
                            horizontal: AppSpacing.lg, vertical: AppSpacing.xl),
                        children: <Widget>[
                          const _Logo(),
                          const SizedBox(height: AppSpacing.lg),
                          _Header(
                            email: state.email,
                            onChangeEmail: _isSubmitting
                                ? null
                                : () => context.canPop()
                                    ? context.pop()
                                    : context.go(AppRoutes.loginEmail),
                          ),
                          IgnorePointer(
                            ignoring: _isSubmitting,
                            child: Opacity(
                              opacity: _isSubmitting ? 0.4 : 1,
                              child: Column(
                                children: <Widget>[
                                  PinDots(
                                      filled: _pin.length,
                                      total: _pinLength,
                                      onNavy: true),
                                  PinKeypad(onKey: _onKey, onNavy: true),
                                ],
                              ),
                            ),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          Center(
                            child: TextButton(
                              onPressed: _isSubmitting
                                  ? null
                                  : () => context
                                      .push(AppRoutes.loginForgotPassword),
                              style: TextButton.styleFrom(
                                  foregroundColor: AppColors.white),
                              child: const Text('PIN oublié ?'),
                            ),
                          ),
                        ],
                      ),
                      if (_isSubmitting)
                        const Positioned.fill(
                          child: IgnorePointer(
                            child: Center(
                              child: CircularProgressIndicator(
                                  color: AppColors.gold),
                            ),
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
  const _Header({required this.email, required this.onChangeEmail});

  final String email;

  /// Retour à la saisie de l'email — `context.pop()` si cet écran a été
  /// atteint depuis `LoginEmailPage` (parcours normal), sinon
  /// `context.go(AppRoutes.loginEmail)` : sur un appareil déjà reconnu,
  /// `AuthGuard` amène directement ici sans passer par cette page — voir
  /// `auth_guard.dart` — rien à dépiler dans ce cas (échange du
  /// 24/09/2026 : impossible de corriger un email mal saisi jusque-là).
  /// `null` pendant une soumission en cours — désactive le lien plutôt
  /// que de permettre de quitter l'écran au milieu d'une connexion.
  final VoidCallback? onChangeEmail;

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
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Flexible(
              child: Text(
                email,
                style: TextStyle(
                    color: AppColors.white.withValues(alpha: 0.65),
                    fontSize: 13),
                overflow: TextOverflow.ellipsis,
              ),
            ),
            const SizedBox(width: 8),
            GestureDetector(
              onTap: onChangeEmail,
              child: const Text(
                'Modifier',
                style: TextStyle(
                  color: AppColors.gold,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  decoration: TextDecoration.underline,
                  decorationColor: AppColors.gold,
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}
