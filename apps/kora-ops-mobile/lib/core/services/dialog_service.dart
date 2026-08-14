import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_radius.dart';
import '../../app/theme/app_spacing.dart';

/// Service centralisé pour toutes les alertes/dialogues de l'application.
///
/// Décision documentée (voir document.md § ProDialog) : le pack "ProDialog"
/// demandé dans le cahier des charges n'a pas de version publique vérifiable
/// pour la version Flutter/Dart de ce projet au moment du scaffolding. Pour
/// ne pas bloquer la mise en place de l'architecture, [DialogService]
/// expose déjà l'API cible (`showError`, `showSuccess`, `showConfirmation`,
/// `showInfo`, `showLoading`) et l'implémente pour l'instant avec les
/// dialogues Material stylés selon le Design System KORA.
///
/// Aucun écran ne doit appeler `showDialog()` directement : tout passe par
/// ce service, afin que le remplacement par le vrai package ProDialog se
/// fasse en un seul endroit.
class DialogService {
  Future<void> showSuccess(BuildContext context, {required String message, String? title}) {
    return _showStatusDialog(
      context,
      icon: Icons.check_circle,
      iconColor: AppColors.success,
      title: title ?? 'Succès',
      message: message,
    );
  }

  Future<void> showError(BuildContext context, {required String message, String? title}) {
    return _showStatusDialog(
      context,
      icon: Icons.error,
      iconColor: AppColors.error,
      title: title ?? 'Erreur',
      message: message,
    );
  }

  Future<void> showInfo(BuildContext context, {required String message, String? title}) {
    return _showStatusDialog(
      context,
      icon: Icons.info,
      iconColor: AppColors.info,
      title: title ?? 'Information',
      message: message,
    );
  }

  Future<bool> showConfirmation(
    BuildContext context, {
    required String message,
    String title = 'Confirmer',
    String confirmLabel = 'Confirmer',
    String cancelLabel = 'Annuler',
  }) async {
    final bool? result = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.lg)),
        title: Text(title),
        content: Text(message),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(cancelLabel),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(confirmLabel),
          ),
        ],
      ),
    );
    return result ?? false;
  }

  /// Affiche un dialogue de chargement bloquant. Doit être fermé avec
  /// `Navigator.pop(context)` une fois l'opération terminée.
  void showLoading(BuildContext context, {String? message}) {
    showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext context) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.lg)),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              const CircularProgressIndicator(color: AppColors.primary),
              const SizedBox(width: AppSpacing.md),
              Flexible(child: Text(message ?? 'Chargement…')),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showStatusDialog(
    BuildContext context, {
    required IconData icon,
    required Color iconColor,
    required String title,
    required String message,
  }) {
    return showDialog<void>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.lg)),
        title: Row(
          children: <Widget>[
            Icon(icon, color: iconColor),
            const SizedBox(width: AppSpacing.sm),
            Text(title),
          ],
        ),
        content: Text(message),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }
}
