import 'package:flutter/services.dart';

/// Groupe un numéro de carte par quatre au fil de la frappe
/// (`4242 4242 4242 4242`), comme la maquette.
///
/// Le regroupement n'est pas décoratif : seize chiffres d'affilée ne se
/// relisent pas, et c'est précisément le moment où le voyageur doit
/// pouvoir comparer ce qu'il a tapé avec la carte qu'il tient.
///
/// Le curseur est replacé en fin de saisie plutôt que laissé où il était :
/// insérer une espace décale tout ce qui suit, et un curseur qui ne suit
/// pas ferait taper le chiffre suivant au milieu du groupe précédent.
class CardNumberFormatter extends TextInputFormatter {
  const CardNumberFormatter({this.maxDigits = 19, this.group = 4});

  /// 19 chiffres : la plus longue carte du schéma ISO/IEC 7812.
  final int maxDigits;
  final int group;

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final kept = digits.length > maxDigits
        ? digits.substring(0, maxDigits)
        : digits;

    final buffer = StringBuffer();
    for (var i = 0; i < kept.length; i++) {
      if (i > 0 && i % group == 0) buffer.write(' ');
      buffer.write(kept[i]);
    }

    final text = buffer.toString();
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}

/// Le mois et l'année d'expiration, séparés au fil de la frappe
/// (`08 / 29`).
///
/// Même raison que le numéro : la barre apparaît toute seule, pour que
/// personne n'ait à deviner s'il faut la taper.
class CardExpiryFormatter extends TextInputFormatter {
  const CardExpiryFormatter();

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final kept = digits.length > 4 ? digits.substring(0, 4) : digits;

    final text = kept.length <= 2
        ? kept
        : '${kept.substring(0, 2)} / ${kept.substring(2)}';

    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}
