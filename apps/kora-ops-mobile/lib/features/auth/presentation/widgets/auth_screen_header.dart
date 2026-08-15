import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/app_typography.dart';

/// En-tête commun aux écrans d'authentification à fond clair (email, OTP,
/// code invalide, nouvel appareil) : bouton retour circulaire, petit
/// libellé de section en capitales, grand titre — fidèle à la maquette.
class AuthScreenHeader extends StatelessWidget {
  const AuthScreenHeader(
      {required this.eyebrow, required this.title, this.onBack, super.key});

  final String eyebrow;
  final String title;
  final VoidCallback? onBack;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        _BackButton(
            onPressed: onBack ?? () => context.canPop() ? context.pop() : null),
        const SizedBox(height: AppSpacing.md),
        Text(
          eyebrow.toUpperCase(),
          style: AppTypography.caption.copyWith(
            color: AppColors.textSecondary,
            fontWeight: FontWeight.w700,
            letterSpacing: 0.6,
          ),
        ),
        const SizedBox(height: 4),
        Text(title, style: Theme.of(context).textTheme.displayMedium),
      ],
    );
  }
}

class _BackButton extends StatelessWidget {
  const _BackButton({this.onPressed});

  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Theme.of(context).colorScheme.surface,
      shape: const CircleBorder(),
      elevation: 1,
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onPressed,
        child: const Padding(
          padding: EdgeInsets.all(10),
          child: Icon(Icons.chevron_left, color: AppColors.primary),
        ),
      ),
    );
  }
}
