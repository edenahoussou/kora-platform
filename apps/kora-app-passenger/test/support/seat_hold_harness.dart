// Le blocage des sièges, monté pour les tests d'écran.
//
// Les trois écrans du tunnel (plan, formulaire passagers, récapitulatif)
// lisent le [SeatHoldBloc] fourni par leur route mère. Montés seuls dans un
// test, ils ne le trouveraient pas : ce harnais le pose au-dessus, avec un
// dépôt qui ne parle à personne.

import 'package:dartz/dartz.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/quote.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat_hold.dart';
import 'package:kora_app_passenger/features/booking/domain/repositories/reservation_repository.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';

/// Dépôt de blocage réglable : réussit par défaut, échoue sur demande.
class StubReservationRepository implements ReservationRepository {
  StubReservationRepository({
    this.failure,
    this.holdDuration = const Duration(minutes: 7),
    DateTime? now,
  }) : now = now ?? DateTime.now().toUtc();

  /// L'instant de référence des échéances. Fixé par le test quand il
  /// vérifie le décompte : sinon `expires_at` est calculé à l'exécution,
  /// et le temps restant dépend de la vitesse de la machine.
  final DateTime now;

  /// Renvoyé par [quote] et [hold] quand il est posé.
  final Failure? failure;

  /// Ce que le « serveur » accorde. Réglable pour vérifier l'expiration
  /// sans attendre sept minutes.
  final Duration holdDuration;

  final List<String> calls = [];

  @override
  Future<Either<Failure, Quote>> quote({
    required Departure departure,
    required int passengers,
  }) async {
    calls.add('quote');
    final failure = this.failure;
    if (failure != null) return Left(failure);

    return Right(
      Quote(
        id: 'quote-1',
        total: Price(12500 * passengers),
        passengerCount: passengers,
        expiresAt: now.add(holdDuration),
      ),
    );
  }

  @override
  Future<Either<Failure, SeatHold>> hold({
    required Departure departure,
    required Quote quote,
    required List<Seat> seats,
  }) async {
    calls.add('hold');
    final failure = this.failure;
    if (failure != null) return Left(failure);

    return Right(
      SeatHold(
        id: 'hold-1',
        expiresAt: now.add(holdDuration),
        seatLabels: [for (final seat in seats) seat.label],
        total: quote.total,
      ),
    );
  }

  @override
  Future<Either<Failure, void>> release(String reservationId) async {
    calls.add('release:$reservationId');
    return const Right(null);
  }
}

/// Enveloppe [child] du [SeatHoldBloc] que sa route mère fournirait.
Widget withSeatHold(Widget child, {required SeatHoldBloc bloc}) {
  return BlocProvider<SeatHoldBloc>.value(value: bloc, child: child);
}
