import '../../../app/router/app_routes.dart';
import '../../auth/presentation/cubit/auth_cubit.dart';
import '../../terminal/presentation/cubit/terminal_cubit.dart';

/// Décide de la destination une fois `checkAuthStatus()` **et**
/// `TerminalCubit.bootstrap()` résolus.
///
/// Extrait de `SplashPage` pour être réutilisé tel quel par `OnboardingPage`
/// : un agent qui voit l'onboarding au premier lancement doit atterrir
/// exactement là où le splash l'aurait envoyé s'il l'avait déjà vu — les
/// deux ne doivent jamais diverger sur cette logique.
///
/// Authentifié mais sans terminal lié (`TerminalUnlinked`/`TerminalUnknown`/
/// `TerminalNotUsable`, ou `TerminalInitial`/`TerminalLinking` si appelé
/// avant la résolution) → écran de liaison terminal, jamais `/home`
/// directement — voir `AuthGuard`, qui applique exactement la même règle
/// pour toute navigation ultérieure.
String resolveAuthDestination(
    AuthState authState, TerminalState terminalState) {
  switch (authState) {
    case AuthAuthenticated():
      return terminalState is TerminalLinked
          ? AppRoutes.home
          : AppRoutes.terminalSetup;
    case AuthUnauthenticated(savedIdentifier: final String? savedIdentifier):
      return savedIdentifier == null
          ? AppRoutes.loginEmail
          : AppRoutes.loginPin;
    case AuthInitial() || AuthLoading():
      // Ne devrait pas arriver : on attend toujours checkAuthStatus() avant
      // d'appeler cette fonction.
      return AppRoutes.loginEmail;
    case AuthCheckFailed():
      throw UnimplementedError();
  }
}
