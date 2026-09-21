import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:kora_ops_mobile/features/terminal/presentation/cubit/terminal_cubit.dart';

import '../../core/widgets/kora_bottom_nav.dart';
import '../../features/auth/presentation/bloc/auth_flow_bloc.dart';
import '../../features/auth/presentation/cubit/auth_cubit.dart';
import '../../features/auth/presentation/pages/forgot_password_page.dart';
import '../../features/auth/presentation/pages/login_email_page.dart';
import '../../features/auth/presentation/pages/new_pin_page.dart';
import '../../features/auth/presentation/pages/otp_invalid_page.dart';
import '../../features/auth/presentation/pages/otp_verification_page.dart';
import '../../features/auth/presentation/pages/pin_entry_page.dart';
import '../../features/border_manifest/presentation/cubit/border_manifest_print_cubit.dart';
import '../../features/border_manifest/presentation/pages/border_manifest_pdf_page.dart';
import '../../features/border_manifest/presentation/pages/border_manifest_print_page.dart';
import '../../features/descente/presentation/pages/descente_anticipee_page.dart';
import '../../features/home/presentation/pages/accueil_page.dart';
import '../../features/incidents/presentation/pages/incident_report_page.dart';
import '../../features/manifeste/presentation/pages/detail_passager_page.dart';
import '../../features/manifeste/presentation/pages/manifeste_page.dart';
import '../../features/notifications/presentation/pages/notifications_page.dart';
import '../../features/onboarding/data/onboarding_store.dart';
import '../../features/onboarding/presentation/pages/onboarding_page.dart';
import '../../features/printing/presentation/cubit/printing_cubit.dart';
import '../../features/printing/presentation/pages/boarding_pdf_preview_page.dart';
import '../../features/printing/presentation/pages/printer_test_page.dart';
import '../../features/profil/presentation/pages/mes_informations_page.dart';
import '../../features/profil/presentation/pages/preferences_page.dart';
import '../../features/profil/presentation/pages/profil_page.dart';
import '../../features/profil/presentation/pages/securite_page.dart';
import '../../features/scan/presentation/pages/scan_boarding_page.dart';
import '../../features/scan/presentation/pages/scan_result_page.dart';
import '../../features/splash/domain/resolve_auth_destination.dart';
import '../../features/splash/presentation/pages/splash_page.dart';
import '../../features/synchro/presentation/pages/synchro_page.dart';
import '../../features/terminal/presentation/pages/terminal_setup_page.dart';
import '../../features/tournee/presentation/pages/tournee_page.dart';
import '../../injection/injection.dart';
import 'app_routes.dart';
import 'auth_guard.dart';
import 'root_navigator_key.dart';

class AppRouter {
  AppRouter(this._authGuard);

  final AuthGuard _authGuard;

  /// Enveloppe une page du flux `/login/*` avec l'instance partagée de
  /// [AuthFlowBloc] (singleton GetIt) afin que l'email/le contexte OTP
  /// survivent à la navigation entre écrans.
  Widget _withAuthFlow(Widget child) {
    return BlocProvider<AuthFlowBloc>.value(
        value: getIt<AuthFlowBloc>(), child: child);
  }

  late final GoRouter router = GoRouter(
    navigatorKey: rootNavigatorKey,
    initialLocation: AppRoutes.splash,
    refreshListenable: _authGuard,
    redirect: (context, state) => _authGuard.redirect(state.matchedLocation),
    routes: <RouteBase>[
      GoRoute(
          path: AppRoutes.splash,
          builder: (context, state) => const SplashPage()),
      GoRoute(
        path: AppRoutes.onboarding,
        builder: (context, state) => OnboardingPage(
          onDone: () async {
            await getIt<OnboardingStore>().markSeen();
            if (context.mounted) {
              context.go(
                resolveAuthDestination(
                  context.read<AuthCubit>().state,
                  context.read<TerminalCubit>().state,
                ),
              );
            }
          },
        ),
      ),
      GoRoute(
        path: AppRoutes.terminalSetup,
        builder: (context, state) => const TerminalSetupPage(),
      ),
      GoRoute(
        path: AppRoutes.loginEmail,
        builder: (context, state) => _withAuthFlow(const LoginEmailPage()),
      ),
      GoRoute(
        path: AppRoutes.loginForgotPassword,
        builder: (context, state) => _withAuthFlow(const ForgotPasswordPage()),
      ),
      GoRoute(
        path: AppRoutes.loginPin,
        builder: (context, state) => _withAuthFlow(const PinEntryPage()),
      ),
      GoRoute(
        path: AppRoutes.loginOtp,
        builder: (context, state) => _withAuthFlow(const OtpVerificationPage()),
      ),
      GoRoute(
        path: AppRoutes.loginOtpInvalid,
        builder: (context, state) => _withAuthFlow(const OtpInvalidPage()),
      ),
      GoRoute(
        path: AppRoutes.loginNewPin,
        builder: (context, state) => _withAuthFlow(const NewPinPage()),
      ),
      GoRoute(
        path: AppRoutes.manifeste,
        builder: (context, state) => const ManifestePage(),
      ),
      GoRoute(
        path: AppRoutes.detailPassager,
        builder: (context, state) =>
            DetailPassagerPage(passengerId: state.extra as String? ?? ''),
      ),
      GoRoute(
        path: AppRoutes.borderManifestPrint,
        builder: (context, state) => BlocProvider<BorderManifestPrintCubit>(
          create: (_) => getIt<BorderManifestPrintCubit>(),
          child: const BorderManifestPrintPage(),
        ),
      ),
      GoRoute(
        path: AppRoutes.borderManifestPdf,
        builder: (context, state) => BorderManifestPdfPage(
          args: state.extra! as BorderManifestPdfArgs,
        ),
      ),
      GoRoute(
        path: AppRoutes.scanResult,
        builder: (context, state) => const ScanResultPage(),
      ),
      GoRoute(
        path: AppRoutes.boardingPdfPreview,
        builder: (context, state) => BoardingPdfPreviewPage(
          args: state.extra! as BoardingPdfPreviewArgs,
        ),
      ),
      GoRoute(
        path: AppRoutes.synchro,
        builder: (context, state) => const SynchroPage(),
      ),
      GoRoute(
        path: AppRoutes.mesInformations,
        builder: (context, state) => const MesInformationsPage(),
      ),
      GoRoute(
        path: AppRoutes.securite,
        builder: (context, state) => const SecuritePage(),
      ),
      GoRoute(
        path: AppRoutes.preferences,
        builder: (context, state) => const PreferencesPage(),
      ),
      GoRoute(
        path: AppRoutes.incidentReport,
        builder: (context, state) => const IncidentReportPage(),
      ),
      GoRoute(
        path: AppRoutes.notifications,
        builder: (context, state) => const NotificationsPage(),
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
                  path: AppRoutes.tournee,
                  builder: (context, state) => const TourneePage()),
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
                  path: AppRoutes.profil,
                  builder: (context, state) => const ProfilPage()),
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
