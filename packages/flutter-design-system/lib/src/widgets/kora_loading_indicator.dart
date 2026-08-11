import 'package:flutter/material.dart';

import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';
import '../motion/app_motion.dart';

/// Les deux façons de montrer un chargement dans le prototype :
/// un spinner (`.spinner`, dialogs de chargement) ou un skeleton animé
/// (`.loader`, listes en cours de chargement — voir `kora-ops-web`).
enum KoraLoadingIndicatorVariant { spinner, skeleton }

/// Indicateur de chargement KORA. La variante [KoraLoadingIndicatorVariant.spinner]
/// est un wrapper direct du `CircularProgressIndicator` Material 3 : sa
/// couleur dorée vient de `ThemeData.progressIndicatorTheme` (voir
/// `AppTheme`), jamais d'une valeur en dur ici.
///
/// Exemple d'utilisation :
/// ```dart
/// const KoraLoadingIndicator() // spinner doré, taille par défaut
///
/// const KoraLoadingIndicator(
///   variant: KoraLoadingIndicatorVariant.skeleton,
///   skeletonLines: 3,
/// )
/// ```
class KoraLoadingIndicator extends StatefulWidget {
  const KoraLoadingIndicator({
    super.key,
    this.variant = KoraLoadingIndicatorVariant.spinner,
    this.size = 28,
    this.skeletonLines = 1,
  });

  final KoraLoadingIndicatorVariant variant;

  /// Diamètre du spinner (ignoré en mode [KoraLoadingIndicatorVariant.skeleton]).
  final double size;

  /// Nombre de barres empilées en mode skeleton — la dernière barre est
  /// toujours plus courte (80%), comme dans le prototype (`.loader`).
  final int skeletonLines;

  @override
  State<KoraLoadingIndicator> createState() => _KoraLoadingIndicatorState();
}

class _KoraLoadingIndicatorState extends State<KoraLoadingIndicator>
    with SingleTickerProviderStateMixin {
  // Créé dans initState() (pas en `late final` paresseux) : seule la
  // variante skeleton en a besoin, et un `late final` initialisé à la
  // première lecture se serait déclenché dans dispose() pour la variante
  // spinner (qui ne lit jamais controller pendant build()) — au moment
  // précis où le widget est démonté, ce qui plante (`vsync: this` cherche
  // un ancêtre déjà désactivé). Le créer conditionnellement ici évite à
  // la fois le crash et un Ticker qui tournerait pour rien en mode spinner.
  AnimationController? _controller;

  @override
  void initState() {
    super.initState();
    if (widget.variant == KoraLoadingIndicatorVariant.skeleton) {
      _controller = AnimationController(
        vsync: this,
        duration: const Duration(milliseconds: 1200),
      )..repeat();
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.variant == KoraLoadingIndicatorVariant.spinner) {
      // Pas de `color:` ici : elle vient de `ThemeData.progressIndicatorTheme`
      // (doré KORA), défini une seule fois dans AppTheme.
      return SizedBox(
        width: widget.size,
        height: widget.size,
        child: const CircularProgressIndicator(strokeWidth: 2.6),
      );
    }

    final koraColors = context.koraColors;
    // Non-null ici : on est forcément dans la branche skeleton, où
    // initState() l'a créé (voir plus haut).
    final controller = _controller!;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(widget.skeletonLines, (index) {
        final isLast = index == widget.skeletonLines - 1;

        return Padding(
          padding: EdgeInsets.only(bottom: isLast ? 0 : AppSpacing.sm),
          child: FractionallySizedBox(
            widthFactor: isLast && widget.skeletonLines > 1 ? 0.8 : 1,
            child: AnimatedBuilder(
              animation: controller,
              builder: (context, _) {
                return ShaderMask(
                  shaderCallback: (rect) => LinearGradient(
                    begin: Alignment.centerLeft,
                    end: Alignment.centerRight,
                    colors: [
                      koraColors.line,
                      koraColors.textMuted.withValues(alpha: 0.35),
                      koraColors.line,
                    ],
                    stops: const [0.25, 0.5, 0.75],
                    transform: _SlidingGradientTransform(controller.value),
                  ).createShader(rect),
                  child: Container(
                    height: 12,
                    decoration: BoxDecoration(
                      color: koraColors.line,
                      borderRadius: BorderRadius.circular(AppRadius.sm),
                    ),
                  ),
                );
              },
            ),
          ),
        );
      }),
    );
  }
}

/// Fait glisser le dégradé du skeleton de gauche à droite en boucle, avec
/// la courbe [AppMotion.ease] (même courbe que le shimmer `--ease` web).
class _SlidingGradientTransform extends GradientTransform {
  const _SlidingGradientTransform(this.progress);

  final double progress;

  @override
  Matrix4 transform(Rect bounds, {TextDirection? textDirection}) {
    final t = AppMotion.ease.transform(progress);
    return Matrix4.translationValues(bounds.width * (t * 2 - 1), 0, 0);
  }
}
