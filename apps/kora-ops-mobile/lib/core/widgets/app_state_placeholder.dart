import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_spacing.dart';

/// Placeholder générique pour les états "vide" et "erreur" d'un écran —
/// utilisé aussi bien dans l'app que dans Widgetbook pour documenter ces
/// deux variantes.
class AppStatePlaceholder extends StatelessWidget {
  const AppStatePlaceholder.empty({required this.message, super.key})
      : icon = Icons.inbox_outlined,
        iconColor = AppColors.textSecondary,
        actionLabel = null,
        onAction = null;

  const AppStatePlaceholder.error({
    required this.message,
    this.actionLabel,
    this.onAction,
    super.key,
  })  : icon = Icons.error_outline,
        iconColor = AppColors.error;

  final String message;
  final IconData icon;
  final Color iconColor;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(icon, size: 48, color: iconColor),
            const SizedBox(height: AppSpacing.md),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            if (actionLabel != null && onAction != null) ...<Widget>[
              const SizedBox(height: AppSpacing.md),
              OutlinedButton(onPressed: onAction, child: Text(actionLabel!)),
            ],
          ],
        ),
      ),
    );
  }
}
