import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('PaginatedResponse', () {
    test('parse le format Laravel (data + meta)', () {
      final json = {
        'data': [
          {'id': 1},
          {'id': 2},
        ],
        'meta': {'current_page': 2, 'per_page': 2, 'total': 5, 'last_page': 3},
      };

      final page = PaginatedResponse<int>.fromJson(
        json,
        (item) => (item as Map<String, dynamic>)['id'] as int,
      );

      expect(page.items, [1, 2]);
      expect(page.page, 2);
      expect(page.pageSize, 2);
      expect(page.totalItems, 5);
      expect(page.totalPages, 3);
      expect(page.hasNextPage, isTrue);
      expect(page.isNotEmpty, isTrue);
    });

    test(
      'gère une réponse sans meta ni data avec des valeurs par défaut sûres',
      () {
        final page = PaginatedResponse<int>.fromJson({}, (item) => item as int);

        expect(page.items, isEmpty);
        expect(page.isEmpty, isTrue);
        expect(page.page, 1);
        expect(page.totalPages, 1);
        expect(page.hasNextPage, isFalse);
      },
    );

    test('hasNextPage est faux sur la dernière page', () {
      final page = PaginatedResponse<int>(
        items: const [1],
        page: 3,
        pageSize: 1,
        totalItems: 3,
        totalPages: 3,
      );

      expect(page.hasNextPage, isFalse);
    });
  });
}
