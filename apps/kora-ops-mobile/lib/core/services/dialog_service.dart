import 'dart:async';
import 'package:flutter/material.dart';
import 'package:pro_dialog/pro_dialog.dart';
import '../../app/theme/app_radius.dart';

/// Service centralisé pour toutes les alertes/dialogues de l'application,
/// construit sur le package **pro_dialog** (pub.dev/packages/pro_dialog,
/// v1.0.9). Aucun écran ne doit appeler `showDialog()` ni un dialogue
/// natif Flutter directement — tout passe par ce service, pour garder un
/// seul point de configuration visuelle (rayon, animation…) et pouvoir
/// personnaliser le thème KORA en un seul endroit.
///
/// pro_dialog gère nativement le mode sombre (il lit `Theme.of(context)`),
/// aucune adaptation manuelle n'est donc nécessaire ici pour le dark mode.
class DialogService {
  static final ProDialogTheme _theme = const ProDialogTheme(
    borderRadius: AppRadius.lg,
    animationStyle: DialogAnimationStyle.scale,
    iconAnimationStyle: IconAnimationStyle.pulse,
  );

  Future<void> showSuccess(BuildContext context,
      {required String message, String? title}) {
    return showProDialog(
      context,
      type: DialogType.success,
      title: title ?? 'Succès',
      description: message,
      theme: _theme,
    );
  }

  Future<void> showError(BuildContext context,
      {required String message, String? title}) {
    return showProDialog(
      context,
      type: DialogType.error,
      title: title ?? 'Erreur',
      description: message,
      theme: _theme,
    );
  }

  Future<void> showWarning(BuildContext context,
      {required String message, String? title}) {
    return showProDialog(
      context,
      type: DialogType.warning,
      title: title ?? 'Attention',
      description: message,
      theme: _theme,
    );
  }

  Future<void> showInfo(BuildContext context,
      {required String message, String? title}) {
    return showProDialog(
      context,
      type: DialogType.info,
      title: title ?? 'Information',
      description: message,
      theme: _theme,
    );
  }

  /// Dialogue de confirmation (type `question`) — retourne `true` si
  /// l'utilisateur confirme, `false` sinon (annulation ou fermeture hors
  /// dialogue).
  Future<bool> showConfirmation(
    BuildContext context, {
    required String message,
    String title = 'Confirmer',
    String confirmLabel = 'Confirmer',
    String cancelLabel = 'Annuler',
  }) async {
    final Completer<bool> completer = Completer<bool>();

    // ignore: inference_failure_on_function_invocation
    await showProDialog(
      context,
      type: DialogType.question,
      title: title,
      description: message,
      theme: _theme,
      barrierDismissible: false,
      buttons: <DialogButton>[
        DialogButton(
          text: cancelLabel,
          style: DialogButtonStyle.outlined,
          onPressed: () {
            if (!completer.isCompleted) completer.complete(false);
            Navigator.of(context).pop();
          },
        ),
        DialogButton(
          text: confirmLabel,
          isPrimary: true,
          onPressed: () {
            if (!completer.isCompleted) completer.complete(true);
            Navigator.of(context).pop();
          },
        ),
      ],
    );

    if (!completer.isCompleted) completer.complete(false);
    return completer.future;
  }

  /// Affiche un dialogue de chargement bloquant. Doit être fermé avec
  /// `Navigator.pop(context)` une fois l'opération terminée.
  void showLoading(BuildContext context, {String? message}) {
    // ignore: inference_failure_on_function_invocation
    showLoadingDialog(
      context,
      title: message ?? 'Chargement…',
      description: '',
    );
  }
}
