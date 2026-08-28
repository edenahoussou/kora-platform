import '../../../../core/network/api_json.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/quote.dart';

/// Couche DATA : le devis renvoyé par `POST bus/quotes`.
///
/// ```json
/// {
///   "uuid": "9b1deb4d-…",
///   "final_amount": 12500,
///   "total_amount": 12500,
///   "currency": "XOF",
///   "passenger_count": 1,
///   "expires_at": "2026-08-20T08:10:00Z"
/// }
/// ```
///
/// `total_amount` couvre tous les voyageurs, `final_amount` un seul : c'est
/// le premier qu'on garde, puisque le devis vaut pour la réservation
/// entière.
class QuoteModel {
  const QuoteModel({
    required this.uuid,
    required this.totalAmount,
    required this.currency,
    required this.passengerCount,
    required this.expiresAt,
  });

  factory QuoteModel.fromJson(Map<String, dynamic> json) {
    return QuoteModel(
      uuid: ApiJson.string(json['uuid']),
      totalAmount:
          ApiJson.intOrNull(json['total_amount']) ??
          ApiJson.intOrNull(json['final_amount']) ??
          0,
      currency: ApiJson.string(json['currency'], 'XOF'),
      passengerCount: ApiJson.intOrNull(json['passenger_count']) ?? 1,
      expiresAt: ApiJson.stringOrNull(json['expires_at']),
    );
  }

  final String uuid;
  final int totalAmount;
  final String currency;
  final int passengerCount;
  final String? expiresAt;

  Quote toEntity() {
    return Quote(
      id: uuid,
      total: Price(totalAmount, currency: _displayCurrency(currency)),
      passengerCount: passengerCount,
      expiresAt: parseExpiry(expiresAt),
    );
  }
}

/// `XOF` est le code ISO ; les écrans, eux, disent `FCFA` — c'est ce que
/// lit le voyageur sur son billet et dans la maquette.
String _displayCurrency(String code) =>
    code.toUpperCase() == 'XOF' ? 'FCFA' : code;

/// Lit une échéance ISO en **UTC**.
///
/// Le serveur envoie du `Z` ; le comparer à une heure locale décalerait le
/// décompte d'un fuseau entier. En l'absence d'échéance lisible, on rend
/// l'instant présent : un blocage déjà expiré est le seul repli honnête —
/// il renvoie au plan au lieu de laisser croire à du temps qui n'existe
/// pas.
DateTime parseExpiry(String? raw) {
  if (raw == null) return DateTime.now().toUtc();
  return DateTime.tryParse(raw)?.toUtc() ?? DateTime.now().toUtc();
}
