import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:kora_ops_mobile/features/terminal/presentation/cubit/terminal_cubit.dart';

import '../../core/widgets/kora_bottom_nav.dart';
import '../../features/app_update/presentation/cubit/app_update_cubit.dart';
import '../../features/app_update/presentation/pages/app_update_page.dart';
import '../../features/auth/presentation/bloc/auth_flow_bloc.dart';
import '../../features/auth/presentation/cubit/auth_cubit.dart';
import '../../features/auth/presentation/pages/change_pin_page.dart';
import '../../features/auth/presentation/pages/forgot_password_page.dart';
import '../../features/auth/presentation/pages/login_identifier_page.dart';
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
import '../../features/notifications/domain/entities/ops_notification.dart';
import '../../features/notifications/presentation/pages/notification_detail_page.dart';
import '../../features/notifications/presentation/pages/notifications_page.dart';
import '../../features/notifications/presentation/pages/sent_notifications_page.dart';
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
import '../../features/splash/domain/resolve_auth_destination.dart';
import '../../features/splash/presentation/pages/splash_page.dart';
import '../../features/support_chat/domain/support_chat_config.dart';
import '../../features/support_chat/presentation/pages/support_chat_page.dart';
import '../../features/synchro/presentation/pages/synchro_page.dart';
import '../../features/terminal/presentation/pages/terminal_setup_page.dart';
import '../../features/tournee/presentation/pages/tournee_page.dart';
import '../../features/trip_log/presentation/pages/trip_expense_form_page.dart';
import '../../features/trip_log/presentation/pages/trip_log_page.dart';
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
        builder: (context, state) => _withAuthFlow(const LoginIdentifierPage()),
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
        path: AppRoutes.changePin,
        builder: (context, state) => const ChangePinPage(),
      ),
      GoRoute(
        path: AppRoutes.supportChat,
        builder: (context, state) =>
            SupportChatPage(config: state.extra! as SupportChatConfig),
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
        path: AppRoutes.tripLog,
        builder: (context, state) => const TripLogPage(),
      ),
      GoRoute(
        path: AppRoutes.tripExpenseForm,
        builder: (context, state) => TripExpenseFormPage(
          args: state.extra! as TripExpenseFormArgs,
        ),
      ),
      // Mise à jour non obligatoire (MEZ-150) — « Plus tard » reporte la
      // proposition de 24 h puis referme l'écran.
      GoRoute(
        path: AppRoutes.appUpdate,
        builder: (context, state) => AppUpdatePage(
          mandatory: false,
          onLater: () {
            context.read<AppUpdateCubit>().postpone();
            if (context.canPop()) {
              context.pop();
            } else {
              context.go(AppRoutes.home);
            }
          },
        ),
      ),
      GoRoute(
        path: AppRoutes.notifications,
        builder: (context, state) => const NotificationsPage(),
      ),
      GoRoute(
        path: AppRoutes.notificationsSent,
        builder: (context, state) => const SentNotificationsPage(),
      ),
      GoRoute(
        path: AppRoutes.notificationDetail,
        builder: (context, state) => NotificationDetailPage(
          notification: state.extra! as OpsNotification,
        ),
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
///
/// Double appui pour quitter (échange du 23/09/2026) : un retour sur
/// n'importe lequel des 5 onglets ne doit pas fermer l'app au premier
/// appui — le convoyeur peut avoir voulu juste changer d'onglet avant.
/// [PopScope] intercepte systématiquement le retour système (`canPop:
/// false`) ; un deuxième appui dans les [_exitWindow] confirme et laisse
/// l'app se fermer via [SystemNavigator.pop] (pas `Navigator.pop`, qui
/// n'aurait rien à dépiler ici — GoRouter a déjà consommé la navigation
/// interne des onglets via `navigationShell.goBranch`).
class _ScaffoldWithBottomNav extends StatefulWidget {
  const _ScaffoldWithBottomNav({required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  @override
  State<_ScaffoldWithBottomNav> createState() => _ScaffoldWithBottomNavState();
}

class _ScaffoldWithBottomNavState extends State<_ScaffoldWithBottomNav> {
  static const Duration _exitWindow = Duration(seconds: 2);

  DateTime? _lastBackPress;

  void _handleBackPress() {
    final DateTime now = DateTime.now();
    final DateTime? last = _lastBackPress;

    if (last != null && now.difference(last) <= _exitWindow) {
      SystemNavigator.pop();
      return;
    }

    _lastBackPress = now;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(const SnackBar(
        content: Text('Appuyez de nouveau pour quitter'),
        duration: _exitWindow,
      ));
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (bool didPop, Object? result) {
        if (didPop) return;
        _handleBackPress();
      },
      child: Scaffold(
        extendBody: true,
        body: widget.navigationShell,
        bottomNavigationBar: Padding(
          padding: const EdgeInsets.fromLTRB(11, 0, 11, 11),
          child: KoraBottomNav(
            currentIndex: widget.navigationShell.currentIndex,
            onTap: (int index) => widget.navigationShell.goBranch(
              index,
              initialLocation: index == widget.navigationShell.currentIndex,
            ),
          ),
        ),
      ),
    );
  }
}
