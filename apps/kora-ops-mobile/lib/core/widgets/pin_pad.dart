import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/kora_colors.dart';

/// Une pastille par chiffre, remplie au fur et à mesure de la saisie.
///
/// [onNavy] pour un écran entièrement bleu marine (l'écran PIN de
/// connexion) : les pastilles y sont cerclées de blanc et se remplissent
/// en doré.
class PinDots extends StatelessWidget {
  const PinDots(
      {required this.filled,
      required this.total,
      this.onNavy = false,
      super.key});

  final int filled;
  final int total;
  final bool onNavy;

  @override
  Widget build(BuildContext context) {
    final bool isDark = Theme.of(context).brightness == Brightness.dark;
    final bool onDarkBackground = onNavy || isDark;

    final Color active = onDarkBackground ? AppColors.gold : AppColors.kora;
    final Color empty = onDarkBackground
        ? AppColors.white.withValues(alpha: 0.5)
        : AppColors.kora.withValues(alpha: 0.3);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 27),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          for (int i = 0; i < total; i++) ...<Widget>[
            if (i != 0) const SizedBox(width: 15),
            Container(
              width: 15,
              height: 15,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: i < filled ? active : Colors.transparent,
                border:
                    Border.all(color: i < filled ? active : empty, width: 2),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Les douze touches du pavé PIN (effacement `⌫` et validation `✓`
/// comprises).
class PinKeypad extends StatelessWidget {
  const PinKeypad({required this.onKey, this.onNavy = false, super.key});

  final ValueChanged<String> onKey;

  /// Sur fond navy, les touches restent blanches quel que soit le thème.
  final bool onNavy;

  static const String backspace = '⌫';
  static const String validate = '✓';

  static const List<String> _keys = <String>[
    '1', '2', '3', //
    '4', '5', '6',
    '7', '8', '9',
    backspace, '0', validate,
  ];

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;
    final bool isDark = Theme.of(context).brightness == Brightness.dark;

    final Color background = onNavy ? AppColors.white : koraColors.surface;
    final Color foreground =
        onNavy || !isDark ? AppColors.kora : koraColors.textPrimary;

    return GridView.count(
      crossAxisCount: 3,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      childAspectRatio: 118 / 55,
      children: <Widget>[
        for (final String key in _keys)
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
                      fontWeight: FontWeight.w800),
                ),
              ),
            ),
          ),
      ],
    );
  }
}
