// connectivity_plus déplacé dans SyncCubit (MEZ-88, écoute continue) —
// plus utilisé directement ici.
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../injection/injection.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../onboarding/data/onboarding_store.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/presentation/cubit/sync_cubit.dart';
import '../../../terminal/presentation/cubit/terminal_cubit.dart';
import '../../domain/resolve_auth_destination.dart';
import '../widgets/kora_wordmark.dart';

/// L'écran de lancement — copie conforme du splash `kora-passager` : le
/// bus tourne autour du logo le temps que la session se décide. Seul le
/// texte change (« KORA OPS », accroche adaptée au métier).
///
/// **Il n'annonce aucune durée.** Ni barre de progression, ni pourcentage :
/// l'attente dépend du réseau puis de la session, et une jauge qui stagne
/// à 80 % inquiète plus qu'un mouvement continu. L'orbite tourne tant
/// qu'il faut.
///
/// C'est le même vocabulaire que l'onboarding qui peut suivre — cercles
/// filaires à 10 % de blanc, dégradé navy à 155°, pastille dorée — pour
/// que la première image de l'app soit déjà la sienne.
///
/// **La logique métier ne change pas** (voir échanges du 16 et 18/08/2026)
/// : fond blanc → navy mis à part, l'écran reste affiché **toujours 3
/// secondes pleines**, vérifie la connectivité en tâche de fond pour
/// initialiser `SyncCubit` (non bloquant), et c'est lui — et non
/// `AuthGuard` — qui déclenche la navigation une fois le délai minimum et
/// `checkAuthStatus()` tous deux résolus. Nouveau depuis cette refonte :
/// avant de trancher entre connexion et accueil, l'écran vérifie si
/// l'onboarding a déjà été vu (`OnboardingStore`) et l'affiche sinon —
/// une seule fois, au tout premier lancement.
class SplashPage extends StatefulWidget {
  const SplashPage({super.key});

  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage>
    with SingleTickerProviderStateMixin {
  static const Duration _minimumDisplayDuration = Duration(seconds: 3);

  /// Un tour toutes les 3,4 s : assez lent pour rester calme, assez rapide
  /// pour qu'on voie que l'app travaille — identique au passager.
  late final AnimationController _orbit = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 3400),
  )..repeat();

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void dispose() {
    _orbit.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final Stopwatch stopwatch = Stopwatch()..start();

    // Les trois vérifications tournent en parallèle ; seule la durée
    // minimale d'affichage (3s) est réellement attendue ci-dessous.
    // TerminalCubit.bootstrap() relit le terminal en cache (aucun appel
    // réseau bloquant, voir sa docstring) — le résultat conditionne la
    // destination au même titre que checkAuthStatus() (voir
    // resolveAuthDestination, MEZ-91). SyncCubit.bootstrap() (MEZ-88)
    // relit le nombre d'opérations en attente et démarre l'écoute
    // continue de la connectivité — remplace l'ancienne vérification
    // ponctuelle ci-dessous (_checkConnectivity, conservée en commentaire).
    await Future.wait<void>(<Future<void>>[
      context.read<AuthCubit>().checkAuthStatus(),
      context.read<TerminalCubit>().bootstrap(),
      context.read<SyncCubit>().bootstrap(),
      context.read<SelectedDepartCubit>().bootstrap(),
    ]);

    final Duration elapsed = stopwatch.elapsed;
    if (elapsed < _minimumDisplayDuration) {
      await Future<void>.delayed(_minimumDisplayDuration - elapsed);
    }

    if (mounted) _navigateNext();
  }

  /// Envoie vers l'onboarding s'il n'a jamais été vu, sinon directement
  /// vers la destination décidée par [resolveAuthDestination] — reproduit
  /// ici la logique de `AuthGuard`, qui ne redirige plus jamais depuis
  /// `/` (voir échange du 18/08/2026).
  void _navigateNext() {
    if (!getIt<OnboardingStore>().hasSeen) {
      context.go(AppRoutes.onboarding);
      return;
    }

    context.go(
      resolveAuthDestination(
        context.read<AuthCubit>().state,
        context.read<TerminalCubit>().state,
      ),
    );
  }

  // Remplacé par `SyncCubit.bootstrap()` (MEZ-88, détection continue de la
  // connectivité + reprise automatique de la file) — conservé en
  // commentaire, plus appelé.
  //
  // /// Vérifie la présence d'une connexion réseau et initialise [SyncCubit]
  // /// en conséquence. N'importe quel résultat (y compris une erreur) est
  // /// traité sans bloquer le démarrage — voir la docstring de la classe.
  // Future<void> _checkConnectivity() async {
  //   if (!mounted) return;
  //   try {
  //     final List<ConnectivityResult> result =
  //         await Connectivity().checkConnectivity();
  //     final bool isOnline = !result.contains(ConnectivityResult.none);
  //     if (!mounted) return;
  //     isOnline
  //         ? context.read<SyncCubit>().goOnline()
  //         : context.read<SyncCubit>().goOffline();
  //   } catch (_) {
  //     // Vérification indisponible sur cet appareil/émulateur — on démarre
  //     // simplement en ligne par défaut (état initial de SyncCubit).
  //   }
  // }

  @override
  Widget build(BuildContext context) {
    // `SizedBox.expand` : sans lui la pile se dimensionne sur son plus
    // grand enfant — l'anneau de 330 — et le dégradé s'arrête là, laissant
    // le reste de l'écran au fond du thème.
    return Scaffold(
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
                const Positioned(
                  bottom: 44,
                  left: 0,
                  right: 0,
                  child: Text(
                    'Connexion à vos tournées…',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Color(0x80FFFFFF),
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

/// Les points épars de la scène.
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
