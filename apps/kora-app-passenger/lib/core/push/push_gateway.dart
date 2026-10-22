import 'package:equatable/equatable.dart';

import 'firebase_configuration.dart';

/// Une notification telle que l'app la reçoit.
///
/// [data] est le sac de clés que le serveur joint au message. Il sert au
/// routage : savoir quel écran ouvrir quand le voyageur touche la
/// notification.
///
/// **Il est vide aujourd'hui.** Le serveur ne construit que le couple
/// titre/texte ; le `fcm_options.link` qu'il ajoute par ailleurs ne vaut
/// que pour le web et n'atteint jamais Android ni iOS. Le routage fin
/// attend donc que le back publie ces clés — d'où [PushMessage.route],
/// qui les lira sans qu'on touche au reste.
class PushMessage extends Equatable {
  const PushMessage({this.title = '', this.body = '', this.data = const {}});

  final String title;
  final String body;
  final Map<String, String> data;

  /// Vrai quand il y a quelque chose à montrer au voyageur.
  bool get hasText => title.isNotEmpty || body.isNotEmpty;

  /// La destination demandée par le serveur, s'il en donne une.
  ///
  /// On accepte `route` comme `link` : la première est la clé qu'on
  /// proposera au back, la seconde celle qu'il emploie déjà côté web.
  /// `null` quand rien n'est dit — l'écran se déduit alors de
  /// [resourceUuid], et à défaut de la liste des voyages.
  String? get route {
    for (final key in const ['route', 'link']) {
      final value = data[key];
      if (value != null && value.isNotEmpty) return value;
    }

    return null;
  }

  /// Ce dont parle la notification : l'identifiant de la réservation.
  ///
  /// C'est la clé que la boîte de réception lit déjà pour ouvrir le bon
  /// billet (`resource_uuid`). Une notification poussée porte le même
  /// sujet : la lire ici évite de demander au serveur une seconde clé,
  /// un chemin tout fait, qui dirait la même chose dans un autre
  /// vocabulaire — et qu'il faudrait refaire à chaque écran déplacé.
  ///
  /// `null` tant que le bloc `data` ne la porte pas.
  String? get resourceUuid {
    final value = data['resource_uuid'];
    return value == null || value.isEmpty ? null : value;
  }

  /// Ce qu'annonce la notification : `ticket.issued`, `payment.confirmed`…
  String? get type {
    final value = data['type'];
    return value == null || value.isEmpty ? null : value;
  }

  @override
  List<Object?> get props => [title, body, data];
}

/// Ce que l'appareil répond à la demande d'autorisation.
enum PushPermission { granted, denied, provisional }

/// La frontière entre l'app et Firebase.
///
/// Tout ce qui touche au SDK passe par ici, pour une raison pratique :
/// les greffons Firebase ne tournent pas dans `flutter test`. Avec cette
/// coupure, [PushMessaging] — c'est-à-dire la logique qui décide quand
/// inscrire, réinscrire ou effacer un jeton — se teste sans émulateur.
abstract class PushGateway {
  /// Démarre Firebase avec la configuration reçue du serveur.
  Future<void> initialize(FirebaseConfiguration configuration);

  /// Demande l'autorisation d'afficher des notifications.
  ///
  /// Obligatoire depuis Android 13 et sur iOS. Un refus n'est pas une
  /// erreur : c'est un choix du voyageur, qu'on respecte en n'inscrivant
  /// pas l'appareil.
  Future<PushPermission> requestPermission();

  /// Le jeton de cet appareil, ou `null` si Firebase n'a pas pu en
  /// produire — appareil sans services Google, par exemple.
  Future<String?> token();

  /// Firebase renouvelle le jeton de sa propre initiative. Sans cette
  /// écoute, l'appareil cesse silencieusement de recevoir.
  Stream<String> get tokenRefreshes;

  /// Messages reçus pendant que l'app est au premier plan. Android ne les
  /// affiche pas tout seul dans ce cas : c'est à l'app de le faire. Sur
  /// iOS, le système s'en charge et ce flux reste vide.
  Stream<PushMessage> get messages;

  /// Le voyageur a touché une notification alors que l'app vivait en
  /// arrière-plan.
  Stream<PushMessage> get openings;

  /// La notification qui a démarré l'application, si c'est le cas.
  /// À lire une seule fois, au lancement.
  Future<PushMessage?> initialMessage();

  /// Oublie le jeton de cet appareil côté Firebase.
  Future<void> deleteToken();
}
