import 'package:flutter/foundation.dart';
import '../../features/auth/presentation/cubit/auth_cubit.dart';
import 'app_routes.dart';

/// Redirige selon l'état d'authentification courant.
///
///   en cours de résolution → reste sur `/` (splash)
///   non authentifié, aucun email enregistré → `/login/email`
///   non authentifié, email déjà enregistré  → `/login/pin`
///   authentifié                              → `/home`
///
/// Toutes les sous-routes `/login/*` (otp, otp-invalid, new-device) sont
/// des étapes intermédiaires du même parcours et ne sont donc jamais la
/// cible d'une redirection automatique — seule la navigation explicite
/// (`context.push`) y mène.
class AuthGuard extends ChangeNotifier {
  AuthGuard(this._authCubit) {
    _subscription = _authCubit.stream.listen((_) => notifyListeners());
  }

  final AuthCubit _authCubit;
  late final dynamic _subscription;

  static const Set<String> _loginFlowRoutes = <String>{
    AppRoutes.loginEmail,
    AppRoutes.loginOtp,
    AppRoutes.loginOtpInvalid,
    AppRoutes.loginPin,
    AppRoutes.loginNewDevice,
  };

  String? redirect(String currentLocation) {
    final AuthState state = _authCubit.state;
    final bool isResolving = state is AuthInitial || state is AuthLoading;
    final bool isSplash = currentLocation == AppRoutes.splash;
    final bool isInLoginFlow = _loginFlowRoutes.contains(currentLocation);

    // Tant que checkAuthStatus() n'a pas résolu l'état, on reste sur
    // /splash pour éviter un flash d'écran.
    if (isResolving) return isSplash ? null : AppRoutes.splash;

    if (state is AuthAuthenticated) {
      return isInLoginFlow || isSplash ? AppRoutes.home : null;
    }

    // Non authentifié : ne redirige que depuis /splash (entrée du flux) ou
    // /home (déconnexion) — une fois dans le flux, chaque écran gère sa
    // propre navigation explicite (email → otp → pin → nouvel appareil).
    if (isSplash || currentLocation == AppRoutes.home) {
      final String? savedEmail = (state as AuthUnauthenticated).savedEmail;
      return savedEmail == null ? AppRoutes.loginEmail : AppRoutes.loginPin;
    }

    return null;
  }

  @override
  void dispose() {
    // ignore: avoid_dynamic_calls
    _subscription.cancel();
    super.dispose();
  }
}
