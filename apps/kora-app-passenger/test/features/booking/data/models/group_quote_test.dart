// Ce que l'app comprend de `POST bus/itinerary-quotes`.
//
// C'est le seul point d'entrée qui rend la remise de groupe au passager :
// `group-discounts` et `group-booking-settings` sont fermés par une
// permission d'administration. Si cette lecture se trompe, le voyageur voit
// un prix que la caisse ne reconnaît pas.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/booking/data/models/group_quote_model.dart';

void main() {
  Map<String, dynamic> response({
    int subtotal = 62500,
    int total = 56250,
    Map<String, dynamic>? discount,
  }) => {
    'itinerary_quote_uuid': 'iq-1',
    'total_amount': total,
    'currency': 'XOF',
    'expires_at': '2026-09-05T18:15:00Z',
    'pricing': {
      'subtotal_amount': subtotal,
      'dynamic_adjustment_amount': 0,
      'group_discount':
          discount ??
          {
            'applied': true,
            'threshold': 5,
            'percentage': 10.0,
            'amount': 6250,
            'rule_uuid': 'rule-1',
          },
      'total_amount': total,
    },
  };

  test('lit le sous-total, la remise et le total', () {
    final quote = GroupQuoteModel.fromJson(
      response(),
    ).toEntity(passengerCount: 5);

    expect(quote.id, 'iq-1');
    expect(quote.subtotal.amount, 62500);
    expect(quote.discount.amount.amount, 6250);
    expect(quote.discount.threshold, 5);
    expect(quote.total.amount, 56250);
  });

  test('déduit le tarif unitaire du sous-total, pas du total', () {
    // Le prix affiché par voyageur est celui d'avant remise : la remise a
    // sa propre ligne, et la compter deux fois la ferait disparaître.
    final quote = GroupQuoteModel.fromJson(
      response(),
    ).toEntity(passengerCount: 5);

    expect(quote.unitPrice.amount, 12500);
  });

  test('une remise non appliquée ne s’affiche pas', () {
    final quote = GroupQuoteModel.fromJson(
      response(
        subtotal: 25000,
        total: 25000,
        discount: {
          'applied': false,
          'threshold': 5,
          'percentage': 0.0,
          'amount': 0,
          'rule_uuid': null,
        },
      ),
    ).toEntity(passengerCount: 2);

    expect(quote.discount.applied, isFalse);
    expect(quote.discount.isVisible, isFalse);
  });

  test('une remise appliquée mais nulle ne mérite pas sa ligne', () {
    // Le serveur peut annoncer une règle active dont le montant tombe à
    // zéro ; une ligne « −0 FCFA » n'apprend rien.
    final quote = GroupQuoteModel.fromJson(
      response(
        subtotal: 25000,
        total: 25000,
        discount: {
          'applied': true,
          'threshold': 5,
          'percentage': 0.0,
          'amount': 0,
          'rule_uuid': 'rule-1',
        },
      ),
    ).toEntity(passengerCount: 2);

    expect(quote.discount.isVisible, isFalse);
  });

  test('un bloc de remise absent ne fait pas échouer la lecture', () {
    // Le serveur pourrait cesser de l'envoyer sur un voyage individuel.
    final json = response()..['pricing'] = {'total_amount': 12500};
    final quote = GroupQuoteModel.fromJson(json).toEntity(passengerCount: 1);

    expect(quote.discount, isNotNull);
    expect(quote.discount.isVisible, isFalse);
    expect(quote.total.amount, 12500);
  });

  test('sans sous-total, le total en tient lieu', () {
    final json = response()
      ..['pricing'] = {
        'total_amount': 12500,
        'group_discount': {'applied': false, 'amount': 0},
      };
    final quote = GroupQuoteModel.fromJson(json).toEntity(passengerCount: 1);

    expect(quote.subtotal.amount, 12500);
    expect(quote.unitPrice.amount, 12500);
  });
}
