import 'package:flutter/services.dart';

/// Ce qu'un code de parrainage accepte à la saisie.
///
/// Deux écrans le demandent — l'inscription, qui le rattache en créant le
/// compte, et le Wallet, qui le rattache après coup — et le serveur
/// applique la même règle aux deux.

/// Longueur retenue par l'API (`max:24`). Au-delà, elle répond 422 sans
/// même chercher le code : autant ne pas laisser taper plus.
const int referralCodeMaxLength = 24;

/// Met la saisie en majuscules au fil de la frappe.
///
/// Le serveur compare des majuscules : laisser passer « klv5sa3h »
/// ferait répondre « code inconnu » pour une simple casse.
class ReferralCodeCase extends TextInputFormatter {
  const ReferralCodeCase();

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue previous,
    TextEditingValue next,
  ) => TextEditingValue(
    text: next.text.toUpperCase(),
    selection: next.selection,
  );
}

/// Les deux règles ensemble, dans l'ordre où elles s'appliquent.
List<TextInputFormatter> referralCodeFormatters() => [
  const ReferralCodeCase(),
  LengthLimitingTextInputFormatter(referralCodeMaxLength),
];
