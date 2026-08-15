import 'dart:math' as math;
import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

/// `.welcome-scene` : la scène illustrée en tête de l'écran de bienvenue —
/// dégradé navy, orbites, trait doré, la route parcourue par le bus, et la
/// pastille de réassurance.
///
/// Couleurs fixes : la scène reste navy quel que soit le thème, comme
/// l'en-tête de l'accueil.
class WelcomeScene extends StatefulWidget {
  const WelcomeScene({super.key, this.height = 388});

  final double height;

  @override
  State<WelcomeScene> createState() => _WelcomeSceneState();
}

class _WelcomeSceneState extends State<WelcomeScene>
    with SingleTickerProviderStateMixin {
  /// `animation: busTravel 4.8s infinite` — le bus parcourt la route en
  /// boucle, de la gauche vers la droite (voir [_Route]).
  late final AnimationController _bus = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 4800),
  );

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();

    // Respecte « réduire les animations » du système : le bus se pose
    // alors au départ de la route, sans boucler.
    if (MediaQuery.disableAnimationsOf(context)) {
      _bus.stop();
    } else if (!_bus.isAnimating) {
      _bus.repeat();
    }
  }

  @override
  void dispose() {
    _bus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final topInset = MediaQuery.paddingOf(context).top;

    return ClipRRect(
      borderRadius: const BorderRadius.vertical(bottom: Radius.circular(54)),
      child: SizedBox(
        height: widget.height,
        child: DecoratedBox(
          // `linear-gradient(155deg, #00153b 0%, var(--kora) 64%, #00306e)`
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF00153B), AppColors.kora, Color(0xFF00306E)],
              stops: [0, 0.64, 1],
            ),
          ),
          child: Stack(
            children: [
              // `radial-gradient(circle at 85% 12%, rgba(0,87,184,.54), …)`
              const Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: RadialGradient(
                      center: Alignment(0.7, -0.76),
                      radius: 0.66,
                      colors: [Color(0x8A0057B8), Colors.transparent],
                    ),
                  ),
                ),
              ),
              // `.welcome-orbit` — deux cercles filaires sortant du cadre.
              const _Orbit(size: 290, right: -82, top: 44),
              const _Orbit(size: 190, right: -32, top: 94),
              // `.welcome-scene::after` — le grand trait doré incliné.
              Positioned(
                right: -135,
                bottom: 50,
                child: Transform.rotate(
                  angle: -24 * math.pi / 180,
                  child: Container(
                    width: 510,
                    height: 7,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Colors.transparent, AppColors.gold],
                        stops: [0, 0.2],
                      ),
                      borderRadius: BorderRadius.circular(AppRadius.pill),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.white.withValues(alpha: 0.13),
                          offset: const Offset(0, -14),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              const _Star(left: 42, top: 145),
              const _Star(right: 48, top: 132),
              const _Star(right: 115, top: 248),
              Positioned(
                left: 37,
                right: 37,
                top: 196,
                height: 57,
                child: _Route(progress: _bus),
              ),
              Positioned(
                left: 20,
                right: 20,
                top: topInset + 13,
                child: const _Brand(),
              ),
              const Positioned(
                left: 0,
                right: 0,
                bottom: 27,
                child: Center(child: _ServicePill()),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.welcome-brand` : le logo et la signature de la marque.
class _Brand extends StatelessWidget {
  const _Brand();

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Container(
          width: 112,
          height: 58,
          padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 7),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(14),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.18),
                offset: const Offset(0, 12),
                blurRadius: 30,
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(AppRadius.sm),
            child: Image.asset(
              'assets/images/kora_logo.png',
              fit: BoxFit.cover,
            ),
          ),
        ),
        Flexible(
          child: Text(
            'VOYAGEZ PLUS LOIN',
            textAlign: TextAlign.right,
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.62),
              fontSize: 9,
              fontWeight: FontWeight.w800,
              letterSpacing: 1.17, // .13em × 9
            ),
          ),
        ),
      ],
    );
  }
}

/// `.welcome-orbit` : un cercle filaire décoratif.
class _Orbit extends StatelessWidget {
  const _Orbit({required this.size, required this.right, required this.top});

  final double size;
  final double right;
  final double top;

  @override
  Widget build(BuildContext context) {
    return Positioned(
      right: right,
      top: top,
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(color: AppColors.white.withValues(alpha: 0.09)),
        ),
      ),
    );
  }
}

/// `.welcome-stars i` : une étoile dorée, cerclée et auréolée.
class _Star extends StatelessWidget {
  const _Star({this.left, this.right, required this.top});

  final double? left;
  final double? right;
  final double top;

  @override
  Widget build(BuildContext context) {
    return Positioned(
      left: left,
      right: right,
      top: top,
      child: Container(
        width: 5,
        height: 5,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(color: AppColors.gold),
          boxShadow: [
            BoxShadow(
              color: AppColors.gold.withValues(alpha: 0.65),
              blurRadius: 12,
            ),
          ],
        ),
      ),
    );
  }
}

/// `.welcome-route` : les deux arrêts, la route, et le bus qui la parcourt.
class _Route extends StatelessWidget {
  const _Route({required this.progress});

  final Animation<double> progress;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const _Station(),
        Expanded(
          // Hauteur explicite : sans elle, la pile se règle sur la route
          // (2px) et le bus, posé dedans, se retrouve écrasé à la même
          // hauteur.
          child: SizedBox(
            height: 57,
            child: Stack(
              clipBehavior: Clip.none,
              alignment: Alignment.center,
              children: [
                // `.welcome-road` : le ruban qui va du blanc au doré.
                Center(
                  child: Container(
                    height: 2,
                    decoration: const BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          Color(0x80FFFFFF),
                          Color(0x26FFFFFF),
                          AppColors.gold,
                        ],
                        stops: [0, 0.68, 1],
                      ),
                    ),
                  ),
                ),
                Positioned.fill(
                  child: AnimatedBuilder(
                    animation: progress,
                    builder: (context, _) => Align(
                      // `busTravel` : le bus parcourt la route de la
                      // gauche vers la droite, en boucle.
                      alignment: Alignment(progress.value * 2 - 1, 0),
                      child: Transform.translate(
                        // `.welcome-bus { top: -15px }` : il roule SUR la
                        // ligne — sa caisse au-dessus, ses roues posées
                        // dessus.
                        offset: const Offset(0, -15),
                        child: const _Bus(),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
        const _Station(isFinish: true),
      ],
    );
  }
}

/// `.welcome-station` : un arrêt, cerclé de blanc au départ et de doré à
/// l'arrivée.
class _Station extends StatelessWidget {
  const _Station({this.isFinish = false});

  final bool isFinish;

  @override
  Widget build(BuildContext context) {
    final ring = isFinish ? AppColors.gold : AppColors.white;

    return Container(
      width: 14,
      height: 14,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.kora,
        border: Border.all(color: ring, width: 3),
        boxShadow: [
          BoxShadow(
            color: ring.withValues(alpha: isFinish ? 0.11 : 0.08),
            spreadRadius: 5,
          ),
        ],
      ),
    );
  }
}

/// `.welcome-bus` : la pastille dorée qui parcourt la route, roues
/// comprises.
class _Bus extends StatelessWidget {
  const _Bus();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 30,
      height: 34,
      child: Stack(
        clipBehavior: Clip.none,
        alignment: Alignment.topCenter,
        children: [
          Container(
            width: 30,
            height: 28,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.gold,
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(8),
                topRight: Radius.circular(9),
                bottomLeft: Radius.circular(5),
                bottomRight: Radius.circular(5),
              ),
              boxShadow: [
                BoxShadow(
                  color: AppColors.gold.withValues(alpha: 0.3),
                  offset: const Offset(0, 8),
                  blurRadius: 20,
                ),
              ],
            ),
            child: const Text(
              'K',
              style: TextStyle(
                color: AppColors.kora,
                fontSize: 12,
                fontWeight: FontWeight.w900,
                fontStyle: FontStyle.italic,
              ),
            ),
          ),
          const Positioned(bottom: 3, left: 4, child: _Wheel()),
          const Positioned(bottom: 3, right: 4, child: _Wheel()),
        ],
      ),
    );
  }
}

class _Wheel extends StatelessWidget {
  const _Wheel();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 5,
      height: 5,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.white,
        border: Border.all(color: AppColors.kora, width: 2),
      ),
    );
  }
}

/// `.welcome-service-pill` : la réassurance posée en bas de la scène.
class _ServicePill extends StatelessWidget {
  const _ServicePill();

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(17),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.13),
            offset: const Offset(0, 14),
            blurRadius: 35,
          ),
        ],
      ),
      // `backdrop-filter: blur(12px)` : la pastille est en verre dépoli,
      // elle floute la scène derrière elle. Un simple blanc translucide ne
      // rendait que la transparence, pas le flou.
      child: ClipRRect(
        borderRadius: BorderRadius.circular(17),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 12, sigmaY: 12),
          child: Container(
            width: 244,
            constraints: const BoxConstraints(minHeight: 57),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
            decoration: BoxDecoration(
              color: AppColors.white.withValues(alpha: 0.09),
              borderRadius: BorderRadius.circular(17),
              border: Border.all(
                color: AppColors.white.withValues(alpha: 0.13),
              ),
            ),
            child: Row(
              children: [
                Container(
                  width: 34,
                  height: 34,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: AppColors.gold,
                    borderRadius: BorderRadius.circular(AppRadius.sm),
                  ),
                  child: const Icon(
                    Icons.verified_user_outlined,
                    size: 18,
                    color: AppColors.kora,
                  ),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text(
                        'Voyage sous contrôle',
                        style: TextStyle(
                          color: AppColors.white,
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        'Billet, paiement et suivi réunis',
                        style: TextStyle(
                          color: AppColors.white.withValues(alpha: 0.52),
                          fontSize: 9,
                        ),
                      ),
                    ],
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
