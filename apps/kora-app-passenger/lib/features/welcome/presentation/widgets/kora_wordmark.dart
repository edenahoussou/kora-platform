import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

/// Le dégradé de la scène KORA, repris tel quel de la maquette
/// (`.welcome-scene`).
///
/// Partagé par le lancement et l'onboarding : trois écrans qui se suivent
/// à la première ouverture doivent avoir exactement le même fond, sinon la
/// transition se voit.
const LinearGradient kKoraSceneGradient = LinearGradient(
  begin: Alignment.topLeft,
  end: Alignment.bottomRight,
  colors: [Color(0xFF00153B), AppColors.kora, Color(0xFF00306E)],
  stops: [0, 0.64, 1],
);

enum KoraWordmarkSize { small, large }

/// Le bloc de marque : le « K » doré et le nom.
///
/// Dessiné plutôt qu'imagé : `assets/kora-logo.png` est un logo sur fond
/// clair, illisible sur le navy de ces écrans. Le jour où une version
/// blanche existe, c'est ce widget seul qui change.
class KoraWordmark extends StatelessWidget {
  const KoraWordmark({super.key, this.size = KoraWordmarkSize.small});

  final KoraWordmarkSize size;

  bool get _isLarge => size == KoraWordmarkSize.large;

  @override
  Widget build(BuildContext context) {
    final side = _isLarge ? 54.0 : 34.0;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: side,
          height: side,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.gold,
            borderRadius: BorderRadius.circular(_isLarge ? 16 : 10),
            boxShadow: _isLarge
                ? [
                    BoxShadow(
                      color: AppColors.gold.withValues(alpha: 0.34),
                      blurRadius: 30,
                      offset: const Offset(0, 12),
                    ),
                  ]
                : null,
          ),
          child: Text(
            'K',
            style: TextStyle(
              color: AppColors.kora,
              fontSize: _isLarge ? 29 : 19,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        const SizedBox(width: 9),
        Text(
          'KORA EXPRESS',
          style: TextStyle(
            color: AppColors.white,
            fontSize: _isLarge ? 21 : 15,
            fontWeight: FontWeight.w900,
            letterSpacing: 1.1,
          ),
        ),
      ],
    );
  }
}
