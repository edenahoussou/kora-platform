import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

/// `.pin-dots` : une pastille par chiffre, remplie au fur et à mesure de
/// la saisie.
///
/// [onNavy] pour un écran entièrement bleu marine (la connexion) : les
/// pastilles y sont cerclées de blanc et se remplissent en doré. Sur un
/// écran clair (création du PIN), elles suivent le bleu KORA, comme le
/// prévoit `.navy-screen .pin-dots i` dans la maquette.
class PinDots extends StatelessWidget {
  const PinDots({
    super.key,
    required this.filled,
    required this.total,
    this.onNavy = false,
  });

  final int filled;
  final int total;
  final bool onNavy;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    // Sur fond navy — ou en thème sombre, où le fond d'écran l'est aussi —
    // le bleu KORA ne se verrait pas : le doré prend le relais.
    final onDarkBackground = onNavy || isDark;

    final active = onDarkBackground ? AppColors.gold : AppColors.kora;
    final empty = onDarkBackground
        ? AppColors.white.withValues(alpha: 0.5)
        : AppColors.kora.withValues(alpha: 0.3);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 27),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          for (var i = 0; i < total; i++) ...[
            if (i != 0) const SizedBox(width: 15),
            Container(
              width: 15,
              height: 15,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: i < filled ? active : Colors.transparent,
                border: Border.all(
                  color: i < filled ? active : empty,
                  width: 2,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// `.pin-keypad` : les douze touches, effacement (`⌫`) et validation (`✓`)
/// comprises.
class PinKeypad extends StatelessWidget {
  const PinKeypad({super.key, required this.onKey, this.onNavy = false});

  final ValueChanged<String> onKey;

  /// Sur fond navy, les touches restent blanches quel que soit le thème —
  /// c'est le contraste de l'écran de connexion. Ailleurs, elles suivent
  /// la surface du thème.
  final bool onNavy;

  static const backspace = '⌫';
  static const validate = '✓';

  static const _keys = [
    '1', '2', '3', //
    '4', '5', '6',
    '7', '8', '9',
    backspace, '0', validate,
  ];

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    final isDark = Theme.of(context).brightness == Brightness.dark;

    // `.pin-keypad button { color: var(--kora); background: white }` : les
    // chiffres sont en bleu KORA, pas dans la couleur du texte courant. En
    // thème sombre ce bleu serait illisible sur la surface foncée.
    final background = onNavy ? AppColors.white : koraColors.surface;
    final foreground = onNavy || !isDark
        ? AppColors.kora
        : koraColors.textPrimary;

    return GridView.count(
      crossAxisCount: 3,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      // `.pin-keypad button { height: 55px }` — la grille répartit la
      // largeur en trois, on fixe le rapport pour retrouver cette hauteur.
      childAspectRatio: 118 / 55,
      children: [
        for (final key in _keys)
          Material(
            color: background,
            borderRadius: BorderRadius.circular(17),
            child: InkWell(
              onTap: () => onKey(key),
              borderRadius: BorderRadius.circular(17),
              child: Center(
                child: Text(
                  key,
                  style: TextStyle(
                    color: foreground,
                    fontSize: 19,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}
