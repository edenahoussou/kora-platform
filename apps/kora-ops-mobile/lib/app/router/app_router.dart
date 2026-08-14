import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../features/auth/presentation/pages/login_page.dart';
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

  late final GoRouter router = GoRouter(
    initialLocation: AppRoutes.splash,
    refreshListenable: _authGuard,
    redirect: (context, state) => _authGuard.redirect(state.matchedLocation),
    routes: <RouteBase>[
      GoRoute(path: AppRoutes.splash, builder: (context, state) => const SplashPage()),
      GoRoute(path: AppRoutes.login, builder: (context, state) => const LoginPage()),
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
