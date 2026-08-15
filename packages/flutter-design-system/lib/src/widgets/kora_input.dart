import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';
import '../typography/app_text_styles.dart';

/// Champ de saisie KORA. Équivalent `.field` + `.control` du prototype
/// (label au-dessus, champ à coins arrondis, état focus digital, état
/// erreur). Le style visuel du champ lui-même (fond, bordure, radius)
/// vient de `AppTheme.inputDecorationTheme` — ce widget ajoute juste le
/// label et la gestion de l'erreur/mot de passe par-dessus.
///
/// Exemple d'utilisation :
/// ```dart
/// KoraInput(
///   label: 'Adresse e-mail',
///   hint: 'toi@exemple.com',
///   onChanged: (value) => ...,
/// )
///
/// KoraInput(
///   label: 'Mot de passe',
///   obscureText: true,
///   errorText: 'Minimum 8 caractères',
/// )
/// ```
class KoraInput extends StatefulWidget {
  const KoraInput({
    super.key,
    this.label,
    this.hint,
    this.controller,
    this.onChanged,
    this.errorText,
    this.prefixIcon,
    this.obscureText = false,
    this.enabled = true,
    this.keyboardType,
  });

  final String? label;
  final String? hint;
  final TextEditingController? controller;
  final ValueChanged<String>? onChanged;
  final String? errorText;
  final IconData? prefixIcon;

  /// Affiche un bouton "œil" pour révéler/masquer la saisie — équivalent
  /// `.password-wrap` + `.reveal` du prototype.
  final bool obscureText;
  final bool enabled;
  final TextInputType? keyboardType;

  @override
  State<KoraInput> createState() => _KoraInputState();
}

class _KoraInputState extends State<KoraInput> {
  late bool _obscured = widget.obscureText;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final hasError = widget.errorText != null && widget.errorText!.isNotEmpty;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (widget.label != null) ...[
          // `.field > span { color: var(--kora) }` : le libellé d'un champ
          // est en bleu KORA, pas dans la couleur du texte courant
          // (graphite), qui le rendait presque noir en thème clair. En
          // thème sombre, ce bleu serait invisible : `textPrimary` (blanc)
          // y reste le bon choix.
          Text(
            widget.label!,
            style: TextStyle(
              color: Theme.of(context).brightness == Brightness.dark
                  ? koraColors.textPrimary
                  : AppColors.kora,
              fontSize: 13,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
        ],
        TextField(
          controller: widget.controller,
          onChanged: widget.onChanged,
          obscureText: _obscured,
          enabled: widget.enabled,
          keyboardType: widget.keyboardType,
          style: AppTextStyles.body(koraColors.textPrimary),
          decoration: InputDecoration(
            hintText: widget.hint,
            prefixIcon: widget.prefixIcon == null
                ? null
                : Icon(
                    widget.prefixIcon,
                    size: 19,
                    color: koraColors.textMuted,
                  ),
            suffixIcon: widget.obscureText
                ? IconButton(
                    icon: Icon(
                      _obscured
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                      size: 19,
                      color: koraColors.textMuted,
                    ),
                    onPressed: () => setState(() => _obscured = !_obscured),
                  )
                : null,
            errorText: hasError ? widget.errorText : null,
            enabledBorder: hasError
                ? OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadius.input),
                    borderSide: const BorderSide(
                      color: AppColors.danger,
                      width: 1,
                    ),
                  )
                : null,
          ),
        ),
      ],
    );
  }
}
