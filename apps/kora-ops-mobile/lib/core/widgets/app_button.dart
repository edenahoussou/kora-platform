import 'package:flutter/material.dart';
import '../../app/theme/app_radius.dart';

enum AppButtonVariant { primary, secondary, outline }

/// Bouton unique de l'application — les écrans ne doivent pas appeler
/// `ElevatedButton`/`OutlinedButton` directement pour rester cohérents avec
/// le Design System KORA.
class AppButton extends StatelessWidget {
  const AppButton({
    required this.label,
    required this.onPressed,
    this.variant = AppButtonVariant.primary,
    this.isLoading = false,
    this.icon,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final AppButtonVariant variant;
  final bool isLoading;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final VoidCallback? handler = isLoading ? null : onPressed;
    final Widget child = isLoading
        ? const SizedBox(
            height: 18,
            width: 18,
            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              if (icon != null) ...<Widget>[Icon(icon, size: 18), const SizedBox(width: 8)],
              Text(label),
            ],
          );

    return switch (variant) {
      AppButtonVariant.primary => ElevatedButton(onPressed: handler, child: child),
      AppButtonVariant.secondary => ElevatedButton(
          style: ElevatedButton.styleFrom(
            backgroundColor: Theme.of(context).colorScheme.secondary,
          ),
          onPressed: handler,
          child: child,
        ),
      AppButtonVariant.outline => OutlinedButton(onPressed: handler, child: child),
    };
  }
}
