import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/widgets/app_button.dart';
import '../../../../core/widgets/otp_code_input.dart';
import '../cubit/auth_cubit.dart';
import '../cubit/login_cubit.dart';
import '../widgets/auth_screen_header.dart';

/// Écran "Code de vérification" — saisie de l'OTP à 6 chiffres, fidèle à la
/// maquette (timer, renvoi, lien de démonstration vers l'état invalide).
/// Route : `/login/otp`.
class OtpVerificationPage extends StatefulWidget {
  const OtpVerificationPage({super.key});

  @override
  State<OtpVerificationPage> createState() => _OtpVerificationPageState();
}

class _OtpVerificationPageState extends State<OtpVerificationPage> {
  static const int _codeLength = 6;
  static const Duration _codeValidity = Duration(minutes: 5);

  String _code = '';
  Timer? _timer;
  Duration _remaining = _codeValidity;

  @override
  void initState() {
    super.initState();
    _startTimer();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _startTimer() {
    _timer?.cancel();
    setState(() => _remaining = _codeValidity);
    _timer = Timer.periodic(const Duration(seconds: 1), (Timer timer) {
      if (_remaining.inSeconds <= 0) {
        timer.cancel();
        return;
      }
      setState(() => _remaining -= const Duration(seconds: 1));
    });
  }

  String get _formattedRemaining {
    final int minutes = _remaining.inMinutes;
    final int seconds = _remaining.inSeconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
  }

  String _maskedEmail(String? email) {
    if (email == null || !email.contains('@')) return '••••@••••';
    final List<String> parts = email.split('@');
    final String local = parts.first;
    final String masked = local.isEmpty ? '' : '${local[0]}${'*' * (local.length - 1)}';
    return '$masked@${parts.last}';
  }

  Future<void> _verify(BuildContext context) async {
    if (_code.length != _codeLength) return;
    final LoginCubit loginCubit = context.read<LoginCubit>();
    final bool success = await loginCubit.submitOtp(_code);
    if (!context.mounted) return;
    if (success) {
      final user = loginCubit.state.user;
      if (user != null) context.read<AuthCubit>().authenticate(user);
      context.go(AppRoutes.home);
    } else {
      await context.push(AppRoutes.loginOtpInvalid);
    }
  }

  Future<void> _resend(BuildContext context) async {
    await context.read<LoginCubit>().resendOtp();
    if (mounted) _startTimer();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: Responsive.value<double>(context, mobile: double.infinity, tablet: 480),
            ),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: BlocBuilder<LoginCubit, LoginState>(
                builder: (BuildContext context, LoginState state) {
                  final bool isSubmitting = state.status == LoginStatus.submitting;
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      const AuthScreenHeader(eyebrow: 'Sécurité', title: 'Code de vérification'),
                      const SizedBox(height: AppSpacing.md),
                      Text.rich(
                        TextSpan(
                          style: Theme.of(context).textTheme.bodyMedium,
                          children: <InlineSpan>[
                            const TextSpan(text: 'Saisissez le code envoyé à '),
                            TextSpan(
                              text: _maskedEmail(state.email),
                              style: const TextStyle(fontWeight: FontWeight.w700),
                            ),
                            const TextSpan(text: '.'),
                          ],
                        ),
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      OtpCodeInput(
                        length: _codeLength,
                        onChanged: (String value) => setState(() => _code = value),
                        onCompleted: (_) => _verify(context),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: <Widget>[
                          Text(
                            _remaining.inSeconds > 0
                                ? 'Code valable pendant $_formattedRemaining'
                                : 'Code expiré',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                          TextButton(
                            onPressed: isSubmitting ? null : () => _resend(context),
                            child: const Text('Renvoyer'),
                          ),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      AppButton(
                        label: 'Vérifier le code',
                        isLoading: isSubmitting,
                        onPressed: _code.length == _codeLength ? () => _verify(context) : null,
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      AppButton(
                        label: 'Je n\'ai pas reçu le code',
                        variant: AppButtonVariant.outline,
                        onPressed: isSubmitting ? null : () => _resend(context),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Center(
                        child: TextButton(
                          onPressed: () => context.push(AppRoutes.loginOtpInvalid),
                          child: const Text(
                            'Voir l\'état code invalide',
                            style: TextStyle(color: AppColors.secondary),
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

