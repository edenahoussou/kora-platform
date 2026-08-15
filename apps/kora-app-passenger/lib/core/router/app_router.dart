import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/pages/login_page.dart';
import '../../features/colis/presentation/pages/colis_page.dart';
import '../../features/home/presentation/pages/home_page.dart';
import '../../features/profil/presentation/pages/profil_page.dart';
import '../../features/voyages/presentation/pages/voyages_page.dart';
import '../../features/wallet/presentation/pages/wallet_page.dart';
import 'auth_guard.dart';

/// Couche CORE : configuration centrale de la navigation avec `go_router`.
///
/// On utilise un [StatefulShellRoute.indexedStack] : c'est le pattern
/// recommandé par `go_router` pour une app avec une bottom nav bar. Chaque
/// onglet (Accueil / Voyages / Colis / Wallet / Profil) garde son propre
/// état de navigation même quand on change d'onglet (contrairement à un
/// simple `IndexedStack` géré à la main).
///
/// Pour naviguer vers un onglet depuis n'importe où dans l'app :
/// ```dart
/// context.go('/voyages');
/// ```
///
/// Pour ajouter un écran de détail au-dessus d'un onglet (ex: le détail
/// d'un trajet, accessible depuis Voyages) :
/// ```dart
/// GoRoute(
///   path: 'trip/:tripId',
///   builder: (context, state) => TripDetailPage(tripId: state.pathParameters['tripId']!),
/// ),
/// ```
/// à ajouter dans la liste `routes:` de la branche `/voyages` ci-dessous.
class AppRouter {
  const AppRouter._();

  /// Chemin de l'écran de connexion — seule route accessible sans session
  /// active (voir `redirect` ci-dessous).
  static const String loginPath = '/login';

  /// Guard de navigation global, réévalué par `go_router` à chaque
  /// changement de route.
  ///
  /// - Connecté et sur `/login` → redirigé vers `/home` (pas de raison de
  ///   revoir l'écran de connexion).
  /// - Sinon → `null`, pas de redirection, la navigation demandée a lieu.
  ///
  /// **La redirection "non connecté → `/login`" est volontairement
  /// désactivée pour l'instant** : `LoginPage` n'a pas encore de vrai
  /// formulaire (voir GUIDE.md §13), donc la forcer enfermerait tout le
  /// monde sur un écran sans issue, impossible de revenir sur la bottom
  /// nav. TOO(auth): réactiver `if (!authenticated && !onLoginPage) return
  /// loginPath;` dès que `LoginPage` permet réellement de se connecter.
  ///
  /// Volontairement pas de `refreshListenable` non plus : sans feature
  /// `auth` écrite, il n'existe pas encore de flux qui notifierait un
  /// changement de session en dehors d'une navigation. Le jour où
  /// `AuthBloc` existe, câbler `refreshListenable` dessus pour que
  /// `redirect` se réévalue aussi juste après un login/logout, sans
  /// attendre une navigation manuelle.
  static Future<String?> _redirect(
    BuildContext context,
    GoRouterState state,
  ) async {
    final onLoginPage = state.matchedLocation == loginPath;

    if (onLoginPage && await AuthGuard.isAuthenticated) return '/home';
    return null;
  }

  static final GoRouter router = GoRouter(
    initialLocation: '/home',
    redirect: _redirect,
    routes: [
      GoRoute(path: loginPath, builder: (context, state) => const LoginPage()),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          return _ScaffoldWithNavBar(navigationShell: navigationShell);
        },
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/home',
                builder: (context, state) => const HomePage(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/voyages',
                builder: (context, state) => const VoyagesPage(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/colis',
                builder: (context, state) => const ColisPage(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/wallet',
                builder: (context, state) => const WalletPage(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/profil',
                builder: (context, state) => const ProfilPage(),
              ),
            ],
          ),
        ],
      ),
    ],
  );
}

/// Le `Scaffold` commun à tous les onglets : il porte la [KoraBottomNav]
/// et affiche la branche active via [navigationShell].
class _ScaffoldWithNavBar extends StatelessWidget {
  const _ScaffoldWithNavBar({required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBody: true,
      body: navigationShell,
      bottomNavigationBar: Padding(
        padding: const EdgeInsets.fromLTRB(11, 0, 11, 11),
        child: KoraBottomNav(
          currentIndex: navigationShell.currentIndex,
          onTap: (index) => navigationShell.goBranch(
            index,
            // Si on retape sur l'onglet déjà actif, on revient à sa racine
            // (comportement standard des apps mobiles).
            initialLocation: index == navigationShell.currentIndex,
          ),
        ),
      ),
    );
  }
}
