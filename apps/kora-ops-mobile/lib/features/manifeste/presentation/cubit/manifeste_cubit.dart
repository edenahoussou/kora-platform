import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/errors/failures.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/domain/entities/pending_operation.dart';
import '../../../synchro/presentation/cubit/sync_cubit.dart';
import '../../domain/repositories/manifeste_repository.dart';

part 'manifeste_state.dart';

/// Charge et met à jour la liste des passagers du départ courant. Écoute
/// [SelectedDepartCubit] pour se recharger automatiquement quand l'agent
/// change de départ (que ce soit depuis `AccueilPage` ou le sélecteur de
/// l'en-tête du Manifeste — voir document.md § Sélection du départ).
///
/// [Depart.id] doit être un UUID de départ réel (`GET /ops/departures/
/// {uuid}/manifest`) — la sélection de départ elle-même (onglet Tournée)
/// reste mockée pour l'instant (hors périmètre MEZ-91, voir `MockOpsData
/// Source`) ; ce cubit fonctionnera dès que la sélection de départ fournira
/// de vrais UUID, sans changement ici.
class ManifesteCubit extends Cubit<ManifesteState> {
  ManifesteCubit({
    required SelectedDepartCubit selectedDepartCubit,
    required SyncCubit syncCubit,
    required ManifesteRepository repository,
  })  : _syncCubit = syncCubit,
        _repository = repository,
        super(const ManifesteState()) {
    _subscription = selectedDepartCubit.stream.listen(_onDepartChanged);
    if (selectedDepartCubit.state != null) {
      _onDepartChanged(selectedDepartCubit.state);
    }
  }

  final SyncCubit _syncCubit;
  final ManifesteRepository _repository;
  late final dynamic _subscription;

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

    final result = await _repository.load(depart.id);
    result.when(
      onSuccess: (passengers) => emit(state.copyWith(
          status: ManifesteStatus.success, passengers: passengers)),
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
  /// d'acceptation MEZ-91). Contrairement à [updateStatut], n'enfile rien
  /// dans `SyncCubit` : la synchronisation de cette opération est déjà
  /// suivie dans `boarding_operations` (sqlite) par `BoardingRepository`,
  /// pas dans la file mock de `SyncCubit` — ne rien afficher en double.
  /// Silencieux si [ticketUuid] n'est pas dans le manifeste en cache (ex.
  /// scan accepté hors ligne, manifeste pas encore rafraîchi).
  void markBoarded(String ticketUuid) {
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
    return super.close();
  }
}
