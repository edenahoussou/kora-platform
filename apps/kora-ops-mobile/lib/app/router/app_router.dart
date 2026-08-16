import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../core/widgets/kora_bottom_nav.dart';
import '../../features/auth/presentation/cubit/login_cubit.dart';
import '../../features/auth/presentation/pages/new_device_detected_page.dart';
import '../../features/auth/presentation/pages/otp_invalid_page.dart';
import '../../features/auth/presentation/pages/otp_verification_page.dart';
import '../../features/auth/presentation/pages/pin_entry_page.dart';
import '../../features/auth/presentation/pages/splash_page.dart';
import '../../features/descente/presentation/pages/descente_anticipee_page.dart';
import '../../features/home/presentation/pages/accueil_page.dart';
import '../../features/home/presentation/pages/selection_depart_page.dart';
import '../../features/manifeste/presentation/pages/detail_passager_page.dart';
import '../../features/manifeste/presentation/pages/manifeste_page.dart';
import '../../features/printing/presentation/cubit/printing_cubit.dart';
import '../../features/printing/presentation/pages/printer_test_page.dart';
import '../../features/scan/presentation/pages/scan_boarding_page.dart';
import '../../features/scan/presentation/pages/scan_result_page.dart';
import '../../features/synchro/presentation/pages/synchro_page.dart';
import '../../injection/injection.dart';
import 'app_routes.dart';
import 'auth_guard.dart';

class AppRouter {
  AppRouter(this._authGuard);

  final AuthGuard _authGuard;

  /// Enveloppe une page du flux `/login/*` avec l'instance partagée de
  /// [LoginCubit] (singleton GetIt) afin que l'email/le contexte OTP
  /// survivent à la navigation entre écrans.
  Widget _withLoginCubit(Widget child) {
    return BlocProvider<LoginCubit>.value(
        value: getIt<LoginCubit>(), child: child);
  }

  late final GoRouter router = GoRouter(
    initialLocation: AppRoutes.splash,
    refreshListenable: _authGuard,
    redirect: (context, state) => _authGuard.redirect(state.matchedLocation),
    routes: <RouteBase>[
      GoRoute(
          path: AppRoutes.splash,
          builder: (context, state) => const SplashPage()),
      GoRoute(
        path: AppRoutes.loginPin,
        builder: (context, state) => _withLoginCubit(const PinEntryPage()),
      ),
      GoRoute(
        path: AppRoutes.loginOtp,
        builder: (context, state) =>
            _withLoginCubit(const OtpVerificationPage()),
      ),
      GoRoute(
        path: AppRoutes.loginOtpInvalid,
        builder: (context, state) => _withLoginCubit(const OtpInvalidPage()),
      ),
      GoRoute(
        path: AppRoutes.loginNewDevice,
        builder: (context, state) =>
            _withLoginCubit(const NewDeviceDetectedPage()),
      ),
      GoRoute(
        path: AppRoutes.selectionDepart,
        builder: (context, state) => const SelectionDepartPage(),
      ),
      GoRoute(
        path: AppRoutes.detailPassager,
        builder: (context, state) =>
            DetailPassagerPage(passengerId: state.extra as String? ?? ''),
      ),
      GoRoute(
        path: AppRoutes.scanResult,
        builder: (context, state) => const ScanResultPage(),
      ),
      GoRoute(
        path: AppRoutes.printerTest,
        builder: (context, state) => BlocProvider<PrintingCubit>(
          create: (_) => getIt<PrintingCubit>(),
          child: const PrinterTestPage(),
        ),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) =>
            _ScaffoldWithBottomNav(navigationShell: navigationShell),
        branches: <StatefulShellBranch>[
          StatefulShellBranch(
            routes: <RouteBase>[
              GoRoute(
                  path: AppRoutes.home,
                  builder: (context, state) => const AccueilPage()),
            ],
          ),
          StatefulShellBranch(
            routes: <RouteBase>[
              GoRoute(
                  path: AppRoutes.manifeste,
                  builder: (context, state) => const ManifestePage()),
            ],
          ),
          StatefulShellBranch(
            routes: <RouteBase>[
              GoRoute(
                  path: AppRoutes.scanBoarding,
                  builder: (context, state) => const ScanBoardingPage()),
            ],
          ),
          StatefulShellBranch(
            routes: <RouteBase>[
              GoRoute(
                path: AppRoutes.descenteAnticipee,
                builder: (context, state) => const DescenteAnticipeePage(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: <RouteBase>[
              GoRoute(
                  path: AppRoutes.synchro,
                  builder: (context, state) => const SynchroPage()),
            ],
          ),
        ],
      ),
    ],
  );
}

/// Le `Scaffold` commun aux 5 onglets : porte la [KoraBottomNav] et affiche
/// la branche active via [navigationShell]. `ScanBoardingCubit` doit
/// pouvoir lire `ManifesteCubit` (déjà fourni plus haut dans l'arbre par
/// `injection.dart`/`BlocProvider` global — voir app.dart).
class _ScaffoldWithBottomNav extends StatelessWidget {
  const _ScaffoldWithBottomNav({required this.navigationShell});

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
          onTap: (int index) => navigationShell.goBranch(
            index,
            initialLocation: index == navigationShell.currentIndex,
          ),
        ),
      ),
    );
  }
}
