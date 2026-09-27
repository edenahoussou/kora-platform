import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/errors/failures.dart';
import '../../domain/entities/ops_notification.dart';
import '../../domain/repositories/notification_repository.dart';

part 'sent_notification_state.dart';

/// Page "Notifications envoyées" (Profil) — événements que le convoyeur a
/// lui-même déclenchés (voir `NotificationRepository.listSent`). Simple
/// vue de consultation : pas de cache local, pas d'état lu/non lu, pas de
/// démarrage automatique — chargée à l'ouverture de la page uniquement
/// (contrairement à `NotificationCubit`, qui est un singleton alimentant
/// aussi le badge cloche).
class SentNotificationCubit extends Cubit<SentNotificationState> {
  SentNotificationCubit(this._repository)
      : super(const SentNotificationState());

  final NotificationRepository _repository;

  Future<void> load() async {
    emit(state.copyWith(status: SentNotificationStatus.loading));

    final result = await _repository.listSent();
    result.when(
      onSuccess: (notifications) => emit(state.copyWith(
        status: notifications.isEmpty
            ? SentNotificationStatus.empty
            : SentNotificationStatus.success,
        notifications: notifications,
      )),
      onFailure: (Failure failure) => emit(state.copyWith(
        status: SentNotificationStatus.error,
        errorMessage: failure.message,
      )),
    );
  }
}
