import '../../../../core/network/api_json.dart';
import '../../domain/entities/kkiapay_widget.dart';
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
      // `checkout_url` est celui des recharges ; les deux autres noms
      // restent acceptés pour l'achat d'un billet.
      checkoutUrl:
          ApiJson.stringOrNull(_json['checkout_url']) ??
          ApiJson.stringOrNull(_json['payment_url']) ??
          ApiJson.stringOrNull(_json['redirect_url']),
      widget: _widget(),
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
  /// La configuration du widget KKiaPay, quand le serveur l'envoie.
  ///
  /// Les noms viennent du SDK, pas de nos conventions : `key`,
  /// `partnerId`, `paymentmethod`. On les lit tels quels plutôt que de
  /// les renommer, pour que la correspondance avec la documentation
  /// KKiaPay reste lisible.
  KkiapayWidget? _widget() {
    final raw = _json['widget'];
    if (raw is! Map) return null;

    final widget = raw.cast<String, dynamic>();
    final key = ApiJson.string(widget['key']);
    if (key.isEmpty) return null;

    return KkiapayWidget(
      publicKey: key,
      amount: ApiJson.intOrNull(widget['amount']) ?? 0,
      partnerId: ApiJson.string(widget['partnerId']),
      // Absent, on suppose le bac à sable : se tromper dans ce sens
      // n'encaisse rien, alors que l'inverse débiterait pour de vrai.
      sandbox: widget['sandbox'] != false,
      methods: ApiJson.strings(widget['paymentmethod']),
      countries: ApiJson.strings(widget['countries']),
      phone: ApiJson.stringOrNull(widget['phone']),
      reason: ApiJson.stringOrNull(widget['data']),
    );
  }

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
