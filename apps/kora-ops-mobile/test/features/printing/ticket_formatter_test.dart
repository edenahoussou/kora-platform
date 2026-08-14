import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/printing/data/formatters/ticket_formatter.dart';
import 'package:kora_ops_mobile/features/printing/domain/entities/ticket_payload.dart';

void main() {
  const TicketPayload payload = TicketPayload(
    tripRoute: 'Cotonou - Porto-Novo',
    tripDate: '14/08/2026',
    tripTime: '07:30',
    passengerName: 'Agent Test',
    seatNumber: '12A',
    qrData: 'KORA-TICKET-000123',
  );

  group('TicketFormatter.format', () {
    test('aucune ligne ne dépasse 32 caractères (largeur imprimante 58mm)', () {
      final List<String> lines = TicketFormatter.format(payload);

      for (final String line in lines) {
        expect(
          line.length,
          lessThanOrEqualTo(TicketFormatter.lineWidth),
          reason: 'Ligne trop longue: "$line" (${line.length} caractères)',
        );
      }
    });

    test('lineWidth vaut 32 caractères', () {
      expect(TicketFormatter.lineWidth, 32);
    });

    test('le header KORA EXPRESS est centré sur la première ligne', () {
      final List<String> lines = TicketFormatter.format(payload);
      final String header = lines.first;

      expect(header.trim(), 'KORA EXPRESS');
      expect(header.length, TicketFormatter.lineWidth);
      // Centré : le texte ne colle pas au bord gauche.
      expect(header.startsWith(' '), isTrue);
    });

    test('le marqueur QR est présent et centré', () {
      final List<String> lines = TicketFormatter.format(payload);
      final String qrLine = lines.firstWhere((String l) => l.contains('QR CODE'));

      expect(qrLine.trim(), '[ QR CODE ]');
      expect(qrLine.length, TicketFormatter.lineWidth);

      final int leftPadding = qrLine.length - qrLine.trimLeft().length;
      final int rightPadding = qrLine.length - qrLine.trimRight().length;
      // Centrage à +/-1 caractère près (largeur totale peut être impaire).
      expect((leftPadding - rightPadding).abs(), lessThanOrEqualTo(1));
    });

    test('contient les informations passager et siège', () {
      final List<String> lines = TicketFormatter.format(payload);
      final String joined = lines.join('\n');

      expect(joined, contains('Agent Test'));
      expect(joined, contains('12A'));
      expect(joined, contains('07:30'));
      expect(joined, contains('14/08/2026'));
    });

    test('contient le pied de page fiscal (tronqué si nécessaire)', () {
      final List<String> lines = TicketFormatter.format(payload);
      final String footer = lines.last.trim();
      
      // Le pied de page doit contenir "KORA" (version tronquée acceptée)
      expect(footer, contains('KORA'));
      // Et ne pas dépasser la largeur max
      expect(lines.last.length, lessThanOrEqualTo(TicketFormatter.lineWidth));
    });

    test('tronque un nom de passager trop long sans dépasser la largeur', () {
      const TicketPayload longNamePayload = TicketPayload(
        tripRoute: 'Cotonou - Porto-Novo',
        tripDate: '14/08/2026',
        tripTime: '07:30',
        passengerName: 'Un Nom De Passager Extrêmement Long Pour Le Ticket',
        seatNumber: '12A',
        qrData: 'KORA-TICKET-000123',
      );

      final List<String> lines = TicketFormatter.format(longNamePayload);
      for (final String line in lines) {
        expect(line.length, lessThanOrEqualTo(TicketFormatter.lineWidth));
      }
    });

    test('un diviseur fait exactement 32 caractères', () {
      final List<String> lines = TicketFormatter.format(payload);
      final String divider = lines.firstWhere((String l) => l.trim() == '-' * 32 || l == '-' * 32);
      expect(divider.length, TicketFormatter.lineWidth);
    });
  });

  group('TicketFormatter.isValid', () {
    test('retourne true pour un ticket correctement formaté', () {
      final List<String> lines = TicketFormatter.format(payload);
      expect(TicketFormatter.isValid(lines), isTrue);
    });

    test('retourne false pour une liste vide', () {
      expect(TicketFormatter.isValid(<String>[]), isFalse);
    });

    test('retourne false si une ligne dépasse la largeur', () {
      expect(TicketFormatter.isValid(<String>['x' * 33]), isFalse);
    });
  });

  group('TicketFormatter.formatAsText', () {
    test('assemble les lignes avec des sauts de ligne', () {
      final String text = TicketFormatter.formatAsText(payload);
      final List<String> lines = TicketFormatter.format(payload);
      expect(text, lines.join('\n'));
    });
  });
}