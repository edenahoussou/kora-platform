import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/widgets/app_button.dart';
import '../widgets/auth_screen_header.dart';

/// Écran "Code non valide" — état d'erreur dédié affiché après un OTP
/// refusé (fidèle à la maquette : icône, message, deux actions). Route :
/// `/login/otp-invalid`.
class OtpInvalidPage extends StatelessWidget {
  const OtpInvalidPage({super.key});

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
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  const AuthScreenHeader(eyebrow: 'Vérification OTP', title: 'Code non valide'),
                  const SizedBox(height: AppSpacing.xl),
                  Center(
                    child: Container(
                      width: 96,
                      height: 96,
                      decoration: const BoxDecoration(color: AppColors.background, shape: BoxShape.circle),
                      child: Center(
                        child: Container(
                          width: 56,
                          height: 56,
                          decoration: BoxDecoration(
                            color: AppColors.primary,
                            borderRadius: BorderRadius.circular(AppRadius.lg),
                          ),
                          child: const Icon(Icons.priority_high_rounded, color: AppColors.white),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  Text(
                    'Le code est incorrect ou expiré',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineMedium,
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Text(
                    'Un seul code reste actif à la fois. Demandez-en un nouveau puis saisissez '
                    'les six chiffres dans les cinq minutes.',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  AppButton(
                    label: 'Recevoir un nouveau code',
                    onPressed: () => context.pushReplacement(AppRoutes.loginOtp),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  AppButton(
                    label: 'Modifier l\'email',
                    variant: AppButtonVariant.outline,
                    onPressed: () =>
                        context.go(AppRoutes.loginEmail),
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
