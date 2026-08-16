import 'package:flutter/foundation.dart';
import '../../features/auth/presentation/cubit/auth_cubit.dart';
import 'app_routes.dart';

/// Redirige selon l'état d'authentification courant.
///
///   en cours de résolution → reste sur `/` (splash)
///   non authentifié         → `/login/pin` (entrée unique — pas
///                              d'auto-inscription côté OPS, voir
///                              document.md § Flux d'authentification)
///   authentifié              → `/home`
///
/// Les sous-routes `/login/otp`, `/login/otp-invalid` et
/// `/login/new-device` sont des étapes intermédiaires du même parcours et
/// ne sont donc jamais la cible d'une redirection automatique — seule la
/// navigation explicite (`context.push`) y mène.
class AuthGuard extends ChangeNotifier {
  AuthGuard(this._authCubit) {
    _subscription = _authCubit.stream.listen((_) => notifyListeners());
  }

  final AuthCubit _authCubit;
  late final dynamic _subscription;

  static const Set<String> _loginFlowRoutes = <String>{
    AppRoutes.loginPin,
    AppRoutes.loginOtp,
    AppRoutes.loginOtpInvalid,
    AppRoutes.loginNewDevice,
  };

  String? redirect(String currentLocation) {
    final AuthState state = _authCubit.state;
    final bool isResolving = state is AuthInitial || state is AuthLoading;
    final bool isSplash = currentLocation == AppRoutes.splash;
    final bool isInLoginFlow = _loginFlowRoutes.contains(currentLocation);

    if (isResolving) return isSplash ? null : AppRoutes.splash;

    if (state is AuthAuthenticated) {
      return isInLoginFlow || isSplash ? AppRoutes.home : null;
    }

    if (isSplash || currentLocation == AppRoutes.home) {
      return AppRoutes.loginPin;
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
