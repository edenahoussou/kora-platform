import 'package:flutter/foundation.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../features/auth/presentation/cubit/auth_cubit.dart';
import 'app_routes.dart';

/// Redirige selon l'état d'authentification courant.
///
///   non authentifié → /login
///   authentifié      → /home
///
/// Écoute [AuthCubit] via [refreshListenable] pour que GoRouter réévalue la
/// redirection à chaque changement d'état (login/logout), sans backend réel
/// à ce stade (voir spec § 6).
class AuthGuard extends ChangeNotifier {
  AuthGuard(this._authCubit) {
    _subscription = _authCubit.stream.listen((_) => notifyListeners());
  }

  final AuthCubit _authCubit;
  late final dynamic _subscription;

  String? redirect(String currentLocation) {
    final AuthState state = _authCubit.state;
    final bool isResolving = state is AuthInitial || state is AuthLoading;
    final bool isAuthenticated = state is AuthAuthenticated;
    final bool isSplash = currentLocation == AppRoutes.splash;
    final bool isLoggingIn = currentLocation == AppRoutes.login;

    // Tant que checkAuthStatus() n'a pas résolu l'état, on reste sur
    // /splash pour éviter un flash /login → /home.
    if (isResolving) return isSplash ? null : AppRoutes.splash;

    if (!isAuthenticated && !isLoggingIn) return AppRoutes.login;
    if (isAuthenticated && isLoggingIn) return AppRoutes.home;
    if (isAuthenticated && isSplash) return AppRoutes.home;
    if (!isAuthenticated && isSplash) return AppRoutes.login;
    return null;
  }

  @override
  void dispose() {
    _subscription.cancel();
    super.dispose();
  }
}
