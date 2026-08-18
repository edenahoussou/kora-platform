import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../synchro/presentation/cubit/sync_cubit.dart';

/// Écran de démarrage — fond blanc, logo KORA bien visible (voir échange
/// du 16/08/2026), affiché **toujours 3 secondes pleines**, y compris en
/// mode démo (voir échange du 18/08/2026).
///
/// **Corrigé le 18/08/2026** : la navigation est désormais déclenchée
/// explicitement par cet écran ([_navigateToNextScreen]), après le délai
/// minimum — et non plus laissée à `AuthGuard`, qui réagissait à chaque
/// changement d'état de `AuthCubit` (parfois résolu en quelques
/// millisecondes, bien avant la fin du splash) et redirigeait trop tôt,
/// coupant l'affichage du splash.
///
/// Vérifie la connexion internet en tâche de fond ([_checkConnectivity])
/// pour initialiser [SyncCubit] — **non bloquant** : une base de données
/// locale est prévue pour permettre le travail hors-ligne, avec
/// synchronisation ultérieure vers le serveur (voir document.md § Splash
/// et § Synchro) ; l'absence de réseau au démarrage n'empêche donc jamais
/// de continuer vers l'écran de connexion.
class SplashPage extends StatefulWidget {
  const SplashPage({super.key});

  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage> {
  static const Duration _minimumDisplayDuration = Duration(seconds: 3);

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    final Stopwatch stopwatch = Stopwatch()..start();

    // Les deux vérifications tournent en parallèle ; seule la durée
    // minimale d'affichage (3s) est réellement attendue ci-dessous.
    await Future.wait<void>(<Future<void>>[
      _checkConnectivity(),
      context.read<AuthCubit>().checkAuthStatus(),
    ]);

    final Duration elapsed = stopwatch.elapsed;
    if (elapsed < _minimumDisplayDuration) {
      await Future<void>.delayed(_minimumDisplayDuration - elapsed);
    }

    if (mounted) _navigateToNextScreen();
  }

  /// Décide de la destination une fois `checkAuthStatus()` résolu **et**
  /// le délai minimum écoulé (voir docstring de la classe) — reproduit ici
  /// la logique de `AuthGuard`, qui ne redirige plus jamais depuis `/`.
  void _navigateToNextScreen() {
    final AuthState state = context.read<AuthCubit>().state;
    switch (state) {
      case AuthAuthenticated():
        context.go(AppRoutes.home);
      case AuthUnauthenticated(savedEmail: final String? savedEmail):
        context
            .go(savedEmail == null ? AppRoutes.loginEmail : AppRoutes.loginPin);
      case AuthInitial() || AuthLoading():
        // Ne devrait pas arriver : on a attendu checkAuthStatus() ci-dessus.
        context.go(AppRoutes.loginEmail);
    }
  }

  /// Vérifie la présence d'une connexion réseau et initialise [SyncCubit]
  /// en conséquence. N'importe quel résultat (y compris une erreur) est
  /// traité sans bloquer le démarrage — voir la docstring de la classe.
  Future<void> _checkConnectivity() async {
    if (!mounted) return;
    try {
      final List<ConnectivityResult> result =
          await Connectivity().checkConnectivity();
      final bool isOnline = !result.contains(ConnectivityResult.none);
      if (!mounted) return;
      isOnline
          ? context.read<SyncCubit>().goOnline()
          : context.read<SyncCubit>().goOffline();
    } catch (_) {
      // Vérification indisponible sur cet appareil/émulateur — on démarre
      // simplement en ligne par défaut (état initial de SyncCubit).
    }
  }

  @override
  Widget build(BuildContext context) {
    return const AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
        statusBarBrightness: Brightness.light,
      ),
      child: Scaffold(
        backgroundColor: AppColors.white,
        body: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Image(
                image: AssetImage('assets/images/kora_logo.png'),
                width: 180,
                fit: BoxFit.contain,
              ),
              SizedBox(height: 32),
              CircularProgressIndicator(color: AppColors.kora),
            ],
          ),
        ),
      ),
    );
  }
}
