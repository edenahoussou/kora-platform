// ignore_for_file: inference_failure_on_function_invocation

import 'dart:async';
import 'package:flutter/material.dart';
import 'package:kora_ops_mobile/app/theme/app_colors.dart';
import 'package:pro_dialog/pro_dialog.dart';
import '../../app/theme/app_radius.dart';

/// Point d'accès unique à toutes les alertes/dialogues de l'application,
/// construit sur **pro_dialog** (pub.dev/packages/pro_dialog, v1.0.9).
/// Aucun écran n'appelle `showDialog()`, un dialogue natif Flutter, ni un
/// `SnackBar` directement — tout passe par ici, y compris les messages
/// transitoires ("nouveau code envoyé"…) qui utiliseraient un `SnackBar`
/// ailleurs.
abstract final class KoraDialogs {
  static final ProDialogTheme _theme = const ProDialogTheme(
    borderRadius: AppRadius.lg,
    animationStyle: DialogAnimationStyle.scale,
    iconAnimationStyle: IconAnimationStyle.pulse,
  );

  static Future<void> success(BuildContext context,
      {required String title, String? description}) {
    return showProDialog(
      context,
      type: DialogType.success,
      title: title,
      description: description ?? '',
      theme: _theme,
    );
  }

  static Future<void> error(BuildContext context,
      {required String title, String? description}) {
    return showProDialog(
      context,
      type: DialogType.error,
      title: title,
      description: description ?? '',
      theme: _theme,
    );
  }

  static Future<void> warning(BuildContext context,
      {required String title, String? description}) {
    return showProDialog(
      context,
      type: DialogType.warning,
      title: title,
      description: description ?? '',
      theme: _theme,
    );
  }

  static Future<void> info(BuildContext context,
      {required String title, String? description}) {
    return showProDialog(
      context,
      type: DialogType.info,
      title: title,
      description: description ?? '',
      theme: _theme,
    );
  }

  /// Dialogue de confirmation — retourne `true` si l'utilisateur confirme.
  ///
  /// **Bug corrigé le 18/08/2026** : les boutons fermaient le dialogue via
  /// `Navigator.of(context).pop()` (navigateur local), alors que
  /// `showProDialog` pousse sur le navigateur **racine**. Sur un écran de
  /// la bottom nav (ex. `ProfilPage`), ce navigateur local est celui, très
  /// restreint, de la branche `StatefulShellRoute` — le vider provoquait
  /// `'currentConfiguration.isNotEmpty'` (« popped the last page »). On
  /// pop désormais explicitement le navigateur racine, comme le fait déjà
  /// [hideLoading].

  static Future<bool> confirm(
    BuildContext context, {
    required String title,
    String? description,
    String confirmLabel = 'Confirmer',
    String cancelLabel = 'Annuler',
  }) async {
    final Completer<bool> completer = Completer<bool>();

    void closeDialog() {
      final NavigatorState rootNavigator =
          Navigator.of(context, rootNavigator: true);
      if (rootNavigator.canPop()) rootNavigator.pop();
    }

    await showProDialog(
      context,
      type: DialogType.question,
      title: title,
      description: description ?? '',
      theme: _theme,
      barrierDismissible: false,
      iconBackgroundColor: AppColors.kora,
      buttons: <DialogButton>[
        DialogButton(
            text: cancelLabel,
            style: DialogButtonStyle.outlined,
            onPressed: () {
              if (!completer.isCompleted) completer.complete(false);
              closeDialog();
            },
            color: AppColors.kora),
        DialogButton(
          text: confirmLabel,
          isPrimary: true,
          onPressed: () {
            if (!completer.isCompleted) completer.complete(true);
            closeDialog();
          },
          color: AppColors.kora,
        ),
      ],
    );

    if (!completer.isCompleted) completer.complete(false);
    return completer.future;
  }

  /// Affiche un dialogue de chargement bloquant. **Toujours** en paire avec
  /// [hideLoading] une fois l'opération terminée.
  static void showLoading(BuildContext context,
      {String message = 'Chargement…'}) {
    showLoadingDialog(context, title: message, description: '');
  }

  static void hideLoading(BuildContext context) {
    if (Navigator.of(context, rootNavigator: true).canPop()) {
      Navigator.of(context, rootNavigator: true).pop();
    }
  }
}
