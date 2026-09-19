import '../../../../core/network/api_json.dart';
import '../../domain/entities/group_quote.dart';
import '../../domain/entities/price.dart';

/// Couche DATA : la réponse de `POST bus/itinerary-quotes`.
///
/// Le corps n'est **pas** enveloppé dans `data` : ce point d'entrée rend
/// ses champs à plat, contrairement au reste de l'API.
class GroupQuoteModel {
  const GroupQuoteModel._(this._json);

  factory GroupQuoteModel.fromJson(Map<String, dynamic> json) =>
      GroupQuoteModel._(json);

  final Map<String, dynamic> _json;

  GroupQuote toEntity({required int passengerCount}) {
    final pricing = ApiJson.map(_json['pricing']);

    return GroupQuote(
      id: ApiJson.string(_json['itinerary_quote_uuid']),
      subtotal: _price(pricing['subtotal_amount']) ?? _totalPrice(pricing),
      total: _totalPrice(pricing),
      passengerCount: passengerCount,
      discount: _discount(pricing),
      expiresAt: _dateOrNull(_json['expires_at']),
      promotion: _promotion(pricing),
    );
  }

  /// `pricing.promotion` (MEZ-73) : le code retenu et sa remise, déjà
  /// déduite du total. Sans code, le serveur rend `null` — et `promotions`,
  /// le tableau du découpage tarifaire, sert de repli.
  QuotePromotion? _promotion(Map<String, dynamic> pricing) {
    final list = pricing['promotions'];
    final raw =
        pricing['promotion'] ??
        (list is List && list.isNotEmpty ? list.first : null);
    if (raw is! Map) return null;

    final promotion = ApiJson.map(raw);
    final code = ApiJson.stringOrNull(promotion['code']);
    if (code == null) return null;

    return QuotePromotion(
      code: code,
      name: ApiJson.stringOrNull(promotion['name']),
      discount:
          _price(promotion['discount_amount']) ??
          _price(pricing['promotion_discount_amount']) ??
          const Price(0),
    );
  }

  Price _totalPrice(Map<String, dynamic> pricing) =>
      _price(pricing['total_amount']) ??
      _price(_json['total_amount']) ??
      const Price(0);

  GroupDiscount _discount(Map<String, dynamic> pricing) {
    final raw = pricing['group_discount'];
    if (raw is! Map) return GroupDiscount.none;

    final discount = ApiJson.map(raw);

    return GroupDiscount(
      applied: discount['applied'] == true,
      threshold: ApiJson.intOrNull(discount['threshold']),
      percentage: ApiJson.doubleOrNull(discount['percentage']) ?? 0,
      amount: _price(discount['amount']) ?? const Price(0),
    );
  }

  static Price? _price(Object? raw) {
    final amount = ApiJson.intOrNull(raw);
    return amount == null ? null : Price(amount);
  }

  static DateTime? _dateOrNull(Object? raw) {
    final text = ApiJson.stringOrNull(raw);
    if (text == null) return null;

    return DateTime.tryParse(text)?.toUtc();
  }
}
