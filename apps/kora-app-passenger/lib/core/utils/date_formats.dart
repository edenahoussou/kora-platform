import 'package:intl/intl.dart';

/// Formats de date/heure de l'app, définis une seule fois.
///
/// Les données de localisation `fr_FR` sont chargées au démarrage par
/// `bootstrap.dart` (`initializeDateFormatting`) — sans ça, `intl` lèverait
/// une exception à la première utilisation d'un format non anglais.
class KoraDateFormats {
  const KoraDateFormats._();

  /// Locale par défaut des formats ci-dessous.
  ///
  /// Les écrans déjà traduits passent plutôt par les variantes `…In(locale)`,
  /// qui suivent la langue choisie : `04 août` en français, `04 August` en
  /// anglais. Les formats figés restent le temps que les écrans encore
  /// écrits en dur soient migrés.
  static const String locale = 'fr_FR';

  /// `04 août` / `04 August`, dans la langue passée.
  static String dayMonthIn(DateTime date, String locale) =>
      DateFormat('dd MMMM', locale).format(date);

  /// `4 août 2026` / `4 August 2026`.
  static String dayMonthYearIn(DateTime date, String locale) =>
      DateFormat('d MMMM yyyy', locale).format(date);

  /// `Mar. 04 août` / `Tue 4 August` — rappel du jour sur un billet.
  static String weekdayDayMonthIn(DateTime date, String locale) =>
      capitalize(DateFormat('E d MMMM', locale).format(date));

  /// `07:30` — identique partout, mais on passe la locale pour ne pas
  /// dépendre d'un format horaire qui varierait ailleurs.
  static String timeIn(DateTime date, String locale) =>
      DateFormat('HH:mm', locale).format(date);

  /// `4 août 2026` — date choisie dans le formulaire de recherche.
  static final DateFormat dayMonthYear = DateFormat('d MMMM yyyy', locale);

  /// `04 août` — format court des cartes de voyage.
  static final DateFormat dayMonth = DateFormat('dd MMMM', locale);

  /// `04 août` — le mois abrégé, pour les espaces étroits.
  ///
  /// Le billet aligne trois champs sur une ligne : « 02 SEPTEMBRE » y
  /// passerait à la ligne et décalerait l'heure et le siège.
  static final DateFormat dayMonthShort = DateFormat('dd MMM', locale);

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

  /// Le mois seul (`août`), pour la grille des mois du calendrier.
  static final DateFormat monthOnly = DateFormat('MMMM', locale);

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
