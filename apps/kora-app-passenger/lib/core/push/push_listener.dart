import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../features/auth/presentation/bloc/auth_bloc.dart';
import '../di/injector.dart';
import '../l10n/app_localization.dart';
import '../router/app_router.dart';
import 'notification_presenter.dart';
import 'push_gateway.dart';
import 'push_messaging.dart';

/// Branche les notifications push sur la vie de l'application.
///
/// Trois raccordements, et un seul endroit pour les trois :
///
/// 1. **La session.** Le jeton s'inscrit à l'ouverture d'une session et
///    s'efface à sa fermeture. Les deux endpoints exigent d'être
///    authentifié, et un jeton laissé derrière soi ferait arriver les
///    notifications d'un compte sur un téléphone qu'il a quitté.
/// 2. **Le premier plan.** Android n'affiche rien quand l'app est à
///    l'écran : c'est à elle de poser la notification, ce dont
///    [NotificationPresenter] se charge.
/// 3. **Le réveil.** Toucher une notification doit mener quelque part,
///    qu'elle vienne du système ou de l'application elle-même.
///
/// Ce widget ne dessine rien de lui-même : il enveloppe l'application et
/// se contente d'écouter.
class PushListener extends StatefulWidget {
  const PushListener({required this.child, super.key});

  final Widget child;

  @override
  State<PushListener> createState() => _PushListenerState();
}

class _PushListenerState extends State<PushListener> {
  PushMessaging get _push => getIt<PushMessaging>();
  NotificationPresenter get _notifications => getIt<NotificationPresenter>();

  StreamSubscription<PushMessage>? _messages;
  StreamSubscription<PushMessage>? _openings;
  StreamSubscription<PushMessage>? _shownOpenings;

  /// Vrai une fois le jeton inscrit pour la session en cours. Sans ce
  /// garde-fou, chaque reconstruction du widget relancerait la demande
  /// de permission et un appel réseau.
  var _registered = false;

  @override
  void dispose() {
    _messages?.cancel();
    _openings?.cancel();
    _shownOpenings?.cancel();
    super.dispose();
  }

  /// Ouvre la session côté notifications : configuration, permission,
  /// jeton. Le résultat n'interrompt jamais le parcours — au pire, le
  /// voyageur ne recevra rien, ce qui n'empêche pas de réserver.
  Future<void> _register(String deviceUuid) async {
    if (_registered) return;
    _registered = true;

    // Rien de ce qui touche aux notifications ne doit pouvoir empêcher de
    // réserver un voyage. Un SDK absent, un service Google manquant, un
    // appareil exotique : on l'encaisse et on passe.
    final PushRegistration outcome;
    try {
      outcome = await _push.register(deviceUuid);
    } on Exception {
      _registered = false;
      return;
    }

    if (!mounted) return;

    // Un seul cas mérite qu'on s'adresse au voyageur : celui qu'il peut
    // corriger lui-même, depuis ses préférences. Un refus de permission
    // est un choix, et une intégration absente ne le regarde pas.
    if (outcome == PushRegistration.preferenceDisabled) {
      _tell(context.l10n.pushPreferenceDisabled);
    }

    if (outcome != PushRegistration.registered) {
      // Firebase n'a pas démarré : s'abonner maintenant reviendrait à
      // réclamer une instance qui n'existe pas.
      _registered = false;
      return;
    }

    _listen();
    await _openInitialMessage();
  }

  Future<void> _unregister() async {
    _registered = false;
    await _messages?.cancel();
    await _openings?.cancel();
    await _shownOpenings?.cancel();
    _messages = null;
    _openings = null;
    _shownOpenings = null;

    try {
      await _push.unregister();
    } on Exception {
      // La session est déjà close : il n'y a plus rien à révoquer ici.
    }
  }

  void _listen() {
    // Au premier plan, Firebase remet le message à l'application sans rien
    // afficher. On pose donc nous-mêmes la notification, sur le canal des
    // voyages : le voyageur la voit arriver en haut de l'écran, et la
    // retrouve dans son volet plus tard.
    _messages ??= _push.messages.listen(_notifications.show);
    _openings ??= _push.openings.listen(_open);
    _shownOpenings ??= _notifications.openings.listen(_open);
  }

  /// La notification qui a démarré l'application. Elle n'est lue qu'une
  /// fois : au prochain lancement, Firebase ne la rendra plus.
  Future<void> _openInitialMessage() async {
    final message = await _push.initialMessage();
    if (message != null) _open(message);
  }

  /// Ouvre l'écran que désigne la notification.
  ///
  /// **Le serveur n'envoie encore aucune destination** : son
  /// `fcm_options.link` ne vaut que pour le web et n'atteint pas le
  /// téléphone. On retombe donc sur la liste des voyages, qui est le
  /// sujet de presque tous les messages — un billet émis, un départ qui
  /// change, un paiement confirmé s'y retrouvent tous.
  ///
  /// Le jour où le back joindra un bloc `data`, [PushMessage.route] le
  /// rendra et cette méthode suivra sans autre changement.
  void _open(PushMessage message) {
    if (!mounted) return;

    final router = AppRouter.router;
    final route = message.route;

    router.go(
      route != null && route.startsWith('/') ? route : AppRouter.tripsPath,
    );
  }

  /// Un mot à l'écran, réservé aux réglages.
  ///
  /// Les notifications reçues ne passent **pas** par ici : elles vont dans
  /// la barre d'état, comme n'importe quelle notification. Ce qui reste
  /// est d'une autre nature — « vos notifications sont désactivées dans vos
  /// préférences » parle de l'application, pas d'un voyage, et n'a rien à
  /// faire dans le volet du téléphone.
  void _tell(String text) {
    final messenger = ScaffoldMessenger.maybeOf(context);
    messenger?.showSnackBar(
      SnackBar(content: Text(text), behavior: SnackBarBehavior.floating),
    );
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<AuthBloc, AuthState>(
      listenWhen: (previous, current) =>
          previous.status != current.status ||
          previous.session?.device?.uuid != current.session?.device?.uuid,
      listener: (context, state) {
        final deviceUuid = state.session?.device?.uuid;

        if (state.isAuthenticated && deviceUuid != null) {
          unawaited(_register(deviceUuid));
          return;
        }

        if (!state.isAuthenticated && _registered) unawaited(_unregister());
      },
      child: widget.child,
    );
  }
}
