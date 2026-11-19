import 'dart:async';

import 'package:dartz/dartz.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/group_payer.dart';
import '../../domain/entities/group_quote.dart';
import '../../domain/entities/journey.dart';
import '../../domain/entities/passenger.dart';
import '../../domain/entities/seat_hold.dart';
import '../../domain/repositories/reservation_repository.dart';

part 'seat_hold_event.dart';
part 'seat_hold_state.dart';

/// Le devis et le blocage des sièges, du récapitulatif jusqu'au paiement.
///
/// **Le blocage part du récapitulatif**, au « Continuer vers le paiement »,
/// et non plus du plan des sièges. MEZ-73 n'accepte le code promo qu'au
/// devis, et le blocage fige ce devis : bloquer plus tôt obligeait à
/// libérer puis rebloquer pour appliquer un code. Le plan et le formulaire
/// passagers ne font donc que choisir ; le récapitulatif demande le devis
/// — avec ou sans code — puis bloque, puis envoie les identités.
///
/// **Il vit au-dessus des écrans, pas dans l'un d'eux.** Le décompte doit
/// survivre aux allers-retours entre le récapitulatif et le paiement ; un
/// Bloc par écran l'aurait remis à zéro à chaque navigation. Celui-ci est
/// fourni par la route mère du tunnel (voir `AppRouter`).
class SeatHoldBloc extends Bloc<SeatHoldEvent, SeatHoldState> {
  SeatHoldBloc(this._repository, {DateTime Function()? clock})
    : _clock = clock ?? DateTime.now,
      super(const SeatHoldState()) {
    on<SeatHoldCheckoutRequested>(_onCheckoutRequested);
    on<SeatHoldReleased>(_onReleased);
    on<SeatHoldSettled>(_onSettled);
    on<SeatHoldResumed>(_onResumed);
    on<SeatHoldTicked>(_onTicked);
    on<SeatHoldRepriceRequested>(_onRepriceRequested);
  }

  final ReservationRepository _repository;

  /// Injectable pour les tests : un décompte se vérifie mal quand il lit
  /// l'horloge du monde.
  final DateTime Function() _clock;

  Timer? _ticker;

  /// Marge sous laquelle un devis est tenu pour périmé : le blocage part
  /// quelques instants après, et un devis qui expire en route serait
  /// refusé.
  static const _quoteMargin = Duration(seconds: 30);

  @override
  Future<void> close() {
    _ticker?.cancel();
    return super.close();
  }

  /// « Continuer vers le paiement » : devis, blocage, puis identités.
  ///
  /// Le devis est celui que le récapitulatif affiche, pour que le voyageur
  /// paie le prix qu'il a lu — remise de groupe et code promo compris. Il
  /// n'est redemandé, avec le même code, que s'il manque ou va expirer.
  ///
  /// Revenu du paiement, le voyageur a déjà ses sièges : on ne rebloque
  /// pas, on renvoie seulement les identités, qu'il a pu corriger.
  Future<void> _onCheckoutRequested(
    SeatHoldCheckoutRequested event,
    Emitter<SeatHoldState> emit,
  ) async {
    if (state.isCheckingOut || state.isRepricing) return;

    final current = state.hold;
    if (state.isHeld && current != null) {
      emit(
        state.copyWith(
          checkingOut: true,
          passengersStatus: PassengersStatus.saving,
          clearError: true,
        ),
      );
      await _savePassengers(current, event.passengers, emit);
      return;
    }

    emit(
      SeatHoldState(
        status: SeatHoldStatus.holding,
        departure: event.journey.outbound.first,
        passengers: event.passengers.length,
        quote: state.quote,
        checkingOut: true,
      ),
    );

    final quoted = await _freshQuote(event);
    final quoteFailure = quoted.fold((failure) => failure, (_) => null);
    if (quoteFailure != null) {
      emit(_failed(quoteFailure));
      return;
    }
    final quote = quoted.getOrElse(() => throw StateError('devis absent'));

    final held = await _repository.holdGroup(
      quote: quote,
      // Le devis porte un tronçon par bus, et le serveur exige un siège
      // pour chacun : en taire un ferait refuser le blocage entier.
      journey: event.journey,
      payer: event.payer,
      // Déterministe : un réseau qui coupe après l'envoi laisse le
      // voyageur réappuyer, et le serveur rend alors le blocage déjà
      // ouvert plutôt que d'en créer un second.
      idempotencyKey: 'kora-${quote.id}',
    );

    final holdFailure = held.fold((failure) => failure, (_) => null);
    if (holdFailure != null) {
      emit(_failed(holdFailure));
      return;
    }
    final hold = held.getOrElse(() => throw StateError('blocage absent'));

    emit(
      state.copyWith(
        status: SeatHoldStatus.held,
        hold: hold,
        remaining: hold.remaining(_clock()),
        quote: quote,
        passengersStatus: PassengersStatus.saving,
      ),
    );
    _startTicking();

    await _savePassengers(hold, event.passengers, emit);
  }

  /// Le devis affiché s'il est encore valable, un nouveau sinon — avec le
  /// même code, pour ne pas perdre la remise en route.
  Future<Either<Failure, GroupQuote>> _freshQuote(
    SeatHoldCheckoutRequested event,
  ) async {
    final shown = event.quote;
    final expiresAt = shown?.expiresAt;
    if (shown != null &&
        (expiresAt == null || expiresAt.isAfter(_clock().add(_quoteMargin)))) {
      return Right(shown);
    }

    return _repository.groupQuote(
      journey: event.journey,
      passengers: event.passengers.length,
      promoCode: shown?.promotion?.code,
    );
  }

  /// Rattache les identités à la réservation, puis se recale sur ce que
  /// rend le serveur : `status` et `expires_at` reviennent avec la
  /// réponse, c'est lui qui fait autorité sur le temps restant.
  ///
  /// Un refus laisse le blocage en place : le voyageur corrige et
  /// réappuie, sans reperdre ses sièges.
  Future<void> _savePassengers(
    SeatHold hold,
    List<Passenger> passengers,
    Emitter<SeatHoldState> emit,
  ) async {
    final result = await _repository.savePassengers(
      reservationId: hold.id,
      passengers: passengers,
    );

    result.fold(
      (failure) => emit(
        state.copyWith(
          checkingOut: false,
          passengersStatus: PassengersStatus.failure,
          errorMessage: failure,
        ),
      ),
      (refreshed) => emit(
        state.copyWith(
          checkingOut: false,
          hold: refreshed,
          remaining: refreshed.remaining(_clock()),
          passengersStatus: PassengersStatus.saved,
        ),
      ),
    );
  }

  /// Le checkout s'arrête sans rien retenir. Le devis affiché reste : le
  /// récapitulatif garde son prix et son code.
  SeatHoldState _failed(Failure failure) => SeatHoldState(
    status: SeatHoldStatus.failure,
    departure: state.departure,
    passengers: state.passengers,
    quote: state.quote,
    failure: failure,
  );

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

  /// Coupe le décompte sans rien rendre : la réservation est payée.
  void _onSettled(SeatHoldSettled event, Emitter<SeatHoldState> emit) {
    _ticker?.cancel();
    emit(
      SeatHoldState(departure: state.departure, passengers: state.passengers),
    );
  }

  /// Les voyageurs sont déjà rattachés à la réservation, depuis son envoi
  /// à l'agence : elle est payable telle quelle.
  void _onResumed(SeatHoldResumed event, Emitter<SeatHoldState> emit) {
    emit(
      SeatHoldState(
        status: SeatHoldStatus.held,
        hold: event.hold,
        remaining: event.hold.remaining(_clock()),
        passengers: event.passengers,
        passengersStatus: PassengersStatus.saved,
      ),
    );
    _startTicking();
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

  /// Applique, remplace ou retire un code promo.
  ///
  /// Un code vide **retire** la remise en cours ; sans remise appliquée,
  /// il ne demande rien.
  ///
  /// **Avant le blocage** — le cas normal, le récapitulatif précédant le
  /// paiement — c'est un simple devis, avec ou sans code : accepté, il
  /// devient le prix affiché puis bloqué ; refusé, rien ne change et le
  /// message du serveur s'affiche.
  ///
  /// **Après le blocage** — le voyageur revient du paiement — le serveur
  /// recalcule la réservation en place (`PUT bookings/{uuid}/promo-code`).
  /// Ni libération ni nouveau blocage : les sièges, les identités et
  /// l'échéance restent les mêmes, seul le montant change.
  Future<void> _onRepriceRequested(
    SeatHoldRepriceRequested event,
    Emitter<SeatHoldState> emit,
  ) async {
    if (state.isRepricing || state.isCheckingOut) return;

    final code = event.promoCode.trim().toUpperCase();
    final applied = state.quote?.promotion;
    // Un champ vide retire le code appliqué ; sans code appliqué, il ne
    // demande rien.
    final removing = code.isEmpty;
    if (removing && applied == null) return;

    emit(
      state.copyWith(promoStatus: PromoStatus.applying, clearPromoError: true),
    );

    final current = state.hold;
    if (current != null) {
      await _repriceHeld(event, emit, current, removing ? null : code);
      return;
    }

    final quoted = await _repository.groupQuote(
      journey: event.journey,
      passengers: event.passengers.length,
      promoCode: removing ? null : code,
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

    emit(
      state.copyWith(
        promoStatus: PromoStatus.idle,
        quote: quoted.getOrElse(() => throw StateError('devis absent')),
      ),
    );
  }

  /// Le même geste, sur une réservation déjà retenue : c'est le serveur
  /// qui recalcule, l'app n'a qu'à reprendre le montant qu'il rend.
  Future<void> _repriceHeld(
    SeatHoldRepriceRequested event,
    Emitter<SeatHoldState> emit,
    SeatHold current,
    String? code,
  ) async {
    final updated = await _repository.updatePromoCode(
      reservationId: current.id,
      promoCode: code,
    );

    final refused = updated.fold((failure) => failure, (_) => null);
    if (refused != null) {
      // Rien n'a bougé côté serveur : le blocage et son prix tiennent.
      emit(
        state.copyWith(
          promoStatus: PromoStatus.idle,
          promoError: refused.message,
        ),
      );
      return;
    }
    final pricing = updated.getOrElse(() => throw StateError('prix absent'));

    final hold = SeatHold(
      id: current.id,
      expiresAt: pricing.expiresAt?.toUtc() ?? current.expiresAt,
      seatLabels: current.seatLabels,
      total: pricing.total,
    );

    final previous = state.quote;
    emit(
      state.copyWith(
        promoStatus: PromoStatus.idle,
        hold: hold,
        remaining: hold.remaining(_clock()),
        quote: previous == null
            ? null
            : GroupQuote(
                id: previous.id,
                subtotal: pricing.subtotal,
                total: pricing.total,
                passengerCount: previous.passengerCount,
                discount: previous.discount,
                expiresAt: previous.expiresAt,
                promotion: pricing.promotion,
              ),
      ),
    );
  }

  /// Une seconde : c'est la granularité qu'affiche le décompte (`MM:SS`).
  void _startTicking() {
    _ticker?.cancel();
    _ticker = Timer.periodic(
      const Duration(seconds: 1),
      (_) => add(const SeatHoldTicked()),
    );
  }
}
