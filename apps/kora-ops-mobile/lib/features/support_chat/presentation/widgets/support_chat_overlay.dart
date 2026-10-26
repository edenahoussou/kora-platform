import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/router/app_routes.dart';
import '../../../../app/router/root_navigator_key.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../cubit/support_chat_cubit.dart';

/// Affiche le bouton flottant « Chat support » (tawk.to) par-dessus **tous**
/// les écrans connectés de l'app — branché dans `MaterialApp.router(builder:)`
/// (voir `app.dart`), donc sans toucher aux pages une par une.
///
/// Règles d'affichage :
///   * agent authentifié uniquement (le chat identifie le convoyeur) — donc
///     jamais sur splash, onboarding, connexion (email/PIN/OTP) ni liaison
///     du terminal ;
///   * masqué sur la page du chat elle-même ;
///   * masqué quand le clavier est ouvert (il couvrirait les champs) ;
///   * en bas à gauche, pour ne pas chevaucher les boutons flottants des
///     pages (Filtrer, menu d'actions du Manifeste), tous à droite.
///
/// Ce widget vit au-dessus du `Navigator` : pas de `GoRouter.of(context)`,
/// de `Overlay` ni de `Tooltip`. La navigation passe par [router] et les
/// dialogues par [rootNavigatorKey].
class SupportChatOverlay extends StatelessWidget {
  const SupportChatOverlay({
    required this.router,
    required this.child,
    super.key,
  });

  final GoRouter router;
  final Widget? child;

  /// Écrans où le bouton n'a pas lieu d'être même connecté.
  static const Set<String> _hiddenRoutes = <String>{
    AppRoutes.splash,
    AppRoutes.onboarding,
    AppRoutes.terminalSetup,
    AppRoutes.loginEmail,
    AppRoutes.loginForgotPassword,
    AppRoutes.loginPin,
    AppRoutes.loginOtp,
    AppRoutes.loginOtpInvalid,
    AppRoutes.loginNewPin,
    AppRoutes.supportChat,
  };

  /// Onglets de la barre de navigation flottante : le bouton se place
  /// au-dessus d'elle. Ailleurs (écrans empilés), il se colle en bas.
  static const Set<String> _tabRoutes = <String>{
    AppRoutes.home,
    AppRoutes.tournee,
    AppRoutes.scanBoarding,
    AppRoutes.descenteAnticipee,
    AppRoutes.profil,
  };

  /// Hauteur de la barre de navigation flottante + ses marges (voir
  /// `_ScaffoldWithBottomNav`), même décalage que l'ancien bouton du Profil.
  static const double _aboveBottomNav = 88;
  static const double _aboveScreenEdge = 16;
  static const double _sideMargin = 16;

  @override
  Widget build(BuildContext context) {
    final Widget content = child ?? const SizedBox.shrink();

    return Stack(
      fit: StackFit.expand,
      children: <Widget>[
        content,
        BlocBuilder<AuthCubit, AuthState>(
          builder: (BuildContext context, AuthState auth) {
            if (auth is! AuthAuthenticated) return const SizedBox.shrink();

            return ListenableBuilder(
              listenable: router.routerDelegate,
              builder: (BuildContext context, _) {
                final String path =
                    router.routerDelegate.currentConfiguration.uri.path;
                final MediaQueryData media = MediaQuery.of(context);
                if (_hiddenRoutes.contains(path) ||
                    media.viewInsets.bottom > 0) {
                  return const SizedBox.shrink();
                }

                final double bottom = media.padding.bottom +
                    (_tabRoutes.contains(path)
                        ? _aboveBottomNav
                        : _aboveScreenEdge);

                // `Align` plutôt que `Positioned` (interdit sous un builder) ;
                // hors du bouton, les touches traversent jusqu'à la page.
                return Align(
                  alignment: Alignment.bottomLeft,
                  child: Padding(
                    padding: EdgeInsets.only(left: _sideMargin, bottom: bottom),
                    child: _SupportChatButton(router: router),
                  ),
                );
              },
            );
          },
        ),
      ],
    );
  }
}

/// Bouton « Chat support » : grisé tant que le chat n'est pas utilisable
/// (hors ligne, désactivé, config injoignable) — un tap explique pourquoi,
/// sans jamais bloquer ni planter.
class _SupportChatButton extends StatelessWidget {
  const _SupportChatButton({required this.router});

  final GoRouter router;

  @override
  Widget build(BuildContext context) {
    final SupportChatState state = context.watch<SupportChatCubit>().state;
    final bool available =
        state.status == SupportChatStatus.available && state.config != null;

    // `Material` : le bouton vit hors du `Scaffold` des pages (donc sans
    // ancêtre Material pour l'effet d'encre).
    return Material(
      type: MaterialType.transparency,
      child: Semantics(
        button: true,
        label: 'Chat support',
        child: FloatingActionButton(
          heroTag: 'support-chat-fab',
          backgroundColor: available
              ? AppColors.gold
              : AppColors.gold.withValues(alpha: 0.35),
          foregroundColor: available
              ? AppColors.onGold
              : AppColors.onGold.withValues(alpha: 0.5),
          elevation: available ? 6 : 0,
          onPressed: () => _onPressed(context, state, available),
          child: const Icon(Icons.support_agent),
        ),
      ),
    );
  }

  void _onPressed(
    BuildContext context,
    SupportChatState state,
    bool available,
  ) {
    if (available) {
      router.push(AppRoutes.supportChat, extra: state.config);
      return;
    }

    final (String title, String description) = switch (state.status) {
      SupportChatStatus.offline => (
          'Hors ligne',
          'Le chat support nécessite une connexion internet. '
              'Réessayez dès que vous avez du réseau.',
        ),
      SupportChatStatus.loading => (
          'Chargement',
          'Vérification de la disponibilité du chat en cours…',
        ),
      _ => (
          'Chat indisponible',
          'Le chat support n\'est pas disponible actuellement. '
              'Contactez votre superviseur d\'agence.',
        ),
    };

    // Le contexte de ce widget est au-dessus du Navigator : les dialogues
    // s'ouvrent via le contexte du navigateur racine.
    final BuildContext? dialogContext = rootNavigatorKey.currentContext;
    if (dialogContext != null) {
      KoraDialogs.info(dialogContext, title: title, description: description);
    }
    if (state.status != SupportChatStatus.loading) {
      context.read<SupportChatCubit>().load();
    }
  }
}
