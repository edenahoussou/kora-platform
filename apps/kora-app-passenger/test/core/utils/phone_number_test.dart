// La règle du numéro quand on change d'indicatif, partagée par les trois
// écrans qui demandent un téléphone.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/utils/phone_number.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/phone_country.dart';

void main() {
  final benin = PhoneCountry.benin;
  final togo = PhoneCountry.all.firstWhere((c) => c.code == '+228');
  final ghana = PhoneCountry.all.firstWhere((c) => c.code == '+233');

  group('rebase', () {
    test('retire le 01 béninois quand on part vers un autre pays', () {
      expect(
        PhoneNumberInput.rebase('01 97 42 18 60', from: benin, to: togo),
        '97 42 18 60',
      );
    });

    test('garde la mise en forme du numéro', () {
      // Et non `9742186 0` : recomposer le numéro d'un bloc donnerait
      // l'impression que l'app l'a réécrit.
      final rebased = PhoneNumberInput.rebase(
        '01 97 42 18 60',
        from: benin,
        to: ghana,
      );

      expect(rebased, isNot(contains('  ')));
      expect(rebased, '97 42 18 60');
    });

    test('n’ajoute jamais le préfixe du pays d’arrivée', () {
      // Compléter un numéro togolais d'un `01` inventerait deux chiffres
      // que personne n'a tapés.
      expect(
        PhoneNumberInput.rebase('90 12 34 56', from: togo, to: benin),
        '90 12 34 56',
      );
    });

    test('ne touche à rien si le pays ne change pas', () {
      expect(
        PhoneNumberInput.rebase('01 97 42 18 60', from: benin, to: benin),
        '01 97 42 18 60',
      );
    });

    test('laisse intact un numéro béninois qui ne commence pas par 01', () {
      // Un ancien numéro à huit chiffres : il n'y a pas de préfixe à
      // retirer, et en amputer deux chiffres serait pire que de ne rien
      // faire.
      expect(
        PhoneNumberInput.rebase('97 42 18 60', from: benin, to: togo),
        '97 42 18 60',
      );
    });

    test('un champ vide reste vide', () {
      expect(PhoneNumberInput.rebase('', from: benin, to: togo), '');
    });
  });

  group('validate', () {
    test('signale un champ vide', () {
      expect(PhoneNumberInput.validate('', benin), PhoneIssue.missing);
    });

    test('exige le 01 au Bénin, et nulle part ailleurs', () {
      expect(
        PhoneNumberInput.validate('97 42 18 60', benin),
        PhoneIssue.wrongPrefix,
      );
      // Huit chiffres au Togo : le même numéro y est parfaitement valable.
      expect(PhoneNumberInput.validate('97 42 18 60', togo), isNull);
    });

    test('compte les chiffres attendus par le pays', () {
      expect(
        PhoneNumberInput.validate('01 97 42 18', benin),
        PhoneIssue.tooShort,
      );
      expect(PhoneNumberInput.validate('01 97 42 18 60', benin), isNull);
      expect(PhoneNumberInput.validate('97 42 18', togo), PhoneIssue.tooShort);
    });

    test('le préfixe est vérifié avant la longueur', () {
      // Sinon un numéro béninois sans `01` serait annoncé « trop court »,
      // et le voyageur ajouterait deux chiffres au mauvais endroit.
      expect(
        PhoneNumberInput.validate('97 42 18', benin),
        PhoneIssue.wrongPrefix,
      );
    });
  });
}
