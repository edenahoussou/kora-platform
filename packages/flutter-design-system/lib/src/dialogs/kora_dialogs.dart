import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pro_dialog/pro_dialog.dart';

import '../colors/app_colors.dart';

/// Point d'entrée unique pour afficher un dialog dans toute l'application.
/// Utilise le package `pro_dialog` (dialogs animés) mais **aucun écran de
/// l'app n'importe `pro_dialog` directement** : tout passe par
/// [KoraDialogs]. Avantage : si demain on change de librairie de dialogs,
/// on modifie UNIQUEMENT ce fichier.
///
/// Exemple d'utilisation dans un écran :
/// ```dart
/// KoraDialogs.success(context, title: 'Trajet réservé', description: 'À bientôt !');
///
/// KoraDialogs.error(context, title: 'Échec du paiement', description: 'Réessaie plus tard.');
///
/// final confirmed = await KoraDialogs.confirm(
///   context,
///   title: 'Annuler le trajet ?',
///   description: 'Cette action est irréversible.',
/// );
/// if (confirmed) { ... }
///
/// KoraDialogs.showLoading(context);
/// // ... appel réseau ...
/// KoraDialogs.hideLoading(context);
/// ```
class KoraDialogs {
  const KoraDialogs._();

  /// Thème visuel commun à tous les dialogs KORA.
  static ProDialogTheme get _theme => const ProDialogTheme(
    useGlassmorphism: false,
    useGradientBackground: false,
    borderRadius: 20,
    animationStyle: DialogAnimationStyle.scale,
    iconAnimationStyle: IconAnimationStyle.bounce,
  );

  static void success(
    BuildContext context, {
    required String title,
    String? description,
    VoidCallback? onDismiss,
  }) {
    showProDialog<void>(
      context,
      type: DialogType.success,
      title: title,
      description: description,
      theme: _theme,
      buttons: [
        DialogButton(
          text: 'OK',
          isPrimary: true,
          onPressed: () {
            Navigator.of(context).pop();
            onDismiss?.call();
          },
        ),
      ],
    );
  }

  static void error(
    BuildContext context, {
    required String title,
    String? description,
    VoidCallback? onDismiss,
  }) {
    showProDialog<void>(
      context,
      type: DialogType.error,
      title: title,
      description: description,
      theme: _theme,
      buttons: [
        DialogButton(
          text: 'Fermer',
          isPrimary: true,
          onPressed: () {
            Navigator.of(context).pop();
            onDismiss?.call();
          },
        ),
      ],
    );
  }

  static void warning(
    BuildContext context, {
    required String title,
    String? description,
    VoidCallback? onDismiss,
  }) {
    showProDialog<void>(
      context,
      type: DialogType.warning,
      title: title,
      description: description,
      theme: _theme,
      buttons: [
        DialogButton(
          text: "J'ai compris",
          isPrimary: true,
          onPressed: () {
            Navigator.of(context).pop();
            onDismiss?.call();
          },
        ),
      ],
    );
  }

  /// Affiche un dialog de confirmation à deux boutons et attend le choix
  /// de l'utilisateur. Retourne `true` si confirmé, `false` sinon (y
  /// compris si l'utilisateur ferme le dialog sans choisir).
  static Future<bool> confirm(
    BuildContext context, {
    required String title,
    String? description,
    String confirmLabel = 'Confirmer',
    String cancelLabel = 'Annuler',
  }) async {
    final completer = Completer<bool>();

    // Le Future retourné par showProDialog se résout à la fermeture du
    // dialog (peu importe le bouton) : ce n'est pas lui qui porte le choix
    // de l'utilisateur (c'est `completer`, complété dans les `onPressed`
    // ci-dessous), donc on l'ignore volontairement plutôt que de l'attendre.
    unawaited(
      showProDialog<void>(
        context,
        type: DialogType.question,
        title: title,
        description: description,
        theme: _theme,
        buttons: [
          DialogButton(
            text: cancelLabel,
            isPrimary: false,
            onPressed: () {
              Navigator.of(context).pop();
              if (!completer.isCompleted) completer.complete(false);
            },
          ),
          DialogButton(
            text: confirmLabel,
            isPrimary: true,
            onPressed: () {
              Navigator.of(context).pop();
              if (!completer.isCompleted) completer.complete(true);
            },
          ),
        ],
      ),
    );

    return completer.future;
  }

  /// Affiche un dialog de chargement bloquant (ex: pendant un appel
  /// réseau). Toujours appairer avec [hideLoading].
  static void showLoading(BuildContext context, {String? message}) {
    showProDialog<void>(
      context,
      customContent: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircularProgressIndicator(color: AppColors.gold),
          if (message != null) ...[
            const SizedBox(height: 16),
            Text(message, textAlign: TextAlign.center),
          ],
        ],
      ),
    );
  }

  /// Ferme le dialog de chargement ouvert par [showLoading].
  static void hideLoading(BuildContext context) {
    Navigator.of(context, rootNavigator: true).pop();
  }
}
