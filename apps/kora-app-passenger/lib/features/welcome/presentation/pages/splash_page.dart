import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../widgets/kora_wordmark.dart';

/// L'écran de lancement : le bus tourne autour du logo le temps que la
/// session se décide.
///
/// **Il n'annonce aucune durée.** Ni barre de progression, ni pourcentage :
/// l'attente dépend du stockage sécurisé puis du réseau, et une jauge qui
/// stagne à 80 % inquiète plus qu'un mouvement continu. L'orbite tourne
/// tant qu'il faut.
///
/// C'est le même vocabulaire que l'écran de bienvenue — cercles filaires à
/// 10 % de blanc, dégradé navy à 155°, pastille dorée — pour que la
/// première image de l'app soit déjà la sienne.
class SplashPage extends StatefulWidget {
  const SplashPage({super.key});

  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage>
    with SingleTickerProviderStateMixin {
  /// Un tour toutes les 3,4 s : assez lent pour rester calme, assez rapide
  /// pour qu'on voie que l'app travaille.
  late final AnimationController _orbit = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 3400),
  )..repeat();

  @override
  void dispose() {
    _orbit.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // `SizedBox.expand` : sans lui la pile se dimensionne sur son plus
      // grand enfant — l'anneau de 330 — et le dégradé s'arrête là, laissant
      // le reste de l'écran au fond du thème.
      body: SizedBox.expand(
        child: DecoratedBox(
          decoration: const BoxDecoration(gradient: kKoraSceneGradient),
          child: SafeArea(
            child: Stack(
              alignment: Alignment.center,
              children: [
                // Les deux anneaux, l'extérieur plus discret : ils donnent
                // sa profondeur à la scène sans concurrencer le logo.
                const _Ring(size: 330, opacity: 0.06),
                const _Ring(size: 230, opacity: 0.10),
                const _Star(dx: -74, dy: -46),
                const _Star(dx: 88, dy: 12, gold: true),
                const _Star(dx: -52, dy: 96),
                RotationTransition(
                  turns: _orbit,
                  child: const SizedBox(
                    width: 230,
                    height: 230,
                    child: Align(
                      alignment: Alignment.topCenter,
                      child: _OrbitingBus(),
                    ),
                  ),
                ),
                // `FittedBox` : à 200 % de taille de police système, le
                // bloc de marque déborderait sur un écran étroit. Il
                // rétrécit plutôt que de se faire couper.
                const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 24),
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: KoraWordmark(size: KoraWordmarkSize.large),
                  ),
                ),
                Positioned(
                  bottom: 44,
                  left: 0,
                  right: 0,
                  child: Text(
                    context.l10n.splashLead,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: AppColors.white.withValues(alpha: 0.5),
                      fontSize: 11,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Un anneau filaire, centré sur le logo.
class _Ring extends StatelessWidget {
  const _Ring({required this.size, required this.opacity});

  final double size;
  final double opacity;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(
            color: AppColors.white.withValues(alpha: opacity),
            width: 2,
          ),
        ),
      ),
    );
  }
}

/// Le bus qui parcourt l'orbite intérieure.
class _OrbitingBus extends StatelessWidget {
  const _OrbitingBus();

  @override
  Widget build(BuildContext context) {
    return Transform.translate(
      offset: const Offset(0, -11),
      child: Container(
        width: 22,
        height: 22,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: AppColors.gold,
          borderRadius: BorderRadius.circular(7),
        ),
        child: const Text(
          'K',
          style: TextStyle(
            color: AppColors.kora,
            fontSize: 11,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    );
  }
}

/// Les points épars de l'écran de bienvenue.
class _Star extends StatelessWidget {
  const _Star({required this.dx, required this.dy, this.gold = false});

  final double dx;
  final double dy;
  final bool gold;

  @override
  Widget build(BuildContext context) {
    return Transform.translate(
      offset: Offset(dx, dy),
      child: Container(
        width: 7,
        height: 7,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: gold ? AppColors.gold : AppColors.white.withValues(alpha: 0.5),
        ),
      ),
    );
  }
}
