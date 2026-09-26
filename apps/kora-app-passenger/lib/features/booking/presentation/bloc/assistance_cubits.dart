import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../domain/entities/assisted_booking.dart';
import '../../domain/entities/seat_hold.dart';
import '../../domain/repositories/assisted_booking_repository.dart';

/// Couche PRESENTATION : confier la réservation retenue au support KORA.
///
/// Le blocage des sièges ne vit pas ici : c'est [SeatHoldBloc] qui le fait,
/// comme pour le paiement en ligne. Ce cubit reçoit la réservation déjà
/// retenue, avec ses voyageurs — le serveur n'accepte d'envoyer en
/// assistance qu'une réservation `held`.
class AssistanceRequestCubit extends Cubit<AssistanceRequestState> {
  AssistanceRequestCubit(this._repository)
    : super(const AssistanceRequestState());

  final AssistedBookingRepository _repository;

  /// Le canal par lequel le voyageur veut être aidé : WhatsApp par
  /// défaut, c'est là que le message part déjà rédigé.
  void chooseChannel(AssistanceChannel channel) =>
      emit(state.copyWith(channel: channel, clearFailure: true));

  /// Envoie la réservation au support KORA, puis garde sa trace.
  ///
  /// **La clé ne dépend que de la réservation** : un envoi coupé par le
  /// réseau, puis retenté, rend le dossier déjà créé au lieu d'en ouvrir
  /// un second — le serveur n'en admet qu'un par réservation.
  Future<void> send({
    required String reservationId,
    required String contactName,
    required String contactPhone,
    required AssistanceRecord Function(AssistedBooking booking) record,
  }) async {
    if (state.isSending) return;

    emit(state.copyWith(isSending: true, clearFailure: true));

    final result = await _repository.request(
      reservationId: reservationId,
      channel: state.channel,
      contactName: contactName,
      contactPhone: contactPhone,
      idempotencyKey: 'kora-assist-$reservationId',
    );

    await result.fold(
      (failure) async =>
          emit(state.copyWith(isSending: false, failure: failure)),
      (booking) async {
        // Gardée avant d'annoncer le succès : le voyageur quitte l'écran
        // dans la foulée pour WhatsApp, et sa demande doit l'attendre au
        // retour.
        await _repository.remember(record(booking));
        emit(state.copyWith(isSending: false, sent: booking));
      },
    );
  }
}

class AssistanceRequestState extends Equatable {
  const AssistanceRequestState({
    this.channel = AssistanceChannel.whatsapp,
    this.isSending = false,
    this.sent,
    this.failure,
  });

  final AssistanceChannel channel;
  final bool isSending;

  /// Le dossier créé : l'écran passe alors à la confirmation.
  final AssistedBooking? sent;
  final Failure? failure;

  AssistanceRequestState copyWith({
    AssistanceChannel? channel,
    bool? isSending,
    AssistedBooking? sent,
    Failure? failure,
    bool clearFailure = false,
  }) {
    return AssistanceRequestState(
      channel: channel ?? this.channel,
      isSending: isSending ?? this.isSending,
      sent: sent ?? this.sent,
      failure: clearFailure ? null : (failure ?? this.failure),
    );
  }

  @override
  List<Object?> get props => [channel, isSending, sent, failure];
}

/// Couche PRESENTATION : le suivi d'une demande, et le retour au paiement
/// en ligne.
class AssistanceStatusCubit extends Cubit<AssistanceStatusState> {
  AssistanceStatusCubit(this._repository)
    : super(const AssistanceStatusState());

  final AssistedBookingRepository _repository;

  /// [initial] : le dossier que l'envoi vient de rendre — l'écran s'ouvre
  /// dessus sans attendre le réseau.
  Future<void> load(String reservationId, {AssistedBooking? initial}) async {
    final record = await _repository.record(reservationId);
    emit(
      state.copyWith(
        reservationId: reservationId,
        record: record,
        booking: initial,
        isLoading: initial == null,
      ),
    );
    if (initial == null) await refresh();
  }

  /// Relit le dossier — à l'ouverture, au retour de WhatsApp, à la demande.
  Future<void> refresh() async {
    final reservationId = state.reservationId;
    if (reservationId == null) return;

    emit(state.copyWith(isLoading: true, clearFailure: true));
    final result = await _repository.fetch(reservationId);
    result.fold(
      // Le dossier déjà affiché reste : un réseau qui coupe ne doit pas
      // effacer la référence sous les yeux du voyageur.
      (failure) => emit(state.copyWith(isLoading: false, failure: failure)),
      (booking) => emit(state.copyWith(isLoading: false, booking: booking)),
    );
  }

  /// Reprend les sièges demandés et rend la réservation payable en ligne.
  ///
  /// Clé liée à la réservation : un second appui, ou un retour après un
  /// paiement abandonné, rend la même reprise au lieu d'en tenter une autre.
  Future<void> resumeOnline() async {
    final reservationId = state.reservationId;
    final seats = state.record?.seats ?? const [];
    if (reservationId == null || seats.isEmpty || state.isResuming) return;

    emit(state.copyWith(isResuming: true, clearFailure: true));
    final result = await _repository.resumeOnline(
      reservationId: reservationId,
      seats: seats,
      idempotencyKey: 'kora-resume-$reservationId',
    );

    await result.fold((failure) async {
      emit(state.copyWith(isResuming: false, failure: failure));
      // Un agent a pu prendre le dossier entre-temps : le statut affiché
      // doit le dire.
      await refresh();
    }, (hold) async => emit(state.copyWith(isResuming: false, resumed: hold)));
  }
}

class AssistanceStatusState extends Equatable {
  const AssistanceStatusState({
    this.reservationId,
    this.record,
    this.booking,
    this.isLoading = true,
    this.isResuming = false,
    this.resumed,
    this.failure,
  });

  final String? reservationId;

  /// La trace locale : de quoi afficher la référence hors ligne, et les
  /// sièges à reprendre en ligne.
  final AssistanceRecord? record;
  final AssistedBooking? booking;
  final bool isLoading;
  final bool isResuming;

  /// La réservation de nouveau retenue : l'écran part au paiement.
  final SeatHold? resumed;
  final Failure? failure;

  /// La reprise en ligne est possible, et l'app sait quels sièges demander.
  bool get canResumeOnline =>
      (booking?.canResumeOnline ?? false) &&
      (record?.seats.isNotEmpty ?? false);

  AssistanceStatusState copyWith({
    String? reservationId,
    AssistanceRecord? record,
    AssistedBooking? booking,
    bool? isLoading,
    bool? isResuming,
    SeatHold? resumed,
    Failure? failure,
    bool clearFailure = false,
  }) {
    return AssistanceStatusState(
      reservationId: reservationId ?? this.reservationId,
      record: record ?? this.record,
      booking: booking ?? this.booking,
      isLoading: isLoading ?? this.isLoading,
      isResuming: isResuming ?? this.isResuming,
      resumed: resumed ?? this.resumed,
      failure: clearFailure ? null : (failure ?? this.failure),
    );
  }

  @override
  List<Object?> get props => [
    reservationId,
    record,
    booking,
    isLoading,
    isResuming,
    resumed,
    failure,
  ];
}

/// Couche PRESENTATION : les demandes envoyées depuis ce téléphone, pour
/// l'onglet Voyages.
class AssistanceRequestsCubit extends Cubit<AssistanceRequestsState> {
  AssistanceRequestsCubit(this._repository)
    : super(const AssistanceRequestsState()) {
    // Une demande envoyée pendant que l'onglet reste en mémoire : la liste
    // se relit d'elle-même.
    _changes = _repository.changes.listen((_) => load());
  }

  final AssistedBookingRepository _repository;
  late final StreamSubscription<void> _changes;

  @override
  Future<void> close() {
    _changes.cancel();
    return super.close();
  }

  /// Les traces d'abord, pour afficher tout de suite ; les statuts ensuite,
  /// ensemble.
  Future<void> load() async {
    final records = await _repository.records();
    emit(AssistanceRequestsState(records: records, statuses: state.statuses));
    if (records.isEmpty) return;

    final results = await Future.wait([
      for (final record in records) _repository.fetch(record.reservationId),
    ]);

    final statuses = <String, AssistanceStatus>{...state.statuses};
    for (final (index, result) in results.indexed) {
      result.fold(
        (_) {},
        (booking) => statuses[records[index].reservationId] = booking.status,
      );
    }
    emit(AssistanceRequestsState(records: records, statuses: statuses));
  }
}

class AssistanceRequestsState extends Equatable {
  const AssistanceRequestsState({
    this.records = const [],
    this.statuses = const {},
  });

  final List<AssistanceRecord> records;

  /// Le dernier statut connu, par réservation. Absent tant que le serveur
  /// n'a pas répondu.
  final Map<String, AssistanceStatus> statuses;

  /// Les demandes qui attendent encore l'agence — statut inconnu compris :
  /// hors ligne, une demande reste « en attente » plutôt que de disparaître.
  List<AssistanceRequestView> get openRequests => [
    for (final record in records)
      if (!(statuses[record.reservationId]?.isClosed ?? false))
        AssistanceRequestView(record, statuses[record.reservationId]),
  ];

  /// Celles qui n'ont pas abouti : refusées, expirées, indisponibles,
  /// annulées. Elles se rangent avec les billets annulés.
  List<AssistanceRequestView> get deadEndRequests => [
    for (final record in records)
      if (statuses[record.reservationId]?.isDeadEnd ?? false)
        AssistanceRequestView(record, statuses[record.reservationId]),
  ];

  @override
  List<Object?> get props => [records, statuses];
}

/// Une demande et son dernier statut connu, prête à être affichée.
class AssistanceRequestView {
  const AssistanceRequestView(this.record, this.status);

  final AssistanceRecord record;

  /// `null` tant que le serveur n'a pas répondu.
  final AssistanceStatus? status;
}
