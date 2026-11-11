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
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/company_account.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/group_payer.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/group_quote.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/group_ticket.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/passenger.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/reservation_pricing.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat_hold.dart';
import 'package:kora_app_passenger/features/booking/domain/repositories/reservation_repository.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/group_payer_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/group_quote_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_attempt.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_catalog.dart';
import 'package:kora_app_passenger/features/payment/domain/repositories/payment_repository.dart';
import 'package:kora_app_passenger/features/payment/presentation/bloc/payment_catalog_bloc.dart';
import 'package:kora_app_passenger/features/payment/presentation/bloc/payment_flow_bloc.dart';

import 'stub_auth_repository.dart';

/// L'identifiant du blocage que rend [StubReservationRepository].
///
/// Nommé plutôt que recopié : un écran qui compare la réservation en
/// cours à celle qu'un paiement règle a besoin des deux côtés de la
/// comparaison, et deux littéraux finiraient par diverger.
const kHeldReservationId = 'hold-groupe-1';

/// Dépôt de blocage réglable : réussit par défaut, échoue sur demande.
class StubReservationRepository implements ReservationRepository {
  StubReservationRepository({
    this.failure,
    this.holdDuration = const Duration(minutes: 7),
    this.passengersFailure,
    this.groupQuoteValue,
    this.accounts = const [],
    this.tickets = const [],
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

  /// Renvoyé par [groupQuote] quand un code promo est soumis : le refus du
  /// serveur (inconnu, expiré, hors périmètre, quota atteint).
  Failure? promoFailure;

  /// Échec rendu par [release] — une libération refusée par le serveur.
  Failure? releaseFailure;

  /// Échecs à rendre, dans l'ordre, par les prochains [holdGroup] — un siège
  /// pris entre la libération et le nouveau blocage.
  final List<Failure> nextHoldFailures = [];

  /// Le devis groupé rendu par le stub : sous-total pour tous, et une
  /// remise seulement au-delà du seuil.
  final GroupQuote? groupQuoteValue;

  @override
  Future<Either<Failure, GroupQuote>> groupQuote({
    required Departure departure,
    required int passengers,
    Departure? returnDeparture,
    String? promoCode,
  }) async {
    calls.add(
      (returnDeparture == null
              ? 'groupQuote:$passengers'
              : 'groupQuote:$passengers:aller-retour') +
          (promoCode == null ? '' : ':promo=$promoCode'),
    );
    final failure = this.failure;
    if (failure != null) return Left(failure);

    // Un code refusé par le serveur : 422 sur `promo_code`.
    final promoFailure = this.promoFailure;
    if (promoCode != null && promoFailure != null) return Left(promoFailure);

    final value = groupQuoteValue;
    if (value != null) return Right(value);

    // Le barème du staging : 10 % à partir de cinq voyageurs. Un code promo
    // retire 10 % avant la remise groupe, dans l'ordre du serveur.
    final subtotal = 12500 * passengers;
    final promo = promoCode == null ? 0 : subtotal ~/ 10;
    final applies = passengers >= 5;
    final discount = applies ? (subtotal - promo) ~/ 10 : 0;

    return Right(
      GroupQuote(
        id: 'itinerary-quote-1',
        subtotal: Price(subtotal),
        total: Price(subtotal - promo - discount),
        promotion: promoCode == null
            ? null
            : QuotePromotion(code: promoCode, discount: Price(promo)),
        passengerCount: passengers,
        discount: applies
            ? GroupDiscount(
                applied: true,
                threshold: 5,
                percentage: 10,
                amount: Price(discount),
              )
            : GroupDiscount.none,
        expiresAt: now.add(holdDuration),
      ),
    );
  }

  /// L'annulation demandée par le détail d'un billet.
  @override
  Future<Either<Failure, Unit>> cancelBooking(String reservationId) async {
    calls.add('cancelBooking:$reservationId');
    final failure = cancelFailure;
    return failure == null ? const Right(unit) : Left(failure);
  }

  /// Échec rendu par [cancelBooking] — un serveur qui refuse.
  Failure? cancelFailure;

  /// Le prix rendu par [updatePromoCode] : le même barème que le devis,
  /// appliqué à la réservation déjà retenue.
  @override
  Future<Either<Failure, ReservationPricing>> updatePromoCode({
    required String reservationId,
    String? promoCode,
  }) async {
    calls.add('promoCode:$reservationId:${promoCode ?? 'aucun'}');

    // Un code refusé par le serveur : 422 sur `promo_code`. Retirer, lui,
    // ne peut pas être refusé pour cause de code inconnu.
    final promoFailure = this.promoFailure;
    if (promoCode != null && promoFailure != null) return Left(promoFailure);

    const subtotal = 12500;
    final promo = promoCode == null ? 0 : subtotal ~/ 10;

    return Right(
      ReservationPricing(
        total: Price(subtotal - promo),
        subtotal: const Price(subtotal),
        promotion: promoCode == null
            ? null
            : QuotePromotion(code: promoCode, discount: Price(promo)),
        expiresAt: now.add(holdDuration),
      ),
    );
  }

  /// Les voyageurs reçus par [savePassengers], tels quels.
  ///
  /// [calls] n'en retient que le nombre ; un test qui vérifie ce que porte
  /// chaque voyageur — sa déclaration de vaccination, par exemple — a
  /// besoin des objets eux-mêmes.
  final List<Passenger> savedPassengers = [];

  /// Les billets rendus par [bookingTickets]. Vide par défaut : l'émission
  /// suit le paiement, et un écran ouvert trop tôt n'en voit aucun.
  final List<GroupTicket> tickets;

  @override
  Future<Either<Failure, List<GroupTicket>>> bookingTickets(
    String reservationId,
  ) async {
    calls.add('bookingTickets');
    final failure = this.failure;
    return failure != null ? Left(failure) : Right(tickets);
  }

  /// Les comptes entreprise rendus par [companyAccounts]. Vide par défaut :
  /// la plupart des voyageurs n'appartiennent à aucune entreprise.
  final List<CompanyAccount> accounts;

  @override
  Future<Either<Failure, List<CompanyAccount>>> companyAccounts() async {
    calls.add('companyAccounts');
    final failure = this.failure;
    return failure != null ? Left(failure) : Right(accounts);
  }

  /// Le payeur reçu par [holdGroup] : c'est lui que vérifient les tests du
  /// compte entreprise.
  GroupPayer? groupPayer;

  /// Les sièges de retour reçus par [holdGroup], `null` sur un aller
  /// simple.
  List<Seat>? groupReturnSeats;

  /// Le total du dernier blocage : le serveur le garde sur la réservation,
  /// et le rend tel quel avec les voyageurs.
  Price? heldTotal;

  @override
  Future<Either<Failure, SeatHold>> holdGroup({
    required GroupQuote quote,
    required List<Seat> seats,
    required GroupPayer payer,
    required String idempotencyKey,
    List<Seat>? returnSeats,
  }) async {
    calls.add(
      returnSeats == null
          ? 'holdGroup:${seats.length}'
          : 'holdGroup:${seats.length}+${returnSeats.length}',
    );
    groupPayer = payer;
    groupReturnSeats = returnSeats;

    final failure = this.failure;
    if (failure != null) return Left(failure);
    if (nextHoldFailures.isNotEmpty) return Left(nextHoldFailures.removeAt(0));

    heldTotal = quote.total;
    return Right(
      SeatHold(
        id: kHeldReservationId,
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
        total: heldTotal ?? Price(12500 * passengers.length),
      ),
    );
  }

  @override
  Future<Either<Failure, void>> release(String reservationId) async {
    calls.add('release:$reservationId');
    final releaseFailure = this.releaseFailure;
    if (releaseFailure != null) return Left(releaseFailure);
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

/// Le catalogue tel que le staging le sert depuis MEZ-146 : deux
/// passerelles pour le Mobile Money, et la carte.
///
/// KKiaPay encaisse dans sa propre feuille (`sdk_widget`), FedaPay
/// pousse la demande sur le téléphone — c'est cette différence que les
/// écrans de paiement lisent pour savoir quoi demander.
const PaymentCatalog kStubGatewayCatalog = PaymentCatalog(
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
          // Les neuf réseaux du staging : trois pays, et des marques
          // qui ne sont pas servies partout.
          networks: [
            'mtn_momo',
            'moov_flooz',
            'celtiis_cash',
            'mtn_momo_ci',
            'moov_flooz_ci',
            'wave_ci',
            'orange_money_ci',
            'moov_flooz_tg',
            'tmoney_tg',
          ],
          requires: ['phone', 'mobile_money_network'],
          interaction: 'push_or_redirect',
          verification: 'server_verify',
        ),
        PaymentProvider(
          code: 'kkiapay',
          label: 'KKiaPay',
          enabled: true,
          networks: ['mtn_momo', 'moov_flooz', 'celtiis_cash', 'wave_ci'],
          interaction: 'sdk_widget',
          verification: 'server_verify',
        ),
      ],
    ),
    PaymentMethod(
      code: PaymentCatalog.cardCode,
      label: 'Carte bancaire',
      enabled: true,
      providers: [
        PaymentProvider(
          code: 'kkiapay',
          label: 'KKiaPay',
          enabled: true,
          interaction: 'sdk_widget',
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

/// Ce qu'un double de [PaymentRepository] doit fournir quand il ne teste
/// que le catalogue.
///
/// Le contrat porte aussi l'initiation et la relecture d'un paiement ;
/// les refuser franchement vaut mieux que de rendre un faux succès, qui
/// laisserait passer un test appelant une méthode qu'il n'a pas préparée.
mixin PaymentCatalogOnly implements PaymentRepository {
  @override
  Future<Either<Failure, PaymentAttempt>> initiate({
    required String reservationId,
    required String method,
    required String provider,
    required String idempotencyKey,
    String? network,
    String? phone,
    int? walletAmount,
    int? pointsAmount,
  }) => throw UnimplementedError('Ce double ne sert que pour le catalogue.');

  @override
  Future<Either<Failure, PaymentAttempt>> status(String paymentId) =>
      throw UnimplementedError('Ce double ne sert que pour le catalogue.');

  @override
  Future<Either<Failure, PaymentAttempt>> verify(
    String paymentId, {
    String? transactionId,
  }) => throw UnimplementedError('Ce double ne sert que pour le catalogue.');
}

/// Dépôt de paiement réglable : rend le catalogue du staging par défaut,
/// échoue sur demande.
class StubPaymentRepository implements PaymentRepository {
  StubPaymentRepository({
    this.failure,
    this.value = kStubPaymentCatalog,
    this.attempt = kStubPaymentAttempt,
    this.initiateFailure,
    this.statuses = const [],
  });

  final Failure? failure;
  final PaymentCatalog value;

  /// Ce que rend [initiate], et [status] quand [statuses] est épuisée.
  final PaymentAttempt attempt;

  /// Échec réservé à [initiate] : le catalogue arrive, seul le lancement
  /// du paiement échoue.
  final Failure? initiateFailure;

  /// États successifs rendus par [status], dans l'ordre — de quoi jouer un
  /// paiement qui reste en attente puis se confirme, sans horloge réelle.
  final List<PaymentAttempt> statuses;

  final List<String> calls = [];

  /// Les clés d'idempotence reçues, dans l'ordre des demandes.
  final List<String> idempotencyKeys = [];

  /// Les répartitions reçues — (solde, points) — pour vérifier qu'un
  /// paiement mixte part bien avec ses trois parts.
  final List<(int?, int?)> splits = [];

  int _statusReads = 0;

  @override
  Future<Either<Failure, PaymentCatalog>> catalog() async {
    calls.add('catalog');
    final failure = this.failure;
    return failure != null ? Left(failure) : Right(value);
  }

  @override
  Future<Either<Failure, PaymentAttempt>> initiate({
    required String reservationId,
    required String method,
    required String provider,
    required String idempotencyKey,
    String? network,
    String? phone,
    int? walletAmount,
    int? pointsAmount,
  }) async {
    // Le réseau est absent d'un paiement par carte, et d'un Mobile Money
    // encaissé par le widget : la trace garde alors le seul moyen.
    calls.add(
      network == null ? 'initiate:$provider' : 'initiate:$provider/$network',
    );
    // La clé d'idempotence se relit dans les tests : c'est elle qui décide
    // si le serveur voit une demande neuve ou la relecture d'un refus.
    idempotencyKeys.add(idempotencyKey);
    splits.add((walletAmount, pointsAmount));
    final failure = initiateFailure ?? this.failure;
    return failure != null ? Left(failure) : Right(attempt);
  }

  @override
  Future<Either<Failure, PaymentAttempt>> status(String paymentId) async {
    calls.add('status');
    final failure = this.failure;
    if (failure != null) return Left(failure);

    if (_statusReads < statuses.length) return Right(statuses[_statusReads++]);
    return Right(statuses.isEmpty ? attempt : statuses.last);
  }

  @override
  Future<Either<Failure, PaymentAttempt>> verify(
    String paymentId, {
    String? transactionId,
  }) async {
    // L'identifiant du widget apparaît dans la trace : c'est lui que le
    // serveur attend pour conclure un paiement KKiaPay.
    calls.add(transactionId == null ? 'verify' : 'verify:$transactionId');
    final failure = this.failure;
    if (failure != null) return Left(failure);

    return Right(statuses.isEmpty ? attempt : statuses.last);
  }
}

/// Un paiement lancé, en attente de confirmation chez l'opérateur.
const PaymentAttempt kStubPaymentAttempt = PaymentAttempt(
  id: 'pay-1',
  status: PaymentAttemptStatus.pending,
  reference: 'FEDA-AB12CD34',
  nextAction: 'verify',
  pollingInterval: Duration(seconds: 10),
);

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

/// Un [PaymentCatalogBloc] dont le catalogue est **déjà chargé**.
///
/// Comme [openedSession], l'attente est nécessaire : le chargement passe
/// par un événement, et sans elle l'écran de paiement se construit sur un
/// catalogue vide — donc sans aucun moyen à proposer. Dans l'app la
/// demande part à l'entrée du tunnel, bien avant l'écran de paiement.
///
/// Passer [failure] simule un serveur injoignable ; passer [catalog]
/// simule un serveur qui n'annonce pas les mêmes moyens.
Future<PaymentCatalogBloc> loadedCatalog({
  PaymentCatalog catalog = kStubPaymentCatalog,
  Failure? failure,
}) async {
  final bloc = PaymentCatalogBloc(
    StubPaymentRepository(value: catalog, failure: failure),
  )..add(const PaymentCatalogRequested());

  await bloc.stream.firstWhere(
    (state) => state.status != PaymentCatalogStatus.loading,
  );
  return bloc;
}

/// Pose un blocage réel sur [bloc], comme le ferait le récapitulatif.
///
/// Les écrans de paiement comparent la réservation qu'ils paient à celle
/// que le tunnel retient : sans blocage posé, il n'y a rien à comparer
/// et le test ne prouve rien.
Future<void> holdOneSeat(SeatHoldBloc bloc) async {
  final departure = mockDeparturesFor(
    SearchCriteria(
      origin: kMockCities.firstWhere((city) => city.id == 'ctn'),
      destination: kMockCities.firstWhere((city) => city.id == 'mlv'),
      date: DateTime(2026, 8, 14),
    ),
  ).first;

  final seat = mockSeatsFor(
    departure,
  ).firstWhere((seat) => seat.status == SeatStatus.free);

  bloc.add(
    SeatHoldCheckoutRequested(
      departure: departure,
      passengers: [
        Passenger(
          firstName: 'Dolg',
          lastName: 'Boss',
          phone: '+2290197421800',
          seat: seat,
        ),
      ],
    ),
  );

  await bloc.stream.firstWhere((state) => state.isHeld && !state.isCheckingOut);
}

/// Enveloppe [child] des trois Blocs que l'app fournit au-dessus du
/// tunnel : le blocage des sièges, la session — le formulaire voyageurs
/// pré-remplit le premier bloc avec le titulaire du compte — et le
/// catalogue des moyens de paiement.
///
/// Le catalogue est fourni **chargé** par défaut, parce que c'est l'état
/// normal quand on arrive au paiement : il est demandé à l'entrée du
/// tunnel, plusieurs écrans plus tôt. Les tests qui veulent éprouver le
/// chargement ou l'échec passent leur propre [catalog].
Widget withSeatHold(
  Widget child, {
  required SeatHoldBloc bloc,
  AuthBloc? auth,
  GroupQuoteCubit? groupQuote,
  GroupPayerCubit? groupPayer,
  PaymentCatalogBloc? catalog,
  PaymentFlowBloc? flow,
}) {
  return MultiBlocProvider(
    providers: [
      BlocProvider<SeatHoldBloc>.value(value: bloc),
      BlocProvider<AuthBloc>.value(
        value: auth ?? AuthBloc(StubAuthRepository()),
      ),
      // Le récapitulatif détaille le prix depuis le devis du serveur : le
      // tunnel le fournit dans l'app, le harnais doit en faire autant.
      BlocProvider<GroupQuoteCubit>.value(
        value: groupQuote ?? GroupQuoteCubit(StubReservationRepository()),
      ),
      // Le payeur du groupe : choisi sur l'écran de groupe, lu au blocage.
      BlocProvider<GroupPayerCubit>.value(
        value: groupPayer ?? GroupPayerCubit(StubReservationRepository()),
      ),
      BlocProvider<PaymentCatalogBloc>.value(
        value:
            catalog ??
            (PaymentCatalogBloc(StubPaymentRepository())
              ..add(const PaymentCatalogRequested())),
      ),
      BlocProvider<PaymentFlowBloc>.value(
        value: flow ?? PaymentFlowBloc(StubPaymentRepository()),
      ),
    ],
    child: child,
  );
}
