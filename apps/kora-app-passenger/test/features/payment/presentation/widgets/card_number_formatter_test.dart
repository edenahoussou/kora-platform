// La mise en forme du numéro de carte et de sa date d'expiration, au fil
// de la frappe.

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/payment/presentation/widgets/card_number_formatter.dart';

void main() {
  /// Rejoue une saisie caractère par caractère, comme le clavier.
  String type(TextInputFormatter formatter, String keys) {
    var value = TextEditingValue.empty;

    for (final key in keys.split('')) {
      final typed = TextEditingValue(
        text: value.text + key,
        selection: TextSelection.collapsed(offset: value.text.length + 1),
      );
      value = formatter.formatEditUpdate(value, typed);
    }

    return value.text;
  }

  group('numéro de carte', () {
    const formatter = CardNumberFormatter();

    test('groupe les chiffres par quatre', () {
      expect(type(formatter, '4242424242424242'), '4242 4242 4242 4242');
    });

    test('n’ajoute l’espace qu’à partir du cinquième chiffre', () {
      expect(type(formatter, '4242'), '4242');
      expect(type(formatter, '42424'), '4242 4');
    });

    test('ignore ce qui n’est pas un chiffre', () {
      expect(type(formatter, '4a2b4c2-4 2 4 2'), '4242 4242');
    });

    test('s’arrête à dix-neuf chiffres', () {
      // La plus longue carte du schéma ISO/IEC 7812.
      final text = type(formatter, '12345678901234567890123');

      expect(text.replaceAll(' ', '').length, 19);
      expect(text, '1234 5678 9012 3456 789');
    });

    test('laisse le curseur en fin de saisie', () {
      // L'espace insérée décale tout ce qui suit : un curseur resté en
      // place ferait taper le chiffre suivant au milieu du groupe
      // précédent.
      final value = formatter.formatEditUpdate(
        TextEditingValue.empty,
        const TextEditingValue(
          text: '42424',
          selection: TextSelection.collapsed(offset: 5),
        ),
      );

      expect(value.selection.baseOffset, value.text.length);
    });
  });

  group('expiration', () {
    const formatter = CardExpiryFormatter();

    test('sépare le mois de l’année', () {
      expect(type(formatter, '0829'), '08 / 29');
    });

    test('laisse le mois seul tant qu’il est incomplet', () {
      expect(type(formatter, '0'), '0');
      expect(type(formatter, '08'), '08');
    });

    test('s’arrête à quatre chiffres', () {
      expect(type(formatter, '082999'), '08 / 29');
    });
  });
}
