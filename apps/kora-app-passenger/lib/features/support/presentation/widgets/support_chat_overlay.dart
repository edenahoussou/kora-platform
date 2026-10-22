import 'dart:async';

import 'package:flutter/material.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/router/app_router.dart';
import '../../domain/repositories/support_chat_repository.dart';
import '../bloc/support_chat_cubit.dart';
import 'support_chat_fab.dart';

/// Le bouton d'assistance, posé au-dessus de **toutes** les pages.
///
/// Il vivait dans le `Scaffold` de la coquille de navigation : il ne
/// suivait donc que les cinq onglets et disparaissait dès qu'on ouvrait
/// Sécurité, un billet ou le tunnel de réservation — c'est-à-dire
/// précisément là où l'on a une question à poser.
///
/// Posé ici une fois plutôt que dans chacun des quarante écrans : un
/// `Scaffold` oublié, et le bouton manquerait à nouveau quelque part.
///
/// **Il s'efface là où il n'a rien à faire** — voir [_hiddenOn].
class SupportChatOverlay extends StatefulWidget {
  const SupportChatOverlay({required this.child, super.key});

  final Widget child;

  /// Les écrans qui ne montrent pas le bouton.
  ///
  /// Trois familles :
  /// - **le parcours d'authentification** et les écrans d'accueil : sans
  ///   session il n'y a pas de configuration de chat à lire, et le
  ///   bouton recouvrirait les boutons pleine largeur de ces écrans ;
  /// - **la conversation elle-même** et l'écran Contacter KORA, qui
  ///   porte déjà son entrée : un bouton qui mène à la page où l'on se
  ///   trouve ne sert à rien ;
  /// - **le tunnel de réservation**, dont chaque écran se termine par un
  ///   bouton pleine largeur — « Choisir mon siège », « Continuer »,
  ///   « Payer » : vérifié sur l'appareil, le bouton flottant en
  ///   recouvrait le bord droit. Entre joindre un agent et payer son
  ///   billet, c'est le paiement qui doit rester intact ; l'assistance
  ///   reste à un retour en arrière ;
  /// - **les cinq onglets**, dont la coquille s'occupe déjà — son
  ///   `Scaffold` sait placer le bouton au-dessus de la barre de
  ///   navigation, ce qu'une surcouche ne peut pas deviner.
  static bool _hiddenOn(String location) {
    const screens = {
      AppRouter.splashPath,
      AppRouter.onboardingPath,
      AppRouter.welcomePath,
      AppRouter.loginPath,
      AppRouter.signupPath,
      AppRouter.otpPath,
      AppRouter.createPinPath,
      AppRouter.newDevicePath,
      AppRouter.permissionsPath,
      AppRouter.supportChatPath,
      AppRouter.contactPath,
    };
    if (screens.contains(location)) return true;

    const funnel = {
      AppRouter.busSearchPath,
      AppRouter.departuresPath,
      AppRouter.seatsPath,
      AppRouter.passengerInfoPath,
      AppRouter.summaryPath,
      AppRouter.holdExpiredPath,
      AppRouter.paymentMethodPath,
      AppRouter.paymentMobileMoneyPath,
      AppRouter.groupBookingPath,
      AppRouter.groupTicketsPath,
      AppRouter.groupDiscountPath,
    };
    if (funnel.contains(location)) return true;

    // Les onglets, et eux seuls : `/voyages/<uuid>` est un billet ouvert
    // par-dessus, pas l'onglet Voyages.
    const tabs = {'/home', '/voyages', '/colis', '/wallet', '/profil'};

    return tabs.contains(location);
  }

  @override
  State<SupportChatOverlay> createState() => _SupportChatOverlayState();
}

class _SupportChatOverlayState extends State<SupportChatOverlay> {
  /// Un seul cubit pour toute l'app.
  ///
  /// Le bouton réapparaît à chaque changement de route ; lui laisser
  /// construire son cubit redemanderait la configuration au serveur à
  /// chaque écran ouvert.
  SupportChatCubit? _cubit;

  /// Le cubit, construit au premier écran qui montre le bouton.
  ///
  /// Pas à l'ouverture de l'app : sur l'écran de bienvenue ou de
  /// connexion le bouton ne s'affiche pas, et la configuration
  /// d'assistance n'est de toute façon pas lisible sans session. On
  /// n'appelle donc le serveur que lorsqu'il y a un bouton à peindre.
  SupportChatCubit _ensureCubit() {
    final existing = _cubit;
    if (existing != null) return existing;

    final created = SupportChatCubit(getIt<SupportChatRepository>());
    unawaited(created.load());
    _cubit = created;

    return created;
  }

  @override
  void dispose() {
    _cubit?.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final router = AppRouter.router;

    return Stack(
      children: [
        widget.child,
        // Le délégué prévient à chaque changement de route : sans cette
        // écoute, le bouton resterait affiché — ou absent — selon la
        // page où la surcouche a été construite.
        AnimatedBuilder(
          animation: router.routerDelegate,
          builder: (context, _) {
            if (SupportChatOverlay._hiddenOn(router.state.matchedLocation)) {
              return const SizedBox.shrink();
            }

            return SafeArea(
              child: Align(
                alignment: Alignment.bottomRight,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: SupportChatFab(
                    cubit: _ensureCubit(),
                    onOpen: (configuration) => router.push(
                      AppRouter.supportChatPath,
                      extra: configuration,
                    ),
                  ),
                ),
              ),
            );
          },
        ),
      ],
    );
  }
}
