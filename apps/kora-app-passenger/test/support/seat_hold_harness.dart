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
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_bloc.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/passenger.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/quote.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat_hold.dart';
import 'package:kora_app_passenger/features/booking/domain/repositories/reservation_repository.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_catalog.dart';
import 'package:kora_app_passenger/features/payment/domain/repositories/payment_repository.dart';

import 'stub_auth_repository.dart';

/// Dépôt de blocage réglable : réussit par défaut, échoue sur demande.
class StubReservationRepository implements ReservationRepository {
  StubReservationRepository({
    this.failure,
    this.holdDuration = const Duration(minutes: 7),
    this.passengersFailure,
    DateTime? now,
  }) : now = now ?? DateTime.now().toUtc();

  /// Échec réservé à [savePassengers] : le blocage réussit, seul l'envoi
  /// des identités échoue. Sans ça, un test du refus devrait bricoler
  /// l'état du Bloc au lieu de le traverser.
  final Failure? passengersFailure;

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

  /// Les voyageurs reçus par [savePassengers], tels quels.
  ///
  /// [calls] n'en retient que le nombre ; un test qui vérifie ce que porte
  /// chaque voyageur — sa déclaration de vaccination, par exemple — a
  /// besoin des objets eux-mêmes.
  final List<Passenger> savedPassengers = [];

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
  Future<Either<Failure, SeatHold>> savePassengers({
    required String reservationId,
    required List<Passenger> passengers,
  }) async {
    calls.add('passengers:${passengers.length}');
    savedPassengers
      ..clear()
      ..addAll(passengers);
    final failure = passengersFailure ?? this.failure;
    if (failure != null) return Left(failure);

    // Le serveur rend la réservation rafraîchie : on rejoue ça, échéance
    // comprise, pour que les tests puissent vérifier le recalage.
    return Right(
      SeatHold(
        id: reservationId,
        expiresAt: now.add(holdDuration),
        seatLabels: [for (final p in passengers) p.seat.label],
        total: Price(12500 * passengers.length),
      ),
    );
  }

  @override
  Future<Either<Failure, void>> release(String reservationId) async {
    calls.add('release:$reservationId');
    return const Right(null);
  }
}

/// Le catalogue de paiement du staging, tel que le renvoie
/// `GET payments/methods` : Mobile Money ouvert via FedaPay, Mini Wallet
/// hors périmètre.
const PaymentCatalog kStubPaymentCatalog = PaymentCatalog(
  country: 'BJ',
  currency: 'XOF',
  methods: [
    PaymentMethod(
      code: PaymentCatalog.mobileMoneyCode,
      label: 'Mobile Money',
      enabled: true,
      providers: [
        PaymentProvider(
          code: 'fedapay',
          label: 'FedaPay',
          enabled: true,
          networks: ['mtn_momo', 'moov_flooz', 'celtiis_cash'],
          requires: ['phone', 'mobile_money_network'],
          interaction: 'push_or_redirect',
          verification: 'server_verify',
        ),
      ],
    ),
    PaymentMethod(
      code: PaymentCatalog.walletCode,
      label: 'Mini Wallet',
      enabled: false,
      availability: 'out_of_mvp',
    ),
  ],
);

/// Dépôt de paiement réglable : rend le catalogue du staging par défaut,
/// échoue sur demande.
class StubPaymentRepository implements PaymentRepository {
  const StubPaymentRepository({this.failure, this.value = kStubPaymentCatalog});

  final Failure? failure;
  final PaymentCatalog value;

  @override
  Future<Either<Failure, PaymentCatalog>> catalog() async {
    final failure = this.failure;
    return failure != null ? Left(failure) : Right(value);
  }
}

/// Un [AuthBloc] dont la session est **déjà ouverte**.
///
/// L'ouverture passe par un événement, donc par un tour de boucle : sans
/// cette attente, le formulaire voyageurs se construit avant que la
/// session n'arrive et ne pré-remplit rien. Dans l'app le compte est
/// ouvert bien avant qu'on entre dans le tunnel — le test doit refléter
/// cet ordre, pas l'inverser.
Future<AuthBloc> openedSession([StubAuthRepository? repository]) async {
  final bloc = AuthBloc(repository ?? StubAuthRepository())
    ..add(AuthSessionOpened(StubAuthRepository.sessionWithPhone));

  await bloc.stream.firstWhere((state) => state.session != null);
  return bloc;
}

/// Enveloppe [child] des deux Blocs que l'app fournit au-dessus du
/// tunnel : le blocage des sièges, et la session — le formulaire
/// voyageurs pré-remplit le premier bloc avec le titulaire du compte.
///
/// Le catalogue des moyens de paiement n'y figure plus : l'écran Mobile
/// Money affiche une liste écrite en dur le temps que l'endpoint soit
/// réparé côté serveur (voir `kMobileMoneyProviders`). Le
/// [StubPaymentRepository] ci-dessus reste, lui, utilisé par les tests du
/// Bloc — la chaîne est prête à rebrancher.
Widget withSeatHold(
  Widget child, {
  required SeatHoldBloc bloc,
  AuthBloc? auth,
}) {
  return MultiBlocProvider(
    providers: [
      BlocProvider<SeatHoldBloc>.value(value: bloc),
      BlocProvider<AuthBloc>.value(
        value: auth ?? AuthBloc(StubAuthRepository()),
      ),
    ],
    child: child,
  );
}
