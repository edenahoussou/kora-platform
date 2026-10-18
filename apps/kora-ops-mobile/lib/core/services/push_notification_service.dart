import 'dart:async';

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
import '../../features/notifications/presentation/cubit/notification_cubit.dart';
import '../constants/api_constants.dart';
import '../utils/app_logger.dart';

/// Handler FCM en arrière-plan / app fermée : doit être top-level. Le
/// message `notification` est affiché par le système, rien d'autre à faire.
@pragma('vm:entry-point')
Future<void> koraFirebaseBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

/// Notifications push FCM de KORA OPS : permission, token, enregistrement
/// auprès du back (`PUT auth/devices/{uuid}/push-token`) et ouverture de
/// l'écran Notifications au clic. Toute erreur est avalée : le push ne doit
/// jamais bloquer l'app.
class PushNotificationService {
  PushNotificationService(
      this._dio, this._authLocal, this._authCubit, this._notificationCubit);

  static const String _channelId = 'kora_ops_alerts';

  final Dio _dio;
  final AuthLocalDataSource _authLocal;
  final AuthCubit _authCubit;
  final NotificationCubit _notificationCubit;
  final FlutterLocalNotificationsPlugin _local =
      FlutterLocalNotificationsPlugin();

  bool _available = false;
  bool _pendingOpen = false;

  Future<void> init() async {
    try {
      await Firebase.initializeApp();
      _available = true;
    } catch (e) {
      AppLogger.d('Firebase indisponible, push désactivé : $e');
      return;
    }

    FirebaseMessaging.onBackgroundMessage(koraFirebaseBackgroundHandler);

    await _local.initialize(
      settings: const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
        iOS: DarwinInitializationSettings(),
      ),
      onDidReceiveNotificationResponse: (_) => _openNotifications(),
    );
    await _local
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(const AndroidNotificationChannel(
          _channelId,
          'Alertes opérationnelles',
          importance: Importance.high,
        ));

    // Premier plan : FCM n'affiche rien, on affiche nous-mêmes.
    FirebaseMessaging.onMessage.listen(_onForeground);
    FirebaseMessaging.onMessageOpenedApp.listen((_) => _openNotifications());
    // ignore: deprecated_member_use
    FirebaseMessaging.instance.onTokenRefresh
        .listen((String t) => unawaited(_register(t)));

    // Lancement à froid depuis une notification : on attend la session.
    final RemoteMessage? initial =
        await FirebaseMessaging.instance.getInitialMessage();
    _pendingOpen = initial != null;

    _authCubit.stream.listen((AuthState state) {
      if (state is AuthAuthenticated) {
        unawaited(_onAuthenticated());
      } else if (state is AuthUnauthenticated) {
        // Terminal partagé : l'ancien agent ne doit plus recevoir d'alertes.
        // ignore: deprecated_member_use
        unawaited(_safe(() => FirebaseMessaging.instance.deleteToken()));
      }
    });
    if (_authCubit.state is AuthAuthenticated) {
      unawaited(_onAuthenticated());
    }
  }

  Future<void> _onAuthenticated() async {
    if (!_available) return;
    try {
      final NotificationSettings settings =
          await FirebaseMessaging.instance.requestPermission();
      if (settings.authorizationStatus == AuthorizationStatus.denied) return;

      // ignore: deprecated_member_use
      final String? token = await FirebaseMessaging.instance.getToken();
      if (token != null) await _register(token);
    } catch (e) {
      AppLogger.d('Enregistrement push impossible : $e');
    }

    if (_pendingOpen) {
      _pendingOpen = false;
      // Laisse le splash terminer sa navigation avant de pousser l'écran.
      await Future<void>.delayed(const Duration(milliseconds: 1500));
      _openNotifications();
    }
  }

  Future<void> _register(String token) async {
    final session = await _authLocal.readSession();
    final String? deviceUuid = session?.device?.uuid;
    if (deviceUuid == null || deviceUuid.isEmpty) return;

    await _safe(() => _dio.put<dynamic>(
          ApiConstants.devicePushToken(deviceUuid),
          data: <String, dynamic>{'enabled': true, 'push_token': token},
        ));
  }

  void _onForeground(RemoteMessage message) {
    unawaited(_notificationCubit.refresh());
    final RemoteNotification? n = message.notification;
    if (n == null) return;
    unawaited(_local.show(
      id: n.hashCode,
      title: n.title,
      body: n.body,
      notificationDetails: const NotificationDetails(
        android: AndroidNotificationDetails(
          _channelId,
          'Alertes opérationnelles',
          importance: Importance.high,
          priority: Priority.high,
        ),
        iOS: DarwinNotificationDetails(),
      ),
    ));
  }

  void _openNotifications() {
    unawaited(_notificationCubit.refresh());
    final BuildContext? context = rootNavigatorKey.currentContext;
    if (context == null) return;
    GoRouter.of(context).push(AppRoutes.notifications);
  }

  Future<void> _safe(Future<Object?> Function() call) async {
    try {
      await call();
    } catch (e) {
      if (kDebugMode) AppLogger.d('Push : $e');
    }
  }
}
