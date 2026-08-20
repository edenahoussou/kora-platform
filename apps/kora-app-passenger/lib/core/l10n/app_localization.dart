import 'dart:convert';

import 'package:flutter/foundation.dart'
    show SynchronousFuture, visibleForTesting;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show rootBundle;

import '../../features/language/domain/entities/app_language.dart';

/// Les textes de l'app, dans la langue active.
///
/// Chaque langue est un fichier plat `assets/languages/<code>.json`, chargé
/// au changement de locale. Les écrans ne lisent jamais ce JSON
/// directement : ils passent par `context.l10n.<clé>`, si bien qu'une clé
/// absente se voit à la compilation et non à l'exécution.
///
/// Exemple d'utilisation :
/// ```dart
/// Text(context.l10n.ticketsTitle)
/// Text(context.l10n.ticketsUpcomingTab(1))
/// ```
///
/// Pour ajouter un texte : une entrée dans `fr.json`, la même dans
/// `en.json`, puis un getter ci-dessous. Les trois vont ensemble — un
/// getter sans entrée retomberait sur sa clé brute à l'écran.
class AppLocalization {
  AppLocalization(this.locale);

  final Locale locale;

  static const LocalizationsDelegate<AppLocalization> delegate =
      _AppLocalizationDelegate();

  /// Les textes de la langue courante, mis à disposition par [load].
  Map<String, String> _strings = const {};

  /// Les textes français, chargés en plus dès qu'une autre langue est
  /// active : ils servent de filet quand une clé n'a pas encore été
  /// traduite. Un texte français au milieu d'un écran anglais reste
  /// lisible, une clé brute (`tickets_title`) non.
  Map<String, String> _fallbackStrings = const {};

  static AppLocalization of(BuildContext context) {
    final localization = Localizations.of<AppLocalization>(
      context,
      AppLocalization,
    );

    assert(
      localization != null,
      'Aucune AppLocalization dans le contexte : ajoutez '
      'AppLocalization.delegate aux localizationsDelegates de MaterialApp '
      '(voir lib/app.dart).',
    );

    return localization!;
  }

  /// Fichiers déjà lus, par code de langue.
  ///
  /// Une langue n'est lue qu'une fois par lancement : au-delà, `load()`
  /// rend la main immédiatement. Ce n'est pas qu'une économie —
  /// `Localizations` n'affiche **rien** tant qu'un délégué n'a pas
  /// répondu, si bien qu'une lecture asynchrone à chaque changement de
  /// langue ferait clignoter l'écran.
  static final Map<String, Map<String, String>> _cache = {};

  /// Lit à l'avance les fichiers de toutes les langues, pour que le
  /// premier écran s'affiche sans frame vide.
  ///
  /// Appelé par `bootstrap()` avant `runApp` : sans ça, le tout premier
  /// `Localizations` attend la lecture du JSON et l'app démarre sur un
  /// écran blanc, bref mais visible.
  static Future<void> preloadAll() async {
    for (final language in AppLanguage.values) {
      _cache[language.code] ??= await _readLanguageFile(language.code);
    }
  }

  /// Vide le cache — réservé aux tests, qui vérifient le chemin de
  /// lecture réel.
  @visibleForTesting
  static void resetCache() => _cache.clear();

  /// Codes des langues dont ce jeu de textes a besoin : la langue
  /// demandée, plus le français quand ce n'est pas elle (voir
  /// [_fallbackStrings]).
  List<String> get _neededCodes => [
    locale.languageCode,
    if (locale.languageCode != AppLanguage.fallback.code)
      AppLanguage.fallback.code,
  ];

  /// Charge les textes, de façon synchrone si les fichiers ont déjà été
  /// lus. Le `Future` reste dans la signature parce que c'est ce
  /// qu'attend [LocalizationsDelegate].
  Future<void> load() {
    if (_neededCodes.every(_cache.containsKey)) {
      _readFromCache();
      return SynchronousFuture(null);
    }

    return _loadFromBundle();
  }

  Future<void> _loadFromBundle() async {
    for (final code in _neededCodes) {
      _cache[code] ??= await _readLanguageFile(code);
    }

    _readFromCache();
  }

  void _readFromCache() {
    _strings = _cache[locale.languageCode] ?? const {};

    _fallbackStrings = locale.languageCode == AppLanguage.fallback.code
        ? const {}
        : _cache[AppLanguage.fallback.code] ?? const {};
  }

  static Future<Map<String, String>> _readLanguageFile(String code) async {
    final raw = await rootBundle.loadString('assets/languages/$code.json');
    final decoded = json.decode(raw) as Map<String, dynamic>;

    return decoded.map((key, value) => MapEntry(key, '$value'));
  }

  /// Texte associé à [key]. Retombe sur le français, puis sur la clé
  /// elle-même — visible en développement, jamais un écran vide.
  @visibleForTesting
  String translate(String key) {
    final value = _strings[key] ?? _fallbackStrings[key];

    assert(
      value != null,
      'Clé de traduction absente : "$key". Ajoutez-la dans '
      'assets/languages/fr.json et assets/languages/en.json.',
    );

    return value ?? key;
  }

  /// Remplace les jetons `{nom}` d'un texte par leur valeur — les langues
  /// ne placent pas leurs variables au même endroit dans la phrase, une
  /// concaténation en Dart figerait l'ordre français.
  String _withArgs(String key, Map<String, Object?> args) {
    var text = translate(key);

    for (final entry in args.entries) {
      text = text.replaceAll('{${entry.key}}', '${entry.value}');
    }

    return text;
  }

  // ---------------------------------------------------------------- commun
  String get appTagline => translate('app_tagline');
  String get actionRetry => translate('action_retry');
  String get actionContinue => translate('action_continue');
  String get actionSeeAll => translate('action_see_all');

  // -------------------------------------------------- navigation principale
  String get navHome => translate('nav_home');
  String get navTrips => translate('nav_trips');
  String get navParcels => translate('nav_parcels');
  String get navWallet => translate('nav_wallet');
  String get navProfile => translate('nav_profile');

  // ------------------------------------------------------ écran Mes voyages
  String get ticketsEyebrow => translate('tickets_eyebrow');
  String get ticketsTitle => translate('tickets_title');
  String get ticketsNotifications => translate('tickets_notifications');

  String ticketsTabUpcoming(int count) =>
      _withArgs('tickets_tab_upcoming', {'count': count});
  String ticketsTabPast(int count) =>
      _withArgs('tickets_tab_past', {'count': count});
  String ticketsTabCancelled(int count) =>
      _withArgs('tickets_tab_cancelled', {'count': count});

  String get ticketsStatusConfirmed => translate('tickets_status_confirmed');
  String get ticketsStatusCompleted => translate('tickets_status_completed');
  String get ticketsStatusCancelled => translate('tickets_status_cancelled');
  String get ticketsSeeTicket => translate('tickets_see_ticket');

  String ticketsSeat(String seat) => _withArgs('tickets_seat', {'seat': seat});
  String ticketsDepartureIn(String delay) =>
      _withArgs('tickets_departure_in', {'delay': delay});

  String get ticketsNewDestination => translate('tickets_new_destination');
  String get ticketsBookTrip => translate('tickets_book_trip');

  String get ticketsEmptyUpcomingTitle =>
      translate('tickets_empty_upcoming_title');
  String get ticketsEmptyUpcomingText =>
      translate('tickets_empty_upcoming_text');
  String get ticketsEmptyPastTitle => translate('tickets_empty_past_title');
  String get ticketsEmptyPastText => translate('tickets_empty_past_text');
  String get ticketsEmptyCancelledTitle =>
      translate('tickets_empty_cancelled_title');
  String get ticketsEmptyCancelledText =>
      translate('tickets_empty_cancelled_text');

  String get ticketsErrorTitle => translate('tickets_error_title');

  /// `aujourd'hui`, `demain`, `dans 7 jours` — le délai avant un départ,
  /// tel que l'affichent les cartes de voyage.
  ///
  /// Un délai négatif ne devrait pas remonter jusqu'ici ; on préfère un
  /// libellé honnête à une exception dans un widget.
  String relativeDays(int days) {
    if (days < 0) return translate('date_past');
    if (days == 0) return translate('date_today');
    if (days == 1) return translate('date_tomorrow');
    return _withArgs('date_in_days', {'count': days});
  }

  /// Code de la langue active (`fr`, `en`) — à passer aux formats de date.
  String get languageCode => locale.languageCode;

  // ------------------------------------------------ détail d'un billet
  String get ticketDetailEyebrowConfirmed =>
      translate('ticket_detail_eyebrow_confirmed');
  String get ticketDetailEyebrowCompleted =>
      translate('ticket_detail_eyebrow_completed');
  String get ticketDetailEyebrowCancelled =>
      translate('ticket_detail_eyebrow_cancelled');

  String ticketDetailStation(String city) =>
      _withArgs('ticket_detail_station', {'city': city});

  String get ticketDetailQrTitle => translate('ticket_detail_qr_title');
  String get ticketDetailQrSubtitle => translate('ticket_detail_qr_subtitle');
  String get ticketDetailTrackTitle => translate('ticket_detail_track_title');
  String get ticketDetailTrackSubtitle =>
      translate('ticket_detail_track_subtitle');
  String get ticketDetailShareTitle => translate('ticket_detail_share_title');
  String get ticketDetailShareSubtitle =>
      translate('ticket_detail_share_subtitle');
  String get ticketDetailCancelTitle => translate('ticket_detail_cancel_title');
  String get ticketDetailCancelSubtitle =>
      translate('ticket_detail_cancel_subtitle');
  String get ticketDetailReminder => translate('ticket_detail_reminder');
  String get ticketDetailSoon => translate('ticket_detail_soon');

  // ------------------------------------------------- réserver un voyage
  String get searchEyebrow => translate('search_eyebrow');
  String get searchTitle => translate('search_title');
  String get searchHelp => translate('search_help');
  String get searchTripOneway => translate('search_trip_oneway');
  String get searchTripRoundtrip => translate('search_trip_roundtrip');
  String get searchProgressLabel => translate('search_progress_label');
  String get searchProgressStep => translate('search_progress_step');
  String searchProgressCount(int step) =>
      _withArgs('search_progress_count', {'step': step});
  String get searchRoute => translate('search_route');
  String get searchSwap => translate('search_swap');
  String get searchSwapped => translate('search_swapped');
  String get searchDeparture => translate('search_departure');
  String get searchArrival => translate('search_arrival');
  String get searchDepartureHint => translate('search_departure_hint');
  String get searchArrivalHint => translate('search_arrival_hint');
  String get searchPickCity => translate('search_pick_city');
  String get searchDate => translate('search_date');
  String get searchPassengers => translate('search_passengers');

  /// `1 adulte` / `2 adultes` — le pluriel change la phrase entière dans
  /// certaines langues, on garde donc deux clés plutôt qu'un `s` collé
  /// après coup.
  String searchPassengersCount(int count) => _withArgs(
    count > 1 ? 'search_passengers_count_plural' : 'search_passengers_count',
    {'count': count},
  );

  String get searchComfortTitle => translate('search_comfort_title');
  String get searchComfortText => translate('search_comfort_text');
  String get searchCta => translate('search_cta');
  String get searchCtaRoundtrip => translate('search_cta_roundtrip');
  String get searchReturnDate => translate('search_return_date');
  String get searchReturnPick => translate('search_return_pick');
  String get searchMissingReturn => translate('search_missing_return');
  String get searchReturnResultsSoon => translate('search_return_results_soon');
  String get searchRecent => translate('search_recent');
  String get searchMissingCities => translate('search_missing_cities');
  String get searchRoundtripSoon => translate('search_roundtrip_soon');

  // -------------------------------------------------------- écran Colis
  String get parcelsEyebrow => translate('parcels_eyebrow');
  String get parcelsTitle => translate('parcels_title');
  String get parcelsHelp => translate('parcels_help');
  String get parcelsHeroEyebrow => translate('parcels_hero_eyebrow');
  String get parcelsHeroTitle => translate('parcels_hero_title');
  String get parcelsHeroText => translate('parcels_hero_text');
  String get parcelsReferenceLabel => translate('parcels_reference_label');
  String get parcelsReferenceHint => translate('parcels_reference_hint');
  String get parcelsScan => translate('parcels_scan');
  String get parcelsTrackCta => translate('parcels_track_cta');
  String get parcelsTrackEmpty => translate('parcels_track_empty');
  String get parcelsCreateTitle => translate('parcels_create_title');
  String get parcelsCreateSubtitle => translate('parcels_create_subtitle');
  String get parcelsRecentEyebrow => translate('parcels_recent_eyebrow');
  String get parcelsRecentTitle => translate('parcels_recent_title');
  String get parcelsStatusTransit => translate('parcels_status_transit');
  String get parcelsStatusDelivered => translate('parcels_status_delivered');
  String get parcelsStatusPending => translate('parcels_status_pending');
  String get parcelsFrom => translate('parcels_from');
  String get parcelsTo => translate('parcels_to');
  String get parcelsHelpTitle => translate('parcels_help_title');
  String get parcelsHelpSubtitle => translate('parcels_help_subtitle');
  String get parcelsSoon => translate('parcels_soon');

  // ------------------------------------------------------- écran Wallet
  String get walletEyebrow => translate('wallet_eyebrow');
  String get walletTitle => translate('wallet_title');
  String get walletHistory => translate('wallet_history');
  String get walletBalanceLabel => translate('wallet_balance_label');
  String get walletBalanceSource => translate('wallet_balance_source');
  String get walletBalanceAvailable => translate('wallet_balance_available');
  String get walletPointsLabel => translate('wallet_points_label');
  String walletPointsValue(String points, String amount) =>
      _withArgs('wallet_points_value', {'points': points, 'amount': amount});
  String get walletActionTopup => translate('wallet_action_topup');
  String get walletActionUse => translate('wallet_action_use');
  String get walletActionBonus => translate('wallet_action_bonus');
  String get walletActionHistory => translate('wallet_action_history');
  String get walletMovements => translate('wallet_movements');
  String get walletTxRefund => translate('wallet_tx_refund');
  String get walletTxPoints => translate('wallet_tx_points');
  String get walletTxPointsDetail => translate('wallet_tx_points_detail');
  String get walletTxPayment => translate('wallet_tx_payment');
  String get walletSoon => translate('wallet_soon');

  // ------------------------------------------------------- écran Profil
  String get profileWelcome => translate('profile_welcome');
  String profileMemberSince(int year) =>
      _withArgs('profile_member_since', {'year': year});
  String get profileNotifications => translate('profile_notifications');
  String get profileBalanceWallet => translate('profile_balance_wallet');
  String get profileBalancePoints => translate('profile_balance_points');
  String get profileAccount => translate('profile_account');
  String get profileInfoTitle => translate('profile_info_title');
  String get profileInfoSubtitle => translate('profile_info_subtitle');
  String get profileSecurityTitle => translate('profile_security_title');
  String get profileSecuritySubtitle => translate('profile_security_subtitle');
  String get profilePreferencesTitle => translate('profile_preferences_title');
  String get profilePreferencesSubtitle =>
      translate('profile_preferences_subtitle');
  String get profileHelpSection => translate('profile_help_section');
  String get profileHelpTitle => translate('profile_help_title');
  String get profileHelpText => translate('profile_help_text');
  String get profileHelpCenter => translate('profile_help_center');
  String get profileHelpWhatsapp => translate('profile_help_whatsapp');
  String get profileLogout => translate('profile_logout');
  String get profileSoon => translate('profile_soon');

  // ------------------------------------------------ création de compte
  String get signupEyebrow => translate('signup_eyebrow');
  String get signupTitle => translate('signup_title');
  String get signupLead => translate('signup_lead');
  String get signupEmailLabel => translate('signup_email_label');
  String get signupEmailHint => translate('signup_email_hint');
  String get signupEmailRequired => translate('signup_email_required');
  String get signupEmailInvalid => translate('signup_email_invalid');
  String get signupEmailProtected => translate('signup_email_protected');
  String get signupSubmit => translate('signup_submit');
  String get signupOr => translate('signup_or');
  String get signupWithGoogle => translate('signup_with_google');
  String get signupWithFacebook => translate('signup_with_facebook');
  String get signupWithTiktok => translate('signup_with_tiktok');
  String signupSocialSoon(String provider) =>
      _withArgs('signup_social_soon', {'provider': provider});

  // ---------------------------------------------------------------- langues
  String get languageTitle => translate('language_title');
}

class _AppLocalizationDelegate extends LocalizationsDelegate<AppLocalization> {
  const _AppLocalizationDelegate();

  @override
  bool isSupported(Locale locale) {
    return AppLanguage.values.any(
      (language) => language.code == locale.languageCode,
    );
  }

  @override
  Future<AppLocalization> load(Locale locale) {
    final localization = AppLocalization(locale);
    final loading = localization.load();

    // `Localizations` ne construit ses enfants sans frame vide que si le
    // `Future` est déjà résolu : on propage donc la nature synchrone du
    // chargement en cache au lieu de la perdre dans un `async`.
    if (loading is SynchronousFuture) return SynchronousFuture(localization);

    return loading.then((_) => localization);
  }

  // Les textes ne changent qu'au changement de locale, que Flutter gère
  // lui-même en rappelant load().
  @override
  bool shouldReload(_AppLocalizationDelegate old) => false;
}

/// `context.l10n.ticketsTitle` — plus court que `AppLocalization.of(context)`
/// répété à chaque texte d'un écran.
extension AppLocalizationContext on BuildContext {
  AppLocalization get l10n => AppLocalization.of(this);
}
