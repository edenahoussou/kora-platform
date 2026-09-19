import 'dart:async';

import 'package:dartz/dartz.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';

import '../../domain/entities/departure.dart';
import '../../domain/entities/group_payer.dart';
import '../../domain/entities/group_quote.dart';
import '../../domain/entities/passenger.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/seat_hold.dart';
import '../../domain/repositories/reservation_repository.dart';

part 'seat_hold_event.dart';
part 'seat_hold_state.dart';

/// Le blocage des sièges, du « Continuer » du plan jusqu'au paiement.
///
/// **Il vit au-dessus des écrans, pas dans l'un d'eux.** Le décompte
/// commence sur le plan des sièges, s'affiche sur le formulaire passagers
/// puis sur le récapitulatif, et doit survivre à un retour en arrière. Un
/// Bloc par écran l'aurait remis à zéro à chaque navigation ; celui-ci est
/// fourni par la route mère du tunnel (voir `AppRouter`).
class SeatHoldBloc extends Bloc<SeatHoldEvent, SeatHoldState> {
  SeatHoldBloc(this._repository, {DateTime Function()? clock})
    : _clock = clock ?? DateTime.now,
      super(const SeatHoldState()) {
    on<SeatHoldRequested>(_onRequested);
    on<SeatHoldGroupRequested>(_onGroupRequested);
    on<SeatHoldReleased>(_onReleased);
    on<SeatHoldSettled>(_onSettled);
    on<SeatHoldPassengersSubmitted>(_onPassengersSubmitted);
    on<SeatHoldTicked>(_onTicked);
    on<SeatHoldRepriceRequested>(_onRepriceRequested);
  }

  final ReservationRepository _repository;

  /// Injectable pour les tests : un décompte se vérifie mal quand il lit
  /// l'horloge du monde.
  final DateTime Function() _clock;

  Timer? _ticker;

  @override
  Future<void> close() {
    _ticker?.cancel();
    return super.close();
  }

  Future<void> _onRequested(
    SeatHoldRequested event,
    Emitter<SeatHoldState> emit,
  ) async {
    emit(
      state.copyWith(
        status: SeatHoldStatus.holding,
        departure: event.departure,
        passengers: event.seats.length,
        clearError: true,
      ),
    );

    // Le devis d'abord : le blocage réclame son identifiant, et il fige le
    // tarif pour que le montant vu au moment du choix soit celui qu'on
    // paiera.
    //
    // Par `bus/itinerary-quotes` puis `bus/itinerary-holds`, comme un
    // groupe : MEZ-63 a retiré `bus/quotes` et `bus/holds`, et il ne reste
    // donc qu'un seul chemin. Un voyageur seul est simplement un groupe
    // d'un.
    final quote = await _repository.groupQuote(
      departure: event.departure,
      passengers: event.seats.length,
      returnDeparture: event.returnDeparture,
    );

    final held = await quote.fold(
      (failure) async => Left<Failure, SeatHold>(failure),
      (quote) => _repository.holdGroup(
        quote: quote,
        seats: event.seats,
        // Le devis d'un aller-retour porte deux tronçons, et le serveur
        // exige un siège pour chacun : taire les sièges du retour ferait
        // refuser le blocage entier.
        returnSeats: event.returnSeats,
        payer: const GroupPayer.personal(),
        // Déterministe : un réseau qui coupe après l'envoi laisse le
        // voyageur réappuyer, et le serveur rend alors le blocage déjà
        // ouvert plutôt que d'en créer un second sur d'autres sièges.
        idempotencyKey: 'kora-${quote.id}',
      ),
    );

    held.fold(
      (failure) => emit(
        state.copyWith(status: SeatHoldStatus.failure, errorMessage: failure),
      ),
      (hold) {
        emit(
          SeatHoldState(
            status: SeatHoldStatus.held,
            hold: hold,
            remaining: hold.remaining(_clock()),
            departure: event.departure,
            passengers: event.seats.length,
          ),
        );
        _startTicking();
      },
    );
  }

  Future<void> _onGroupRequested(
    SeatHoldGroupRequested event,
    Emitter<SeatHoldState> emit,
  ) async {
    emit(
      state.copyWith(
        status: SeatHoldStatus.holding,
        departure: event.departure,
        passengers: event.seats.length,
        clearError: true,
      ),
    );

    // Pas de devis à demander ici : le groupe l'a déjà obtenu en amont, sur
    // l'écran qui lui a montré la remise. Le redemander risquerait d'en
    // rendre un autre — le barème peut bouger entre deux appels — et le
    // voyageur paierait un prix qu'il n'a pas vu.
    final held = await _repository.holdGroup(
      quote: event.quote,
      seats: event.seats,
      payer: event.payer,
      // Déterministe : un réseau qui coupe après l'envoi laisse le voyageur
      // réappuyer, et le serveur rend alors le blocage déjà créé plutôt que
      // d'en ouvrir un second sur d'autres sièges.
      idempotencyKey: 'kora-groupe-${event.quote.id}',
    );

    held.fold(
      (failure) => emit(
        state.copyWith(status: SeatHoldStatus.failure, errorMessage: failure),
      ),
      (hold) {
        emit(
          SeatHoldState(
            status: SeatHoldStatus.held,
            hold: hold,
            remaining: hold.remaining(_clock()),
            departure: event.departure,
            passengers: event.seats.length,
          ),
        );
        _startTicking();
      },
    );
  }

  Future<void> _onReleased(
    SeatHoldReleased event,
    Emitter<SeatHoldState> emit,
  ) async {
    final hold = state.hold;
    _ticker?.cancel();
    // Le départ survit à la libération : le voyageur reste sur le plan.
    emit(
      SeatHoldState(departure: state.departure, passengers: state.passengers),
    );

    // L'écran n'attend pas la réponse : les sièges sont rendus côté app
    // quoi qu'il arrive, et le serveur les libérera de toute façon à
    // l'échéance. Faire patienter le voyageur devant un appel qui ne peut
    // que réussir plus tard serait gratuit.
    if (hold != null) await _repository.release(hold.id);
  }

  /// Envoie les identités, puis se recale sur ce que rend le serveur.
  ///
  /// `status` et `expires_at` reviennent avec la réponse : c'est le
  /// serveur qui fait autorité sur le temps restant, et cet appel est
  /// l'occasion de rattraper une horloge qui aurait dérivé.
  Future<void> _onPassengersSubmitted(
    SeatHoldPassengersSubmitted event,
    Emitter<SeatHoldState> emit,
  ) async {
    final hold = state.hold;
    if (hold == null) return;

    emit(
      state.copyWith(
        passengersStatus: PassengersStatus.saving,
        clearError: true,
      ),
    );

    final result = await _repository.savePassengers(
      reservationId: hold.id,
      passengers: event.passengers,
    );

    result.fold(
      (failure) => emit(
        state.copyWith(
          passengersStatus: PassengersStatus.failure,
          errorMessage: failure,
        ),
      ),
      (refreshed) => emit(
        state.copyWith(
          hold: refreshed,
          remaining: refreshed.remaining(_clock()),
          passengersStatus: PassengersStatus.saved,
        ),
      ),
    );
  }

  /// Coupe le décompte sans rien rendre : la réservation est payée.
  void _onSettled(SeatHoldSettled event, Emitter<SeatHoldState> emit) {
    _ticker?.cancel();
    emit(
      SeatHoldState(departure: state.departure, passengers: state.passengers),
    );
  }

  void _onTicked(SeatHoldTicked event, Emitter<SeatHoldState> emit) {
    final hold = state.hold;
    if (hold == null) return;

    final remaining = hold.remaining(_clock());
    if (remaining > Duration.zero) {
      emit(state.copyWith(remaining: remaining));
      return;
    }

    // À zéro, le serveur a déjà rendu les sièges : on ne rappelle pas
    // `DELETE`, on annonce l'expiration et l'écran prend le relais.
    _ticker?.cancel();
    emit(
      SeatHoldState(
        status: SeatHoldStatus.expired,
        departure: state.departure,
        passengers: state.passengers,
      ),
    );
  }

  /// Une seconde : c'est la granularité qu'affiche le décompte (`MM:SS`).
  /// Applique, remplace ou retire un code promo sur la réservation bloquée.
  ///
  /// MEZ-73 n'accepte le code qu'au devis, et le blocage fige ce devis :
  /// changer de prix, c'est donc redemander un devis puis rebloquer.
  ///
  /// L'ordre protège le siège autant que possible :
  /// 1. le devis d'abord, avec le code — un code refusé (inconnu, expiré,
  ///    hors périmètre, quota atteint) s'arrête là, **sans rien lâcher** ;
  /// 2. seulement s'il est accepté : libération, nouveau blocage des mêmes
  ///    sièges au prix remisé, puis les identités, attachées à l'ancienne
  ///    réservation.
  ///
  /// Si un siège est pris entre la libération et le nouveau blocage, le
  /// blocage est perdu : l'état passe en expiré et l'écran de fin de délai
  /// propose d'en choisir un autre.
  Future<void> _onRepriceRequested(
    SeatHoldRepriceRequested event,
    Emitter<SeatHoldState> emit,
  ) async {
    final current = state.hold;
    if (current == null || state.isRepricing) return;

    final code = event.promoCode?.trim().toUpperCase();
    // Un champ vide n'est pas un retrait : on ne touche à rien.
    if (event.promoCode != null && (code == null || code.isEmpty)) return;

    emit(
      state.copyWith(
        promoStatus: code == null ? PromoStatus.removing : PromoStatus.applying,
        clearPromoError: true,
        promoUnavailable: false,
      ),
    );

    final quoted = await _repository.groupQuote(
      departure: event.departure,
      passengers: event.passengers.length,
      returnDeparture: event.returnDeparture,
      promoCode: code,
    );

    final refused = quoted.fold((failure) => failure, (_) => null);
    if (refused != null) {
      emit(
        state.copyWith(
          promoStatus: PromoStatus.idle,
          promoError: refused.message,
        ),
      );
      return;
    }
    final quote = quoted.getOrElse(() => throw StateError('devis absent'));

    _ticker?.cancel();
    final released = await _repository.release(current.id);

    // Sans libération, l'ancienne réservation garde les sièges : le
    // nouveau blocage serait refusé, et le voyageur perdrait tout. On
    // garde donc la réservation au tarif normal plutôt que de la risquer.
    if (released.isLeft()) {
      emit(
        state.copyWith(promoStatus: PromoStatus.idle, promoUnavailable: true),
      );
      _startTicking();
      return;
    }

    final held = await _repository.holdGroup(
      quote: quote,
      seats: [for (final passenger in event.passengers) passenger.seat],
      returnSeats: event.returnSeats,
      payer: event.payer,
      idempotencyKey: 'kora-${quote.id}',
    );

    final lost = held.fold((failure) => failure, (_) => null);
    if (lost != null) {
      emit(
        SeatHoldState(
          status: SeatHoldStatus.expired,
          departure: state.departure,
          passengers: state.passengers,
          failure: lost,
        ),
      );
      return;
    }
    final hold = held.getOrElse(() => throw StateError('blocage absent'));

    // Les identités vivaient sur l'ancienne réservation : sans elles, la
    // nouvelle ne serait pas payable.
    final saved = await _repository.savePassengers(
      reservationId: hold.id,
      passengers: event.passengers,
    );
    final refreshed = saved.getOrElse(() => hold);

    emit(
      SeatHoldState(
        status: SeatHoldStatus.held,
        hold: refreshed,
        remaining: refreshed.remaining(_clock()),
        departure: state.departure,
        passengers: state.passengers,
        passengersStatus: saved.isRight()
            ? PassengersStatus.saved
            : PassengersStatus.failure,
        failure: saved.fold((failure) => failure, (_) => null),
        quote: quote,
      ),
    );
    _startTicking();
  }

  void _startTicking() {
    _ticker?.cancel();
    _ticker = Timer.periodic(
      const Duration(seconds: 1),
      (_) => add(const SeatHoldTicked()),
    );
  }
}
