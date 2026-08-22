import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../motion/app_motion.dart';

/// Interrupteur KORA — `.toggle` de la maquette.
///
/// ```css
/// .toggle { width: 44px; height: 25px; padding: 3px; border-radius: 99px;
///           background: rgba(0,29,78,.15); }
/// .toggle::after { width: 19px; height: 19px; background: white; }
/// .toggle.on { background: var(--kora); }
/// .toggle.on::after { transform: translateX(19px); background: var(--gold); }
/// ```
///
/// Volontairement plus petit que le `Switch` de Material, et sans sa
/// couronne au survol : la maquette pose un rail plein et une pastille
/// qui passe au doré une fois activée.
class KoraToggle extends StatelessWidget {
  const KoraToggle({
    super.key,
    required this.value,
    required this.onChanged,
    this.semanticLabel,
  });

  final bool value;

  /// `null` désactive l'interrupteur — le cas d'une autorisation refusée
  /// définitivement, qui ne se rattrape que dans les réglages du système.
  final ValueChanged<bool>? onChanged;

  /// Lu par les lecteurs d'écran, qui n'ont que le rail à annoncer.
  final String? semanticLabel;

  static const double _width = 44;
  static const double _height = 25;
  static const double _padding = 3;
  static const double _knob = 19;

  /// `transition: .2s` sur `.toggle::after`.
  static const Duration _slide = Duration(milliseconds: 200);

  @override
  Widget build(BuildContext context) {
    final enabled = onChanged != null;
    // Les deux couleurs du rail viennent d'un CSS pensé pour un fond
    // clair : sur une carte navy, `--kora` se confond avec le fond et
    // son translucide disparaît. Le thème sombre reprend donc le bleu
    // digital pour l'état actif, et la couleur du texte pour l'éteint.
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final on = isDark ? AppColors.digital : AppColors.kora;
    final off = isDark
        ? context.koraColors.textPrimary.withValues(alpha: .22)
        : AppColors.kora.withValues(alpha: .15);

    return Semantics(
      label: semanticLabel,
      toggled: value,
      enabled: enabled,
      child: GestureDetector(
        onTap: enabled ? () => onChanged!(!value) : null,
        child: Opacity(
          opacity: enabled ? 1 : .45,
          child: AnimatedContainer(
            duration: _slide,
            curve: AppMotion.ease,
            width: _width,
            height: _height,
            padding: const EdgeInsets.all(_padding),
            decoration: BoxDecoration(
              // `border-radius: 99px` : un rail entièrement arrondi.
              borderRadius: BorderRadius.circular(99),
              color: value ? on : off,
            ),
            child: AnimatedAlign(
              duration: _slide,
              curve: AppMotion.ease,
              alignment: value ? Alignment.centerRight : Alignment.centerLeft,
              child: Container(
                width: _knob,
                height: _knob,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: value ? AppColors.gold : Colors.white,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
