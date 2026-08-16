import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';

/// `.skeleton` du prototype : un bloc neutre parcouru par un reflet, qui
/// occupe la place de ce qui est en train de charger.
///
/// Sert de brique à tous les squelettes du design system : on lui donne
/// la forme de ce qu'il remplace (une ligne de texte, une pastille, une
/// carte) plutôt qu'une taille générique — un fantôme à la mauvaise forme
/// annonce autre chose que ce qui va s'afficher, et la page sursaute au
/// moment du remplissage.
///
/// Exemple d'utilisation :
/// ```dart
/// const KoraSkeleton(width: 120, height: 12)          // une ligne
/// const KoraSkeleton(height: 86, radius: 18)          // une carte
/// const KoraSkeleton(width: 34, height: 34, radius: 10) // une pastille
/// ```
class KoraSkeleton extends StatefulWidget {
  const KoraSkeleton({
    super.key,
    this.width,
    this.height = 12,
    this.radius = 8,
    this.onWhite = false,
  });

  /// Largeur du bloc. `null` = toute la largeur disponible.
  final double? width;

  final double height;
  final double radius;

  /// À poser sur une surface toujours blanche quel que soit le thème
  /// (l'intérieur d'une [DepartureCard], par exemple) : sans ça, le bloc
  /// s'éclaircit en thème sombre et disparaît sur du blanc.
  final bool onWhite;

  @override
  State<KoraSkeleton> createState() => _KoraSkeletonState();
}

class _KoraSkeletonState extends State<KoraSkeleton>
    with SingleTickerProviderStateMixin {
  // `animation: shimmer 1.4s infinite`
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1400),
  )..repeat();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // `.skeleton { background: rgba(0,29,78,.06) }` — en thème sombre, du
    // navy translucide sur du navy ne se verrait pas : on éclaircit.
    final base = widget.onWhite
        ? AppColors.kora.withValues(alpha: 0.06)
        : context.koraColors.textPrimary.withValues(alpha: 0.06);

    return ClipRRect(
      borderRadius: BorderRadius.circular(widget.radius),
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) {
          return ShaderMask(
            blendMode: BlendMode.srcATop,
            shaderCallback: (rect) {
              // `linear-gradient(90deg, transparent, rgba(255,255,255,.8),
              // transparent)` qui traverse le bloc de gauche à droite.
              final travel = rect.width * (_controller.value * 2 - 1);
              return LinearGradient(
                colors: [
                  Colors.transparent,
                  AppColors.white.withValues(alpha: 0.8),
                  Colors.transparent,
                ],
                transform: _GradientTranslation(travel),
              ).createShader(rect);
            },
            child: Container(
              width: widget.width,
              height: widget.height,
              color: base,
            ),
          );
        },
      ),
    );
  }
}

/// Décale un dégradé horizontalement — le mouvement du reflet.
@immutable
class _GradientTranslation extends GradientTransform {
  const _GradientTranslation(this.dx);

  final double dx;

  @override
  Matrix4 transform(Rect bounds, {TextDirection? textDirection}) {
    return Matrix4.translationValues(dx, 0, 0);
  }
}
