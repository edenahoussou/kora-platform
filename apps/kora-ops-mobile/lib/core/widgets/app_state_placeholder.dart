import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_spacing.dart';
import '../../app/theme/kora_colors.dart';

/// Placeholder générique pour les états "vide" et "erreur" d'un écran.
class AppStatePlaceholder extends StatelessWidget {
  const AppStatePlaceholder.empty({required this.message, super.key})
      : icon = Icons.inbox_outlined,
        isError = false,
        actionLabel = null,
        onAction = null;

  const AppStatePlaceholder.error({
    required this.message,
    this.actionLabel,
    this.onAction,
    super.key,
  })  : icon = Icons.error_outline,
        isError = true;

  final String message;
  final IconData icon;
  final bool isError;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final Color iconColor =
        isError ? AppColors.danger : context.koraColors.textMuted;

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
