import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('InMemoryNetworkCache', () {
    late InMemoryNetworkCache cache;

    setUp(() => cache = InMemoryNetworkCache());

    test('write puis read retourne la valeur stockée', () async {
      await cache.write('key', {'value': 1});

      expect(await cache.read('key'), {'value': 1});
    });

    test('read sur une clé absente retourne null', () async {
      expect(await cache.read('missing'), isNull);
    });

    test('remove supprime une entrée', () async {
      await cache.write('key', 'value');
      await cache.remove('key');

      expect(await cache.read('key'), isNull);
    });

    test('clear vide tout le cache', () async {
      await cache.write('a', 1);
      await cache.write('b', 2);

      await cache.clear();

      expect(await cache.read('a'), isNull);
      expect(await cache.read('b'), isNull);
    });

    test(
      'une entrée expirée (maxAge dépassé) redevient inaccessible',
      () async {
        await cache.write(
          'key',
          'value',
          maxAge: const Duration(milliseconds: -1),
        );

        expect(await cache.read('key'), isNull);
      },
    );

    test('une entrée sans maxAge ne périme jamais', () async {
      await cache.write('key', 'value');

      expect(await cache.read('key'), 'value');
    });
  });
}
