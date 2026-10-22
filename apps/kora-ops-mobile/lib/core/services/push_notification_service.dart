import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart' show BuildContext;
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:go_router/go_router.dart';

import '../../app/router/app_routes.dart';
import '../../app/router/root_navigator_key.dart';
import '../../features/auth/data/datasources/auth_local_datasource.dart';
import '../../features/auth/presentation/cubit/auth_cubit.dart';
import '../../features/notifications/domain/entities/ops_notification.dart';
import '../../features/notifications/presentation/cubit/notification_cubit.dart';
import '../../features/notifications/presentation/notification_actions.dart';
import '../constants/api_constants.dart';
import '../utils/app_logger.dart';
import 'push/ops_push_payload.dart';
import 'push/push_token_store.dart';

/// Handler FCM en arrière-plan / app tuée : doit être top-level. Le bloc
/// `notification` est affiché par le système sur le canal
/// `kora_ops_alerts` (voir `FirebaseMessagingClient` côté back) ; le tap
/// est ensuite rattrapé par `onMessageOpenedApp` / `getInitialMessage`.
@pragma('vm:entry-point')
Future<void> koraFirebaseBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

/// Notifications push FCM de KORA OPS (P501) — enregistrement du token
/// auprès du back (`PUT auth/devices/{uuid}/push-token`), affichage au
/// premier plan et deep link au tap vers le départ / manifeste / synchro
/// concerné (voir [OpsPushPayload]).
///
/// Contrainte P501 : le push est un bonus. Aucune méthode ne lève, aucune
/// n'est attendue par le parcours boarding, et un enregistrement raté hors
/// ligne est rejoué au retour du réseau (voir [PushTokenStore]).
class PushNotificationService {
  PushNotificationService(
    this._dio,
    this._authLocal,
    this._authCubit,
    this._notificationCubit,
    this._tokenStore, {
    Connectivity? connectivity,
  }) : _connectivity = connectivity ?? Connectivity();

  static const String _channelId = 'kora_ops_alerts';
  static const String _channelName = 'Alertes opérationnelles';

  /// Écrans sur lesquels on ne déclenche pas de deep link : la session ou
  /// le terminal ne sont pas encore prêts, `AuthGuard` y redirigerait.
  static const Set<String> _notReadyRoutes = <String>{
    AppRoutes.splash,
    AppRoutes.onboarding,
    AppRoutes.terminalSetup,
    AppRoutes.loginEmail,
    AppRoutes.loginForgotPassword,
    AppRoutes.loginPin,
    AppRoutes.loginOtp,
    AppRoutes.loginOtpInvalid,
    AppRoutes.loginNewPin,
  };

  final Dio _dio;
  final AuthLocalDataSource _authLocal;
  final AuthCubit _authCubit;
  final NotificationCubit _notificationCubit;
  final PushTokenStore _tokenStore;
  final Connectivity _connectivity;
  final FlutterLocalNotificationsPlugin _local =
      FlutterLocalNotificationsPlugin();

  bool _available = false;
  bool _syncing = false;

  /// Le back a refusé le token (422 : push coupé dans les préférences) —
  /// on arrête de réessayer jusqu'à [setPushEnabled] ou une reconnexion.
  bool _refusedByServer = false;

  /// Tap en attente (app lancée depuis une notif avant la fin du splash /
  /// du PIN) : exécuté dès que l'agent est authentifié.
  OpsPushPayload? _pendingTap;
  bool _flushingTap = false;

  /// Le push est-il utilisable sur cet appareil (Firebase initialisé) ?
  bool get isAvailable => _available;

  Future<void> init() async {
    try {
      await Firebase.initializeApp();
      _available = true;
    } catch (e) {
      AppLogger.d('Firebase indisponible, push désactivé : $e');
      return;
    }

    try {
      FirebaseMessaging.onBackgroundMessage(koraFirebaseBackgroundHandler);
      await _initLocalNotifications();

      FirebaseMessaging.onMessage.listen(_onForeground);
      FirebaseMessaging.onMessageOpenedApp.listen(
          (RemoteMessage m) => _onTap(OpsPushPayload.fromData(m.data)));
      // ignore: deprecated_member_use
      FirebaseMessaging.instance.onTokenRefresh
          .listen((_) => unawaited(_syncToken()));

      // App tuée, lancée par le tap sur un push système.
      final RemoteMessage? initial =
          await FirebaseMessaging.instance.getInitialMessage();
      if (initial != null) {
        _pendingTap = OpsPushPayload.fromData(initial.data);
      }

      // App tuée, lancée par le tap sur une notif locale (premier plan).
      final NotificationAppLaunchDetails? launch =
          await _local.getNotificationAppLaunchDetails();
      if (_pendingTap == null && (launch?.didNotificationLaunchApp ?? false)) {
        _pendingTap =
            OpsPushPayload.decode(launch?.notificationResponse?.payload);
      }
    } catch (e) {
      AppLogger.d('Initialisation push partielle : $e');
    }

    _connectivity.onConnectivityChanged
        .listen((List<ConnectivityResult> results) {
      if (results.any((ConnectivityResult r) => r != ConnectivityResult.none)) {
        unawaited(_syncToken());
      }
    });

    _authCubit.stream.listen(_onAuthState);
    if (_authCubit.state is AuthAuthenticated) {
      unawaited(_onAuthenticated());
    }
  }

  // ---------------------------------------------------------------------
  // Préférence push (écran Préférences)
  // ---------------------------------------------------------------------

  /// Valeur `push_notifications` du compte côté back, `null` si
  /// injoignable (hors ligne) — l'écran affiche alors un état neutre.
  Future<bool?> fetchPushPreference() async {
    try {
      final Response<dynamic> response =
          await _dio.get<dynamic>(ApiConstants.preferences);
      final Object? data =
          response.data is Map ? (response.data as Map)['data'] : null;
      final Object? value = data is Map ? data['push_notifications'] : null;
      return value is bool ? value : null;
    } catch (e) {
      if (kDebugMode) AppLogger.d('Lecture préférence push : $e');
      return null;
    }
  }

  /// Active/coupe le push pour le compte. Couper purge les tokens de tous
  /// les appareils côté back (`UserPreferenceController`). Renvoie `false`
  /// si l'appel n'a pas abouti (hors ligne notamment).
  Future<bool> setPushEnabled(bool enabled) async {
    try {
      await _dio.put<dynamic>(
        ApiConstants.preferences,
        data: <String, dynamic>{'push_notifications': enabled},
      );
    } catch (e) {
      if (kDebugMode) AppLogger.d('Mise à jour préférence push : $e');
      return false;
    }

    await _tokenStore.clear();
    if (enabled) {
      _refusedByServer = false;
      await _requestPermission();
      await _syncToken();
    }
    return true;
  }

  /// L'utilisateur a refusé les notifications au niveau du système : le
  /// toggle ne suffit pas, il faut passer par les réglages Android.
  Future<bool> isSystemPermissionDenied() async {
    if (!_available) return false;
    try {
      final NotificationSettings settings =
          await FirebaseMessaging.instance.getNotificationSettings();
      return settings.authorizationStatus == AuthorizationStatus.denied;
    } catch (_) {
      return false;
    }
  }

  // ---------------------------------------------------------------------
  // Session
  // ---------------------------------------------------------------------

  void _onAuthState(AuthState state) {
    if (state is AuthAuthenticated) {
      unawaited(_onAuthenticated());
    } else if (state is AuthUnauthenticated) {
      unawaited(_onLoggedOut());
    }
  }

  Future<void> _onAuthenticated() async {
    if (!_available) return;
    _refusedByServer = false;
    await _requestPermission();
    await _syncToken();
    await _flushPendingTap();
  }

  /// Terminal partagé : l'agent suivant ne doit pas recevoir les alertes
  /// du précédent. Supprimer le token FCM l'invalide ; le back purge le
  /// token mort au prochain envoi (`SendOpsPushJob`).
  Future<void> _onLoggedOut() async {
    _refusedByServer = false;
    // Pas de token enregistré pour ce terminal (démarrage à froid sur
    // l'écran PIN...) : rien à invalider, on évite un nouveau token inutile.
    if (!_tokenStore.hasRegistration) return;
    await _tokenStore.clear();
    if (!_available) return;
    // ignore: deprecated_member_use
    await _safe(() => FirebaseMessaging.instance.deleteToken());
  }

  Future<void> _requestPermission() async {
    await _safe(() => FirebaseMessaging.instance.requestPermission());
  }

  // ---------------------------------------------------------------------
  // Token
  // ---------------------------------------------------------------------

  /// Envoie le token courant au back s'il n'y est pas déjà. Idempotent,
  /// sans effet hors ligne ou hors session ; rappelé au retour du réseau.
  Future<void> _syncToken() async {
    if (!_available || _syncing || _refusedByServer) return;
    if (_authCubit.state is! AuthAuthenticated) return;
    _syncing = true;
    try {
      final NotificationSettings settings =
          await FirebaseMessaging.instance.getNotificationSettings();
      if (settings.authorizationStatus == AuthorizationStatus.denied) return;

      final String? deviceUuid = (await _authLocal.readSession())?.device?.uuid;
      if (deviceUuid == null || deviceUuid.isEmpty) return;

      // ignore: deprecated_member_use
      final String? token = await FirebaseMessaging.instance.getToken();
      if (token == null || _tokenStore.isRegistered(deviceUuid, token)) return;

      await _dio.put<dynamic>(
        ApiConstants.devicePushToken(deviceUuid),
        data: <String, dynamic>{'enabled': true, 'push_token': token},
      );
      await _tokenStore.markRegistered(deviceUuid, token);
      AppLogger.d('Token push enregistré pour l\'appareil $deviceUuid');
    } on DioException catch (e) {
      if (e.response?.statusCode == 422) {
        // Push coupé dans les préférences ou Firebase non configuré côté
        // serveur : inutile de réessayer à chaque changement de réseau.
        _refusedByServer = true;
      }
      if (kDebugMode) AppLogger.d('Enregistrement push différé : $e');
    } catch (e) {
      // Pas de réseau pour getToken, etc. : rejoué au retour du réseau.
      if (kDebugMode) AppLogger.d('Enregistrement push différé : $e');
    } finally {
      _syncing = false;
    }
  }

  // ---------------------------------------------------------------------
  // Réception / tap
  // ---------------------------------------------------------------------

  Future<void> _initLocalNotifications() async {
    await _local.initialize(
      settings: const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
        iOS: DarwinInitializationSettings(),
      ),
      onDidReceiveNotificationResponse: (NotificationResponse response) =>
          _onTap(OpsPushPayload.decode(response.payload)),
    );
    await _local
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(const AndroidNotificationChannel(
          _channelId,
          _channelName,
          importance: Importance.high,
        ));
  }

  /// Premier plan : FCM n'affiche rien, on affiche nous-mêmes en gardant
  /// la charge utile pour le deep link au tap.
  void _onForeground(RemoteMessage message) {
    unawaited(_safe(() => _notificationCubit.refresh()));
    final RemoteNotification? n = message.notification;
    if (n == null) return;

    final OpsPushPayload payload = OpsPushPayload.fromData(message.data);
    final bool critical = payload.severity == OpsNotificationSeverity.critical;

    unawaited(_safe(() => _local.show(
          id: message.messageId?.hashCode ?? n.hashCode,
          title: n.title,
          body: n.body,
          payload: payload.encode(),
          notificationDetails: NotificationDetails(
            android: AndroidNotificationDetails(
              _channelId,
              _channelName,
              importance: Importance.high,
              priority: critical ? Priority.max : Priority.high,
              category: critical
                  ? AndroidNotificationCategory.alarm
                  : AndroidNotificationCategory.message,
            ),
            iOS: const DarwinNotificationDetails(),
          ),
        )));
  }

  void _onTap(OpsPushPayload? payload) {
    _pendingTap = payload ??
        const OpsPushPayload(
          type: 'unknown',
          action: OpsNotificationAction.none,
          severity: OpsNotificationSeverity.info,
        );
    unawaited(_flushPendingTap());
  }

  /// Exécute le tap en attente une fois l'agent authentifié et l'app sur
  /// un écran métier (jamais pendant splash / PIN / liaison terminal).
  Future<void> _flushPendingTap() async {
    if (_pendingTap == null || _flushingTap) return;
    if (_authCubit.state is! AuthAuthenticated) return;
    _flushingTap = true;
    try {
      final BuildContext? context = await _waitForAppScreen();
      final OpsPushPayload? payload = _pendingTap;
      if (context == null || payload == null || !context.mounted) return;
      _pendingTap = null;

      unawaited(_refreshAndMarkRead(payload.notificationUuid));

      if (payload.action == OpsNotificationAction.none) {
        await GoRouter.of(context).push(AppRoutes.notifications);
      } else {
        await runOpsNotificationAction(
          context,
          payload.action,
          departureUuid: payload.departureUuid,
        );
      }
    } catch (e) {
      AppLogger.d('Deep link push impossible : $e');
    } finally {
      _flushingTap = false;
    }
  }

  /// Attend (max ~15 s) que le routeur ait quitté splash/login.
  Future<BuildContext?> _waitForAppScreen() async {
    for (int attempt = 0; attempt < 30; attempt++) {
      final BuildContext? context = rootNavigatorKey.currentContext;
      if (context != null && context.mounted) {
        final String path = GoRouter.of(context)
            .routerDelegate
            .currentConfiguration
            .uri
            .path;
        if (!_notReadyRoutes.contains(path)) return context;
      }
      if (_authCubit.state is! AuthAuthenticated) return null;
      await Future<void>.delayed(const Duration(milliseconds: 500));
    }
    return null;
  }

  Future<void> _refreshAndMarkRead(String? notificationUuid) async {
    await _safe(() => _notificationCubit.refresh());
    if (notificationUuid != null) {
      await _safe(() => _notificationCubit.markAsRead(notificationUuid));
    }
  }

  Future<void> _safe(Future<Object?> Function() call) async {
    try {
      await call();
    } catch (e) {
      if (kDebugMode) AppLogger.d('Push : $e');
    }
  }
}
