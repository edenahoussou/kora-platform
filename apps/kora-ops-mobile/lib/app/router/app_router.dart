import 'package:flutter/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../features/auth/presentation/cubit/login_cubit.dart';
import '../../features/auth/presentation/pages/email_entry_page.dart';
import '../../features/auth/presentation/pages/new_device_detected_page.dart';
import '../../features/auth/presentation/pages/otp_invalid_page.dart';
import '../../features/auth/presentation/pages/otp_verification_page.dart';
import '../../features/auth/presentation/pages/pin_entry_page.dart';
import '../../features/auth/presentation/pages/splash_page.dart';
import '../../features/home/presentation/pages/home_page.dart';
import '../../features/printing/presentation/cubit/printing_cubit.dart';
import '../../features/printing/presentation/pages/printer_test_page.dart';
import '../../injection/injection.dart';
import 'app_routes.dart';
import 'auth_guard.dart';

class AppRouter {
  AppRouter(this._authGuard);

  final AuthGuard _authGuard;

  /// Enveloppe une page du flux `/login/*` avec l'instance partagée de
  /// [LoginCubit] (singleton GetIt, voir document.md § Flux
  /// d'authentification) afin que l'email saisi et le contexte OTP
  /// survivent à la navigation entre écrans.
  Widget _withLoginCubit(Widget child) {
    return BlocProvider<LoginCubit>.value(value: getIt<LoginCubit>(), child: child);
  }

  late final GoRouter router = GoRouter(
    initialLocation: AppRoutes.splash,
    refreshListenable: _authGuard,
    redirect: (context, state) => _authGuard.redirect(state.matchedLocation),
    routes: <RouteBase>[
      GoRoute(path: AppRoutes.splash, builder: (context, state) => const SplashPage()),
      GoRoute(
        path: AppRoutes.loginEmail,
        builder: (context, state) => _withLoginCubit(const EmailEntryPage()),
      ),
      GoRoute(
        path: AppRoutes.loginOtp,
        builder: (context, state) => _withLoginCubit(const OtpVerificationPage()),
      ),
      GoRoute(
        path: AppRoutes.loginOtpInvalid,
        builder: (context, state) => _withLoginCubit(const OtpInvalidPage()),
      ),
      GoRoute(
        path: AppRoutes.loginPin,
        builder: (context, state) => _withLoginCubit(const PinEntryPage()),
      ),
      GoRoute(
        path: AppRoutes.loginNewDevice,
        builder: (context, state) => _withLoginCubit(const NewDeviceDetectedPage()),
      ),
      GoRoute(path: AppRoutes.home, builder: (context, state) => const HomePage()),
      GoRoute(
        path: AppRoutes.printerTest,
        builder: (context, state) => BlocProvider<PrintingCubit>(
          create: (_) => getIt<PrintingCubit>(),
          child: const PrinterTestPage(),
        ),
      ),
    ],
  );
}
