import 'package:equatable/equatable.dart';

/// Indicatif téléphonique proposé à l'inscription.
///
/// L'API stocke `country_code` et `phone` séparément (`PUT auth/me`) : ce
/// petit catalogue permet de déduire l'un du numéro composé, plutôt que
/// de demander deux fois la même chose à l'utilisateur.
///
/// La liste suit les corridors desservis par KORA — Bénin d'abord, puis
/// les pays voisins et la diaspora — au lieu des 200 indicatifs du monde,
/// qu'il faudrait faire défiler pour rien.
class PhoneCountry extends Equatable {
  const PhoneCountry({
    required this.code,
    required this.flag,
    required this.name,
    required this.digits,
    required this.example,
    this.prefix = '',
  });

  /// Tel qu'attendu par l'API (`country_code`, 4 caractères au plus).
  final String code;

  final String flag;
  final String name;

  /// Longueur du numéro national, indicatif exclu. Sert à repérer une
  /// saisie manifestement incomplète avant l'appel réseau.
  final int digits;

  /// Numéro d'exemple affiché en filigrane dans le champ.
  final String example;

  /// Début imposé du numéro national, quand le pays en a un.
  ///
  /// Le Bénin est passé à 10 chiffres en 2024 : les anciens numéros à 8
  /// chiffres ont tous reçu le préfixe `01`. Un numéro saisi sans lui est
  /// une habitude d'avant la réforme, pas une faute de frappe — l'écran
  /// le dit clairement plutôt que d'annoncer un numéro « incomplet ».
  final String prefix;

  static const benin = PhoneCountry(
    code: '+229',
    flag: '🇧🇯',
    name: 'Bénin',
    digits: 10,
    prefix: '01',
    example: '01 97 00 00 00',
  );

  static const all = <PhoneCountry>[
    benin,
    PhoneCountry(
      code: '+228',
      flag: '🇹🇬',
      name: 'Togo',
      digits: 8,
      example: '90 00 00 00',
    ),
    PhoneCountry(
      code: '+234',
      flag: '🇳🇬',
      name: 'Nigéria',
      digits: 10,
      example: '802 000 0000',
    ),
    PhoneCountry(
      code: '+227',
      flag: '🇳🇪',
      name: 'Niger',
      digits: 8,
      example: '90 00 00 00',
    ),
    PhoneCountry(
      code: '+226',
      flag: '🇧🇫',
      name: 'Burkina Faso',
      digits: 8,
      example: '70 00 00 00',
    ),
    PhoneCountry(
      code: '+225',
      flag: '🇨🇮',
      name: 'Côte d’Ivoire',
      digits: 10,
      example: '01 02 03 04 05',
    ),
    PhoneCountry(
      code: '+233',
      flag: '🇬🇭',
      name: 'Ghana',
      digits: 9,
      example: '24 000 0000',
    ),
    PhoneCountry(
      code: '+221',
      flag: '🇸🇳',
      name: 'Sénégal',
      digits: 9,
      example: '77 000 00 00',
    ),
    PhoneCountry(
      code: '+223',
      flag: '🇲🇱',
      name: 'Mali',
      digits: 8,
      example: '70 00 00 00',
    ),
    PhoneCountry(
      code: '+33',
      flag: '🇫🇷',
      name: 'France',
      digits: 9,
      example: '6 12 34 56 78',
    ),
  ];

  /// Un indicatif choisi hors des corridors KORA.
  ///
  /// Les écrans de connexion et d'inscription ouvrent désormais la liste
  /// complète des pays : un passager joignable sur un numéro canadien ou
  /// marocain ne pouvait pas s'inscrire, faute de trouver son indicatif.
  ///
  /// Quand le pays choisi est déjà l'un des onze, on rend l'entrée du
  /// catalogue — le Bénin garde ainsi son préfixe `01` et ses dix
  /// chiffres attendus. Ailleurs, on ignore la longueur du numéro
  /// national, donc [digits] vaut `0` : l'écran ne juge plus la saisie
  /// trop courte et laisse le serveur trancher, plutôt que de refuser un
  /// numéro parfaitement valide sur la foi d'une règle inventée.
  factory PhoneCountry.beyond({
    required String code,
    required String flag,
    required String name,
    String example = '',
  }) {
    for (final country in all) {
      if (country.code == code) return country;
    }

    return PhoneCountry(
      code: code,
      flag: flag,
      name: name,
      digits: 0,
      example: example,
    );
  }

  /// Retrouve un indicatif à partir d'un `country_code` déjà enregistré,
  /// pour repositionner le sélecteur sur le bon pays.
  static PhoneCountry fromCode(String? code) =>
      all.firstWhere((country) => country.code == code, orElse: () => benin);

  /// Le pays dont l'indicatif ouvre ce numéro, `null` quand le numéro n'en
  /// porte pas — ou qu'il vient d'un pays hors catalogue.
  ///
  /// Le serveur garde `phone` tantôt au format local (`0197000000`),
  /// tantôt au format international (`+2290197000000`) : sans cette
  /// lecture, l'écran recollait un second indicatif devant le premier.
  static PhoneCountry? fromPhone(String? phone) {
    final compact = phone?.replaceAll(RegExp(r'[^\d+]'), '');
    if (compact == null || !compact.startsWith('+')) return null;

    PhoneCountry? found;
    for (final country in all) {
      // Le plus long indicatif gagne : `+22` ne doit pas coiffer `+229`.
      if (compact.startsWith(country.code) &&
          (found == null || country.code.length > found.code.length)) {
        found = country;
      }
    }
    return found;
  }

  /// Le numéro national de [phone], indicatif retiré s'il y en a un.
  String nationalPart(String phone) {
    final compact = phone.replaceAll(RegExp(r'[^\d+]'), '');
    return compact.startsWith(code) ? compact.substring(code.length) : compact;
  }

  @override
  List<Object?> get props => [code, flag, name, digits, example, prefix];
}
