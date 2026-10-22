// Le catalogue d'indicatifs, et ce qu'il advient des pays qui n'y sont
// pas : les écrans de connexion et d'inscription ouvrent désormais la
// liste complète, le catalogue ne peut plus être le dernier mot.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/utils/phone_number.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/phone_country.dart';

void main() {
  group('PhoneCountry.beyond', () {
    test('rend l’entrée du catalogue quand le pays y figure', () {
      // Le sélecteur ne connaît ni le préfixe `01` ni les dix chiffres
      // du Bénin : s'il écrasait l'entrée du catalogue, la réforme de
      // 2024 cesserait d'être expliquée aux passagers béninois.
      final picked = PhoneCountry.beyond(
        code: '+229',
        flag: '🇧🇯',
        name: 'Benin',
        example: '01 20 21 22 23',
      );

      expect(picked, PhoneCountry.benin);
      expect(picked.prefix, '01');
      expect(picked.digits, 10);
    });

    test('accepte un pays hors catalogue sans inventer de règle', () {
      final picked = PhoneCountry.beyond(
        code: '+1',
        flag: '🇨🇦',
        name: 'Canada',
        example: '201 555 0123',
      );

      expect(picked.code, '+1');
      expect(picked.name, 'Canada');
      expect(picked.example, '201 555 0123');
      // Longueur inconnue : on ne refuse pas un numéro valide au nom
      // d'une règle qu'on n'a pas.
      expect(picked.digits, 0);
      expect(picked.prefix, '');
    });

    test('un numéro hors catalogue n’est jamais jugé trop court', () {
      final canada = PhoneCountry.beyond(
        code: '+1',
        flag: '🇨🇦',
        name: 'Canada',
      );

      expect(
        PhoneNumberInput.validate('2015550123', canada),
        isNull,
        reason: 'le serveur tranche, pas l’écran',
      );
      // Le champ vide reste refusé : ça, l'écran le sait.
      expect(PhoneNumberInput.validate('', canada), PhoneIssue.missing);
    });

    test('quitter le Bénin pour un pays hors catalogue retire le 01', () {
      final canada = PhoneCountry.beyond(
        code: '+1',
        flag: '🇨🇦',
        name: 'Canada',
      );

      expect(
        PhoneNumberInput.rebase(
          '01 97 42 18 60',
          from: PhoneCountry.benin,
          to: canada,
        ),
        '97 42 18 60',
      );
    });
  });
}
