import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import 'firebase_configuration.dart';
import 'push_configuration_store.dart';
import 'push_gateway.dart';
import 'push_remote_data_source.dart';

/// Ce qu'il est advenu d'une tentative d'inscription.
///
/// Aucune de ces valeurs n'est une panne : ce sont des situations
/// normales, qu'on nomme pour pouvoir les traiter — et pour ne pas
/// réessayer en boucle ce qui ne peut pas aboutir.
enum PushRegistration {
  /// Le jeton est inscrit : l'appareil recevra les notifications.
  registered,

  /// KORA n'a pas configuré Firebase. Il n'y a rien à faire, et rien à
  /// dire au voyageur.
  notConfigured,

  /// Le voyageur a refusé les notifications sur son téléphone.
  permissionDenied,

  /// Les notifications push sont désactivées dans les préférences du
  /// compte. C'est le seul cas où proposer quelque chose a du sens :
  /// l'écran Préférences peut les rallumer.
  preferenceDisabled,

  /// Firebase n'a pas produit de jeton — appareil sans services Google,
  /// le plus souvent.
  unavailable,

  /// Le réseau a manqué. Celui-là se réessaie.
  failed,
}

/// Les notifications push, de bout en bout : configuration, permission,
/// inscription du jeton, et réception.
///
/// L'ordre des opérations n'est pas arbitraire. On demande d'abord la
/// configuration au serveur, parce qu'elle peut dire « pas de push ici »
/// et dispenser de tout le reste. On ne réclame l'autorisation au
/// voyageur qu'ensuite : rien n'agace plus qu'une demande de permission
/// pour un service qui n'existe pas.
class PushMessaging {
  PushMessaging(
    this._gateway,
    this._remote,
    this._store, {
    required this.platform,
    this.logs = false,
  });

  final PushGateway _gateway;
  final PushRemoteDataSource _remote;
  final PushConfigurationStore _store;

  /// `android` ou `ios` : le serveur rend une configuration par
  /// plateforme, l'identifiant d'app n'étant pas le même.
  final String platform;

  /// Journalise l'issue de l'inscription. Vrai en dev et en staging, où
  /// les journaux réseau sont déjà ouverts ; faux en production, et faux
  /// dans les tests — qui ne démarrent pas d'environnement.
  final bool logs;

  StreamSubscription<String>? _refreshes;
  String? _deviceUuid;

  /// Messages arrivés pendant que l'app est à l'écran.
  Stream<PushMessage> get messages => _gateway.messages;

  /// Notifications touchées par le voyageur.
  Stream<PushMessage> get openings => _gateway.openings;

  /// La notification qui a ouvert l'application, le cas échéant.
  Future<PushMessage?> initialMessage() => _gateway.initialMessage();

  /// Met l'appareil en état de recevoir, et inscrit son jeton.
  ///
  /// À appeler une fois la session ouverte : les deux endpoints exigent
  /// d'être authentifié, et le jeton se rattache à un appareil connu.
  Future<PushRegistration> register(String deviceUuid) async {
    final outcome = await _register(deviceUuid);

    // Une inscription qui n'aboutit pas ne se voit nulle part dans
    // l'interface — c'est voulu, le voyageur n'a pas à s'en occuper. En
    // dev et en staging, où les journaux réseau sont déjà ouverts, elle
    // se lit dans `logcat` : sans quoi diagnostiquer « je ne reçois
    // rien » revient à deviner.
    if (logs) debugPrint('[push] inscription : ${outcome.name}');

    return outcome;
  }

  Future<PushRegistration> _register(String deviceUuid) async {
    _deviceUuid = deviceUuid;

    final FirebaseConfiguration configuration;
    try {
      configuration = await _remote.configuration(platform);
    } on DioException {
      // Le serveur n'a pas répondu : on ne sait pas s'il y a du push.
      // Ne rien effacer, et réessayer à la prochaine ouverture.
      return PushRegistration.failed;
    }

    if (!configuration.isUsable) {
      // L'intégration a pu être coupée depuis la dernière fois : un cache
      // périmé ferait démarrer l'isolat de fond sur un projet abandonné.
      await _store.clear();
      return PushRegistration.notConfigured;
    }

    await _store.save(configuration);
    await _gateway.initialize(configuration);

    final permission = await _gateway.requestPermission();
    if (permission == PushPermission.denied) {
      return PushRegistration.permissionDenied;
    }

    final token = await _gateway.token();
    if (token == null || token.isEmpty) return PushRegistration.unavailable;

    final outcome = await _send(deviceUuid, token);
    if (outcome == PushRegistration.registered) _listenForRefreshes();

    return outcome;
  }

  /// Efface le jeton de cet appareil, côté serveur puis côté Firebase.
  ///
  /// Dans cet ordre, et c'est voulu : tant que le serveur n'a pas oublié
  /// le jeton, il peut écrire à l'appareil. Le supprimer localement
  /// d'abord laisserait une fenêtre où les notifications d'un compte
  /// arriveraient encore après sa déconnexion.
  Future<void> unregister() async {
    final deviceUuid = _deviceUuid;
    await _refreshes?.cancel();
    _refreshes = null;
    _deviceUuid = null;

    if (deviceUuid == null) return;

    try {
      await _remote.savePushToken(deviceUuid: deviceUuid, token: null);
    } on DioException {
      // Session déjà close, réseau absent : rien à sauver ici. Le serveur
      // écartera le jeton de lui-même au premier envoi refusé.
    }

    try {
      await _gateway.deleteToken();
    } on Exception {
      // Firebase non initialisé : il n'y avait alors aucun jeton.
    }

    // Même raison que pour l'inscription : sans cette trace, vérifier que
    // le serveur a bien oublié l'appareil oblige à aller lire la base.
    if (logs) debugPrint('[push] désinscription : $deviceUuid');
  }

  /// Firebase renouvelle le jeton sans prévenir — réinstallation,
  /// restauration, purge des données. Sans cette écoute, l'appareil
  /// cesserait de recevoir en silence.
  void _listenForRefreshes() {
    _refreshes?.cancel();
    _refreshes = _gateway.tokenRefreshes.listen((token) {
      final deviceUuid = _deviceUuid;
      if (deviceUuid != null) _send(deviceUuid, token);
    });
  }

  Future<PushRegistration> _send(String deviceUuid, String token) async {
    try {
      final enabled = await _remote.savePushToken(
        deviceUuid: deviceUuid,
        token: token,
      );

      return enabled
          ? PushRegistration.registered
          : PushRegistration.unavailable;
    } on DioException catch (error) {
      // 422 : le serveur refuse pour une raison qu'il nomme. La seule
      // qui se corrige depuis l'app est la préférence du compte.
      if (error.response?.statusCode == 422) {
        return PushRegistration.preferenceDisabled;
      }

      return PushRegistration.failed;
    }
  }
}
