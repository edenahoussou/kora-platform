import 'package:flutter/foundation.dart';
import '../../features/auth/presentation/cubit/auth_cubit.dart';
import '../../features/terminal/presentation/cubit/terminal_cubit.dart';
import 'app_routes.dart';

/// Redirige selon l'état d'authentification **et** de liaison terminal
/// courants (voir document.md § Flux d'authentification, MEZ-91 § Terminal
/// P501).
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
///   authentifié, terminal non lié (jamais lié, inconnu, perdu,
///   désactivé ou en maintenance)
///                            → `/terminal/setup`
///   authentifié, terminal lié → `/home`
///
/// Les sous-routes `/login/forgot-password`, `/login/otp` et
/// `/login/otp-invalid` sont des étapes intermédiaires du même parcours et
/// ne sont donc jamais la cible d'une redirection automatique — seule la
/// navigation explicite (`context.push`) y mène.
class AuthGuard extends ChangeNotifier {
  AuthGuard(this._authCubit, this._terminalCubit) {
    _authSubscription = _authCubit.stream.listen((_) => notifyListeners());
    _terminalSubscription =
        _terminalCubit.stream.listen((_) => notifyListeners());
  }

  final AuthCubit _authCubit;
  final TerminalCubit _terminalCubit;
  late final dynamic _authSubscription;
  late final dynamic _terminalSubscription;

  static const Set<String> _loginFlowRoutes = <String>{
    AppRoutes.loginEmail,
    AppRoutes.loginForgotPassword,
    AppRoutes.loginPin,
    AppRoutes.loginOtp,
    AppRoutes.loginOtpInvalid,
    AppRoutes.loginNewPin,
  };

  String? redirect(String currentLocation) {
    // Le splash gère sa propre navigation (voir docstring) — ne jamais
    // interférer pendant qu'il est affiché. Même chose pour l'onboarding,
    // qui gère la sienne une fois vu (voir `OnboardingPage.onDone`) : y
    // rediriger prématurément couperait la présentation avant sa fin.
    if (currentLocation == AppRoutes.splash) return null;
    if (currentLocation == AppRoutes.onboarding) return null;
    // Mise à jour de l'app (MEZ-150) : proposée aussi avant connexion,
    // jamais redirigée.
    if (currentLocation == AppRoutes.appUpdate) return null;

    final AuthState state = _authCubit.state;
    final bool isInLoginFlow = _loginFlowRoutes.contains(currentLocation);

    final bool isResolving = state is AuthInitial || state is AuthLoading;
    if (isResolving) return null;

    if (state is AuthAuthenticated) {
      final TerminalState terminalState = _terminalCubit.state;

      if (terminalState is TerminalLinked) {
        // Terminal résolu : jamais laisser l'agent bloqué sur l'écran de
        // liaison ni sur le parcours de connexion.
        return (currentLocation == AppRoutes.terminalSetup || isInLoginFlow)
            ? AppRoutes.home
            : null;
      }

      // Pas encore résolu (`TerminalInitial`/`TerminalLinking`, le temps
      // du bootstrap ou d'un handshake en cours) ou explicitement à relier
      // (`TerminalUnlinked`/`TerminalUnknown`/`TerminalNotUsable`) : rester
      // sur l'écran de liaison, quel que soit l'écran de départ — aucun
      // écran métier (scan, manifeste, sync) n'a de sens sans terminal.
      return currentLocation == AppRoutes.terminalSetup
          ? null
          : AppRoutes.terminalSetup;
    }

    // Non authentifié : redirige depuis n'importe quel écran hors du
    // parcours `/login/*` — la déconnexion peut être déclenchée depuis
    // n'importe quel onglet (ex. `/profil`, voir document.md § Profil).
    if (!isInLoginFlow) {
      final String? savedIdentifier = switch (state) {
        AuthUnauthenticated(:final savedIdentifier) => savedIdentifier,
        AuthCheckFailed(:final savedIdentifier) => savedIdentifier,
        _ => null,
      };
      return savedIdentifier == null
          ? AppRoutes.loginEmail
          : AppRoutes.loginPin;
    }

    return null;
  }

  @override
  void dispose() {
    // ignore: avoid_dynamic_calls
    _authSubscription.cancel();
    // ignore: avoid_dynamic_calls
    _terminalSubscription.cancel();
    super.dispose();
  }
}
