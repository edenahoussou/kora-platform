import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/app_update/domain/entities/app_version.dart';

AppVersion _v(String raw) => AppVersion.tryParse(raw)!;

void main() {
  group('AppVersion.tryParse', () {
    test('lit les formats remontés par les appareils', () {
      expect(_v('1.4.0').toString(), '1.4.0');
      expect(_v('v1.4.0').toString(), '1.4.0');
      expect(_v('1.4.0+42').toString(), '1.4.0+42');
      expect(_v('1.4.0 (42)').toString(), '1.4.0+42');
      expect(_v('1.4.0-beta.1+42').toString(), '1.4.0-beta.1+42');
      expect(_v('1.4').toString(), '1.4.0');
    });

    test('retourne null pour une chaîne vide ou illisible', () {
      expect(AppVersion.tryParse(null), isNull);
      expect(AppVersion.tryParse(''), isNull);
      expect(AppVersion.tryParse('latest'), isNull);
    });
  });

  group('AppVersion.compareTo (miroir du backend)', () {
    test('précédence semver numérique, pas lexicographique', () {
      expect(_v('1.10.0').isNewerThan(_v('1.9.0')), isTrue);
      expect(_v('2.0.0+1').isNewerThan(_v('1.99.99+999')), isTrue);
    });

    test('le build départage deux versions semver identiques', () {
      expect(_v('1.4.0+42').isNewerThan(_v('1.4.0+41')), isTrue);
    });

    test('un build absent ne départage jamais', () {
      expect(_v('1.4.0').compareTo(_v('1.4.0+41')), 0);
    });

    test('une pré-version précède la version finale', () {
      expect(_v('1.4.0-beta.1').isOlderThan(_v('1.4.0')), isTrue);
      expect(_v('1.4.0-beta.2').isOlderThan(_v('1.4.0-beta.10')), isTrue);
      expect(_v('1.4.0-alpha').isNewerThan(_v('1.4.0-1')), isTrue);
    });
  });
}
