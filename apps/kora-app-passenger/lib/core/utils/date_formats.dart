import 'package:intl/intl.dart';

/// Formats de date/heure de l'app, définis une seule fois.
///
/// Les données de localisation `fr_FR` sont chargées au démarrage par
/// `bootstrap.dart` (`initializeDateFormatting`) — sans ça, `intl` lèverait
/// une exception à la première utilisation d'un format non anglais.
class KoraDateFormats {
  const KoraDateFormats._();

  static const String locale = 'fr_FR';

  /// `4 août 2026` — date choisie dans le formulaire de recherche.
  static final DateFormat dayMonthYear = DateFormat('d MMMM yyyy', locale);

  /// `04 août` — format court des cartes de voyage.
  static final DateFormat dayMonth = DateFormat('dd MMMM', locale);

  /// `07:30`.
  static final DateFormat time = DateFormat('HH:mm', locale);

  /// `vendredi 14 août 2026` — date complète du départ, sur son détail.
  static final DateFormat fullDate = DateFormat('EEEE d MMMM yyyy', locale);

  /// `ven. 14 août` — rappel de la recherche en tête des résultats.
  static final DateFormat weekdayDayMonth = DateFormat('E d MMMM', locale);

  /// `≈ 9 h 45` — durée de trajet affichée sur une carte de départ.
  static String approximateDuration(Duration duration) {
    final hours = duration.inHours;
    final minutes = duration.inMinutes.remainder(60);

    if (hours == 0) return '≈ $minutes min';
    if (minutes == 0) return '≈ $hours h';
    return '≈ $hours h ${minutes.toString().padLeft(2, '0')}';
  }

  /// `août 2026` — en-tête du calendrier. `intl` rend le mois en
  /// minuscules ; voir [capitalize] pour l'affichage.
  static final DateFormat monthYear = DateFormat('MMMM yyyy', locale);

  /// Met la première lettre en capitale (`août` → `Août`), comme la
  /// maquette affiche les noms de mois.
  static String capitalize(String value) {
    if (value.isEmpty) return value;
    return value[0].toUpperCase() + value.substring(1);
  }

  /// Libellé relatif d'une date, tel que l'affiche la maquette sur la
  /// carte « Prochain voyage » : `Aujourd'hui`, `Demain`, `Dans 7 jours`.
  ///
  /// [days] est un nombre de jours entiers (voir
  /// `UpcomingTrip.daysUntilDeparture`). Une valeur négative — un départ
  /// déjà passé — ne devrait pas remonter jusqu'ici, mais on préfère un
  /// libellé honnête à une exception dans un widget.
  static String relativeDays(int days) {
    if (days < 0) return 'Départ passé';
    if (days == 0) return "Aujourd'hui";
    if (days == 1) return 'Demain';
    return 'Dans $days jours';
  }
}
