import '../../domain/entities/border_manifest_payload.dart';

/// Formate un [BorderManifestPayload] en lignes de texte adaptées à une
/// imprimante thermique 58 mm — largeur standard de **32 caractères** en
/// police par défaut, comme `TicketFormatter` (MEZ-10).
///
/// ### Un bloc compact par passager
///
/// Nom + nationalité + n° de document + destination + siège ne tiennent pas
/// lisiblement sur 32 caractères : chaque passager occupe **2 lignes** (une
/// ligne logique par passager, coupée en deux pour rester lisible) :
///
/// ```
/// 01 DIOP FATOU
/// SN A1234567 > Porto-Novo 12A
/// ```
///
/// Si la 2e ligne dépasse 32 caractères (n° de document ou destination
/// longs), elle est coupée en deux au lieu d'être tronquée.
///
/// ### Règle d'or : jamais de troncature d'une donnée d'identité
///
/// Un nom ou un n° de document tronqué à la frontière est pire qu'une ligne
/// de plus. Le texte trop long est donc **replié** (coupé aux espaces, ou au
/// caractère près pour un mot plus long qu'une ligne) — jamais rogné. Seuls
/// les libellés d'en-tête purement informatifs peuvent être repliés sur
/// plusieurs lignes ; aucune ligne ne dépasse [lineWidth].
///
/// Le texte est **ASCII** (accents retirés) : la police par défaut du SDK
/// P501 n'imprime pas de façon fiable les caractères accentués — même choix
/// que `TicketFormatter` (« Siege »).
abstract final class BorderManifestFormatter {
  static const int lineWidth = 32;

  /// Nombre de caractères du numéro de version imprimé en pied de page.
  static const int _versionLength = 12;

  static List<String> format(
    BorderManifestPayload payload, {
    required int copyNumber,
    required int totalCopies,
  }) {
    final List<String> lines = <String>[
      _centered('KORA EXPRESS'),
      _centered('MANIFESTE FRONTIERE'),
      _divider(),
      ..._label('Depart', payload.departureNumber),
      ..._label('Date', '${payload.departureDate} ${payload.departureTime}'),
      ..._label('Ligne', payload.lineCode),
      ..._label('Trajet', payload.route),
      ..._label('Passagers', '${payload.passengerCount}'),
      ..._label('Exemplaire', '$copyNumber/$totalCopies'),
      _divider(),
      for (int i = 0; i < payload.passengers.length; i++)
        ..._passengerBlock(i + 1, payload.passengers[i]),
      _divider(),
      if (payload.manifestVersion != null &&
          payload.manifestVersion!.trim().isNotEmpty)
        ..._label('Version', _short(payload.manifestVersion!)),
      if (payload.manifestFetchedAt != null)
        ..._label('Maj', _dateTime(payload.manifestFetchedAt!)),
      ..._label('Imprime', _dateTime(payload.printedAt)),
      _centered('FIN DU MANIFESTE'),
    ];

    assert(
      isValid(lines),
      'Une ligne du manifeste frontière dépasse $lineWidth caractères.',
    );

    return lines;
  }

  /// Assemble les lignes avec des sauts de ligne — aperçu à l'écran en
  /// police monospace.
  static String formatAsText(
    BorderManifestPayload payload, {
    required int copyNumber,
    required int totalCopies,
  }) =>
      format(payload, copyNumber: copyNumber, totalCopies: totalCopies)
          .join('\n');

  /// Vérifie que le document respecte le format 58 mm (aucune ligne au-delà
  /// de [lineWidth], au moins une ligne). Utilisé par les tests et par
  /// `MockThermalPrinter` avant « impression ».
  static bool isValid(List<String> lines) =>
      lines.isNotEmpty &&
      lines.every((String line) => line.length <= lineWidth);

  // ---------------------------------------------------------------------
  // Bloc passager
  // ---------------------------------------------------------------------

  static List<String> _passengerBlock(
    int index,
    BorderManifestPassengerLine passenger,
  ) {
    final String number = index.toString().padLeft(2, '0');
    final String name = ascii(passenger.nomComplet).toUpperCase();
    final String nationality = ascii(passenger.nationalite).toUpperCase();
    // Le n° de document est repris tel quel (seuls les accents éventuels
    // sont retirés) : jamais mis en majuscules ni modifié.
    final String document = ascii(passenger.documentNumero);
    final String destination = ascii(passenger.destination);
    final String seat = ascii(passenger.siege);

    final List<String> lines = <String>[
      ..._wrap('$number $name', continuationIndent: '   '),
    ];

    final String compact = '$nationality $document > $destination $seat';
    if (compact.length <= lineWidth) {
      lines.add(compact);
    } else {
      lines
        ..addAll(_wrap('$nationality $document', continuationIndent: '   '))
        ..addAll(_wrap('> $destination $seat', continuationIndent: '  '));
    }
    return lines;
  }

  // ---------------------------------------------------------------------
  // En-tête / pied
  // ---------------------------------------------------------------------

  static List<String> _label(String label, String value) {
    final String prefix = '$label: ';
    return _wrap('$prefix${ascii(value)}', continuationIndent: ' ' * 2);
  }

  static String _centered(String text) {
    final String clipped =
        text.length <= lineWidth ? text : text.substring(0, lineWidth);
    final int totalPadding = lineWidth - clipped.length;
    final int left = totalPadding ~/ 2;
    return (' ' * left) + clipped;
  }

  static String _divider() => '-' * lineWidth;

  static String _short(String version) {
    final String cleaned = ascii(version).trim();
    return cleaned.length <= _versionLength
        ? cleaned
        : cleaned.substring(0, _versionLength);
  }

  static String _dateTime(DateTime value) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(value.day)}/${two(value.month)}/${value.year} '
        '${two(value.hour)}:${two(value.minute)}';
  }

  // ---------------------------------------------------------------------
  // Repli du texte (jamais de troncature)
  // ---------------------------------------------------------------------

  /// Coupe [text] en lignes de [lineWidth] caractères maximum, aux espaces
  /// quand c'est possible ; un mot plus long qu'une ligne est coupé au
  /// caractère près. Les lignes de continuation sont préfixées de
  /// [continuationIndent]. Ne perd jamais un caractère non blanc.
  static List<String> _wrap(String text, {String continuationIndent = ''}) {
    final List<String> words =
        text.split(RegExp(r'\s+')).where((String w) => w.isNotEmpty).toList();
    if (words.isEmpty) return const <String>[''];

    final List<String> lines = <String>[];
    StringBuffer current = StringBuffer();
    int currentLength = 0;

    String indentFor(int lineIndex) => lineIndex == 0 ? '' : continuationIndent;

    void flush() {
      lines.add(current.toString());
      current = StringBuffer();
      currentLength = 0;
    }

    for (String word in words) {
      while (true) {
        final String indent = indentFor(lines.length);
        final int available = lineWidth - indent.length;

        if (currentLength == 0) {
          current.write(indent);
          currentLength = indent.length;
        }

        final int separator = currentLength > indent.length ? 1 : 0;
        if (currentLength + separator + word.length <= lineWidth) {
          if (separator == 1) current.write(' ');
          current.write(word);
          currentLength += separator + word.length;
          break;
        }

        if (currentLength > indent.length) {
          // La ligne courante a déjà du contenu : on la clôt et on
          // retente le mot sur la ligne suivante.
          flush();
          continue;
        }

        // Mot plus long qu'une ligne entière : coupé au caractère près.
        current.write(word.substring(0, available));
        currentLength += available;
        word = word.substring(available);
        flush();
      }
    }
    if (currentLength > 0) flush();
    return lines;
  }

  // ---------------------------------------------------------------------
  // ASCII
  // ---------------------------------------------------------------------

  static const Map<String, String> _fold = <String, String>{
    'à': 'a',
    'á': 'a',
    'â': 'a',
    'ä': 'a',
    'ã': 'a',
    'å': 'a',
    'ā': 'a',
    'À': 'A',
    'Á': 'A',
    'Â': 'A',
    'Ä': 'A',
    'Ã': 'A',
    'Å': 'A',
    'Ā': 'A',
    'ç': 'c',
    'Ç': 'C',
    'è': 'e',
    'é': 'e',
    'ê': 'e',
    'ë': 'e',
    'ē': 'e',
    'È': 'E',
    'É': 'E',
    'Ê': 'E',
    'Ë': 'E',
    'Ē': 'E',
    'ì': 'i',
    'í': 'i',
    'î': 'i',
    'ï': 'i',
    'ī': 'i',
    'Ì': 'I',
    'Í': 'I',
    'Î': 'I',
    'Ï': 'I',
    'Ī': 'I',
    'ñ': 'n',
    'Ñ': 'N',
    'ò': 'o',
    'ó': 'o',
    'ô': 'o',
    'ö': 'o',
    'õ': 'o',
    'ō': 'o',
    'Ò': 'O',
    'Ó': 'O',
    'Ô': 'O',
    'Ö': 'O',
    'Õ': 'O',
    'Ō': 'O',
    'ù': 'u',
    'ú': 'u',
    'û': 'u',
    'ü': 'u',
    'ū': 'u',
    'Ù': 'U',
    'Ú': 'U',
    'Û': 'U',
    'Ü': 'U',
    'Ū': 'U',
    'ý': 'y',
    'ÿ': 'y',
    'Ý': 'Y',
    'œ': 'oe',
    'Œ': 'OE',
    'æ': 'ae',
    'Æ': 'AE',
    'ß': 'ss',
    'ɛ': 'e',
    'Ɛ': 'E',
    'ɔ': 'o',
    'Ɔ': 'O',
    'ɖ': 'd',
    'Ɖ': 'D',
    '→': '>',
    '–': '-',
    '—': '-',
    '’': "'",
    '‘': "'",
    '“': '"',
    '”': '"',
    '\u00a0': ' ',
  };

  /// Retire les accents et ramène le texte à l'ASCII imprimable. Un
  /// caractère sans équivalent (écriture non latine) devient `?` : mieux
  /// vaut un repère visible qu'un caractère silencieusement perdu.
  static String ascii(String input) {
    final StringBuffer out = StringBuffer();
    for (final int rune in input.runes) {
      final String char = String.fromCharCode(rune);
      final String? folded = _fold[char];
      if (folded != null) {
        out.write(folded);
      } else if (rune >= 32 && rune < 127) {
        out.write(char);
      } else if (rune == 9 || rune == 10 || rune == 13) {
        out.write(' ');
      } else if (rune < 32) {
        // Caractère de contrôle : ignoré.
        continue;
      } else {
        out.write('?');
      }
    }
    return out.toString();
  }
}
