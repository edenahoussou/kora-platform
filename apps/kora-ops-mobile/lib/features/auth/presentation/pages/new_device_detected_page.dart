import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/widgets/app_button.dart';
import '../../../../injection/injection.dart';
import '../cubit/login_cubit.dart';
import '../widgets/auth_screen_header.dart';

/// Écran "Nouvel appareil détecté" — affiché après un PIN valide sur un
/// appareil non reconnu (voir document.md § Flux d'authentification).
/// Route : `/login/new-device`.
class NewDeviceDetectedPage extends StatelessWidget {
  const NewDeviceDetectedPage({super.key});

  Future<void> _requestOtp(BuildContext context) async {
    final bool success =
        await context.read<LoginCubit>().requestDeviceConfirmationOtp();
    if (!context.mounted) return;
    if (success) {
      await context.push(AppRoutes.loginOtp);
    } else {
      final String message =
          context.read<LoginCubit>().state.errorMessage ?? 'Erreur inattendue.';
      await getIt.dialogService.showError(context, message: message);
    }
  }

  Future<void> _rejectDevice(BuildContext context) async {
    final bool confirmed = await getIt.dialogService.showConfirmation(
      context,
      title: 'Ce n\'est pas moi',
      message:
          'Cette tentative de connexion sera bloquée et votre email devra être '
          'reconfirmé pour vous reconnecter.',
      confirmLabel: 'Bloquer la tentative',
      cancelLabel: 'Annuler',
    );
    if (!context.mounted || !confirmed) return;
    context.read<LoginCubit>().reset();
    context.go(AppRoutes.loginEmail);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: Responsive.value<double>(context,
                  mobile: double.infinity, tablet: 480),
            ),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: BlocBuilder<LoginCubit, LoginState>(
                builder: (BuildContext context, LoginState state) {
                  final device = state.deviceInfo;
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      const AuthScreenHeader(
                        eyebrow: 'Protection du compte',
                        title: 'Nouvel appareil détecté',
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      Container(
                        width: double.infinity,
                        padding:
                            const EdgeInsets.symmetric(vertical: AppSpacing.lg),
                        decoration: BoxDecoration(
                          color: AppColors.primary,
                          borderRadius: BorderRadius.circular(AppRadius.lg),
                        ),
                        child: Column(
                          children: <Widget>[
                            Container(
                              padding: const EdgeInsets.all(AppSpacing.sm),
                              decoration: BoxDecoration(
                                color: AppColors.accent,
                                borderRadius:
                                    BorderRadius.circular(AppRadius.md),
                              ),
                              child: const Icon(Icons.pending_actions,
                                  color: AppColors.onAccent),
                            ),
                            const SizedBox(height: AppSpacing.sm),
                            const Text(
                              'Connexion à confirmer',
                              style: TextStyle(
                                color: AppColors.white,
                                fontWeight: FontWeight.w600,
                                fontSize: 12,
                                letterSpacing: 0.4,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      Text('Est-ce bien vous ?',
                          style: Theme.of(context).textTheme.headlineMedium),
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        'Une vérification par email est obligatoire avant d\'autoriser cet '
                        'appareil, même avec le bon PIN.',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: AppSpacing.md,
                          vertical: AppSpacing.xs,
                        ),
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.surface,
                          borderRadius: BorderRadius.circular(AppRadius.lg),
                          border: Border.all(color: AppColors.border),
                        ),
                        child: Column(
                          children: <Widget>[
                            _InfoRow(
                                label: 'Appareil',
                                value: device?.deviceModel ?? '—'),
                            const Divider(height: AppSpacing.lg),
                            _InfoRow(
                              label: 'Tentative',
                              value: device != null
                                  ? '${device.attemptLabel} · ${device.city}'
                                  : '—',
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      AppButton(
                        label: 'Recevoir le code OTP',
                        isLoading: state.status == LoginStatus.submitting,
                        onPressed: () => _requestOtp(context),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      AppButton(
                        label: 'Ce n\'est pas moi',
                        variant: AppButtonVariant.outline,
                        onPressed: () => _rejectDevice(context),
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

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          Text(label, style: Theme.of(context).textTheme.bodyMedium),
          Text(
            value,
            style: Theme.of(context)
                .textTheme
                .bodyMedium
                ?.copyWith(fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }
}
