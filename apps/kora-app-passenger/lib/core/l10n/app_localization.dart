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
  String get profileLogoutPending => translate('profile_logout_pending');
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
  // --- Centre d'aide et contact (PROFILE-05, PROFILE-06) ---
  String get helpEyebrow => translate('help_eyebrow');
  String get helpTitle => translate('help_title');
  String get helpSearch => translate('help_search');
  String get helpFaq => translate('help_faq');
  String get helpNoResult => translate('help_no_result');
  String get helpContact => translate('help_contact');
  String get helpQ1 => translate('help_q1');
  String get helpA1 => translate('help_a1');
  String get helpQ2 => translate('help_q2');
  String get helpA2 => translate('help_a2');
  String get helpQ3 => translate('help_q3');
  String get helpA3 => translate('help_a3');
  String get helpQ4 => translate('help_q4');
  String get helpA4 => translate('help_a4');
  String get helpQ5 => translate('help_q5');
  String get helpA5 => translate('help_a5');
  String get contactEyebrow => translate('contact_eyebrow');
  String get contactTitle => translate('contact_title');
  String get contactWhatsappTitle => translate('contact_whatsapp_title');
  String get contactWhatsappText => translate('contact_whatsapp_text');
  String get contactWhatsappStart => translate('contact_whatsapp_start');
  String get contactOthers => translate('contact_others');
  String get contactPhone => translate('contact_phone');
  String get contactEmail => translate('contact_email');
  String get contactNote => translate('contact_note');
  String contactUnavailable(String target) =>
      _withArgs('contact_unavailable', {'target': target});

  // --- Parcours Bus : villes, recherche, résultats, détail ---
  String get cityPickerEyebrow => translate('city_picker_eyebrow');
  String get cityPickerOriginTitle => translate('city_picker_origin_title');
  String get cityPickerDestinationTitle =>
      translate('city_picker_destination_title');
  String get cityPickerSearch => translate('city_picker_search');
  String get cityPickerSuggestions => translate('city_picker_suggestions');
  String get cityPickerRecent => translate('city_picker_recent');
  String get cityPickerStop => translate('city_picker_stop');
  String get cityPickerStation => translate('city_picker_station');
  String cityPickerNoResult(String query) =>
      _withArgs('city_picker_no_result', {'query': query});
  String get departuresEyebrow => translate('departures_eyebrow');
  String get departuresSearchingTitle =>
      translate('departures_searching_title');
  String get departuresSearchingLead => translate('departures_searching_lead');
  String get departuresSearchingText => translate('departures_searching_text');
  String get paymentMethodEyebrow => translate('payment_method_eyebrow');
  String get paymentMethodTitle => translate('payment_method_title');
  String get paymentMethodMomo => translate('payment_method_momo');
  String get paymentMethodMomoLead => translate('payment_method_momo_lead');
  String get paymentMethodWallet => translate('payment_method_wallet');
  String paymentMethodWalletLead(String balance) =>
      _withArgs('payment_method_wallet_lead', {'balance': balance});
  String get paymentMethodCard => translate('payment_method_card');
  String get paymentMethodCardLead => translate('payment_method_card_lead');
  String paymentMethodHold(String time) =>
      _withArgs('payment_method_hold', {'time': time});
  String get paymentMethodTotal => translate('payment_method_total');
  String get paymentMomoTitle => translate('payment_momo_title');
  String paymentMomoEyebrow(String amount) =>
      _withArgs('payment_momo_eyebrow', {'amount': amount});
  String get paymentMomoOperator => translate('payment_momo_operator');
  String get paymentMomoOperatorHint => translate('payment_momo_operator_hint');
  String get paymentMomoOperatorRequired =>
      translate('payment_momo_operator_required');
  String get paymentMomoPhone => translate('payment_momo_phone');
  String get paymentMomoPhoneRequired =>
      translate('payment_momo_phone_required');
  String get paymentMomoPhoneShort => translate('payment_momo_phone_short');
  String get paymentMomoNotice => translate('payment_momo_notice');
  String paymentMomoPay(String amount) =>
      _withArgs('payment_momo_pay', {'amount': amount});
  String get paymentWalletTitle => translate('payment_wallet_title');
  String get paymentWalletEyebrow => translate('payment_wallet_eyebrow');
  String get paymentWalletBalance => translate('payment_wallet_balance');
  String get paymentWalletAmountTitle =>
      translate('payment_wallet_amount_title');
  String get paymentWalletAmountLabel =>
      translate('payment_wallet_amount_label');
  String get paymentWalletPoints => translate('payment_wallet_points');
  String paymentWalletPointsValue(int count) =>
      _withArgs('payment_wallet_points_value', {'count': count});
  String get paymentWalletTicketTotal =>
      translate('payment_wallet_ticket_total');
  String get paymentWalletCredit => translate('payment_wallet_credit');
  String get paymentWalletRemainder => translate('payment_wallet_remainder');
  String get paymentWalletConfirm => translate('payment_wallet_confirm');
  String get paymentWalletNotice => translate('payment_wallet_notice');
  String get paymentCardTitle => translate('payment_card_title');
  String get paymentCardEyebrow => translate('payment_card_eyebrow');
  String get paymentCardSecure => translate('payment_card_secure');
  String get paymentCardHolder => translate('payment_card_holder');
  String get paymentCardHolderRequired =>
      translate('payment_card_holder_required');
  String get paymentCardNumber => translate('payment_card_number');
  String get paymentCardNumberRequired =>
      translate('payment_card_number_required');
  String get paymentCardNumberInvalid =>
      translate('payment_card_number_invalid');
  String get paymentCardExpiry => translate('payment_card_expiry');
  String get paymentCardExpiryHint => translate('payment_card_expiry_hint');
  String get paymentCardExpiryInvalid =>
      translate('payment_card_expiry_invalid');
  String get paymentCardCvv => translate('payment_card_cvv');
  String get paymentCardCvvInvalid => translate('payment_card_cvv_invalid');
  String get paymentCardSave => translate('payment_card_save');
  String get paymentCardSaveLead => translate('payment_card_save_lead');
  String get paymentCardTotal => translate('payment_card_total');
  String paymentCardPay(String amount) =>
      _withArgs('payment_card_pay', {'amount': amount});
  String get paymentCardSwitch => translate('payment_card_switch');
  String get paymentSoon => translate('payment_soon');
  String get paymentValidationTitle => translate('payment_validation_title');
  String get paymentValidationLead => translate('payment_validation_lead');
  String get paymentValidationText => translate('payment_validation_text');
  String get paymentValidationConfirmed =>
      translate('payment_validation_confirmed');
  String get paymentValidationExpiresIn =>
      translate('payment_validation_expires_in');
  String get paymentSwitchMethod => translate('payment_switch_method');
  String get paymentPendingTitle => translate('payment_pending_title');
  String get paymentPendingText => translate('payment_pending_text');
  String get paymentPendingRefresh => translate('payment_pending_refresh');
  String get paymentPendingReport => translate('payment_pending_report');
  String paymentPendingPolling(int seconds) =>
      _withArgs('payment_pending_polling', {'seconds': seconds});
  String get paymentReference => translate('payment_reference');
  String get bookingSuccessTitle => translate('booking_success_title');
  String get bookingSuccessText => translate('booking_success_text');
  String get bookingSuccessTicket => translate('booking_success_ticket');
  String bookingSuccessSeat(String seat) =>
      _withArgs('booking_success_seat', {'seat': seat});
  String bookingSuccessDeparture(String time, String stop) =>
      _withArgs('booking_success_departure', {'time': time, 'stop': stop});
  String get ticketEyebrow => translate('ticket_eyebrow');
  String get ticketTitle => translate('ticket_title');
  String get ticketStripTitle => translate('ticket_strip_title');
  String get ticketStripLead => translate('ticket_strip_lead');
  String get ticketStateConfirmed => translate('ticket_state_confirmed');
  String get ticketStateCancelled => translate('ticket_state_cancelled');
  String get ticketStampCancelled => translate('ticket_stamp_cancelled');
  String get ticketFieldDate => translate('ticket_field_date');
  String get ticketFieldDeparture => translate('ticket_field_departure');
  String get ticketFieldSeat => translate('ticket_field_seat');
  String get ticketQrSemantics => translate('ticket_qr_semantics');
  String get ticketTrackTitle => translate('ticket_track_title');
  String get ticketTrackSubtitle => translate('ticket_track_subtitle');
  String get ticketShareTitle => translate('ticket_share_title');
  String get ticketShareSubtitle => translate('ticket_share_subtitle');
  String get ticketShareSoon => translate('ticket_share_soon');
  String get ticketOfflineNote => translate('ticket_offline_note');
  String get ticketMissingTitle => translate('ticket_missing_title');
  String get ticketMissingText => translate('ticket_missing_text');
  String get ticketMissingPrimary => translate('ticket_missing_primary');
  String get seatsHoldEyebrow => translate('seats_hold_eyebrow');
  String seatsHoldCountdown(String time) =>
      _withArgs('seats_hold_countdown', {'time': time});
  String get seatsHoldHolding => translate('seats_hold_holding');
  String get seatsHoldRelease => translate('seats_hold_release');
  String get seatsHoldLocked => translate('seats_hold_locked');
  String get holdExpiredTitle => translate('hold_expired_title');
  String get holdExpiredText => translate('hold_expired_text');
  String get holdExpiredPrimary => translate('hold_expired_primary');
  String get holdExpiredSecondary => translate('hold_expired_secondary');
  String departuresCount(int count) => _withArgs(
    count > 1 ? 'departures_count_plural' : 'departures_count',
    {'count': count},
  );
  String departuresRecap(int count, String slots, String prices) => _withArgs(
    count > 1 ? 'departures_recap_plural' : 'departures_recap',
    {'count': count, 'slots': slots, 'prices': prices},
  );
  String get departuresRecapMorning => translate('departures_recap_morning');
  String get departuresRecapAfternoon =>
      translate('departures_recap_afternoon');
  String get departuresRecapEvening => translate('departures_recap_evening');
  String get departuresRecapSlotsJoin =>
      translate('departures_recap_slots_join');
  String departuresRecapPricesRange(String min, String max) =>
      _withArgs('departures_recap_prices_range', {'min': min, 'max': max});
  String departuresRecapPricesFlat(String price) =>
      _withArgs('departures_recap_prices_flat', {'price': price});
  String get departuresEmptyEyebrow => translate('departures_empty_eyebrow');
  String get departuresEmptyTitle => translate('departures_empty_title');
  String get departuresEmptyText => translate('departures_empty_text');
  String get departuresEditSearch => translate('departures_edit_search');
  String get departuresErrorEyebrow => translate('departures_error_eyebrow');
  String get departuresErrorTitle => translate('departures_error_title');
  String get departuresErrorText => translate('departures_error_text');
  String get departuresSortLabel => translate('departures_sort_label');
  String get departuresSortHour => translate('departures_sort_hour');
  String get departuresSortPrice => translate('departures_sort_price');
  String get departuresSortDuration => translate('departures_sort_duration');

  String get departuresFilterLabel => translate('departures_filter_label');
  String get departuresFiltersTitle => translate('departures_filters_title');
  String get departuresFiltersClear => translate('departures_filters_clear');
  String get departuresFiltersDone => translate('departures_filters_done');
  String get departuresSlotMorning => translate('departures_slot_morning');
  String get departuresSlotAfternoon => translate('departures_slot_afternoon');
  String get departuresSlotEvening => translate('departures_slot_evening');

  String get departuresFilteredEmptyEyebrow =>
      translate('departures_filtered_empty_eyebrow');
  String get departuresFilteredEmptyTitle =>
      translate('departures_filtered_empty_title');
  String get departuresFilteredEmptyText =>
      translate('departures_filtered_empty_text');
  String get departuresFiltersReset => translate('departures_filters_reset');
  String get departuresRefreshFailed => translate('departures_refresh_failed');

  String departuresPassengers(int count) => _withArgs(
    count > 1 ? 'departures_passengers_plural' : 'departures_passengers',
    {'count': count},
  );
  String get departureDetailEyebrow => translate('departure_detail_eyebrow');
  String get departureDetailComfort => translate('departure_detail_comfort');
  String get departureDetailStops => translate('departure_detail_stops');
  String get departureDetailSegmentPrice =>
      translate('departure_detail_segment_price');
  String get departureDetailEstimates =>
      translate('departure_detail_estimates');
  String get departureDetailChooseSeat =>
      translate('departure_detail_choose_seat');
  String get departureDetailFull => translate('departure_detail_full');
  String departureDetailSeatsLeft(int count) => _withArgs(
    count > 1
        ? 'departure_detail_seats_left_plural'
        : 'departure_detail_seats_left',
    {'count': count},
  );

  String get departureDetailMissingHeader =>
      translate('departure_detail_missing_header');
  String get departureDetailMissingTitle =>
      translate('departure_detail_missing_title');
  String get departureDetailMissingText =>
      translate('departure_detail_missing_text');
  String get departureDetailBack => translate('departure_detail_back');

  // --- Accueil (HOME-01) ---
  String homeGreeting(String name) =>
      _withArgs('home_greeting', {'name': name});
  String get homeGreetingAnonymous => translate('home_greeting_anonymous');
  String get homeTitle => translate('home_title');
  String get homeSearchEyebrow => translate('home_search_eyebrow');
  String get homeSearchQuestion => translate('home_search_question');
  String get homeSearchOrigin => translate('home_search_origin');
  String get homeSearchDestination => translate('home_search_destination');
  String get homeSearchPickCity => translate('home_search_pick_city');
  String get homeSearchDate => translate('home_search_date');
  String get homeSearchDateEmpty => translate('home_search_date_empty');
  String get homeSearchPassengers => translate('home_search_passengers');

  /// « 1 adulte » / « 3 adultes » — le pluriel se joue sur la clé, les
  /// deux langues ne le formant pas au même endroit.
  String homeSearchPassengersCount(int count) => _withArgs(
    count > 1
        ? 'home_search_passengers_count_plural'
        : 'home_search_passengers_count',
    {'count': count},
  );
  String get homeSearchSubmit => translate('home_search_submit');
  String get homeQuickTitle => translate('home_quick_title');
  String get homeQuickRide => translate('home_quick_ride');
  String get homeQuickParcels => translate('home_quick_parcels');
  String get homeQuickWallet => translate('home_quick_wallet');
  String get homeQuickTickets => translate('home_quick_tickets');
  String get homeRideEyebrow => translate('home_ride_eyebrow');
  String get homeRideTitle => translate('home_ride_title');
  String get homeRideText => translate('home_ride_text');
  String get homeRideSoon => translate('home_ride_soon');
  String get homeNextTrip => translate('home_next_trip');
  String get homeNextTripEmptyTitle => translate('home_next_trip_empty_title');
  String get homeNextTripEmptyText => translate('home_next_trip_empty_text');
  String get homeReminderTitle => translate('home_reminder_title');
  String get homeReminderText => translate('home_reminder_text');
  String get homeErrorTitle => translate('home_error_title');
  String get homeErrorText => translate('home_error_text');

  // --- Centre de notifications (HOME-02) ---
  String get notificationsEyebrow => translate('notifications_eyebrow');
  String get notificationsTitle => translate('notifications_title');
  String get notificationsMarkAll => translate('notifications_mark_all');
  String get notificationsMarked => translate('notifications_marked');
  String get notificationsEmpty => translate('notifications_empty');
  String get notificationsSoon => translate('notifications_soon');
  String notificationsMinutesAgo(int count) =>
      _withArgs('notifications_minutes_ago', {'count': count});
  String get notificationsToday => translate('notifications_today');
  String get notificationsYesterday => translate('notifications_yesterday');

  // --- Profil : informations, sécurité, préférences ---
  String get profileEditEyebrow => translate('profile_edit_eyebrow');
  String get profileEditTitle => translate('profile_edit_title');
  String get profileEditFirstName => translate('profile_edit_first_name');
  String get profileEditLastName => translate('profile_edit_last_name');
  String get profileEditPhone => translate('profile_edit_phone');
  String get profileEditPhoneLocked => translate('profile_edit_phone_locked');
  String get profileEditEmail => translate('profile_edit_email');
  String get profileEditBirthDate => translate('profile_edit_birth_date');
  String get profileEditBirthDateHint =>
      translate('profile_edit_birth_date_hint');
  String get profileEditSave => translate('profile_edit_save');
  String get profileEditSaved => translate('profile_edit_saved');
  String get profileEditFirstNameRequired =>
      translate('profile_edit_first_name_required');
  String get profileEditEmailInvalid => translate('profile_edit_email_invalid');
  String get securityEyebrow => translate('security_eyebrow');
  String get securityTitle => translate('security_title');
  String get securityChangePin => translate('security_change_pin');
  String get securityChangePinSub => translate('security_change_pin_sub');
  String get securityNewDevice => translate('security_new_device');
  String get securityNewDeviceSub => translate('security_new_device_sub');
  String get securityDevices => translate('security_devices');
  String get securityDeviceCurrent => translate('security_device_current');
  String get securityDeviceActive => translate('security_device_active');
  String get securityDeviceRevoke => translate('security_device_revoke');
  String get securityDeviceRevoked => translate('security_device_revoked');
  String securityDeviceLastSeen(String when) =>
      _withArgs('security_device_last_seen', {'when': when});
  String get securityDevicesEmpty => translate('security_devices_empty');
  String get securityDevicesError => translate('security_devices_error');
  String get createPinMismatch => translate('create_pin_mismatch');
  String get changePinIncomplete => translate('change_pin_incomplete');
  String get changePinEyebrow => translate('change_pin_eyebrow');
  String get changePinCurrent => translate('change_pin_current');
  String get changePinCurrentLead => translate('change_pin_current_lead');
  String get changePinNew => translate('change_pin_new');
  String get changePinNewLead => translate('change_pin_new_lead');
  String get changePinConfirm => translate('change_pin_confirm');
  String get changePinConfirmLead => translate('change_pin_confirm_lead');
  String get changePinSame => translate('change_pin_same');
  String get changePinDone => translate('change_pin_done');
  String get preferencesEyebrow => translate('preferences_eyebrow');
  String get preferencesTitle => translate('preferences_title');
  String get preferencesPush => translate('preferences_push');
  String get preferencesPushSub => translate('preferences_push_sub');
  String get preferencesWhatsapp => translate('preferences_whatsapp');
  String get preferencesWhatsappSub => translate('preferences_whatsapp_sub');
  String get preferencesEmail => translate('preferences_email');
  String get preferencesEmailSub => translate('preferences_email_sub');
  String get preferencesDisplay => translate('preferences_display');
  String get preferencesLanguage => translate('preferences_language');
  String get preferencesDark => translate('preferences_dark');
  String get preferencesDarkOn => translate('preferences_dark_on');
  String get preferencesDarkOff => translate('preferences_dark_off');
  String get preferencesError => translate('preferences_error');

  // --- Connexion sociale (Google, Facebook, TikTok) ---
  String oauthNotConfigured(String provider) =>
      _withArgs('oauth_not_configured', {'provider': provider});
  String get oauthUnsupported => translate('oauth_unsupported');
  String oauthFailed(String provider) =>
      _withArgs('oauth_failed', {'provider': provider});

  // --- Nouvel appareil (AUTH-10) et connexion ---
  String get newDeviceEyebrow => translate('new_device_eyebrow');
  String get newDeviceTitle => translate('new_device_title');
  String get newDeviceVisualCaption => translate('new_device_visual_caption');
  String get newDeviceQuestion => translate('new_device_question');
  String get newDeviceLead => translate('new_device_lead');
  String get newDeviceDevice => translate('new_device_device');
  String get newDeviceAttempt => translate('new_device_attempt');
  String get newDeviceAttemptNow => translate('new_device_attempt_now');
  String get newDeviceReceiveCode => translate('new_device_receive_code');
  String get newDeviceNotMe => translate('new_device_not_me');
  String get newDeviceReported => translate('new_device_reported');
  String get loginNewDeviceLink => translate('login_new_device_link');
  String get loginEmailFirst => translate('login_email_first');

  // --- Écran des autorisations (AUTH-07) ---
  String get permissionsEyebrow => translate('permissions_eyebrow');
  String get permissionsTitle => translate('permissions_title');
  String get permissionsLead => translate('permissions_lead');
  String get permissionsIdentityTitle =>
      translate('permissions_identity_title');
  String get permissionsIdentityLead => translate('permissions_identity_lead');
  String get permissionsNameLabel => translate('permissions_name_label');
  String get permissionsNameHint => translate('permissions_name_hint');
  String get permissionsNameRequired => translate('permissions_name_required');
  String get permissionsPhoneLabel => translate('permissions_phone_label');
  String permissionsPhonePrefix(String country, String prefix) => _withArgs(
    'permissions_phone_prefix',
    {'country': country, 'prefix': prefix},
  );
  String get permissionsPhoneCountry => translate('permissions_phone_country');
  String get permissionsPhoneInvalid => translate('permissions_phone_invalid');
  String get permissionsLocationTitle =>
      translate('permissions_location_title');
  String get permissionsLocationText => translate('permissions_location_text');
  String get permissionsNotificationsTitle =>
      translate('permissions_notifications_title');
  String get permissionsNotificationsText =>
      translate('permissions_notifications_text');
  String get permissionsDenied => translate('permissions_denied');
  String get permissionsContinue => translate('permissions_continue');
  String get permissionsLater => translate('permissions_later');

  String get signupOr => translate('signup_or');
  String get signupWithGoogle => translate('signup_with_google');
  String get signupWithFacebook => translate('signup_with_facebook');
  String get signupWithTiktok => translate('signup_with_tiktok');

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
