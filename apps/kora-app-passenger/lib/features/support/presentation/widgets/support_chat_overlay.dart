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
  /// Deux familles :
  /// - **le parcours d'authentification** et les écrans d'accueil : sans
  ///   session il n'y a pas de configuration de chat à lire, et le
  ///   bouton recouvrirait les boutons pleine largeur de ces écrans ;
  /// - **la conversation elle-même** et l'écran Contacter KORA, qui
  ///   porte déjà son entrée : un bouton qui mène à la page où l'on se
  ///   trouve ne sert à rien ;
  /// - **les cinq onglets**, dont la coquille s'occupe déjà — son
  ///   `Scaffold` sait placer le bouton au-dessus de la barre de
  ///   navigation, ce qu'une surcouche ne peut pas deviner.
  ///
  /// Le tunnel de réservation, lui, **garde** le bouton : c'est là qu'on
  /// a le plus de questions. Il en recouvrait le bouton pleine largeur,
  /// d'où son retrait un temps ; il y est maintenant remonté — voir
  /// [_lifted].
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

    // Le plan des sièges : son pied n'est pas un bouton mais une carte —
    // le siège retenu, le total, puis « Continuer ». Remonter le bouton
    // au-dessus d'un bouton suffit ; au-dessus d'une carte entière, il
    // se retrouverait au milieu du plan. Vérifié sur l'appareil : posé
    // à 84 px, il recouvrait le total.
    if (location == AppRouter.seatsPath) return true;

    // Les onglets, et eux seuls : `/voyages/<uuid>` est un billet ouvert
    // par-dessus, pas l'onglet Voyages.
    const tabs = {'/home', '/voyages', '/colis', '/wallet', '/profil'};

    return tabs.contains(location);
  }

  /// Les écrans qui gardent le bouton mais dont le bas est occupé.
  ///
  /// Tout le tunnel de réservation en est : chacun de ses écrans se
  /// termine par une action pleine largeur — « Choisir mon siège »,
  /// « Continuer », « Payer » — et le bouton flottant, posé dans le
  /// coin, en recouvrait le bord droit. On le remonte au-dessus plutôt
  /// que de le retirer : c'est le moment du parcours où une question se
  /// pose le plus, et une question sans réponse coûte la réservation.
  ///
  /// Les deux écrans du tunnel qui n'ont pas d'action en bas — la liste
  /// des départs et le choix du moyen de paiement — en font partie aussi :
  /// le bouton y flotte 84 px plus haut, ce qui ne recouvre rien et lui
  /// évite de sauter d'un écran à l'autre.
  ///
  /// Le plan des sièges, lui, n'y est pas : son pied est une carte, pas
  /// un bouton, et 84 px n'auraient pas suffi — il s'efface là.
  static const Set<String> _lifted = {
    AppRouter.departureDetailPath,
    AppRouter.busSearchPath,
    AppRouter.departuresPath,
    AppRouter.passengerInfoPath,
    AppRouter.summaryPath,
    AppRouter.holdExpiredPath,
    AppRouter.paymentMethodPath,
    AppRouter.paymentMobileMoneyPath,
    AppRouter.paymentCardPath,
    AppRouter.paymentGatewayMomoPath,
    AppRouter.groupBookingPath,
    AppRouter.groupTicketsPath,
    AppRouter.groupDiscountPath,
  };

  /// La marge basse du bouton : juste au-dessus de l'action de l'écran
  /// quand il y en a une, dans le coin sinon.
  ///
  /// 84 px : la hauteur d'un `KoraButton` et sa marge, plus l'espace qui
  /// sépare les deux.
  static double _bottom(String location) =>
      _lifted.contains(location) ? 84 : 16;

  /// Où le bouton se montre, et à quelle hauteur.
  ///
  /// Exposés parce que la règle a changé deux fois : le tunnel a eu le
  /// bouton, l'a perdu parce qu'il recouvrait « Payer », et l'a repris
  /// une fois remonté. Deux tests valent mieux qu'un troisième
  /// aller-retour.
  @visibleForTesting
  static bool showsOn(String location) => !_hiddenOn(location);

  @visibleForTesting
  static double bottomOn(String location) => _bottom(location);

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
                  padding: EdgeInsets.fromLTRB(
                    16,
                    16,
                    16,
                    SupportChatOverlay._bottom(router.state.matchedLocation),
                  ),
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
