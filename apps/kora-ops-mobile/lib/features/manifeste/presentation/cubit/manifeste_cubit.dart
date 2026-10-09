import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/errors/failures.dart';
import '../../../../core/result/result.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/domain/entities/pending_operation.dart';
import '../../../synchro/presentation/cubit/sync_cubit.dart';
import '../../domain/entities/manifest_snapshot.dart';
import '../../domain/entities/passenger_correction.dart';
import '../../domain/repositories/manifeste_repository.dart';
import '../../domain/repositories/passenger_correction_repository.dart';

part 'manifeste_state.dart';

/// Charge et met à jour la liste des passagers du départ courant. Écoute
/// [SelectedDepartCubit] pour se recharger automatiquement quand l'agent
/// change de départ (que ce soit depuis `AccueilPage` ou le sélecteur de
/// l'en-tête du Manifeste — voir document.md § Sélection du départ).
///
/// [Depart.id] est un UUID de départ réel (`GET /ops/departures/{uuid}/
/// manifest`) — la sélection de départ (onglet Tournée) est réelle depuis
/// MEZ-92 (`GET /ops/departures`, voir `DepartureListCubit`).
class ManifesteCubit extends Cubit<ManifesteState> {
  ManifesteCubit({
    required SelectedDepartCubit selectedDepartCubit,
    required SyncCubit syncCubit,
    required ManifesteRepository repository,
    PassengerCorrectionRepository? passengerCorrectionRepository,
  })  : _syncCubit = syncCubit,
        _repository = repository,
        _corrections = passengerCorrectionRepository,
        super(const ManifesteState()) {
    _subscription = selectedDepartCubit.stream.listen(_onDepartChanged);
    if (_corrections != null) {
      // Retour de la connexion : renvoie les corrections passager faites
      // hors ligne, puis rafraîchit le manifeste si au moins une est passée.
      _onlineSubscription = syncCubit.stream
          .map((SyncState s) => s.isOnline)
          .distinct()
          .listen(_onConnectivityChanged);
    }
    if (selectedDepartCubit.state != null) {
      _onDepartChanged(selectedDepartCubit.state);
    }
  }

  final SyncCubit _syncCubit;
  final ManifesteRepository _repository;
  final PassengerCorrectionRepository? _corrections;
  late final dynamic _subscription;
  dynamic _onlineSubscription;

  Depart? _depart;

  Future<void> _onDepartChanged(Depart? depart) async {
    _depart = depart;
    if (depart == null) {
      emit(const ManifesteState());
      return;
    }
    await load();
  }

  Future<void> load() async {
    final Depart? depart = _depart;
    if (depart == null) {
      emit(const ManifesteState());
      return;
    }
    emit(state.copyWith(status: ManifesteStatus.loading));

    // Corrections passager faites hors ligne : envoyées avant de lire le
    // manifeste (la version serveur change alors, donc rechargement complet).
    final PassengerCorrectionRepository? corrections = _corrections;
    if (corrections != null) {
      try {
        await corrections.flushPending(depart.id);
      } on Object {
        // Best-effort : le manifeste doit se charger même si l'envoi échoue.
      }
    }

    final result = await _repository.load(depart.id);
    List<Passenger>? overlaid;
    Set<String> pendingCorrections = const <String>{};
    if (corrections != null && result is Success<ManifestSnapshot>) {
      try {
        overlaid = await corrections.overlayPending(
            depart.id, result.value.passengers);
        pendingCorrections = await corrections.pendingTicketUuids(depart.id);
      } on Object {
        overlaid = null;
      }
    }
    result.when(
      onSuccess: (snapshot) => emit(state.copyWith(
        status: ManifesteStatus.success,
        passengers: overlaid ?? snapshot.passengers,
        pendingCorrectionTicketUuids: pendingCorrections,
        manifestVersion: snapshot.version,
        fetchedAt: snapshot.fetchedAt,
        isStale: snapshot.isStale,
        isFromCache: snapshot.isFromCache,
        // Sentinelle dans copyWith : `null` (inconnu) remplace bien la
        // valeur du départ précédent au lieu de la conserver (MEZ-94).
        isInternational: snapshot.isInternational,
        // Un rafraîchissement complet fait foi : les indicateurs "en
        // attente de sync" purement locaux n'ont plus lieu d'être — le
        // vrai statut serveur (ou, à défaut, l'état persistant de la
        // file — voir SyncStatusBanner) prend le relai.
        pendingReleaseTicketUuids: const <String>{},
      )),
      onFailure: (Failure failure) => emit(state.copyWith(
          status: ManifesteStatus.error, errorMessage: failure.message)),
    );
  }

  /// Met à jour le statut d'un passager (validation/refus de scan,
  /// descente anticipée…) et journalise l'opération dans la file de
  /// synchronisation — voir document.md § Synchro.
  void updateStatut(String passengerId, BoardingStatus statut) {
    final List<Passenger> updated = state.passengers
        .map((Passenger p) =>
            p.id == passengerId ? p.copyWith(statut: statut) : p)
        .toList();
    emit(state.copyWith(passengers: updated));

    final Passenger passenger =
        updated.firstWhere((Passenger p) => p.id == passengerId);
    _syncCubit.enqueue(
      PendingOperation(
        id: '${passengerId}_${DateTime.now().microsecondsSinceEpoch}',
        label:
            '${statut.label} — ${passenger.nomComplet} (siège ${passenger.siege})',
        createdAtLabel: 'À l\'instant',
      ),
    );
  }

  /// Reflète localement un embarquement déjà validé par `BoardingRepository`
  /// (voir `ScanBoardingCubit`) — met à jour l'affichage et le compteur
  /// sans attendre le rafraîchissement complet du manifeste (critère
  /// d'acceptation MEZ-91), et persiste ce statut dans le cache local
  /// (restaurable après un redémarrage à froid — MEZ-87). Contrairement à
  /// [updateStatut], n'enfile rien dans `SyncCubit` : la synchronisation
  /// de cette opération est déjà suivie dans `boarding_operations`
  /// (sqlite) par `BoardingRepository`, pas dans la file mock de
  /// `SyncCubit` — ne rien afficher en double. Silencieux si
  /// [ticketUuid] n'est pas dans le manifeste en cache (ex. scan accepté
  /// hors ligne, manifeste pas encore rafraîchi).
  Future<void> markBoarded(String ticketUuid) async {
    final bool exists =
        state.passengers.any((Passenger p) => p.id == ticketUuid);
    if (!exists) return;

    emit(state.copyWith(
      passengers: state.passengers
          .map((Passenger p) => p.id == ticketUuid
              ? p.copyWith(statut: BoardingStatus.embarque)
              : p)
          .toList(),
    ));

    final Depart? depart = _depart;
    if (depart != null) {
      await _repository.persistStatusUpdate(
        departureUuid: depart.id,
        ticketUuid: ticketUuid,
        statut: BoardingStatus.embarque,
      );
    }
  }

  /// Reflète localement une libération de siège **acceptée** par le
  /// serveur (descente anticipée ou no-show — voir `SeatReleaseRepository`)
  /// et persiste ce statut (MEZ-87, restaurable après redémarrage à
  /// froid). N'appeler que pour un résultat `accepted` — jamais pour
  /// `pendingSync`/`conflict`/`rejected` : afficher "libéré" avant
  /// acceptation serveur violerait le critère d'acceptation MEZ-87
  /// "empêcher une nouvelle vente locale fondée sur une libération non
  /// acceptée" (voir [markSeatReleasePending] pour le cas hors ligne).
  Future<void> markSeatReleaseAccepted(
      String ticketUuid, BoardingStatus statut) async {
    final bool exists =
        state.passengers.any((Passenger p) => p.id == ticketUuid);
    if (!exists) return;

    final Set<String> stillPending =
        Set<String>.of(state.pendingReleaseTicketUuids)..remove(ticketUuid);
    emit(state.copyWith(
      passengers: state.passengers
          .map((Passenger p) =>
              p.id == ticketUuid ? p.copyWith(statut: statut) : p)
          .toList(),
      pendingReleaseTicketUuids: stillPending,
    ));

    final Depart? depart = _depart;
    if (depart != null) {
      await _repository.persistStatusUpdate(
        departureUuid: depart.id,
        ticketUuid: ticketUuid,
        statut: statut,
      );
    }
  }

  /// Signale une libération de siège enregistrée hors ligne, en attente
  /// de synchronisation — n'altère jamais [Passenger.statut] (voir
  /// docstring [markSeatReleaseAccepted]), seulement un indicateur visuel
  /// sur la ligne du manifeste (voir `ManifestePage`, badge "en attente
  /// de sync").
  void markSeatReleasePending(String ticketUuid) {
    emit(state.copyWith(
      pendingReleaseTicketUuids: Set<String>.of(state.pendingReleaseTicketUuids)
        ..add(ticketUuid),
    ));
  }

  /// Reflète localement une annulation exceptionnelle d'embarquement
  /// (voir `BoardingRepository.reverse`) : le passager redevient "en
  /// attente" (le segment repasse "ticketed" côté serveur — voir
  /// `ReverseBoardingEventAction` — donc de nouveau scannable).
  Future<void> markBoardingReversed(String ticketUuid) async {
    final bool exists =
        state.passengers.any((Passenger p) => p.id == ticketUuid);
    if (!exists) return;

    emit(state.copyWith(
      passengers: state.passengers
          .map((Passenger p) => p.id == ticketUuid
              ? p.copyWith(statut: BoardingStatus.enAttente)
              : p)
          .toList(),
    ));

    final Depart? depart = _depart;
    if (depart != null) {
      await _repository.persistStatusUpdate(
        departureUuid: depart.id,
        ticketUuid: ticketUuid,
        statut: BoardingStatus.enAttente,
      );
    }
  }

  Future<void> _onConnectivityChanged(bool isOnline) async {
    final Depart? depart = _depart;
    final PassengerCorrectionRepository? corrections = _corrections;
    if (!isOnline || depart == null || corrections == null) return;
    if (state.pendingCorrectionTicketUuids.isEmpty) return;

    try {
      final int sent = await corrections.flushPending(depart.id);
      if (sent > 0) await load();
    } on Object {
      // Réessai au prochain retour de connexion / chargement.
    }
  }

  /// Correction par le convoyeur des informations d'un passager (nom,
  /// téléphone, passeport, vaccin...), avant comme après l'embarquement.
  /// Met à jour l'écran immédiatement en cas de succès — y compris
  /// enregistrée hors ligne, auquel cas la fiche porte le badge « en
  /// attente de sync ». Un refus du serveur n'altère rien et est renvoyé
  /// tel quel pour affichage.
  Future<Result<PassengerCorrectionOutcome>> correctPassenger({
    required String ticketUuid,
    required PassengerCorrection correction,
    String? reason,
  }) async {
    final Depart? depart = _depart;
    final PassengerCorrectionRepository? corrections = _corrections;
    if (depart == null || corrections == null) {
      return const Result<PassengerCorrectionOutcome>.failure(
        ServerFailure('Correction indisponible : aucun départ sélectionné.'),
      );
    }

    final Result<PassengerCorrectionOutcome> result = await corrections.correct(
      departureUuid: depart.id,
      ticketUuid: ticketUuid,
      correction: correction,
      reason: reason,
    );

    if (result is Success<PassengerCorrectionOutcome>) {
      final Set<String> pending =
          Set<String>.of(state.pendingCorrectionTicketUuids);
      if (result.value == PassengerCorrectionOutcome.pendingSync) {
        pending.add(ticketUuid);
      }
      emit(state.copyWith(
        passengers: state.passengers
            .map((Passenger p) =>
                p.id == ticketUuid ? p.withCorrection(correction) : p)
            .toList(),
        pendingCorrectionTicketUuids: pending,
      ));
    }
    return result;
  }

  Passenger? byId(String id) {
    for (final Passenger passenger in state.passengers) {
      if (passenger.id == id) return passenger;
    }
    return null;
  }

  /// Recherche locale par code court (voir `ScanBoardingCubit`, "recherche
  /// manuelle pour les QR illisibles") — filtre le manifeste déjà chargé,
  /// fonctionne donc aussi hors ligne. Ne renvoie jamais de prix (le
  /// manifeste n'en contient pas).
  List<Passenger> searchByShortCode(String query) {
    final String needle = query.trim().toUpperCase();
    if (needle.isEmpty) return const <Passenger>[];

    return state.passengers
        .where((Passenger p) => p.ticketId.toUpperCase().contains(needle))
        .toList();
  }

  @override
  Future<void> close() {
    // ignore: avoid_dynamic_calls
    _subscription.cancel();
    // ignore: avoid_dynamic_calls
    _onlineSubscription?.cancel();
    return super.close();
  }
}
