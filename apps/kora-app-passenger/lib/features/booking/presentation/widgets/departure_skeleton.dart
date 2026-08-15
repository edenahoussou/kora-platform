import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

/// `.skeleton` de la maquette : le fantôme d'une carte de départ pendant la
/// recherche — un bloc clair de 86px parcouru par un reflet.
///
/// Volontairement distinct de `KoraLoadingIndicator(skeleton)` du design
/// system, qui esquisse des **lignes de texte** de 12px : ici on annonce
/// des cartes, et une esquisse à la mauvaise forme donne une fausse idée de
/// ce qui va s'afficher.
class DepartureSkeleton extends StatefulWidget {
  const DepartureSkeleton({super.key, this.height = 86});

  final double height;

  @override
  State<DepartureSkeleton> createState() => _DepartureSkeletonState();
}

class _DepartureSkeletonState extends State<DepartureSkeleton>
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
    final koraColors = context.koraColors;

    // `.skeleton { background: rgba(0,29,78,.06) }` — en thème sombre, du
    // navy translucide sur du navy ne se verrait pas : on éclaircit.
    final base = koraColors.textPrimary.withValues(alpha: 0.06);

    return ClipRRect(
      borderRadius: BorderRadius.circular(18),
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
                transform: GradientTranslation(travel),
              ).createShader(rect);
            },
            child: Container(height: widget.height, color: base),
          );
        },
      ),
    );
  }
}

/// Décale un dégradé horizontalement — le mouvement du reflet.
@immutable
class GradientTranslation extends GradientTransform {
  const GradientTranslation(this.dx);

  final double dx;

  @override
  Matrix4 transform(Rect bounds, {TextDirection? textDirection}) {
    return Matrix4.translationValues(dx, 0, 0);
  }
}
