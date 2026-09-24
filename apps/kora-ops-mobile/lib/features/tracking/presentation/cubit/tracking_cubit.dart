import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../ops/domain/entities/depart.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../terminal/data/datasources/terminal_local_datasource.dart';
import '../../domain/entities/tracking_availability.dart';
import '../../domain/entities/tracking_runtime_status.dart';
import '../../domain/entities/tracking_stop_reason.dart';
import '../../domain/repositories/tracking_repository.dart';

part 'tracking_state.dart';

/// Démarre/arrête le suivi de position du convoyeur en fonction de la
/// progression du départ sélectionné (MEZ-101) — écoute
/// [SelectedDepartCubit] plutôt que d'être piloté explicitement par
/// chaque écran : `departed` → démarre, `arrived`/`cancelled` ou
/// désélection → arrête. Même conception que `SyncCubit` (singleton,
/// écoute continue, voir `injection.dart`).
///
/// Ne mesure jamais de position elle-même — [_repository] délègue tout au
/// service premier plan (`TrackingTaskHandler`, isolate séparé, voir sa
/// docstring). Ce cubit ne fait que refléter l'état pour l'UI
/// (`TrackingStatusBanner`) et réagir aux transitions de départ.
class TrackingCubit extends Cubit<TrackingState> {
  TrackingCubit(
    this._repository,
    this._terminalLocalDataSource,
    this._selectedDepartCubit,
  ) : super(const TrackingState()) {
    _selectedDepartSubscription =
        _selectedDepartCubit.stream.listen(_onDepartureChanged);
    _statusSubscription = _repository.statusStream.listen(_onRuntimeStatus);
  }

  final TrackingRepository _repository;
  final TerminalLocalDataSource _terminalLocalDataSource;

  /// Référence conservée (pas seulement `.stream`, écouté au constructeur)
  /// pour lire [SelectedDepartCubit.state] de façon synchrone dans
  /// [onAppResumed] — voir sa docstring.
  final SelectedDepartCubit _selectedDepartCubit;

  late final StreamSubscription<Depart?> _selectedDepartSubscription;
  late final StreamSubscription<TrackingRuntimeStatus> _statusSubscription;

  /// Empêche un double appel simultané à [_repository.requestAvailability]
  /// (ex. un appui rapide sur "Autoriser" pendant qu'une demande de
  /// [bootstrap] est déjà en cours).
  bool _isRequestingPermission = false;

  /// `true` dès qu'une demande de permission a été tentée une fois pour
  /// CE lancement de l'app (process en cours) — qu'elle ait abouti ou
  /// non. Empêche de redemander à chaque retour au premier plan (voir
  /// [onAppResumed]) : c'est CE comportement, pas un manque de verrou
  /// anti-ré-entrance, qui causait la boucle persistante (échange du
  /// 24/09/2026) — la permission de localisation Android se demande en
  /// deux temps (précise, puis « toujours autoriser » en arrière-plan) ;
  /// sur beaucoup d'appareils (dont Transsion/Infinix), le second temps
  /// n'aboutit que via les Réglages système, jamais via la boîte de
  /// dialogue elle-même. Tant que le convoyeur n'y est pas allé,
  /// [TrackingAvailability] reste `permissionDenied` — redemander à
  /// chaque resume (y compris ceux causés par la boîte de dialogue
  /// elle-même refermée) rouvrait donc une boîte de dialogue à l'infini.
  bool _hasRequestedThisSession = false;

  /// À appeler une fois au démarrage (voir `SplashPage`) : relit la
  /// disponibilité (permission/GPS/précision) et reprend un suivi resté
  /// actif après un crash ou un redémarrage à froid. Redemande activement
  /// la permission de localisation UNE SEULE FOIS par lancement si elle
  /// n'est pas encore accordée — voir [_requestPermissionIfDenied] : le
  /// suivi en dépend, le convoyeur ne doit pas avoir à aller la chercher
  /// lui-même dans les réglages avant d'avoir vu la boîte de dialogue
  /// système au moins une fois par ouverture. Si le refus persiste après
  /// cette tentative, seule une action explicite du convoyeur (carte
  /// d'accueil/bandeau, [requestPermission]) redemande — jamais
  /// automatiquement une deuxième fois, voir [onAppResumed].
  Future<void> bootstrap() async {
    await _repository.bootstrap();
    final TrackingAvailability availability =
        await _requestPermissionIfDenied(await _repository.checkAvailability());
    final int pendingCount = await _repository.totalPendingCount();
    emit(
        state.copyWith(availability: availability, pendingCount: pendingCount));
  }

  /// À appeler à chaque retour au premier plan de l'app (voir
  /// `_AppLifecycleListener`) — relit SEULEMENT l'état (aucune boîte de
  /// dialogue système) : la permission a pu changer pendant que l'app
  /// était en arrière-plan (le convoyeur est allé l'accorder dans les
  /// réglages, ou au contraire l'a révoquée), mais ne redemande jamais
  /// elle-même — voir docstring [_hasRequestedThisSession] sur pourquoi
  /// c'est essentiel pour éviter la boucle. Une seule tentative
  /// automatique par lancement suffit à satisfaire le besoin (le
  /// convoyeur voit la boîte de dialogue au moins une fois) sans jamais
  /// la relancer toute seule ensuite.
  ///
  /// Si la permission vient justement d'être accordée (le convoyeur
  /// revient des Réglages système, voir `progressDeparture` dans
  /// `depart_progress.dart`) ET qu'un départ `departed` est sélectionné
  /// sans suivi actif, redémarre le suivi tout seul — sans quoi le
  /// convoyeur devrait ressaisir une action pour que le suivi reparte
  /// après avoir corrigé la permission. Aucun risque de boucle ici :
  /// [_start] ne montre jamais de boîte de dialogue, il ne fait que
  /// vérifier l'état déjà là.
  Future<void> onAppResumed() async {
    final TrackingAvailability availability =
        await _repository.checkAvailability();
    emit(state.copyWith(availability: availability));

    if (availability != TrackingAvailability.ready || state.isActive) return;

    final Depart? depart = _selectedDepartCubit.state;
    if (depart?.status == 'departed') await _start(depart!);
  }

  /// Ne redemande que si le blocage vient de la permission (une boîte de
  /// dialogue système n'a rien à faire pour un GPS désactivé ou une
  /// précision insuffisante) ET qu'aucune demande n'a déjà été tentée ce
  /// lancement-ci — voir [_hasRequestedThisSession]. Réservé aux chemins
  /// AUTOMATIQUES ([bootstrap]) : un appui explicite du convoyeur passe
  /// par [_requestNow], qui ignore volontairement cette limite.
  Future<TrackingAvailability> _requestPermissionIfDenied(
      TrackingAvailability current) async {
    if (current != TrackingAvailability.permissionDenied) return current;
    if (_hasRequestedThisSession) return current;

    _hasRequestedThisSession = true;
    return _requestNow();
  }

  /// Appelle réellement [_repository.requestAvailability] — protégé
  /// seulement contre un double appel SIMULTANÉ (ex. deux appuis rapides
  /// sur "Autoriser"), jamais limité en nombre de tentatives : contrairement
  /// à [_requestPermissionIfDenied] (chemin automatique), une action
  /// explicite du convoyeur doit toujours pouvoir rouvrir la boîte de
  /// dialogue système.
  Future<TrackingAvailability> _requestNow() async {
    if (_isRequestingPermission) return state.availability;

    _isRequestingPermission = true;
    try {
      return await _repository.requestAvailability();
    } finally {
      _isRequestingPermission = false;
    }
  }

  /// Demande activement la permission de localisation (boîte de dialogue
  /// système) — à appeler depuis une action explicite du convoyeur (ex.
  /// bouton "Autoriser" sur l'état bloquant affiché par
  /// `TrackingStatusBanner`/la carte d'accueil), jamais automatiquement.
  /// Passe par [_requestNow], PAS par [_requestPermissionIfDenied] : une
  /// action explicite doit toujours pouvoir rouvrir la boîte de dialogue,
  /// même après la tentative automatique unique de [bootstrap].
  Future<TrackingAvailability> requestPermission() async {
    final TrackingAvailability availability = await _requestNow();
    emit(state.copyWith(availability: availability));
    return availability;
  }

  Future<void> _onDepartureChanged(Depart? depart) async {
    if (depart == null) {
      if (state.isActive) await _stop(TrackingStopReason.manualClose);
      return;
    }

    switch (depart.status) {
      case 'departed':
        await _start(depart);
      case 'arrived':
        await _stop(TrackingStopReason.arrived);
      case 'cancelled':
        await _stop(TrackingStopReason.cancelled);
      default:
        // 'scheduled'/'boarding'/null : rien à faire, le suivi ne
        // concerne que le bus effectivement en circulation.
        break;
    }
  }

  Future<void> _start(Depart depart) async {
    final String? terminalSerial =
        await _terminalLocalDataSource.readSerialNumber();
    if (terminalSerial == null || terminalSerial.isEmpty) return;

    final TrackingAvailability availability = await _repository.start(
      departureUuid: depart.id,
      terminalSerial: terminalSerial,
    );

    emit(state.copyWith(
      isActive: availability == TrackingAvailability.ready,
      availability: availability,
    ));
  }

  Future<void> _stop(TrackingStopReason reason) async {
    await _repository.stop(reason: reason);
    emit(state.copyWith(
      isActive: false,
      isOnline: true,
      isSyncing: false,
      pendingCount: 0,
    ));
  }

  void _onRuntimeStatus(TrackingRuntimeStatus status) {
    emit(state.copyWith(
      isActive: true,
      isOnline: status.isOnline,
      isSyncing: status.isSyncing,
      pendingCount: status.pendingCount,
      lastPositionAt: status.lastPositionAt,
      lastError: status.lastError,
    ));
  }

  @override
  Future<void> close() {
    unawaited(_selectedDepartSubscription.cancel());
    unawaited(_statusSubscription.cancel());
    return super.close();
  }
}
