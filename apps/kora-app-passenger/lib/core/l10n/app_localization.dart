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

  /// Lit et décode un fichier de langue.
  ///
  /// On passe par [AssetBundle.load] et un `utf8.decode` fait ici,
  /// plutôt que par `loadString` : au-delà de 50 Kio, celui-ci décode
  /// sur un isolat de fond, et le `Future` ne se résout plus dans le
  /// tour de boucle courant. Les écrans montés dans un test —
  /// `Localizations` attend ce `Future` avant de construire — restaient
  /// alors vides après `pumpAndSettle`, sans la moindre erreur. Les
  /// traductions françaises ont franchi ce seuil, et une centaine de
  /// tests d'écran sont tombés d'un coup.
  static Future<Map<String, String>> _readLanguageFile(String code) async {
    final bytes = await rootBundle.load('assets/languages/$code.json');
    final raw = utf8.decode(bytes.buffer.asUint8List());
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

  /// La traduction de [key], ou `null` si l'app ne la connaît pas.
  ///
  /// Contrairement à [translate], n'accuse pas la clé manquante : elle sert
  /// aux libellés dont la liste est décidée par le **serveur** — les
  /// services à bord, par exemple. Un équipement ajouté côté back après la
  /// sortie de cette version n'est pas un oubli de traduction, et ne doit
  /// pas faire sauter l'assertion en développement.
  String? maybeTranslate(String key) => _strings[key] ?? _fallbackStrings[key];

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
  String get paymentMethodLoading => translate('payment_method_loading');
  String get paymentMethodFailed => translate('payment_method_failed');
  String get paymentMethodRetry => translate('payment_method_retry');
  String get paymentMethodNone => translate('payment_method_none');
  String get paymentWidgetFailed => translate('payment_widget_failed');
  String get paymentMethodOutOfMvp => translate('payment_method_out_of_mvp');
  String get paymentMethodUnavailable =>
      translate('payment_method_unavailable');

  String get appTagline => translate('app_tagline');
  String get actionRetry => translate('action_retry');
  String get actionContinue => translate('action_continue');
  String get actionSeeAll => translate('action_see_all');
  String get actionCancel => translate('action_cancel');

  // -------------------------------------------------- navigation principale
  String get navHome => translate('nav_home');
  String get navTrips => translate('nav_trips');
  String get navParcels => translate('nav_parcels');
  String get navWallet => translate('nav_wallet');
  String get navProfile => translate('nav_profile');

  // ------------------------------------------------------ écran Mes voyages
  String get ticketsEyebrow => translate('tickets_eyebrow');

  /// Affiché quand le serveur refuse d'inscrire l'appareil parce que les
  /// notifications sont coupées dans les préférences du compte — le seul
  /// refus que le voyageur peut lever lui-même.
  String get pushPreferenceDisabled => translate('push_preference_disabled');

  String get ticketsTitle => translate('tickets_title');
  String get ticketsNotifications => translate('tickets_notifications');

  String ticketsTabUpcoming(int count) =>
      _withArgs('tickets_tab_upcoming', {'count': count});
  String ticketsTabPast(int count) =>
      _withArgs('tickets_tab_past', {'count': count});
  String ticketsTabCancelled(int count) =>
      _withArgs('tickets_tab_cancelled', {'count': count});
  String ticketsTabPending(int count) =>
      _withArgs('tickets_tab_pending', {'count': count});
  String get ticketsEmptyPendingTitle =>
      translate('tickets_empty_pending_title');
  String get ticketsEmptyPendingText => translate('tickets_empty_pending_text');
  String get assistSeeRequest => translate('assist_see_request');

  String get ticketsStatusConfirmed => translate('tickets_status_confirmed');
  String get ticketsStatusCompleted => translate('tickets_status_completed');
  String get ticketsStatusCancelled => translate('tickets_status_cancelled');
  String get ticketsSeeTicket => translate('tickets_see_ticket');

  String get ticketsSeeTickets => translate('tickets_see_tickets');

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
  String get ticketCancelTitle => translate('ticket_cancel_title');

  String get ticketCancelText => translate('ticket_cancel_text');

  String ticketCancelTextGroup(String count) =>
      _withArgs('ticket_cancel_text_group', {'count': count});

  String get ticketCancelKeep => translate('ticket_cancel_keep');

  String get ticketCancelConfirm => translate('ticket_cancel_confirm');

  String get ticketCancelProgress => translate('ticket_cancel_progress');

  String get ticketCancelDone => translate('ticket_cancel_done');

  String get ticketDetailCancelTitle => translate('ticket_detail_cancel_title');
  String get ticketDetailCancelSubtitle =>
      translate('ticket_detail_cancel_subtitle');
  String get ticketDetailPdfSubtitle => translate('ticket_detail_pdf_subtitle');
  String get ticketDetailPdfSavedSubtitle =>
      translate('ticket_detail_pdf_saved_subtitle');
  String get ticketDetailPdfOfflineSubtitle =>
      translate('ticket_detail_pdf_offline_subtitle');
  String get ticketDetailPdfCopySubtitle =>
      translate('ticket_detail_pdf_copy_subtitle');
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

  String get searchMissingReturn => translate('search_missing_return');
  String get searchReturnPick => translate('search_return_pick');
  String get searchRecent => translate('search_recent');
  String get searchMissingCities => translate('search_missing_cities');
  String get searchRoundtripSoon => translate('search_roundtrip_soon');

  // -------------------------------------------------------- écran Colis
  String get colisNewShipment => translate('colis_new_shipment');
  String colisStepOf(int step, int total) => _withArgs('colis_step_of', {
    'step': step.toString(),
    'total': total.toString(),
  });
  String colisProgress(int percent) =>
      _withArgs('colis_progress', {'percent': percent.toString()});
  String get colisStepRouteShort => translate('colis_step_route_short');
  String colisPreviousStep(String step) =>
      _withArgs('colis_previous_step', {'step': step});
  String get colisBackToInfo => translate('colis_back_to_info');
  String get colisCancel => translate('colis_cancel');
  String get colisEdit => translate('colis_edit');
  String colisAmount(int amount) =>
      _withArgs('colis_amount', {'amount': _grouped(amount)});

  /// Les milliers se séparent, comme sur la maquette : « 2 500 FCFA » se
  /// lit d'un coup d'œil, « 2500 FCFA » se compte. L'espace est
  /// insécable, sans quoi un montant se coupait en fin de ligne.
  static String _grouped(int amount) {
    final digits = amount.abs().toString();
    final out = StringBuffer(amount < 0 ? '-' : '');

    for (var i = 0; i < digits.length; i++) {
      if (i != 0 && (digits.length - i) % 3 == 0) out.write('\u00A0');
      out.write(digits[i]);
    }

    return out.toString();
  }

  String get colisWeightUnit => translate('colis_weight_unit');
  String colisWeightValue(String weight) =>
      _withArgs('colis_weight_value', {'weight': weight.toString()});
  String colisWeightWithUnit(String weight) =>
      _withArgs('colis_weight_with_unit', {'weight': weight.toString()});
  String colisWeightDeclared(String weight) =>
      _withArgs('colis_weight_declared', {'weight': weight.toString()});
  String colisRange(String low, String high) => _withArgs('colis_range', {
    'low': low.toString(),
    'high': high.toString(),
  });
  String get colisStepRoute => translate('colis_step_route');
  String get colisRouteTitle => translate('colis_route_title');
  String get colisRouteSubtitle => translate('colis_route_subtitle');
  String get colisRouteCta => translate('colis_route_cta');
  String get colisRouteHint => translate('colis_route_hint');
  String get colisOriginHub => translate('colis_origin_hub');
  String get colisDestinationHub => translate('colis_destination_hub');
  String get colisHubPlaceholder => translate('colis_hub_placeholder');
  String get colisRouteUnservedTitle => translate('colis_route_unserved_title');
  String get colisRouteUnservedText => translate('colis_route_unserved_text');
  String get colisAutoAssignTitle => translate('colis_auto_assign_title');
  String get colisAutoAssignText => translate('colis_auto_assign_text');
  String get colisStepContent => translate('colis_step_content');
  String get colisContentTitle => translate('colis_content_title');
  String get colisContentSubtitle => translate('colis_content_subtitle');
  String get colisContentCta => translate('colis_content_cta');
  String get colisContentHint => translate('colis_content_hint');
  String get colisSelectedRoute => translate('colis_selected_route');
  String get colisContentLabel => translate('colis_content_label');
  String get colisContentPlaceholder => translate('colis_content_placeholder');
  String get colisContentHelp => translate('colis_content_help');
  String get colisWeightLabel => translate('colis_weight_label');
  String get colisWeighingTitle => translate('colis_weighing_title');
  String get colisWeighingText => translate('colis_weighing_text');
  String get colisStepContacts => translate('colis_step_contacts');
  String get colisContactsTitle => translate('colis_contacts_title');
  String get colisContactsSubtitle => translate('colis_contacts_subtitle');
  String get colisContactsCta => translate('colis_contacts_cta');
  String get colisContactsHint => translate('colis_contacts_hint');
  String get colisSenderTitle => translate('colis_sender_title');
  String get colisSenderSubtitle => translate('colis_sender_subtitle');
  String get colisSenderTag => translate('colis_sender_tag');
  String get colisSenderTag2 => translate('colis_sender_tag2');
  String get colisSenderName => translate('colis_sender_name');
  String get colisSenderPhone => translate('colis_sender_phone');
  String get colisSenderPhoneHelp => translate('colis_sender_phone_help');
  String get colisRecipientTitle => translate('colis_recipient_title');
  String get colisRecipientSubtitle => translate('colis_recipient_subtitle');
  String get colisRecipientTag => translate('colis_recipient_tag');
  String get colisRecipientTag2 => translate('colis_recipient_tag2');
  String get colisRecipientName => translate('colis_recipient_name');
  String get colisRecipientNameHint => translate('colis_recipient_name_hint');
  String get colisRecipientNameHelp => translate('colis_recipient_name_help');
  String get colisRecipientPhone => translate('colis_recipient_phone');
  String get colisIdentityTitle => translate('colis_identity_title');
  String get colisIdentityText => translate('colis_identity_text');
  String get colisPrivacyTitle => translate('colis_privacy_title');
  String get colisPrivacyText => translate('colis_privacy_text');
  String get colisAssignedByOps => translate('colis_assigned_by_ops');
  String get colisIdentityAtPickup => translate('colis_identity_at_pickup');
  String get colisPaymentAtHub => translate('colis_payment_at_hub');
  String get colisIndicativeEstimate => translate('colis_indicative_estimate');
  String get colisProvisionalTitle => translate('colis_provisional_title');
  String get colisProvisionalText => translate('colis_provisional_text');
  String get colisNextStepsTitle => translate('colis_next_steps_title');
  String get colisNextStep1Title => translate('colis_next_step_1_title');
  String colisNextStep1Text(String city) =>
      _withArgs('colis_next_step_1_text', {'city': city});
  String get colisNextStep2Title => translate('colis_next_step_2_title');
  String get colisNextStep2Text => translate('colis_next_step_2_text');
  String get colisNextStep3Title => translate('colis_next_step_3_title');
  String get colisNextStep3Text => translate('colis_next_step_3_text');
  String get colisPickupCodeTitle => translate('colis_pickup_code_title');
  String get colisPickupCodeText => translate('colis_pickup_code_text');
  String get colisStepSummary => translate('colis_step_summary');
  String get colisSummaryTitle => translate('colis_summary_title');
  String get colisSummarySubtitle => translate('colis_summary_subtitle');
  String get colisSummaryCta => translate('colis_summary_cta');
  String get colisSummaryHint => translate('colis_summary_hint');
  String get colisCorridorTitle => translate('colis_corridor_title');
  String get colisServiceLine => translate('colis_service_line');
  String get colisContentCardTitle => translate('colis_content_card_title');
  String get colisContactsCardTitle => translate('colis_contacts_card_title');
  String get colisNatureLabel => translate('colis_nature_label');
  String get colisDeclaredWeightLabel =>
      translate('colis_declared_weight_label');
  String get colisPackagingNote => translate('colis_packaging_note');
  String get colisIndicativeTitle => translate('colis_indicative_title');
  String colisIndicativeText(String low, String high) => _withArgs(
    'colis_indicative_text',
    {'low': low.toString(), 'high': high.toString()},
  );
  String get colisPreregisteredTitle => translate('colis_preregistered_title');
  String get colisPreregisteredText => translate('colis_preregistered_text');
  String get colisPreregisteredCta => translate('colis_preregistered_cta');
  String get colisBackHome => translate('colis_back_home');
  String get colisShipmentsSearch => translate('colis_shipments_search');
  String get colisBackToParcels => translate('colis_back_to_parcels');
  String get colisToDropOff => translate('colis_to_drop_off');
  String get colisNoReferenceTitle => translate('colis_no_reference_title');
  String get colisNoReferenceText => translate('colis_no_reference_text');
  String get colisLinkTitle => translate('colis_link_title');
  String get colisDirectDeparture => translate('colis_direct_departure');
  String get colisSenderHub => translate('colis_sender_hub');
  String get colisRecipientHub => translate('colis_recipient_hub');
  String get colisNatureWeight => translate('colis_nature_weight');
  String get colisIndicativeFare => translate('colis_indicative_fare');
  String get colisFinalAtCounter => translate('colis_final_at_counter');
  String get colisRotationNote => translate('colis_rotation_note');
  String get colisComplianceTitle => translate('colis_compliance_title');
  String get colisCompliance1 => translate('colis_compliance_1');
  String get colisCompliance2 => translate('colis_compliance_2');
  String get colisCounterOpen => translate('colis_counter_open');
  String colisCounterHours(String hours) =>
      _withArgs('colis_counter_hours', {'hours': hours});
  String get colisCounterCall => translate('colis_counter_call');
  String get colisCounterWhatsapp => translate('colis_counter_whatsapp');
  String get colisCounterDirections => translate('colis_counter_directions');
  String get colisAxisTitle => translate('colis_axis_title');
  String get colisOriginHubLabel => translate('colis_origin_hub_label');
  String get colisDestinationHubLabel =>
      translate('colis_destination_hub_label');
  String get colisNatureChecked => translate('colis_nature_checked');
  String get colisVisualCompliance => translate('colis_visual_compliance');
  String colisAgentRef(String agent) =>
      _withArgs('colis_agent_ref', {'agent': agent});
  String get colisWeighingGapNote => translate('colis_weighing_gap_note');
  String get colisAcceptedMethods => translate('colis_accepted_methods');
  String get colisMethodWallet => translate('colis_method_wallet');
  String get colisMethodMtnMomo => translate('colis_method_mtn_momo');
  String get colisMethodMomo => translate('colis_method_momo');
  String get colisMethodCardShort => translate('colis_method_card_short');
  String colisCounterSupport(String counter) =>
      _withArgs('colis_counter_support', {'counter': counter});
  String colisProtocolSteps(int count) =>
      _withArgs('colis_protocol_steps', {'count': count});
  String get colisProtocolTitle => translate('colis_protocol_title');
  String get colisProtocolStep1Title => translate('colis_protocol_step1_title');
  String colisProtocolStep1Text(String counter) =>
      _withArgs('colis_protocol_step1_text', {'counter': counter.toString()});
  String get colisProtocolStep2Title => translate('colis_protocol_step2_title');
  String get colisProtocolStep2Text => translate('colis_protocol_step2_text');
  String get colisProtocolStep3Title => translate('colis_protocol_step3_title');
  String get colisProtocolStep3Text => translate('colis_protocol_step3_text');
  String get colisProtocolStep4Title => translate('colis_protocol_step4_title');
  String get colisProtocolStep4Text => translate('colis_protocol_step4_text');
  String get colisWeightCertifiedTag => translate('colis_weight_certified_tag');
  String get colisWeightUpdated => translate('colis_weight_updated');
  String get colisWeighedTitle => translate('colis_weighed_title');
  String get colisWeighedText => translate('colis_weighed_text');
  String get colisWeighingCardTitle => translate('colis_weighing_card_title');
  String colisWeightGap(String gap) =>
      _withArgs('colis_weight_gap', {'gap': gap});
  String get colisCertifiedWeightLabel =>
      translate('colis_certified_weight_label');
  String get colisDeclaredOnline => translate('colis_declared_online');
  String get colisCertifiedScale => translate('colis_certified_scale');
  String get colisReclassifiedText => translate('colis_reclassified_text');
  String get colisControlledBy => translate('colis_controlled_by');
  String get colisPricingTitle => translate('colis_pricing_title');
  String get colisPaymentExpected => translate('colis_payment_expected');
  String colisTransportFee(String city, String weight) =>
      _withArgs('colis_transport_fee', {'city': city, 'weight': weight});
  String get colisPackagingFee => translate('colis_packaging_fee');
  String get colisSealFee => translate('colis_seal_fee');
  String get colisInsuranceFee => translate('colis_insurance_fee');
  String get colisInsuranceFree => translate('colis_insurance_free');
  String get colisNetDue => translate('colis_net_due');
  String get colisTaxesIncluded => translate('colis_taxes_included');
  String get colisReferenceOnPaymentTitle =>
      translate('colis_reference_on_payment_title');
  String get colisReferenceOnPaymentText =>
      translate('colis_reference_on_payment_text');
  String colisPayFinalQuote(String amount) =>
      _withArgs('colis_pay_final_quote', {'amount': amount});
  String get colisQuoteCta => translate('colis_quote_cta');
  String get colisQuoteHint => translate('colis_quote_hint');
  String get colisQuoteIssued => translate('colis_quote_issued');
  String get colisPaymentTitle => translate('colis_payment_title');
  String colisPaymentSubtitle(String origin, String destination) => _withArgs(
    'colis_payment_subtitle',
    {'origin': origin, 'destination': destination},
  );
  String get colisTotalDue => translate('colis_total_due');
  String get colisWalletTitle => translate('colis_wallet_title');
  String colisWalletBalance(int amount) =>
      _withArgs('colis_wallet_balance', {'amount': _grouped(amount)});
  String get colisWalletApplied => translate('colis_wallet_applied');
  String get colisRemainingDue => translate('colis_remaining_due');
  String get colisOperators => translate('colis_operators');
  String get colisRecommended => translate('colis_recommended');
  String get colisMethodInstant => translate('colis_method_instant');
  String get colisMethodUssd => translate('colis_method_ussd');
  String get colisMethodCard => translate('colis_method_card');
  String get colisPaymentMockTitle => translate('colis_payment_mock_title');
  String get colisPaymentMockText => translate('colis_payment_mock_text');
  String get colisCheckoutEyebrow => translate('colis_checkout_eyebrow');
  String get colisGatewayMomoLead => translate('colis_gateway_momo_lead');
  String colisGatewaySheetLead(String gateway) =>
      _withArgs('colis_gateway_sheet_lead', {'gateway': gateway});
  String colisCorridorTag(String origin, String destination) => _withArgs(
    'colis_corridor_tag',
    {'origin': origin, 'destination': destination},
  );
  String colisBalanceTag(String weight) =>
      _withArgs('colis_balance_tag', {'weight': weight});
  String get colisAfterPaymentNote => translate('colis_after_payment_note');
  String colisTransactionId(String id) =>
      _withArgs('colis_transaction_id', {'id': id});
  String get colisSplitTitle => translate('colis_split_title');
  String get colisSplitSettled => translate('colis_split_settled');
  String colisSplitDetail(String transport, String seal) =>
      _withArgs('colis_split_detail', {'transport': transport, 'seal': seal});
  String get colisPushSent => translate('colis_push_sent');
  String colisInitiatedAt(String time) =>
      _withArgs('colis_initiated_at', {'time': time});
  String colisSessionActive(int minutes) =>
      _withArgs('colis_session_active', {'minutes': '$minutes'});
  String colisParcelLine(String weight) =>
      _withArgs('colis_parcel_line', {'weight': weight});
  String get colisNoDoublePayTitle => translate('colis_no_double_pay_title');
  String get colisNoDoublePayText => translate('colis_no_double_pay_text');
  String get colisCheckStatus => translate('colis_check_status');
  String get colisResendPush => translate('colis_resend_push');
  String colisOperatorSupport(String phone) =>
      _withArgs('colis_operator_support', {'phone': phone});
  String get colisFailurePreview => translate('colis_failure_preview');
  String get colisFailureTitle => translate('colis_failure_title');
  String get colisFailureReason => translate('colis_failure_reason');
  String get colisFailureText => translate('colis_failure_text');
  String get colisPendingTag => translate('colis_pending_tag');
  String get colisPendingTitle => translate('colis_pending_title');
  String get colisPendingText => translate('colis_pending_text');
  String get colisPendingSimulate => translate('colis_pending_simulate');
  String get colisPendingSwitch => translate('colis_pending_switch');
  String colisPayCta(int amount) =>
      _withArgs('colis_pay_cta', {'amount': amount.toString()});
  String colisPaymentContinue(String amount) =>
      _withArgs('colis_payment_continue', {'amount': amount});
  String get colisEditQuote => translate('colis_edit_quote');
  String get colisShipmentValidated => translate('colis_shipment_validated');
  String colisRegisteredAt(String city) =>
      _withArgs('colis_registered_at', {'city': city});
  String get colisWeighedSealed => translate('colis_weighed_sealed');
  String get colisSecureCustody => translate('colis_secure_custody');
  String get colisNextDeparture => translate('colis_next_departure');
  String get colisSettlementTitle => translate('colis_settlement_title');
  String get colisPaymentVerified => translate('colis_payment_verified');
  String get colisAmountPaid => translate('colis_amount_paid');
  String get colisDebitMethod => translate('colis_debit_method');
  String get colisOfficialTimestamp => translate('colis_official_timestamp');
  String get colisTransactionLabel => translate('colis_transaction_label');
  String colisTodayAt(String time) =>
      _withArgs('colis_today_at', {'time': time});
  String colisTomorrowAt(String time) =>
      _withArgs('colis_tomorrow_at', {'time': time});
  String get colisTrackCta => translate('colis_track_cta');
  String get colisReceiptDownload => translate('colis_receipt_download');
  String get colisPaidTitle => translate('colis_paid_title');
  String get colisPaidText => translate('colis_paid_text');
  String get colisRefShort => translate('colis_ref_short');
  String get colisOfficialReference => translate('colis_official_reference');
  String get colisReceiptNote => translate('colis_receipt_note');
  String get colisAssignedRoute => translate('colis_assigned_route');
  String colisSealedAt(String city) =>
      _withArgs('colis_sealed_at', {'city': city});
  String colisPaidWeight(String weight) =>
      _withArgs('colis_paid_weight', {'weight': weight});
  String get colisShipmentsEyebrow => translate('colis_shipments_eyebrow');
  String get colisTabActive => translate('colis_tab_active');
  String get colisTabHistory => translate('colis_tab_history');
  String get colisTabDrafts => translate('colis_tab_drafts');
  String get colisTrackParcel => translate('colis_track_parcel');
  String colisDroppedAt(String time) =>
      _withArgs('colis_dropped_at', {'time': time});
  String get colisPickupCodeSent => translate('colis_pickup_code_sent');
  String get colisDetailsLive => translate('colis_details_live');
  String get colisDraftPending => translate('colis_draft_pending');
  String colisDraftNumber(String number) =>
      _withArgs('colis_draft_number', {'number': number});
  String colisIndicativeQuote(String amount) =>
      _withArgs('colis_indicative_quote', {'amount': amount});
  String get colisDraftHint => translate('colis_draft_hint');
  String get colisDraftCancel => translate('colis_draft_cancel');
  String get colisDraftFinish => translate('colis_draft_finish');
  String get colisDelivered => translate('colis_delivered');
  String colisCollectedOn(String date) =>
      _withArgs('colis_collected_on', {'date': date});
  String get colisReceipt => translate('colis_receipt');
  String get colisEmptyTitle => translate('colis_empty_title');
  String get colisEmptyText => translate('colis_empty_text');
  String get colisTrackExternal => translate('colis_track_external');
  String get colisTrackExternalHint => translate('colis_track_external_hint');
  String get colisReferenceField => translate('colis_reference_field');
  String get colisSearchStatus => translate('colis_search_status');
  String get colisShipmentsTitle => translate('colis_shipments_title');
  String colisSent(int count) =>
      _withArgs('colis_sent', {'count': count.toString()});
  String colisToReceive(int count) =>
      _withArgs('colis_to_receive', {'count': count.toString()});
  String get colisShipmentsEmpty => translate('colis_shipments_empty');
  String colisStepOfTotal(int step, int total) => _withArgs(
    'colis_step_of_total',
    {'step': step.toString(), 'total': total.toString()},
  );
  String colisEtaShort(String time) =>
      _withArgs('colis_eta_short', {'time': time});
  String colisReadySince(String time) =>
      _withArgs('colis_ready_since', {'time': time});
  String get colisStatusSealed => translate('colis_status_sealed');
  String get colisStatusTransit => translate('colis_status_transit');
  String get colisStatusReady => translate('colis_status_ready');
  String get colisStatusCollected => translate('colis_status_collected');
  String get colisOfficialShipmentNo => translate('colis_official_shipment_no');
  String get colisActive => translate('colis_active');
  String get colisUpcoming => translate('colis_upcoming');
  String get colisSecureSession => translate('colis_secure_session');
  String colisRefreshedAgo(int minutes) =>
      _withArgs('colis_refreshed_ago', {'minutes': '$minutes'});
  String get colisRefresh => translate('colis_refresh');
  String colisBusNumber(String number) =>
      _withArgs('colis_bus_number', {'number': number});
  String colisCheckpoint(String place) =>
      _withArgs('colis_checkpoint', {'place': place});
  String get colisNoIncident => translate('colis_no_incident');
  String get colisMilestonesSource => translate('colis_milestones_source');
  String get colisRecipientSection => translate('colis_recipient_section');
  String get colisDeliveryProtocol => translate('colis_delivery_protocol');
  String get colisDesignatedRecipient =>
      translate('colis_designated_recipient');
  String get colisMasked => translate('colis_masked');
  String get colisPickupPlace => translate('colis_pickup_place');
  String colisCounterUntil(String time) =>
      _withArgs('colis_counter_until', {'time': time});
  String get colisAntiImpersonation => translate('colis_anti_impersonation');
  String get colisAntiImpersonationText =>
      translate('colis_anti_impersonation_text');
  String get colisIdentityAtCounter => translate('colis_identity_at_counter');
  String get colisShowLabel => translate('colis_show_label');
  String get colisShareTracking => translate('colis_share_tracking');
  String colisLineSupport(String line) =>
      _withArgs('colis_line_support', {'line': line});
  String get colisConvoyDesk => translate('colis_convoy_desk');
  String get colisReportIncident => translate('colis_report_incident');
  String get colisWaybillRef => translate('colis_waybill_ref');
  String get colisDeliveryConditions => translate('colis_delivery_conditions');
  String colisConditionsCount(int count) =>
      _withArgs('colis_conditions_count', {'count': '$count'});
  String get colisCondition1Title => translate('colis_condition_1_title');
  String get colisCondition1Text => translate('colis_condition_1_text');
  String get colisCondition2Title => translate('colis_condition_2_title');
  String get colisCondition2Text => translate('colis_condition_2_text');
  String get colisCondition3Title => translate('colis_condition_3_title');
  String get colisCondition3Text => translate('colis_condition_3_text');
  String get colisCodeSentTo => translate('colis_code_sent_to');
  String get colisNoCodeHere => translate('colis_no_code_here');
  String get colisFreeKeeping => translate('colis_free_keeping');
  String colisFreeKeepingDays(int days) =>
      _withArgs('colis_free_keeping_days', {'days': '$days'});
  String colisReceivedOn(String date) =>
      _withArgs('colis_received_on', {'date': date});
  String colisKeepUntil(String date) =>
      _withArgs('colis_keep_until', {'date': date});
  String get colisSealedDetails => translate('colis_sealed_details');
  String get colisArrivalCheckOk => translate('colis_arrival_check_ok');
  String get colisSender => translate('colis_sender');
  String get colisSealNumber => translate('colis_seal_number');
  String get colisSharePublic => translate('colis_share_public');
  String get colisSharePublicText => translate('colis_share_public_text');
  String get colisShareLink => translate('colis_share_link');
  String get colisLinkCopied => translate('colis_link_copied');
  String get colisDirections => translate('colis_directions');
  String colisFreeKeepingText(String until, String fee) =>
      _withArgs('colis_free_keeping_text', {'until': until, 'fee': fee});
  String colisNeedHelp(String phone) =>
      _withArgs('colis_need_help', {'phone': phone});
  String get colisIncidentEyebrow => translate('colis_incident_eyebrow');
  String get colisIncidentTitle => translate('colis_incident_title');
  String get colisIncidentLead => translate('colis_incident_lead');
  String get colisCertifiedParcel => translate('colis_certified_parcel');
  String get colisTrackingNumber => translate('colis_tracking_number');
  String get colisIncidentReasonLabel =>
      translate('colis_incident_reason_label');
  String get colisIncidentSingleChoice =>
      translate('colis_incident_single_choice');
  String get colisReasonLossTitle => translate('colis_reason_loss_title');
  String get colisReasonLossText => translate('colis_reason_loss_text');
  String get colisReasonDamageTitle => translate('colis_reason_damage_title');
  String get colisReasonDamageText => translate('colis_reason_damage_text');
  String get colisReasonCustodyTitle => translate('colis_reason_custody_title');
  String get colisReasonCustodyText => translate('colis_reason_custody_text');
  String get colisIncidentDescriptionLabel =>
      translate('colis_incident_description_label');
  String get colisIncidentDescriptionHint =>
      translate('colis_incident_description_hint');
  String get colisIncidentDescriptionHelp =>
      translate('colis_incident_description_help');
  String get colisGuaranteeTitle => translate('colis_guarantee_title');
  String get colisGuaranteeText => translate('colis_guarantee_text');
  String get colisIncidentSend => translate('colis_incident_send');
  String get colisIncidentSent => translate('colis_incident_sent');
  String get colisClaimSample => translate('colis_claim_sample');
  String get colisClaimOpened => translate('colis_claim_opened');
  String get colisClaimsSupport => translate('colis_claims_support');
  String colisClaimOpenedText(String corridor) =>
      _withArgs('colis_claim_opened_text', {'corridor': corridor});
  String colisClaimContact(String name) =>
      _withArgs('colis_claim_contact', {'name': name});
  String get colisTrackingTitle => translate('colis_tracking_title');
  String get colisDeparture => translate('colis_departure');
  String get colisArrival => translate('colis_arrival');
  String colisLineTag(String line) =>
      _withArgs('colis_line_tag', {'line': line});
  String get colisEtaLabel => translate('colis_eta_label');
  String colisEtaToday(String time) =>
      _withArgs('colis_eta_today', {'time': time});
  String colisEtaSource(String line) =>
      _withArgs('colis_eta_source', {'line': line});
  String colisCertifiedAt(String weight) =>
      _withArgs('colis_certified_at', {'weight': weight});
  String get colisMilestonesTitle => translate('colis_milestones_title');
  String get colisMilestoneSealed => translate('colis_milestone_sealed');
  String get colisMilestoneLoaded => translate('colis_milestone_loaded');
  String get colisMilestoneTransit => translate('colis_milestone_transit');
  String get colisMilestoneArrived => translate('colis_milestone_arrived');
  String get colisMilestoneCollected => translate('colis_milestone_collected');
  String get colisPickupCta => translate('colis_pickup_cta');
  String get colisPickupTitle => translate('colis_pickup_title');
  String get colisArrivedTitle => translate('colis_arrived_title');
  String get colisArrivedText => translate('colis_arrived_text');
  String get colisCounterTitle => translate('colis_counter_title');
  String get colisWeekdays => translate('colis_weekdays');
  String get colisWeekend => translate('colis_weekend');
  String get colisBringTitle => translate('colis_bring_title');
  String get colisBringText => translate('colis_bring_text');
  String get colisParcelTitle => translate('colis_parcel_title');
  String get colisNatureField => translate('colis_nature_field');
  String get colisNatureClothing => translate('colis_nature_clothing');
  String get colisNatureDocuments => translate('colis_nature_documents');
  String get colisNatureElectronics => translate('colis_nature_electronics');
  String get colisNatureOther => translate('colis_nature_other');
  String get colisDeclaredValue => translate('colis_declared_value');
  String get colisDeclaredValueHint => translate('colis_declared_value_hint');
  String get colisDeclaredValueHelp => translate('colis_declared_value_help');
  String get colisSecurityProtocolTitle =>
      translate('colis_security_protocol_title');
  String get colisSecurityProtocolText =>
      translate('colis_security_protocol_text');
  String get colisForbiddenLead => translate('colis_forbidden_lead');
  String get colisForbidden1 => translate('colis_forbidden_1');
  String get colisForbidden2 => translate('colis_forbidden_2');
  String get colisForbidden3 => translate('colis_forbidden_3');
  String get colisForbidden4 => translate('colis_forbidden_4');
  String get colisForbidden5 => translate('colis_forbidden_5');
  String get colisCertifyLabel => translate('colis_certify_label');
  String get colisCertifyRequired => translate('colis_certify_required');
  String get colisTraceabilityText => translate('colis_traceability_text');
  String get colisForbiddenTitle => translate('colis_forbidden_title');
  String get colisForbiddenText => translate('colis_forbidden_text');
  String get colisHubSheetTitle => translate('colis_hub_sheet_title');
  String colisHubSheetSubtitle(String city) =>
      _withArgs('colis_hub_sheet_subtitle', {'city': city});
  String get parcelsEyebrow => translate('parcels_eyebrow');
  String get parcelsTitle => translate('parcels_title');
  String get parcelsHelp => translate('parcels_help');
  String get parcelsHeroEyebrow => translate('parcels_hero_eyebrow');
  String get parcelsHeroTitle => translate('parcels_hero_title');
  String get parcelsHeroText => translate('parcels_hero_text');
  String get parcelsReferenceLabel => translate('parcels_reference_label');
  String get parcelsReferenceCaption => translate('parcels_reference_caption');
  String parcelsActiveTitle(int count) =>
      _withArgs('parcels_active_title', {'count': '$count'});
  String get parcelsSeeAll => translate('parcels_see_all');
  String parcelsLine(String line) => _withArgs('parcels_line', {'line': line});
  String get parcelsStepDropped => translate('parcels_step_dropped');
  String get parcelsStepTransit => translate('parcels_step_transit');
  String parcelsStepHub(String city) =>
      _withArgs('parcels_step_hub', {'city': city});
  String parcelsStepDeparted(String time) =>
      _withArgs('parcels_step_departed', {'time': time});
  String parcelsStepPlanned(String time) =>
      _withArgs('parcels_step_planned', {'time': time});
  String get parcelsLastUpdate => translate('parcels_last_update');
  String parcelsLastUpdateValue(String time) =>
      _withArgs('parcels_last_update_value', {'time': time});
  String get parcelsEta => translate('parcels_eta');
  String parcelsEtaValue(String time) =>
      _withArgs('parcels_eta_value', {'time': time});
  String get parcelsWithdrawalNote => translate('parcels_withdrawal_note');
  String get parcelsDetailsCta => translate('parcels_details_cta');
  String get parcelsReferenceCopied => translate('parcels_reference_copied');
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
  String walletPointsExpiry(String date) =>
      _withArgs('wallet_points_expiry', {'date': date});
  String get walletPointsExpiryNone => translate('wallet_points_expiry_none');
  String walletPointsExpirySoon(int days) =>
      _withArgs('wallet_points_expiry_soon', {'days': '$days'});
  String get walletPointsExpiryToday => translate('wallet_points_expiry_today');
  String get walletReferralLabel => translate('wallet_referral_label');
  String get walletReferralCopy => translate('wallet_referral_copy');
  String get walletReferralNone => translate('wallet_referral_none');
  String get walletDormantTitle => translate('wallet_dormant_title');
  String get walletDormantText => translate('wallet_dormant_text');
  String get walletErrorTitle => translate('wallet_error_title');
  String get walletErrorText => translate('wallet_error_text');
  String get walletMovementsEmpty => translate('wallet_movements_empty');
  String get walletMovementsFailed => translate('wallet_movements_failed');
  String walletPointsUnit(String points) =>
      _withArgs('wallet_points_unit', {'points': points});
  String get walletReasonTopup => translate('wallet_reason_topup');
  String get walletReasonPurchase => translate('wallet_reason_purchase');
  String get walletReasonRefundCash => translate('wallet_reason_refund_cash');
  String get walletReasonRefundPoints =>
      translate('wallet_reason_refund_points');
  String get walletReasonPointsEarn => translate('wallet_reason_points_earn');
  String get walletReasonBonusWelcome =>
      translate('wallet_reason_bonus_welcome');
  String get walletReasonBonusReferral =>
      translate('wallet_reason_bonus_referral');
  String get walletReasonBonusAnniversary =>
      translate('wallet_reason_bonus_anniversary');
  String get walletReasonBonusUsage => translate('wallet_reason_bonus_usage');
  String get walletReasonPointsExpire =>
      translate('wallet_reason_points_expire');
  String get walletReasonPointsCorrection =>
      translate('wallet_reason_points_correction');
  String get walletReasonCompensationOffset =>
      translate('wallet_reason_compensation_offset');
  String get walletReasonRestitution => translate('wallet_reason_restitution');
  String get walletReasonAdjustment => translate('wallet_reason_adjustment');
  String get walletReasonUnknown => translate('wallet_reason_unknown');
  String get walletSoon => translate('wallet_soon');

  // --------------------------------------------- écran Historique Wallet
  String get walletHistoryTitle => translate('wallet_history_title');
  String get walletHistoryEyebrow => translate('wallet_history_eyebrow');
  String get walletHistoryFilterAll => translate('wallet_history_filter_all');
  String get walletHistoryFilterCredits =>
      translate('wallet_history_filter_credits');
  String get walletHistoryFilterUses => translate('wallet_history_filter_uses');
  String get walletHistoryErrorTitle => translate('wallet_history_error_title');
  String get walletHistoryErrorText => translate('wallet_history_error_text');
  String get walletHistoryEmptyTitle => translate('wallet_history_empty_title');
  String get walletHistoryEmptyText => translate('wallet_history_empty_text');
  String get walletHistoryEmptyFiltered =>
      translate('wallet_history_empty_filtered');
  String get walletHistoryMoreFailed => translate('wallet_history_more_failed');
  String get walletHistoryEnd => translate('wallet_history_end');

  // ----------------------------------------------- écran Recharge Wallet
  String get walletTopUpTitle => translate('wallet_top_up_title');
  String get walletTopUpCurrent => translate('wallet_top_up_current');
  String get walletTopUpAmountLabel => translate('wallet_top_up_amount_label');
  String get walletTopUpAmountHint => translate('wallet_top_up_amount_hint');
  String get walletTopUpMethodLabel => translate('wallet_top_up_method_label');
  String get walletTopUpMethodsEmpty =>
      translate('wallet_top_up_methods_empty');
  String get walletTopUpMethodsFailed =>
      translate('wallet_top_up_methods_failed');
  String get walletTopUpAction => translate('wallet_top_up_action');
  String walletTopUpTooSmall(String min) =>
      _withArgs('wallet_top_up_too_small', {'min': min});
  String walletTopUpTooLarge(String max) =>
      _withArgs('wallet_top_up_too_large', {'max': max});
  String walletTopUpOverCap(String cap) =>
      _withArgs('wallet_top_up_over_cap', {'cap': cap});
  String get walletTopUpClosedTitle => translate('wallet_top_up_closed_title');
  String get walletTopUpClosedText => translate('wallet_top_up_closed_text');
  String get walletTopUpErrorTitle => translate('wallet_top_up_error_title');
  String get walletTopUpErrorText => translate('wallet_top_up_error_text');
  String get walletTopUpOpenedTitle => translate('wallet_top_up_opened_title');
  String walletTopUpOpenedText(String amount) =>
      _withArgs('wallet_top_up_opened_text', {'amount': amount});
  String get walletTopUpEyebrow => translate('wallet_top_up_eyebrow');
  String get walletTopUpNote => translate('wallet_top_up_note');
  String get walletTopUpMethodTitle => translate('wallet_top_up_method_title');
  String get walletTopUpMethodMomo => translate('wallet_top_up_method_momo');
  String get walletTopUpMethodMomoLead =>
      translate('wallet_top_up_method_momo_lead');
  String get walletTopUpMethodCard => translate('wallet_top_up_method_card');
  String get walletTopUpMethodCardLead =>
      translate('wallet_top_up_method_card_lead');
  String get walletTopUpNoAmount => translate('wallet_top_up_no_amount');
  String get walletTopUpPaymentPending =>
      translate('wallet_top_up_payment_pending');

  // ------------------------- paiement d'une recharge (mêmes écrans)
  String get paymentTopUpEyebrow => translate('payment_topup_eyebrow');
  String get paymentTopUpPendingTitle =>
      translate('payment_topup_pending_title');
  String get paymentTopUpPendingText => translate('payment_topup_pending_text');
  String get paymentTopUpPendingTextWidget =>
      translate('payment_topup_pending_text_widget');
  String get paymentTopUpFailedText => translate('payment_topup_failed_text');
  String get paymentFailedProviderText =>
      translate('payment_failed_provider_text');
  String get paymentTopUpSuccessTitle =>
      translate('payment_topup_success_title');
  String paymentTopUpSuccessText(String amount) =>
      _withArgs('payment_topup_success_text', {'amount': amount});
  String get paymentTopUpSuccessAction =>
      translate('payment_topup_success_action');

  // ------------------------------------- écrans Services, Règles, Bonus
  String get walletServicesTitle => translate('wallet_services_title');
  String get walletServicesEyebrow => translate('wallet_services_eyebrow');
  String get walletServiceBus => translate('wallet_service_bus');
  String get walletServiceBusLead => translate('wallet_service_bus_lead');
  String get walletServiceRide => translate('wallet_service_ride');
  String get walletServiceRideLead => translate('wallet_service_ride_lead');
  String get walletServiceParcels => translate('wallet_service_parcels');
  String get walletServiceParcelsLead =>
      translate('wallet_service_parcels_lead');
  String get walletServicePremium => translate('wallet_service_premium');
  String get walletServicePremiumLead =>
      translate('wallet_service_premium_lead');
  String get walletServiceSoon => translate('wallet_service_soon');
  String walletServicesRule(String min) =>
      _withArgs('wallet_services_rule', {'min': min});
  String get walletServicesRuleUnknown =>
      translate('wallet_services_rule_unknown');
  String get walletServicesRules => translate('wallet_services_rules');
  String get walletRulesTitle => translate('wallet_rules_title');
  String get walletRulesEyebrow => translate('wallet_rules_eyebrow');
  String walletRuleValue(String point, String value) =>
      _withArgs('wallet_rule_value', {'point': point, 'value': value});
  String get walletRuleValueUnknown => translate('wallet_rule_value_unknown');
  String walletRuleSpend(String min) =>
      _withArgs('wallet_rule_spend', {'min': min});
  String get walletRuleSpendUnknown => translate('wallet_rule_spend_unknown');
  String get walletRuleNoCashOut => translate('wallet_rule_no_cash_out');
  String get walletRuleNoCashOutLead =>
      translate('wallet_rule_no_cash_out_lead');
  String get walletRuleNoTransfer => translate('wallet_rule_no_transfer');
  String get walletRuleNoTransferLead =>
      translate('wallet_rule_no_transfer_lead');
  String get walletRuleNoBonus => translate('wallet_rule_no_bonus');
  String get walletRuleNoBonusLead => translate('wallet_rule_no_bonus_lead');
  String walletRuleExpiry(String months) =>
      _withArgs('wallet_rule_expiry', {'months': months});
  String walletRuleExpiryWithAlerts(String months, String alerts) => _withArgs(
    'wallet_rule_expiry_with_alerts',
    {'months': months, 'alerts': alerts},
  );
  String get walletRuleExpiryUnknown => translate('wallet_rule_expiry_unknown');
  String get walletBonusTitle => translate('wallet_bonus_title');
  String get walletBonusEyebrow => translate('wallet_bonus_eyebrow');
  String get walletBonusEarned => translate('wallet_bonus_earned');
  String get walletBonusEarnedUnknown =>
      translate('wallet_bonus_earned_unknown');
  String get walletBonusObtained => translate('wallet_bonus_obtained');
  String get walletBonusPending => translate('wallet_bonus_pending');
  String get walletBonusInvite => translate('wallet_bonus_invite');
  String get walletReferralEntryTitle =>
      translate('wallet_referral_entry_title');
  String get walletReferralEntryText => translate('wallet_referral_entry_text');
  String get walletReferralEntryHint => translate('wallet_referral_entry_hint');
  String get walletReferralEntryAction =>
      translate('wallet_referral_entry_action');
  String get walletReferralEntryDone => translate('wallet_referral_entry_done');
  String get walletReferralEntryFailed =>
      translate('wallet_referral_entry_failed');
  String get walletReferralAttached => translate('wallet_referral_attached');
  String get walletTopupNoteTitle => translate('wallet_topup_note_title');
  String get paymentSecureWindowTitle =>
      translate('payment_secure_window_title');
  String get paymentCardSecureTitle => translate('payment_card_secure_title');
  String get walletBonusErrorTitle => translate('wallet_bonus_error_title');
  String get walletBonusErrorText => translate('wallet_bonus_error_text');

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
  String get profileLogoutConfirmTitle =>
      translate('profile_logout_confirm_title');
  String get profileLogoutConfirmText =>
      translate('profile_logout_confirm_text');
  String get profileLogoutConfirmAction =>
      translate('profile_logout_confirm_action');
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
  String get signupPhoneTitle => translate('signup_phone_title');
  String get signupPhoneLead => translate('signup_phone_lead');
  String get signupPhoneLabel => translate('signup_phone_label');
  String get signupPhoneProtected => translate('signup_phone_protected');
  String get signupNameLabel => translate('signup_name_label');
  String get signupNameHint => translate('signup_name_hint');
  String get signupNameRequired => translate('signup_name_required');
  String get signupPhoneRequired => translate('signup_phone_required');
  String get signupPhoneIncomplete => translate('signup_phone_incomplete');
  String signupPhonePrefix(String country, String prefix) =>
      _withArgs('signup_phone_prefix', {'country': country, 'prefix': prefix});
  String get signupAccountExistsLead => translate('signup_account_exists_lead');
  String get signupAccountExistsLink => translate('signup_account_exists_link');
  String get signupSwitchToPhone => translate('signup_switch_to_phone');
  String get signupSwitchToEmail => translate('signup_switch_to_email');
  String get signupEmailProtected => translate('signup_email_protected');
  String get signupSubmit => translate('signup_submit');
  String get signupReferralSkip => translate('signup_referral_skip');
  String get signupReferralLabel => translate('signup_referral_label');
  String get signupReferralHint => translate('signup_referral_hint');
  String get signupReferralText => translate('signup_referral_text');
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
  String get contactLiveTitle => translate('contact_live_title');
  String get contactLiveText => translate('contact_live_text');
  String get contactWhatsappItem => translate('contact_whatsapp_item');
  String get contactOthers => translate('contact_others');
  String get contactPhone => translate('contact_phone');
  String get contactChat => translate('contact_chat');
  String get contactChatSubtitle => translate('contact_chat_subtitle');
  String get supportChatEyebrow => translate('support_chat_eyebrow');
  String get supportChatTitle => translate('support_chat_title');
  String get contactEmail => translate('contact_email');
  String get contactNote => translate('contact_note');
  String contactUnavailable(String target) =>
      _withArgs('contact_unavailable', {'target': target});

  // --- Parcours Bus : villes, recherche, résultats, détail ---
  String get cityPickerEyebrow => translate('city_picker_eyebrow');
  String get cityPickerOriginTitle => translate('city_picker_origin_title');
  String get cityPickerDestinationTitle =>
      translate('city_picker_destination_title');
  String get cityPickerParcelEyebrow => translate('city_picker_parcel_eyebrow');
  String get cityPickerParcelOriginTitle =>
      translate('city_picker_parcel_origin_title');
  String get cityPickerParcelDestinationTitle =>
      translate('city_picker_parcel_destination_title');
  String get cityPickerParcelStop => translate('city_picker_parcel_stop');
  String get cityPickerSearch => translate('city_picker_search');
  String get cityPickerSuggestions => translate('city_picker_suggestions');
  String get cityPickerRecent => translate('city_picker_recent');
  String get cityPickerStop => translate('city_picker_stop');

  /// « 3 arrêts KORA » : le nombre de points d'embarquement d'une ville.
  ///
  /// Une ville peut en avoir plusieurs, et le voyageur doit le savoir
  /// avant de choisir — pas une fois devant la mauvaise gare.
  String cityPickerStopsCount(int count) => _withArgs(
    count > 1 ? 'city_picker_stops_count_plural' : 'city_picker_stops_count',
    {'count': '$count'},
  );

  /// Le titre du second choix, quand une ville porte plusieurs arrêts.
  String cityPickerStopTitle(String city) =>
      _withArgs('city_picker_stop_title', {'city': city});

  String get cityPickerStopLead => translate('city_picker_stop_lead');
  String get cityPickerStopAll => translate('city_picker_stop_all');
  String get cityPickerStopAllLead => translate('city_picker_stop_all_lead');
  String cityPickerNoResult(String query) =>
      _withArgs('city_picker_no_result', {'query': query});
  String get departuresEyebrow => translate('departures_eyebrow');

  String get departuresDirectionOutbound =>
      translate('departures_direction_outbound');

  String get departuresDirectionInbound =>
      translate('departures_direction_inbound');
  String get departuresSearchingTitle =>
      translate('departures_searching_title');
  String get departuresSearchingLead => translate('departures_searching_lead');
  String get departuresSearchingText => translate('departures_searching_text');
  String get paymentMethodEyebrow => translate('payment_method_eyebrow');
  String get paymentMethodTitle => translate('payment_method_title');
  String get paymentMethodLead => translate('payment_method_lead');
  String get paymentMethodNext => translate('payment_method_next');
  String get paymentMethodMomo => translate('payment_method_momo');
  String get paymentMethodMomoLead => translate('payment_method_momo_lead');
  String get paymentMethodWallet => translate('payment_method_wallet');
  String paymentMethodWalletLead(String balance) =>
      _withArgs('payment_method_wallet_lead', {'balance': balance});
  String get paymentMethodWalletLeadPlain =>
      translate('payment_method_wallet_lead_plain');
  String get paymentMethodCard => translate('payment_method_card');
  String get paymentMethodCardLead => translate('payment_method_card_lead');
  String paymentMethodHold(String time) =>
      _withArgs('payment_method_hold', {'time': time});
  String get paymentMethodTotal => translate('payment_method_total');
  String paymentGatewayMomoLead(String gateway) =>
      _withArgs('payment_gateway_momo_lead', {'gateway': gateway});
  String get paymentGatewayMomoLeadPlain =>
      translate('payment_gateway_momo_lead_plain');
  String get paymentMomoTitle => translate('payment_momo_title');
  String paymentMomoEyebrow(String amount) =>
      _withArgs('payment_momo_eyebrow', {'amount': amount});
  String get paymentMomoAmount => translate('payment_momo_amount');
  String get paymentMomoSecure => translate('payment_momo_secure');
  String get paymentMomoOperator => translate('payment_momo_operator');
  String get paymentMomoOperatorHint => translate('payment_momo_operator_hint');
  String get paymentMomoOperatorRequired =>
      translate('payment_momo_operator_required');
  String paymentMomoNetworkCountry(String network, String country) => _withArgs(
    'payment_momo_network_country',
    {'network': network, 'country': country},
  );
  String get paymentMomoOperatorsLoading =>
      translate('payment_momo_operators_loading');
  String get paymentMomoOperatorsFailed =>
      translate('payment_momo_operators_failed');
  String get paymentMomoOperatorsNone =>
      translate('payment_momo_operators_none');
  String get paymentMomoPhone => translate('payment_momo_phone');
  String get paymentMomoPhoneRequired =>
      translate('payment_momo_phone_required');
  String paymentMomoPhoneShort(String country, int digits) => _withArgs(
    'payment_momo_phone_short',
    {'country': country, 'digits': '$digits'},
  );
  String get paymentMomoNoticeTitle => translate('payment_momo_notice_title');
  String get paymentMomoNotice => translate('payment_momo_notice');
  String get paymentMomoResume => translate('payment_momo_resume');
  String get paymentAbandon => translate('payment_abandon');
  String get paymentAbandonDone => translate('payment_abandon_done');

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
  String get paymentWalletCashClosed => translate('payment_wallet_cash_closed');
  String get paymentWalletPointsNone => translate('payment_wallet_points_none');
  String paymentWalletPointsMinimum(String min) =>
      _withArgs('payment_wallet_points_minimum', {'min': min});
  String get paymentWalletUnavailable =>
      translate('payment_wallet_unavailable');
  String get paymentCardTitle => translate('payment_card_title');
  String get paymentCardLead => translate('payment_card_lead');
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
  String get paymentSwitchMethod => translate('payment_switch_method');
  // --- Attente et échec du paiement ---

  /// L'écran d'attente, seul écran entre la demande et le verdict.
  String get paymentPendingTitle => translate('payment_pending_title');

  String get paymentPendingText => translate('payment_pending_text');

  String get paymentPendingSlow => translate('payment_pending_slow');
  String get paymentPendingTextWidget =>
      translate('payment_pending_text_widget');
  String get paymentPendingSlowWidget =>
      translate('payment_pending_slow_widget');

  String get paymentPendingRefresh => translate('payment_pending_refresh');

  String get paymentPendingReport => translate('payment_pending_report');

  String paymentPendingPolling(int seconds) =>
      _withArgs('payment_pending_polling', {'seconds': seconds});

  String get paymentFailedEyebrow => translate('payment_failed_eyebrow');

  String get paymentFailedTitle => translate('payment_failed_title');

  String get paymentFailedText => translate('payment_failed_text');

  String get paymentFailedHeldText => translate('payment_failed_held_text');
  String get paymentFailedRetryPayment =>
      translate('payment_failed_retry_payment');
  String get paymentFailedRetry => translate('payment_failed_retry');

  String get paymentFailedSwitch => translate('payment_failed_switch');

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
  String get ticketFieldPassenger => translate('ticket_field_passenger');
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
  String get departureDetailStale => translate('departure_detail_stale');
  String get splashLead => translate('splash_lead');
  String get onboardingSkip => translate('onboarding_skip');
  String get onboardingNext => translate('onboarding_next');
  String get onboardingStart => translate('onboarding_start');
  String get onboardingSearchTitle => translate('onboarding_search_title');
  String get onboardingSearchHighlight =>
      translate('onboarding_search_highlight');
  String get onboardingSearchText => translate('onboarding_search_text');
  String get onboardingSearchPill => translate('onboarding_search_pill');
  String get onboardingSearchPillLead =>
      translate('onboarding_search_pill_lead');
  String get onboardingSeatsTitle => translate('onboarding_seats_title');
  String get onboardingSeatsHighlight =>
      translate('onboarding_seats_highlight');
  String get onboardingSeatsText => translate('onboarding_seats_text');
  String get onboardingSeatsPill => translate('onboarding_seats_pill');
  String get onboardingSeatsPillLead => translate('onboarding_seats_pill_lead');
  String get onboardingTicketTitle => translate('onboarding_ticket_title');
  String get onboardingTicketHighlight =>
      translate('onboarding_ticket_highlight');
  String get onboardingTicketText => translate('onboarding_ticket_text');
  String get onboardingTicketPill => translate('onboarding_ticket_pill');
  String get onboardingTicketPillLead =>
      translate('onboarding_ticket_pill_lead');
  String get homeDateTitle => translate('home_date_title');

  /// Titre du calendrier quand on choisit le retour : le voyageur ne
  /// part plus, il rentre.
  String get homeReturnDateTitle => translate('home_return_date_title');
  String homeDateNotice(int months) =>
      _withArgs('home_date_notice', {'months': months});
  String homeDateConfirm(String date) =>
      _withArgs('home_date_confirm', {'date': date});
  String get homePassengersEyebrow => translate('home_passengers_eyebrow');
  String get homePassengersTitle => translate('home_passengers_title');
  String get homePassengersLabel => translate('home_passengers_label');
  String get homePassengersNotice => translate('home_passengers_notice');
  String homePassengersRange(int max, int min) =>
      _withArgs('home_passengers_range', {'max': max, 'min': min});
  String homePassengersGroup(int min, int max) =>
      _withArgs('home_passengers_group', {'min': min, 'max': max});
  String homePassengersGroupApplies(int min) =>
      _withArgs('home_passengers_group_applies', {'min': min});
  String get homePassengersBusinessPayer =>
      translate('home_passengers_business_payer');
  String get homePassengersBusiness => translate('home_passengers_business');
  String homePassengersConfirm(int count) => _withArgs(
    count > 1 ? 'home_passengers_confirm_plural' : 'home_passengers_confirm',
    {'count': count},
  );
  String get priceAssuranceTitle => translate('price_assurance_title');
  String get priceAssuranceText => translate('price_assurance_text');
  String get seatsTitle => translate('seats_title');

  /// Le titre du plan des sièges sur un aller-retour : il dit quel trajet
  /// on pourvoit, et pour combien de voyageurs.
  String seatsTitleFor({required bool isOutbound, required int passengers}) {
    final many = passengers > 1;

    return translate(
      isOutbound
          ? (many ? 'seats_title_outbound_many' : 'seats_title_outbound_one')
          : (many ? 'seats_title_inbound_many' : 'seats_title_inbound_one'),
    );
  }

  String get seatsStep => translate('seats_step');

  String get seatsLegOutbound => translate('seats_leg_outbound');

  String get seatsLegInbound => translate('seats_leg_inbound');
  String get seatsSelectedOne => translate('seats_selected_one');
  String get seatsSelectedMany => translate('seats_selected_many');
  String seatsNone(int total) => _withArgs('seats_none', {'total': total});
  String seatsChosen(String seats, int count, int total) => _withArgs(
    'seats_chosen',
    {'seats': seats, 'count': count, 'total': total},
  );
  String get seatsTotal => translate('seats_total');
  String get seatsContinue => translate('seats_continue');
  String get seatsErrorTitle => translate('seats_error_title');
  String get seatsErrorPrimary => translate('seats_error_primary');
  String get seatsMissingEyebrow => translate('seats_missing_eyebrow');
  String get seatsMissingTitle => translate('seats_missing_title');
  String get seatsMissingText => translate('seats_missing_text');
  String get seatsMissingPrimary => translate('seats_missing_primary');
  String get passengersTitle => translate('passengers_title');
  String get passengersTitleInternational =>
      translate('passengers_title_international');
  String get passengersEyebrowInternational =>
      translate('passengers_eyebrow_international');
  String get passengersStep => translate('passengers_step');
  String get passengersNotice => translate('passengers_notice');
  String get passengersNoticeInternational =>
      translate('passengers_notice_international');
  String get passengersSubmit => translate('passengers_submit');
  String passengersBlock(int index) =>
      _withArgs('passengers_block', {'index': index});
  String get passengersFirstName => translate('passengers_first_name');
  String get passengersFirstNameRequired =>
      translate('passengers_first_name_required');
  String get passengersLastName => translate('passengers_last_name');
  String get passengersLastNameRequired =>
      translate('passengers_last_name_required');
  String get passengersPhone => translate('passengers_phone');
  String get passengersPhoneRequired => translate('passengers_phone_required');
  String passengersPhonePrefix(String country, String prefix) => _withArgs(
    'passengers_phone_prefix',
    {'country': country, 'prefix': prefix},
  );
  String passengersPhoneLength(int digits) =>
      _withArgs('passengers_phone_length', {'digits': digits});
  String get passengersEmail => translate('passengers_email');
  String get passengersEmailInvalid => translate('passengers_email_invalid');
  String passengersIncompleteDetail(String details) =>
      _withArgs('passengers_incomplete_detail', {'details': details});
  String passengersIncompletePassenger(int index, String fields) => _withArgs(
    'passengers_incomplete_passenger',
    {'index': index, 'fields': fields},
  );
  String get passengersDocumentTypeOptional =>
      translate('passengers_document_type_optional');
  String get passengersDocumentNumberOptional =>
      translate('passengers_document_number_optional');

  String get passengersBirthDateOptional =>
      translate('passengers_birth_date_optional');
  String get passengersDocumentNone => translate('passengers_document_none');
  String get passengersYellowFever => translate('passengers_yellow_fever');
  String get passengersAccuracy => translate('passengers_accuracy');
  String get passengersAccuracyRequired =>
      translate('passengers_accuracy_required');
  String get passengersDocumentType => translate('passengers_document_type');
  String get passengersDocumentNumber =>
      translate('passengers_document_number');
  String get passengersDocumentNumberRequired =>
      translate('passengers_document_number_required');
  String get passengersNationality => translate('passengers_nationality');
  String get passengersBirthDate => translate('passengers_birth_date');
  String get passengersBirthDateChoose =>
      translate('passengers_birth_date_choose');
  String passengersSeat(String seat) =>
      _withArgs('passengers_seat', {'seat': seat});
  String passengersLine(String code) =>
      _withArgs('passengers_line', {'code': code});
  String get passengersDocPassport => translate('passengers_doc_passport');
  String get passengersDocNationalId => translate('passengers_doc_national_id');
  String get passengersDocBiometric => translate('passengers_doc_biometric');
  String get passengersDocConsular => translate('passengers_doc_consular');
  String get summaryEyebrow => translate('summary_eyebrow');
  String get summaryTitle => translate('summary_title');
  String get summaryPassengers => translate('summary_passengers');
  String get summaryEdit => translate('summary_edit');
  String get summaryPriceDetail => translate('summary_price_detail');
  String groupBookingEyebrow(int min, int max) =>
      _withArgs('group_booking_eyebrow', {'min': min, 'max': max});
  String get groupBookingTitle => translate('group_booking_title');
  String get groupPayerTitle => translate('group_payer_title');
  String get groupPayerHint => translate('group_payer_hint');
  String get groupPayerPersonal => translate('group_payer_personal');
  String groupPayerCredit(String amount) =>
      _withArgs('group_payer_credit', {'amount': amount});
  String get groupBookingRowPassengers =>
      translate('group_booking_row_passengers');
  String get groupBookingRowSubtotal => translate('group_booking_row_subtotal');
  String get groupBookingRowTotal => translate('group_booking_row_total');
  String get groupBookingHeroTitle => translate('group_booking_hero_title');
  String groupBookingHeroText(int max) =>
      _withArgs('group_booking_hero_text', {'max': max});
  String get groupBookingCountLabel => translate('group_booking_count_label');
  String groupBookingCountHint(int min, int max) =>
      _withArgs('group_booking_count_hint', {'min': min, 'max': max});
  String get groupBookingDiscountNotice =>
      translate('group_booking_discount_notice');
  String groupBookingConfirm(int count) =>
      _withArgs('group_booking_confirm', {'count': count});
  String groupTicketsEyebrow(int count) =>
      _withArgs('group_tickets_eyebrow', {'count': count});
  String get groupTicketsTitle => translate('group_tickets_title');
  String get groupTicketsNotice => translate('group_tickets_notice');
  String get groupTicketsPending => translate('group_tickets_pending');
  String get groupTicketsDone => translate('group_tickets_done');

  String get groupTicketsQrClose => translate('group_tickets_qr_close');
  String get groupTicketsStatusReady => translate('group_tickets_status_ready');
  String get groupTicketsStatusBoarded =>
      translate('group_tickets_status_boarded');
  String get groupTicketsStatusCancelled =>
      translate('group_tickets_status_cancelled');
  String get groupTicketsStatusUnknown =>
      translate('group_tickets_status_unknown');

  /// Les mêmes écrans servent un voyageur seul : parler de « groupe » et
  /// de « chaque QR » devant un unique billet sonnerait faux.
  String get ticketsOneEyebrow => translate('tickets_one_eyebrow');

  String get ticketsOneTitle => translate('tickets_one_title');

  String get ticketsOneNotice => translate('tickets_one_notice');

  String get ticketsShare => translate('tickets_share');

  String get ticketsDownloadDone => translate('tickets_download_done');

  String get ticketsPdfDownload => translate('tickets_pdf_download');

  String get ticketsPdfDownloading => translate('tickets_pdf_downloading');

  String get ticketsPdfRedownload => translate('tickets_pdf_redownload');

  String get ticketsPdfReady => translate('tickets_pdf_ready');

  String get ticketsPdfOffline => translate('tickets_pdf_offline');

  String get ticketsPdfOpen => translate('tickets_pdf_open');

  String get ticketsPdfSaveCopy => translate('tickets_pdf_save_copy');

  String get ticketsPdfOpenFailed => translate('tickets_pdf_open_failed');

  String get ticketsPdfErrorNetwork => translate('tickets_pdf_error_network');

  String get ticketsPdfErrorSession => translate('tickets_pdf_error_session');

  String get ticketsPdfErrorInaccessible =>
      translate('tickets_pdf_error_inaccessible');

  String get ticketsPdfErrorUnavailable =>
      translate('tickets_pdf_error_unavailable');

  String get ticketsPdfErrorNotDownloadable =>
      translate('tickets_pdf_error_not_downloadable');

  String get ticketsPdfErrorUnexpected =>
      translate('tickets_pdf_error_unexpected');

  String get ticketsOnePending => translate('tickets_one_pending');

  // --- Choix du retour (aller-retour) ---

  /// « Retour » seul, quand aucune date n'a été demandée : le serveur
  /// propose alors les jours qui suivent, et le surtitre n'a pas de date
  /// à annoncer.
  String get returnResultsEyebrowUndated =>
      translate('return_results_eyebrow_undated');

  String returnResultsEyebrow(String date) =>
      _withArgs('return_results_eyebrow', {'date': date});

  /// « 2 retours disponibles » : le pluriel se joue sur la clé.
  String returnResultsTitle(int count) => count > 1
      ? _withArgs('return_results_title_many', {'count': count})
      : translate('return_results_title_one');

  String get returnResultsOutbound => translate('return_results_outbound');

  /// Le titre de l'en-tête. Distinct de celui de l'état vide juste en
  /// dessous : les répéter mot pour mot donnait deux fois la même phrase
  /// à l'écran.
  String get returnResultsEmptyHeading =>
      translate('return_results_empty_heading');

  String get returnResultsEmptyTitle => translate('return_results_empty_title');

  String get returnResultsEmptyText => translate('return_results_empty_text');

  String get returnResultsEmptyCta => translate('return_results_empty_cta');
  String get groupDiscountEyebrow => translate('group_discount_eyebrow');
  String get groupDiscountPendingEyebrow =>
      translate('group_discount_pending_eyebrow');
  String get groupDiscountPendingTitle =>
      translate('group_discount_pending_title');
  String get groupDiscountPendingCaption =>
      translate('group_discount_pending_caption');
  String groupDiscountPendingFooter(int missing) =>
      _withArgs('group_discount_pending_footer', {'missing': missing});
  String get groupDiscountPendingUnknown =>
      translate('group_discount_pending_unknown');
  String get groupDiscountTitle => translate('group_discount_title');
  String get groupDiscountSaved => translate('group_discount_saved');
  String get groupDiscountAutomatic => translate('group_discount_automatic');
  String groupDiscountAutomaticPercent(String percent) =>
      _withArgs('group_discount_automatic_percent', {'percent': percent});
  String groupDiscountStepSeats(int count) =>
      _withArgs('group_discount_step_seats', {'count': count});
  String groupDiscountStepPassengers(int count) =>
      _withArgs('group_discount_step_passengers', {'count': count});
  String get groupDiscountStepPayment =>
      translate('group_discount_step_payment');
  String get groupDiscountChooseSeats =>
      translate('group_discount_choose_seats');
  String get summaryUnitPrice => translate('summary_unit_price');
  String get summaryGroupDiscount => translate('summary_group_discount');
  String summaryGroupDiscountFrom(int min) =>
      _withArgs('summary_group_discount_from', {'min': min});
  String get summaryPromoTitle => translate('summary_promo_title');
  String get summaryPromoHint => translate('summary_promo_hint');
  String get summaryPromoApply => translate('summary_promo_apply');

  String get summaryPromoRemove => translate('summary_promo_remove');
  String get summaryPromoChecking => translate('summary_promo_checking');
  String summaryPromoApplied(String code) =>
      _withArgs('summary_promo_applied', {'code': code});
  String summaryPromoKept(String code) =>
      _withArgs('summary_promo_kept', {'code': code});
  String get summaryAssistCta => translate('summary_assist_cta');
  String get summaryAssistHint => translate('summary_assist_hint');
  String get assistEyebrow => translate('assist_eyebrow');
  String get assistReferenceCopied => translate('assist_reference_copied');
  String get assistStatusPendingText => translate('assist_status_pending_text');
  String get assistStatusInProgressText =>
      translate('assist_status_in_progress_text');
  String get assistStatusFinalizedText =>
      translate('assist_status_finalized_text');
  String get assistStatusRefusedText => translate('assist_status_refused_text');
  String get assistStatusUnavailableText =>
      translate('assist_status_unavailable_text');
  String get assistStatusCancelledText =>
      translate('assist_status_cancelled_text');
  String get assistStatusUnknownText => translate('assist_status_unknown_text');
  String get assistCopyMessage => translate('assist_copy_message');
  String get assistStatusResumedText => translate('assist_status_resumed_text');
  String get assistCopyMessageSub => translate('assist_copy_message_sub');
  String get assistCopyReference => translate('assist_copy_reference');
  String get assistTitle => translate('assist_title');
  String get assistIntro => translate('assist_intro');
  String get assistWarning => translate('assist_warning');
  String assistContact(String phone) =>
      _withArgs('assist_contact', {'phone': phone});
  String get assistNoPhone => translate('assist_no_phone');
  String get assistChannelTitle => translate('assist_channel_title');

  String get assistChannelWhatsapp => translate('assist_channel_whatsapp');

  String get assistChannelWhatsappSub =>
      translate('assist_channel_whatsapp_sub');

  String get assistChannelCall => translate('assist_channel_call');

  String get assistChannelCallSub => translate('assist_channel_call_sub');

  String get assistAgencyPending => translate('assist_agency_pending');

  String get assistSendWhatsapp => translate('assist_send_whatsapp');
  String get assistSendCall => translate('assist_send_call');
  String get assistSending => translate('assist_sending');
  String get assistStatusTitle => translate('assist_status_title');
  String assistDeparture(String date) =>
      _withArgs('assist_departure', {'date': date});
  String get assistOpenWhatsapp => translate('assist_open_whatsapp');
  String get assistCall => translate('assist_call');
  String get assistMessageCopied => translate('assist_message_copied');
  String get assistChannelFailed => translate('assist_channel_failed');
  String get assistResume => translate('assist_resume');
  String get assistResuming => translate('assist_resuming');
  String get assistViewTrips => translate('assist_view_trips');
  String get assistNewBooking => translate('assist_new_booking');
  String get assistHome => translate('assist_home');
  String get assistRefreshError => translate('assist_refresh_error');
  String get assistRequestsTitle => translate('assist_requests_title');
  String get assistChipPending => translate('assist_chip_pending');
  String get assistChipInProgress => translate('assist_chip_in_progress');
  String get assistChipFinalized => translate('assist_chip_finalized');
  String get assistChipRefused => translate('assist_chip_refused');
  String get assistChipExpired => translate('assist_chip_expired');
  String get assistChipUnavailable => translate('assist_chip_unavailable');
  String get assistChipCancelled => translate('assist_chip_cancelled');
  String get assistChipUnknown => translate('assist_chip_unknown');
  String get assistChipResumed => translate('assist_chip_resumed');
  String get assistOpenWhatsappSub => translate('assist_open_whatsapp_sub');
  String get assistCallSub => translate('assist_call_sub');
  String get assistResumeSub => translate('assist_resume_sub');
  String get assistViewTripsSub => translate('assist_view_trips_sub');
  String get assistNewBookingSub => translate('assist_new_booking_sub');
  String summaryPromoLine(String code) =>
      _withArgs('summary_promo_line', {'code': code});
  String get summaryPromoContinue => translate('summary_promo_continue');
  String get summaryServiceFee => translate('summary_service_fee');
  String get summaryServiceFeeValue => translate('summary_service_fee_value');
  // --- Aller-retour ---

  String get summaryOutbound => translate('summary_outbound');

  String get summaryReturn => translate('summary_return');

  /// Les deux sièges d’un voyageur : « 5A · 2C », l’aller puis le retour.
  String summarySeatsRound(String outbound, String inbound) => _withArgs(
    'summary_seats_round',
    {'outbound': outbound, 'inbound': inbound},
  );

  String get summaryTotal => translate('summary_total');
  String get summaryPayQuestion => translate('summary_pay_question');

  String get summaryContinue => translate('summary_continue');
  String summarySeatOne(String seats) =>
      _withArgs('summary_seat_one', {'seats': seats});
  String summarySeatMany(String seats) =>
      _withArgs('summary_seat_many', {'seats': seats});
  String summaryStation(String station) =>
      _withArgs('summary_station', {'station': station});
  String get summaryMissingTitle => translate('summary_missing_title');
  String get summaryMissingText => translate('summary_missing_text');
  String get summaryMissingPrimary => translate('summary_missing_primary');
  String get summaryMissingEyebrow => translate('summary_missing_eyebrow');
  String get passengersMissingTitle => translate('passengers_missing_title');
  String get passengersMissingText => translate('passengers_missing_text');
  String get passengersMissingPrimary =>
      translate('passengers_missing_primary');
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
  String departuresConnections(int count, String cities) => _withArgs(
    count > 1 ? 'departures_connections_plural' : 'departures_connections',
    {'count': count, 'cities': cities},
  );
  String get departuresDirect => translate('departures_direct');
  String get ticketsRoundTrip => translate('tickets_round_trip');
  String get ticketDetailLegs => translate('ticket_detail_legs');
  String ticketDetailLegSeat(String seat) =>
      _withArgs('ticket_detail_leg_seat', {'seat': seat});
  String departuresConnectionsTag(int count) => _withArgs(
    count > 1
        ? 'departures_connections_tag_plural'
        : 'departures_connections_tag',
    {'count': count},
  );
  String departuresConnectionNote(String cities, String wait) =>
      _withArgs('departures_connection_note', {'cities': cities, 'wait': wait});
  String departuresConnectionNotePlural(String cities) =>
      _withArgs('departures_connection_note_plural', {'cities': cities});
  String departuresSegments(int count) =>
      _withArgs('departures_segments', {'count': count});
  String departuresConnectionWait(String duration, String city) => _withArgs(
    'departures_connection_wait',
    {'duration': duration, 'city': city},
  );
  String departuresLegOrder(int order, int total) =>
      _withArgs('departures_leg_order', {'order': order, 'total': total});
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

  String departureDetailStopDeparture(String time) =>
      _withArgs('departure_detail_stop_departure', {'time': time});

  String departureDetailStopArrival(String time) =>
      _withArgs('departure_detail_stop_arrival', {'time': time});

  String departureDetailStopDistance(int km) =>
      _withArgs('departure_detail_stop_distance', {'km': '$km'});

  /// « Arrivée 09:12 » : l'heure de passage à une étape, telle que
  /// l'arrêt la vit.
  String departureDetailStopEta(String time) =>
      _withArgs('departure_detail_stop_eta', {'time': time});

  /// « Départ 09:20 » : l'heure à laquelle le bus repart de l'étape.
  String departureDetailStopEtd(String time) =>
      _withArgs('departure_detail_stop_etd', {'time': time});

  /// « 128 km », pour la ligne de détail d'une étape — la forme longue
  /// « à 128 km du départ » y voisinerait deux heures et déborderait.
  String departureDetailStopKm(int km) =>
      _withArgs('departure_detail_stop_km', {'km': '$km'});
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
  String get homeNextTrip => translate('home_next_trip');
  String get homeNextTripEmptyTitle => translate('home_next_trip_empty_title');
  String get homeNextTripEmptyText => translate('home_next_trip_empty_text');
  String get homeReminderTitle => translate('home_reminder_title');
  String get homeReminderText => translate('home_reminder_text');

  String get homeRoundTripSection => translate('home_roundtrip_section');

  String get homeRoundTripTitle => translate('home_roundtrip_title');

  String get homeRoundTripText => translate('home_roundtrip_text');
  String get homeErrorTitle => translate('home_error_title');
  String get homeErrorText => translate('home_error_text');

  // --- Centre de notifications (HOME-02) ---
  String get notificationsEyebrow => translate('notifications_eyebrow');
  String get notificationsTitle => translate('notifications_title');
  String get notificationsMarkAll => translate('notifications_mark_all');
  String get notificationsMarked => translate('notifications_marked');
  String get notificationsEmpty => translate('notifications_empty');
  String get notificationsEmptyLead => translate('notifications_empty_lead');
  String get notificationsSoon => translate('notifications_soon');
  String get notificationsErrorTitle => translate('notifications_error_title');
  String get notificationsErrorText => translate('notifications_error_text');
  String get notificationsMore => translate('notifications_more');
  String get ticketGoneTitle => translate('ticket_gone_title');
  String get ticketGoneText => translate('ticket_gone_text');
  String get ticketUnavailableTitle => translate('ticket_unavailable_title');
  String get ticketUnavailableText => translate('ticket_unavailable_text');
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
  String get profileEditPhoneHintFree =>
      translate('profile_edit_phone_hint_free');
  String get profileEditPhoneHelp => translate('profile_edit_phone_help');
  String profileEditPhoneInvalid(String country, int digits) => _withArgs(
    'profile_edit_phone_invalid',
    {'country': country, 'digits': digits},
  );
  String get profileEditEmailHint => translate('profile_edit_email_hint');
  String get profileEditEmailNone => translate('profile_edit_email_none');
  String get profileEditEmailLocked => translate('profile_edit_email_locked');
  String get profileEditPhoneAdd => translate('profile_edit_phone_add');
  String get profileEditEmail => translate('profile_edit_email');
  String get profileEditBirthDate => translate('profile_edit_birth_date');
  String get profileEditBirthDateHint =>
      translate('profile_edit_birth_date_hint');
  String get birthDatePromptTitle => translate('birth_date_prompt_title');
  String get birthDatePromptText => translate('birth_date_prompt_text');
  String get birthDatePromptAction => translate('birth_date_prompt_action');
  String get birthDatePromptLater => translate('birth_date_prompt_later');
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
  String get securityDeleteAccount => translate('security_delete_account');
  String get securityDeleteAccountSub =>
      translate('security_delete_account_sub');
  String get securityDevices => translate('security_devices');

  // --- Suppression du compte ---
  String get accountDeletionEyebrow => translate('account_deletion_eyebrow');
  String get accountDeletionTitle => translate('account_deletion_title');
  String get accountDeletionLead => translate('account_deletion_lead');
  String get accountDeletionEffectsTitle =>
      translate('account_deletion_effects_title');
  String get accountDeletionEffectsRemovedLabel =>
      translate('account_deletion_effects_removed_label');
  String get accountDeletionEffectsRemoved =>
      translate('account_deletion_effects_removed');
  String get accountDeletionEffectsKeptLabel =>
      translate('account_deletion_effects_kept_label');
  String get accountDeletionEffectsKept =>
      translate('account_deletion_effects_kept');
  String get accountDeletionEffectsPendingLabel =>
      translate('account_deletion_effects_pending_label');
  String get accountDeletionEffectsPending =>
      translate('account_deletion_effects_pending');
  String get accountDeletionConfirmTitle =>
      translate('account_deletion_confirm_title');
  String get accountDeletionConfirmText =>
      translate('account_deletion_confirm_text');
  String get accountDeletionConfirmCta =>
      translate('account_deletion_confirm_cta');
  String get accountDeletionReason => translate('account_deletion_reason');
  String get accountDeletionReasonHint =>
      translate('account_deletion_reason_hint');
  String get accountDeletionPinTitle => translate('account_deletion_pin_title');
  String get accountDeletionPinLead => translate('account_deletion_pin_lead');
  String get accountDeletionSubmit => translate('account_deletion_submit');
  String get accountDeletionDone => translate('account_deletion_done');
  String get securityDeviceCurrent => translate('security_device_current');
  String get securityDeviceActive => translate('security_device_active');
  String get securityDeviceRevoke => translate('security_device_revoke');
  String get securityDeviceRevokeConfirmTitle =>
      translate('security_device_revoke_confirm_title');
  String securityDeviceRevokeConfirmText(String device) =>
      _withArgs('security_device_revoke_confirm_text', {'device': device});
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
  String get preferencesSms => translate('preferences_sms');
  String get preferencesSmsSub => translate('preferences_sms_sub');
  String get preferencesEmail => translate('preferences_email');
  String get preferencesEmailSub => translate('preferences_email_sub');
  String get preferencesDisplay => translate('preferences_display');
  String get preferencesLanguage => translate('preferences_language');
  String get preferencesDark => translate('preferences_dark');
  String get preferencesDarkOn => translate('preferences_dark_on');
  String get preferencesDarkOff => translate('preferences_dark_off');
  String get preferencesError => translate('preferences_error');

  // --- Connexion sociale (Google, Facebook, TikTok) ---

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

  String get loginNewDeviceTitle => translate('login_new_device_title');

  String get loginNewDeviceHint => translate('login_new_device_hint');

  String get loginResetTitle => translate('login_reset_title');

  String get loginResetHint => translate('login_reset_hint');

  String get loginEmailContinue => translate('login_email_continue');

  String get loginEmailBack => translate('login_email_back');
  String get loginEmailFirst => translate('login_email_first');
  String get loginPhoneFirst => translate('login_phone_first');
  String get loginSwitchToPhone => translate('login_switch_to_phone');
  String get loginSwitchToEmail => translate('login_switch_to_email');
  String get loginNewDevicePhoneHint =>
      translate('login_new_device_phone_hint');
  String get loginResetPhoneHint => translate('login_reset_phone_hint');
  String get loginPhoneIncomplete => translate('login_phone_incomplete');
  String loginPhonePrefix(String country, String prefix) =>
      _withArgs('login_phone_prefix', {'country': country, 'prefix': prefix});

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
