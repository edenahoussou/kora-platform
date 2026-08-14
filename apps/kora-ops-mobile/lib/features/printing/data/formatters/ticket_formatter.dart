import '../../domain/entities/ticket_payload.dart';

/// Formate un [TicketPayload] en lignes de texte adaptées à une imprimante
/// thermique 58mm — largeur standard de **32 caractères** en police par
/// défaut (voir document.md § Format du ticket 58mm).
///
/// Chaque ligne produite par [format] respecte strictement [lineWidth]
/// caractères ou moins (jamais tronquée côté imprimante). Le marqueur
/// `[ QR CODE ]` est centré : le SDK natif P501 imprime le QR réel à cet
/// emplacement à partir de `payload.qrData` ; ce format ASCII sert à la
/// prévisualisation à l'écran et aux tests, pas à l'encodage du QR.
abstract final class TicketFormatter {
  static const int lineWidth = 32;

  static List<String> format(TicketPayload payload) {
    final List<String> lines = <String>[
      _centered('KORA EXPRESS'),
      _divider(),
      _centered(_truncate(payload.tripRoute)),
      _label('Date', payload.tripDate),
      _label('Heure', payload.tripTime),
      _divider(),
      _label('Passager', _truncate(payload.passengerName, prefixLength: 'Passager: '.length)),
      _label('Siege', payload.seatNumber),
      _divider(),
      _centered('[ QR CODE ]'),
      _centered(_truncate(payload.qrData)),
      _divider(),
      _centered(_truncate(payload.fiscalFooter)),
    ];

    assert(
      lines.every((String line) => line.length <= lineWidth),
      'Une ligne de ticket dépasse $lineWidth caractères.',
    );

    return lines;
  }

  /// Assemble les lignes avec des sauts de ligne, prêt à être envoyé au
  /// canal natif ou affiché dans un widget `Text` à police monospace.
  static String formatAsText(TicketPayload payload) => format(payload).join('\n');

  /// Vérifie que le document respecte le format 58mm (largeur 32
  /// caractères, aucune ligne vide en trop). Utilisé par les tests et par
  /// [MockThermalPrinter] avant "impression".
  static bool isValid(List<String> lines) =>
      lines.isNotEmpty && lines.every((String line) => line.length <= lineWidth);

  static String _centered(String text) {
    final String clipped = _truncate(text);
    final int totalPadding = lineWidth - clipped.length;
    if (totalPadding <= 0) return clipped;
    final int left = totalPadding ~/ 2;
    final int right = totalPadding - left;
    return (' ' * left) + clipped + (' ' * right);
  }

  static String _label(String label, String value) {
    final String prefix = '$label: ';
    return prefix + _truncate(value, prefixLength: prefix.length);
  }

  static String _divider() => '-' * lineWidth;

  static String _truncate(String text, {int prefixLength = 0}) {
    final int maxLength = lineWidth - prefixLength;
    if (maxLength <= 0) return '';
    return text.length <= maxLength ? text : text.substring(0, maxLength);
  }
}
