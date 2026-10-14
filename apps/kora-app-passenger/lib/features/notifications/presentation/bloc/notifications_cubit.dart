import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../domain/entities/app_notification.dart';
import '../../domain/entities/notification_feed.dart';
import '../../domain/repositories/notification_repository.dart';

/// Couche PRESENTATION : le centre de notifications.
///
/// Deux partis pris, pour que l'écran reste vivant :
///
/// - **Marquer lu ne recharge rien.** La pastille s'éteint tout de suite,
///   et l'appel part derrière. Attendre le serveur pour un geste sans
///   conséquence donnerait une interface qui traîne.
/// - **Charger la suite n'efface pas ce qui est affiché.** Les pages
///   s'ajoutent, l'écran ne repart jamais d'une liste vide.
class NotificationsCubit extends Cubit<NotificationsState> {
  NotificationsCubit(this._repository) : super(const NotificationsState());

  final NotificationRepository _repository;

  /// Première page, ou rechargement complet après un geste « tirer pour
  /// rafraîchir ».
  Future<void> load() async {
    if (state.isLoading) return;
    emit(state.copyWith(isLoading: true, clearFailure: true));

    final result = await _repository.fetch();

    if (isClosed) return;
    emit(
      result.fold(
        (failure) => state.copyWith(isLoading: false, failure: failure),
        (feed) => NotificationsState(feed: feed),
      ),
    );
  }

  /// La page suivante, ajoutée à la suite de celles déjà lues.
  Future<void> loadMore() async {
    if (state.isLoading || state.isLoadingMore || !state.feed.hasMore) return;
    emit(state.copyWith(isLoadingMore: true, clearFailure: true));

    final result = await _repository.fetch(page: state.feed.page + 1);

    if (isClosed) return;
    emit(
      result.fold(
        (failure) => state.copyWith(isLoadingMore: false, failure: failure),
        (feed) => NotificationsState(
          feed: NotificationFeed(
            notifications: [...state.feed.notifications, ...feed.notifications],
            unreadCount: feed.unreadCount,
            page: feed.page,
            lastPage: feed.lastPage,
          ),
        ),
      ),
    );
  }

  /// Marque une notification lue. Sans effet si elle l'est déjà.
  Future<void> markRead(AppNotification notification) async {
    if (!notification.isUnread) return;

    emit(
      state.copyWith(
        feed: NotificationFeed(
          notifications: [
            for (final item in state.feed.notifications)
              item.id == notification.id ? item.markRead() : item,
          ],
          unreadCount: (state.feed.unreadCount - 1).clamp(0, 1 << 30),
          page: state.feed.page,
          lastPage: state.feed.lastPage,
        ),
      ),
    );

    // Un échec ne se dit pas : la notification se rallumera au prochain
    // chargement, et prévenir qu'une pastille n'a pas été éteinte serait
    // plus perturbant que la pastille elle-même.
    await _repository.markRead(notification.id);
  }

  /// Marque toutes les notifications lues.
  Future<void> markAllRead() async {
    if (state.feed.unreadCount == 0) return;

    emit(
      state.copyWith(
        feed: NotificationFeed(
          notifications: [
            for (final item in state.feed.notifications) item.markRead(),
          ],
          page: state.feed.page,
          lastPage: state.feed.lastPage,
        ),
      ),
    );

    await _repository.markAllRead();
  }
}

class NotificationsState extends Equatable {
  const NotificationsState({
    this.feed = const NotificationFeed(),
    this.isLoading = false,
    this.isLoadingMore = false,
    this.failure,
  });

  final NotificationFeed feed;
  final bool isLoading;
  final bool isLoadingMore;
  final Failure? failure;

  /// Vrai tant que rien n'a encore été reçu : c'est le seul moment où
  /// l'écran a le droit d'occuper toute la page avec un chargement.
  bool get isEmptyAndLoading => isLoading && feed.notifications.isEmpty;

  /// Vrai quand le serveur a répondu, et qu'il n'y a rien à montrer.
  bool get isEmpty =>
      !isLoading && failure == null && feed.notifications.isEmpty;

  NotificationsState copyWith({
    NotificationFeed? feed,
    bool? isLoading,
    bool? isLoadingMore,
    Failure? failure,
    bool clearFailure = false,
  }) => NotificationsState(
    feed: feed ?? this.feed,
    isLoading: isLoading ?? this.isLoading,
    isLoadingMore: isLoadingMore ?? this.isLoadingMore,
    failure: clearFailure ? null : (failure ?? this.failure),
  );

  @override
  List<Object?> get props => [feed, isLoading, isLoadingMore, failure];
}
