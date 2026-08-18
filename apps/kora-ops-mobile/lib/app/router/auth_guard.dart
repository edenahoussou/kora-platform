import 'package:flutter/foundation.dart';
import '../../features/auth/presentation/cubit/auth_cubit.dart';
import 'app_routes.dart';

/// Redirige selon l'état d'authentification courant (voir document.md §
/// Flux d'authentification).
///
///   `/` (splash)             → **jamais** de redirection automatique
///                              d'ici : c'est `SplashPage` elle-même qui
///                              navigue, une fois son délai minimum de 3s
///                              écoulé (voir échange du 18/08/2026 — sans
///                              ça, `checkAuthStatus()` se résout parfois
///                              en quelques millisecondes et coupait
///                              l'affichage du splash)
///   non authentifié, email jamais activé sur cet appareil
///                            → `/login/email` (email + mot de passe)
///   non authentifié, email déjà activé sur cet appareil
///                            → `/login/pin` (PIN direct)
///   authentifié              → `/home`
///
/// Les sous-routes `/login/forgot-password`, `/login/otp` et
/// `/login/otp-invalid` sont des étapes intermédiaires du même parcours et
/// ne sont donc jamais la cible d'une redirection automatique — seule la
/// navigation explicite (`context.push`) y mène.
class AuthGuard extends ChangeNotifier {
  AuthGuard(this._authCubit) {
    _subscription = _authCubit.stream.listen((_) => notifyListeners());
  }

  final AuthCubit _authCubit;
  late final dynamic _subscription;

  static const Set<String> _loginFlowRoutes = <String>{
    AppRoutes.loginEmail,
    AppRoutes.loginForgotPassword,
    AppRoutes.loginPin,
    AppRoutes.loginOtp,
    AppRoutes.loginOtpInvalid,
  };

  String? redirect(String currentLocation) {
    // Le splash gère sa propre navigation (voir docstring) — ne jamais
    // interférer pendant qu'il est affiché.
    if (currentLocation == AppRoutes.splash) return null;

    final AuthState state = _authCubit.state;
    final bool isInLoginFlow = _loginFlowRoutes.contains(currentLocation);

    final bool isResolving = state is AuthInitial || state is AuthLoading;
    if (isResolving) return null;

    if (state is AuthAuthenticated) {
      return isInLoginFlow ? AppRoutes.home : null;
    }

    // Non authentifié : redirige depuis n'importe quel écran hors du
    // parcours `/login/*` — la déconnexion peut être déclenchée depuis
    // n'importe quel onglet (ex. `/profil`, voir document.md § Profil).
    if (!isInLoginFlow) {
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
