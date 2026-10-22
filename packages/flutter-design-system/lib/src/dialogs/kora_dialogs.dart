import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pro_dialog/pro_dialog.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../typography/app_text_styles.dart';

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
    // Pas de pastille derrière l'icône.
    //
    // Le paquet la peint en dégradé entre la couleur qu'on lui donne et
    // une seconde, **en dur dans son code**, propre au type de dialog —
    // violet pour une question. Donner l'or de KORA produisait donc un
    // dégradé or-violet, pire que la couleur d'origine. L'icône seule,
    // à la couleur de la charte, est la seule mise en forme que son API
    // nous laisse maîtriser de bout en bout.
    showIconBackground: false,
  );

  /// Le bouton principal : bleu marine, pas la couleur du paquet.
  ///
  /// `pro_dialog` colore chaque type avec sa propre palette — le type
  /// `question` arrive en violet (#8B5CF6), qui n'appartient à aucune
  /// charte KORA. On impose donc la couleur à chaque bouton plutôt que
  /// de laisser le paquet décider.
  ///
  /// Marine et non or : le paquet écrit le libellé d'un bouton plein en
  /// blanc, en dur. Sur l'or de KORA le texte deviendrait illisible.
  static DialogButton _primary(String text, VoidCallback onPressed) =>
      DialogButton(
        text: text,
        isPrimary: true,
        color: AppColors.kora,
        onPressed: onPressed,
      );

  static void success(
    BuildContext context, {
    required String title,
    String? description,
    VoidCallback? onDismiss,
  }) {
    // Refermer le navigateur racine, celui où le dialog a été poussé
    // (voir [confirm]).
    final navigator = Navigator.of(context, rootNavigator: true);

    showProDialog<void>(
      context,
      type: DialogType.success,
      title: title,
      description: description,
      theme: _theme,
      iconColor: AppColors.success,
      buttons: [
        _primary('OK', () {
          navigator.pop();
          onDismiss?.call();
        }),
      ],
    );
  }

  static void error(
    BuildContext context, {
    required String title,
    String? description,
    VoidCallback? onDismiss,
  }) {
    // Refermer le navigateur racine, celui où le dialog a été poussé
    // (voir [confirm]).
    final navigator = Navigator.of(context, rootNavigator: true);

    showProDialog<void>(
      context,
      type: DialogType.error,
      title: title,
      description: description,
      theme: _theme,
      iconColor: AppColors.danger,
      buttons: [
        _primary('Fermer', () {
          navigator.pop();
          onDismiss?.call();
        }),
      ],
    );
  }

  static void warning(
    BuildContext context, {
    required String title,
    String? description,
    VoidCallback? onDismiss,
  }) {
    // Refermer le navigateur racine, celui où le dialog a été poussé
    // (voir [confirm]).
    final navigator = Navigator.of(context, rootNavigator: true);

    showProDialog<void>(
      context,
      type: DialogType.warning,
      title: title,
      description: description,
      theme: _theme,
      iconColor: AppColors.gold,
      buttons: [
        _primary("J'ai compris", () {
          navigator.pop();
          onDismiss?.call();
        }),
      ],
    );
  }

  /// Affiche un dialog de confirmation à deux boutons et attend le choix
  /// de l'utilisateur. Retourne `true` si confirmé, `false` sinon (y
  /// compris si l'utilisateur ferme le dialog sans choisir).
  ///
  /// **Celui-ci ne passe pas par `pro_dialog`.** Le paquet impose son
  /// en-tête : l'icône y est toujours posée sur un dégradé dont la
  /// seconde couleur est écrite en dur dans son code, par type de dialog
  /// — violet pour une question. Aucun réglage ne la change. Comme c'est
  /// l'écran que les voyageurs voient avant de se déconnecter ou de
  /// révoquer un appareil, il porte les couleurs de KORA, et le reste du
  /// fichier continue de s'appuyer sur le paquet.
  static Future<bool> confirm(
    BuildContext context, {
    required String title,
    String? description,
    String confirmLabel = 'Confirmer',
    String cancelLabel = 'Annuler',
    Axis buttonsAxis = Axis.horizontal,
  }) async {
    final choice = await showDialog<bool>(
      context: context,
      builder: (context) => _ConfirmDialog(
        title: title,
        description: description,
        confirmLabel: confirmLabel,
        cancelLabel: cancelLabel,
        buttonsAxis: buttonsAxis,
      ),
    );

    // Fermé d'un appui à côté ou du bouton retour : rien n'a été choisi,
    // et on ne fait rien.
    return choice ?? false;
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

/// La modale de confirmation aux couleurs de KORA.
class _ConfirmDialog extends StatelessWidget {
  const _ConfirmDialog({
    required this.title,
    required this.description,
    required this.confirmLabel,
    required this.cancelLabel,
    required this.buttonsAxis,
  });

  final String title;
  final String? description;
  final String confirmLabel;
  final String cancelLabel;
  final Axis buttonsAxis;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    // Le marine disparaîtrait sur un fond sombre : le titre et le
    // contour passent au blanc, la pastille garde ses couleurs.
    final accent = isDark ? AppColors.white : AppColors.kora;

    final text = description;

    return Dialog(
      backgroundColor: koraColors.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      insetPadding: const EdgeInsets.symmetric(horizontal: 24),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(24, 32, 24, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const _ConfirmIcon(),
            const SizedBox(height: 20),
            Text(
              title,
              textAlign: TextAlign.center,
              style: AppTextStyles.heading2(accent),
            ),
            if (text != null) ...[
              const SizedBox(height: 8),
              Text(
                text,
                textAlign: TextAlign.center,
                style: AppTextStyles.body(koraColors.textMuted),
              ),
            ],
            const SizedBox(height: 24),
            _ConfirmButtons(
              confirmLabel: confirmLabel,
              cancelLabel: cancelLabel,
              buttonsAxis: buttonsAxis,
              accent: accent,
            ),
          ],
        ),
      ),
    );
  }
}

/// La pastille : l'or derrière, le rond marine qui porte le symbole.
class _ConfirmIcon extends StatelessWidget {
  const _ConfirmIcon();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 84,
      height: 84,
      alignment: Alignment.center,
      decoration: const BoxDecoration(
        color: AppColors.gold,
        shape: BoxShape.circle,
      ),
      child: Container(
        width: 54,
        height: 54,
        alignment: Alignment.center,
        decoration: const BoxDecoration(
          color: AppColors.kora,
          shape: BoxShape.circle,
        ),
        child: const Icon(
          Icons.question_mark_rounded,
          size: 26,
          color: AppColors.gold,
        ),
      ),
    );
  }
}

class _ConfirmButtons extends StatelessWidget {
  const _ConfirmButtons({
    required this.confirmLabel,
    required this.cancelLabel,
    required this.buttonsAxis,
    required this.accent,
  });

  final String confirmLabel;
  final String cancelLabel;
  final Axis buttonsAxis;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    // Le dialog se referme par **son** contexte : c'est la route de la
    // modale qui part, jamais l'écran qui l'a ouverte.
    void close(bool choice) => Navigator.of(context).pop(choice);

    final cancel = OutlinedButton(
      onPressed: () => close(false),
      style: OutlinedButton.styleFrom(
        foregroundColor: accent,
        side: BorderSide(color: accent.withValues(alpha: 0.55), width: 1.5),
        minimumSize: const Size(0, 48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      child: Text(cancelLabel, style: AppTextStyles.button(accent)),
    );

    final confirm = ElevatedButton(
      onPressed: () => close(true),
      style: ElevatedButton.styleFrom(
        backgroundColor: AppColors.kora,
        foregroundColor: AppColors.white,
        elevation: 0,
        minimumSize: const Size(0, 48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      child: Text(confirmLabel, style: AppTextStyles.button(AppColors.white)),
    );

    // Côte à côte, deux libellés un peu longs débordent dès 390 px de
    // large. Les empiler règle la question quelle que soit la langue,
    // mais reste un choix de l'appelant.
    if (buttonsAxis == Axis.vertical) {
      return Column(
        children: [
          SizedBox(width: double.infinity, child: cancel),
          const SizedBox(height: 10),
          SizedBox(width: double.infinity, child: confirm),
        ],
      );
    }

    return Row(
      children: [
        Expanded(child: cancel),
        const SizedBox(width: 10),
        Expanded(child: confirm),
      ],
    );
  }
}
