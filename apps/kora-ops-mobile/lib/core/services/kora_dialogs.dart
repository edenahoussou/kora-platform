// ignore_for_file: inference_failure_on_function_invocation

import 'dart:async';
import 'package:flutter/material.dart';
import 'package:kora_ops_mobile/app/theme/app_colors.dart';
import 'package:pro_dialog/pro_dialog.dart';
import '../../app/theme/app_radius.dart';
import '../../app/theme/kora_colors.dart';

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

  /// Couleur du bouton principal (action confirmée) — toujours doré, comme
  /// `KoraButton.primary` partout ailleurs dans l'app (jamais marine :
  /// c'est la couleur d'action KORA, indépendante du thème).
  static const Color _primaryColor = AppColors.gold;

  /// Couleur d'un bouton à fond transparent (bordure/texte seulement,
  /// ex. "Annuler") — mêmes couleurs que `KoraButton.ghost` : blanc en
  /// sombre, marine en clair. Le marine fixe, utilisé ici auparavant,
  /// était quasi identique au fond des dialogues en mode sombre
  /// (`KoraColors.dark.surface`), rendant bordure et texte pratiquement
  /// invisibles (échange du 24/09/2026).
  static Color _outlineColor(BuildContext context) =>
      Theme.of(context).brightness == Brightness.dark
          ? AppColors.white
          : AppColors.kora;

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
            color: _outlineColor(context)),
        DialogButton(
          text: confirmLabel,
          isPrimary: true,
          onPressed: () {
            if (!completer.isCompleted) completer.complete(true);
            closeDialog();
          },
          color: _primaryColor,
        ),
      ],
    );

    if (!completer.isCompleted) completer.complete(false);
    return completer.future;
  }

  /// Affiche le chargement de l'application : exactement celui de l'écran
  /// de connexion (`PinEntryPage`) — le même `CircularProgressIndicator`
  /// doré, centré, sans texte, sur un voile plein écran (couleur de fond du mode
  /// clair / sombre, 85 % d'opacité) pour rester bien visible. Il remplace l'ancien chargement
  /// `pro_dialog`. Les touches sont bloquées (barrière transparente, retour
  /// système inactif) tant que [hideLoading] n'a pas été appelé : **toujours**
  /// en paire avec [hideLoading] une fois l'opération terminée.
  ///
  /// [message] n'est plus affiché (le chargement de la connexion n'a pas de
  /// texte) ; le paramètre est conservé pour ne pas toucher aux appelants.
  static void showLoading(BuildContext context,
      {String message = 'Chargement…'}) {
    showGeneralDialog<void>(
      context: context,
      barrierDismissible: false,
      barrierColor: Colors.transparent,
      transitionDuration: Duration.zero,
      pageBuilder: (BuildContext dialogContext, Animation<double> _,
              Animation<double> __) =>
          PopScope(
        canPop: false,
        // Voile plein écran à la couleur de fond du mode actif (clair /
        // sombre) à 85 % d'opacité : l'écran derrière reste à peine
        // perceptible et le spinner doré bien visible. Il absorbe aussi
        // les touches.
        child: SizedBox.expand(
          child: ColoredBox(
            color: dialogContext.koraColors.background.withValues(alpha: 0.85),
            child: const Center(
              child: CircularProgressIndicator(color: AppColors.gold),
            ),
          ),
        ),
      ),
    );
  }

  static void hideLoading(BuildContext context) {
    if (Navigator.of(context, rootNavigator: true).canPop()) {
      Navigator.of(context, rootNavigator: true).pop();
    }
  }

  /// Demande une courte saisie texte obligatoire (ex. motif d'annulation
  /// d'embarquement, MEZ-87) — retourne le texte saisi, ou `null` si
  /// annulé. `pro_dialog` n'offre pas de variante avec champ de saisie ;
  /// ce dialogue reste néanmoins le seul point d'entrée pour ce besoin
  /// (voir docstring de la classe), stylé pour rester cohérent (mêmes
  /// rayons, mêmes couleurs) même s'il n'est pas construit sur pro_dialog.
  static Future<String?> prompt(
    BuildContext context, {
    required String title,
    String? description,
    String confirmLabel = 'Confirmer',
    String cancelLabel = 'Annuler',
    int minLength = 1,
    String? hintText,
  }) async {
    final TextEditingController controller = TextEditingController();
    final ValueNotifier<bool> isValid = ValueNotifier<bool>(false);
    controller.addListener(
      () => isValid.value = controller.text.trim().length >= minLength,
    );

    final String? result = await showDialog<String>(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) => AlertDialog(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        title: Text(title),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            if (description != null) ...<Widget>[
              Text(description),
              const SizedBox(height: 12),
            ],
            TextField(
              controller: controller,
              autofocus: true,
              minLines: 2,
              maxLines: 4,
              decoration: InputDecoration(hintText: hintText),
            ),
          ],
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: Text(cancelLabel),
          ),
          ValueListenableBuilder<bool>(
            valueListenable: isValid,
            builder: (context, valid, _) => FilledButton(
              style: FilledButton.styleFrom(
                backgroundColor: _primaryColor,
                foregroundColor: AppColors.onGold,
                disabledBackgroundColor: _primaryColor.withValues(alpha: 0.5),
                disabledForegroundColor:
                    AppColors.onGold.withValues(alpha: 0.5),
              ),
              onPressed: valid
                  ? () =>
                      Navigator.of(dialogContext).pop(controller.text.trim())
                  : null,
              child: Text(confirmLabel),
            ),
          ),
        ],
      ),
    );

    controller.dispose();
    isValid.dispose();
    return result;
  }
}
