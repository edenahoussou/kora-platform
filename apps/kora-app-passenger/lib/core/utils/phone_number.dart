import '../../features/auth/domain/entities/phone_country.dart';

/// Ce qu'il advient d'un numéro déjà saisi quand on change d'indicatif.
///
/// Trois écrans posent la même paire « indicatif + numéro » — permissions,
/// formulaire voyageurs, paiement Mobile Money — et se posaient donc trois
/// fois la même question. La règle vit ici, une seule fois : la dupliquer
/// laisserait deux écrans se comporter différemment sur le même geste.
abstract final class PhoneNumberInput {
  /// Le numéro [text] réaccordé au passage de [from] à [to].
  ///
  /// Seul le préfixe imposé par le pays qu'on **quitte** est retiré. Le
  /// Bénin est passé à dix chiffres en 2024 et fait commencer ses numéros
  /// par `01` ; le Togo n'impose rien. Sans ce retrait, le champ affichait
  /// `01 97 42 18 60` sous un indicatif `+228` — un numéro qui n'existe
  /// dans aucun des deux pays.
  ///
  /// **On n'ajoute jamais le préfixe du pays d'arrivée.** Compléter un
  /// numéro togolais d'un `01` pour en faire un numéro béninois
  /// inventerait deux chiffres que personne n'a tapés — et le voyageur
  /// paierait sur un numéro qu'il n'a pas choisi.
  static String rebase(
    String text, {
    required PhoneCountry from,
    required PhoneCountry to,
  }) {
    if (from == to || from.prefix.isEmpty) return text;

    final digits = text.replaceAll(RegExp(r'\D'), '');
    if (!digits.startsWith(from.prefix)) return text;

    return _withoutLeadingDigits(text, from.prefix.length);
  }

  /// Retire les [count] premiers chiffres de [text] **sans toucher à sa
  /// mise en forme** : `01 97 42 18 60` devient `97 42 18 60`, et non
  /// `9742186 0`.
  ///
  /// On ne repart pas des chiffres seuls, justement : le numéro affiché
  /// est espacé par groupes, et le recomposer d'un bloc donnerait au
  /// voyageur l'impression que l'app a réécrit son numéro.
  static String _withoutLeadingDigits(String text, int count) {
    final buffer = StringBuffer();
    var dropped = 0;

    for (final character in text.split('')) {
      if (dropped < count) {
        // Les séparateurs rencontrés avant la fin du préfixe partent avec
        // lui : ils ne séparent plus rien.
        if (RegExp(r'\d').hasMatch(character)) dropped++;
        continue;
      }
      buffer.write(character);
    }

    return buffer.toString().trimLeft();
  }

  /// L'erreur à afficher pour [text] dans le pays [country], ou `null` si
  /// le numéro est acceptable.
  ///
  /// Ne rend pas de message tout fait : chaque écran a ses propres
  /// libellés traduits. Il rend la **raison**, à l'écran de la formuler.
  static PhoneIssue? validate(String text, PhoneCountry country) {
    final digits = text.replaceAll(RegExp(r'\D'), '');
    if (digits.isEmpty) return PhoneIssue.missing;

    if (country.prefix.isNotEmpty && !digits.startsWith(country.prefix)) {
      return PhoneIssue.wrongPrefix;
    }

    return digits.length < country.digits ? PhoneIssue.tooShort : null;
  }
}

/// Ce qui cloche dans un numéro saisi.
enum PhoneIssue {
  /// Champ vide.
  missing,

  /// Le pays impose un début de numéro que la saisie n'a pas (`01` au
  /// Bénin).
  wrongPrefix,

  /// Moins de chiffres que le pays n'en attend.
  tooShort,
}
