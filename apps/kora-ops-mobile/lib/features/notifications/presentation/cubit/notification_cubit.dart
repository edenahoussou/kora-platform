import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../domain/entities/ops_notification.dart';
import '../../domain/repositories/notification_repository.dart';

part 'notification_state.dart';

/// Alertes opérationnelles du convoyeur (MEZ-97, contrat MEZ-96) — voir
/// document.md § Notifications. Récupère au démarrage puis après chaque
/// reconnexion (`connectivity_plus`, même stratégie que `SyncCubit`) ;
/// entre les deux, le cache local sqlite reste la source affichée, donc
/// les alertes non acquittées survivent à un redémarrage à froid, y
/// compris hors ligne.
///
/// Singleton (voir injection.dart) : même instance pour le badge cloche
/// (`NotificationButton`, tous écrans) et `NotificationsPage`.
class NotificationCubit extends Cubit<NotificationState> {
  NotificationCubit(
    this._repository,
    AuthCubit authCubit, {
    Connectivity? connectivity,
  })  : _connectivity = connectivity ?? Connectivity(),
        super(const NotificationState()) {
    // Terminal partagé : un agent qui se déconnecte ne doit jamais
    // laisser ses alertes (même non lues) visibles au prochain — même
    // règle que `SelectedDepartCubit`. Le bootstrap est lui aussi
    // déclenché ici, sur `AuthAuthenticated`, plutôt que depuis
    // SplashPage : couvre aussi bien "déjà connecté au lancement" que
    // "connexion en cours de session", sans risquer d'appeler
    // `GET /notifications` avant qu'une session existe (voir docstring
    // de `bootstrap`).
    _authSubscription = authCubit.stream.listen((AuthState state) {
      if (state is AuthUnauthenticated) {
        unawaited(_resetForLogout());
      } else if (state is AuthAuthenticated) {
        unawaited(bootstrap());
      }
    });
  }

  final NotificationRepository _repository;
  final Connectivity _connectivity;
  late final StreamSubscription<AuthState> _authSubscription;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;
  bool _isOnline = true;
  bool _bootstrapped = false;

  /// Déclenché automatiquement dès `AuthAuthenticated` (voir le
  /// `listen` du constructeur) — jamais besoin de l'appeler à la main.
  /// Idempotent : un second `AuthAuthenticated` (ex. `checkAuthStatus()`
  /// réémis au retour au premier plan) ne réabonne pas une deuxième fois
  /// l'écoute de connectivité.
  ///
  /// Restaure d'abord le cache local (immédiat, fonctionne hors ligne),
  /// puis tente un rafraîchissement réseau et démarre l'écoute continue
  /// de la connectivité pour les reconnexions suivantes. Volontairement
  /// jamais appelé avant authentification : `GET /notifications` exige
  /// une session, et un 401 ici purgerait la session via
  /// `SessionExpiryInterceptor` avant même la première connexion.
  Future<void> bootstrap() async {
    if (_bootstrapped) return;
    _bootstrapped = true;

    final List<OpsNotification> cached = await _repository.readCache();
    if (cached.isNotEmpty) {
      emit(state.copyWith(
        notifications: cached,
        unreadCount: cached.where((OpsNotification n) => !n.isRead).length,
        criticalUnreadCount: cached
            .where((OpsNotification n) =>
                !n.isRead && n.severity == OpsNotificationSeverity.critical)
            .length,
      ));
    }

    final List<ConnectivityResult> initial =
        await _connectivity.checkConnectivity();
    _isOnline = !initial.contains(ConnectivityResult.none);

    await refresh();

    _connectivitySubscription =
        _connectivity.onConnectivityChanged.listen(_onConnectivityChanged);
  }

  void _onConnectivityChanged(List<ConnectivityResult> results) {
    final bool isOnline = !results.contains(ConnectivityResult.none);
    final bool wasOffline = !_isOnline;
    _isOnline = isOnline;

    // Retour du réseau : on récupère les notifications manquées et on
    // réémet les marquages "lu" faits hors ligne — voir critère MEZ-97
    // "récupérer les notifications au démarrage et après reconnexion".
    if (isOnline && wasOffline) {
      unawaited(_repository.retryPendingReads());
      unawaited(refresh());
    }
  }

  Future<void> refresh() async {
    emit(state.copyWith(status: NotificationStatus.loading));

    final result = await _repository.list();
    final OpsNotificationCounts counts = await _repository.unreadCounts();

    result.when(
      onSuccess: (notifications) => emit(state.copyWith(
        status: NotificationStatus.success,
        notifications: notifications,
        unreadCount: counts.unread,
        criticalUnreadCount: counts.criticalUnread,
      )),
      onFailure: (failure) => emit(state.copyWith(
        status: NotificationStatus.error,
        errorMessage: failure.message,
      )),
    );
  }

  Future<void> markAsRead(String uuid) async {
    // Optimiste : l'UI réagit immédiatement, sans attendre le serveur
    // (utilisable hors ligne — voir NotificationRepository.markRead).
    final List<OpsNotification> updated = state.notifications
        .map((OpsNotification n) => n.uuid == uuid
            ? n.copyWith(isRead: true, readAt: DateTime.now())
            : n)
        .toList();
    emit(state.copyWith(
      notifications: updated,
      unreadCount: updated.where((OpsNotification n) => !n.isRead).length,
      criticalUnreadCount: updated
          .where((OpsNotification n) =>
              !n.isRead && n.severity == OpsNotificationSeverity.critical)
          .length,
    ));

    await _repository.markRead(uuid);
  }

  Future<void> markAllAsRead() async {
    final DateTime now = DateTime.now();
    final List<OpsNotification> updated = state.notifications
        .map((OpsNotification n) =>
            n.isRead ? n : n.copyWith(isRead: true, readAt: now))
        .toList();
    emit(state.copyWith(
      notifications: updated,
      unreadCount: 0,
      criticalUnreadCount: 0,
    ));

    await _repository.markAllRead();
  }

  Future<void> _resetForLogout() async {
    await _connectivitySubscription?.cancel();
    _connectivitySubscription = null;
    _bootstrapped = false;
    _isOnline = true;
    await _repository.clearCache();
    emit(const NotificationState());
  }

  @override
  Future<void> close() {
    unawaited(_connectivitySubscription?.cancel());
    unawaited(_authSubscription.cancel());
    return super.close();
  }
}
