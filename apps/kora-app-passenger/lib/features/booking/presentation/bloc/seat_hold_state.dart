part of 'seat_hold_bloc.dart';

/// Où en est le blocage.
///
/// [idle] n'est pas un état d'attente mais l'état **normal** de la
/// première visite : rien n'est retenu, donc pas de carte de décompte —
/// c'est le choix des sièges qui n'a pas encore été confirmé.
enum SeatHoldStatus { idle, holding, held, expired, failure }

/// Où en est l'envoi des identités des voyageurs.
///
/// Séparé de [SeatHoldStatus] : pendant cet envoi le blocage reste `held`
/// et le décompte doit continuer de courir. Les mêler aurait fait
/// disparaître la carte de décompte le temps de l'appel.
enum PassengersStatus { untouched, saving, saved, failure }

/// Un code promo en cours d'application ou de retrait.
enum PromoStatus { idle, applying, removing }

class SeatHoldState extends Equatable {
  const SeatHoldState({
    this.status = SeatHoldStatus.idle,
    this.hold,
    this.remaining = Duration.zero,
    this.failure,
    this.departure,
    this.passengers = 1,
    this.passengersStatus = PassengersStatus.untouched,
    this.quote,
    this.promoStatus = PromoStatus.idle,
    this.promoError,
    this.promoUnavailable = false,
  });

  final SeatHoldStatus status;
  final SeatHold? hold;

  /// Le départ concerné, gardé au-delà de l'expiration : l'écran de fin de
  /// délai en a besoin pour renvoyer au bon plan de sièges, alors que le
  /// blocage, lui, n'existe plus.
  final Departure? departure;

  final int passengers;

  /// L'envoi des identités, indépendant du blocage.
  final PassengersStatus passengersStatus;

  /// Recalculé à chaque seconde depuis `hold.expiresAt`, jamais décrémenté
  /// pas à pas : un minuteur qui compte ses propres battements dérive dès
  /// que l'app passe en arrière-plan, alors qu'une soustraction de dates
  /// rattrape le temps perdu.
  final Duration remaining;

  final Failure? failure;

  /// Le devis sur lequel la réservation a été rebloquée après un code promo
  /// appliqué ou retiré — celui que le paiement débitera. `null` tant que
  /// le voyageur n'a touché à aucun code.
  final GroupQuote? quote;

  final PromoStatus promoStatus;

  /// Le message du serveur pour le dernier code refusé.
  final String? promoError;

  /// Le code était valable, mais la réservation n'a pas pu être libérée
  /// pour être rebloquée à son prix : elle reste au tarif normal.
  final bool promoUnavailable;

  bool get isRepricing => promoStatus != PromoStatus.idle;

  /// Des sièges sont retenus : le décompte court, sur tous les écrans du
  /// tunnel.
  bool get isHeld => status == SeatHoldStatus.held && hold != null;

  /// `06:42`, tel que l'affiche la maquette.
  String get formatted {
    final minutes = remaining.inMinutes.toString().padLeft(2, '0');
    final seconds = (remaining.inSeconds % 60).toString().padLeft(2, '0');
    return '$minutes:$seconds';
  }

  SeatHoldState copyWith({
    SeatHoldStatus? status,
    SeatHold? hold,
    Duration? remaining,
    Failure? errorMessage,
    Departure? departure,
    int? passengers,
    PassengersStatus? passengersStatus,
    bool clearError = false,
    GroupQuote? quote,
    PromoStatus? promoStatus,
    String? promoError,
    bool clearPromoError = false,
    bool? promoUnavailable,
  }) {
    return SeatHoldState(
      status: status ?? this.status,
      hold: hold ?? this.hold,
      remaining: remaining ?? this.remaining,
      failure: clearError ? null : (errorMessage ?? failure),
      departure: departure ?? this.departure,
      passengers: passengers ?? this.passengers,
      passengersStatus: passengersStatus ?? this.passengersStatus,
      quote: quote ?? this.quote,
      promoStatus: promoStatus ?? this.promoStatus,
      promoError: clearPromoError ? null : (promoError ?? this.promoError),
      promoUnavailable: promoUnavailable ?? this.promoUnavailable,
    );
  }

  @override
  List<Object?> get props => [
    status,
    hold,
    remaining,
    failure,
    departure,
    passengers,
    passengersStatus,
    quote,
    promoStatus,
    promoError,
    promoUnavailable,
  ];
}
