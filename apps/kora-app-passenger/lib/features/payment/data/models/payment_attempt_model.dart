import '../../../../core/network/api_json.dart';
import '../../domain/entities/payment_attempt.dart';

/// Couche DATA : la réponse de `POST payments/initiate` et de
/// `GET payments/{uuid}`, qui portent la même ressource.
class PaymentAttemptModel {
  const PaymentAttemptModel._(this._json);

  factory PaymentAttemptModel.fromJson(Map<String, dynamic> json) =>
      PaymentAttemptModel._(json);

  final Map<String, dynamic> _json;

  PaymentAttempt toEntity() {
    return PaymentAttempt(
      id: ApiJson.string(_json['uuid']),
      status: PaymentAttemptStatus.fromWire(ApiJson.string(_json['status'])),
      reference: ApiJson.stringOrNull(_json['provider_reference']),
      nextAction: ApiJson.string(_json['next_action']),
      pollingInterval: _pollingInterval(),
      expiresAt: _dateOrNull(_json['expires_at']),
      failureMessage: _failureMessage(),
      // Prévu, jamais renvoyé aujourd'hui : le serveur pousse la demande
      // sur le téléphone plutôt que d'ouvrir une page. Les deux noms sont
      // acceptés pour n'avoir rien à changer ici le jour venu.
      checkoutUrl:
          ApiJson.stringOrNull(_json['payment_url']) ??
          ApiJson.stringOrNull(_json['redirect_url']),
      reservationHeld: _reservationHeld(),
    );
  }

  /// La réservation tient-elle encore ses sièges ?
  ///
  /// Le serveur joint son statut à la réponse de paiement. Un refus
  /// d'opérateur la passe à `expired` et relâche les places : c'est ce
  /// qui rendait tout nouvel essai impossible, sans que l'app le sache.
  ///
  /// Sans statut dans la réponse, on suppose la réservation vivante :
  /// mieux vaut proposer un essai de trop que d'annoncer à tort des
  /// sièges perdus.
  bool _reservationHeld() {
    final reservation = _json['reservation'];
    if (reservation is! Map) return true;

    final status = ApiJson.stringOrNull(
      Map<String, dynamic>.from(reservation)['status'],
    );
    if (status == null) return true;

    return const {
      'held',
      'confirmed',
      'assistance_pending',
      'paid',
      'ticketed',
    }.contains(status);
  }

  /// Une cadence nulle ou négative n'en est pas une : elle ferait tourner
  /// la boucle de relecture sans répit.
  Duration _pollingInterval() {
    final seconds = ApiJson.intOrNull(_json['polling_interval_seconds']);
    if (seconds == null || seconds <= 0) {
      return PaymentAttempt.defaultPollingInterval;
    }

    return Duration(seconds: seconds);
  }

  String? _failureMessage() {
    final failure = _json['failure'];
    if (failure is! Map) return null;

    return ApiJson.stringOrNull(failure['message']);
  }

  static DateTime? _dateOrNull(Object? raw) {
    final text = ApiJson.stringOrNull(raw);
    if (text == null) return null;

    return DateTime.tryParse(text)?.toUtc();
  }
}
