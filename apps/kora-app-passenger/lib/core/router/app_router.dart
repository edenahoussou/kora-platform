import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/bloc/auth_bloc.dart';
import '../../features/auth/presentation/bloc/auth_flow_bloc.dart';
import '../../features/auth/presentation/pages/create_pin_page.dart';
import '../../features/auth/presentation/pages/login_page.dart';
import '../../features/auth/presentation/pages/new_device_page.dart';
import '../../features/auth/presentation/pages/otp_page.dart';
import '../../features/auth/presentation/pages/permissions_page.dart';
import '../../features/auth/presentation/pages/signup_email_page.dart';
import '../../features/booking/domain/entities/search_criteria.dart';
import '../../features/booking/domain/usecases/get_tickets_usecase.dart';
import '../../features/booking/presentation/bloc/cancel_ticket_cubit.dart';
import '../../features/booking/presentation/bloc/group_payer_cubit.dart';
import '../../features/booking/presentation/bloc/group_quote_cubit.dart';
import '../../features/booking/presentation/bloc/group_tickets_cubit.dart';
import '../../features/booking/presentation/bloc/seat_hold_bloc.dart';
import '../../features/booking/presentation/pages/assistance_request_page.dart';
import '../../features/booking/presentation/pages/assistance_status_page.dart';
import '../../features/booking/presentation/pages/bus_search_page.dart';
import '../../features/booking/presentation/pages/departure_detail_page.dart';
import '../../features/booking/presentation/pages/departures_page.dart';
import '../../features/booking/presentation/pages/group_booking_page.dart';
import '../../features/booking/presentation/pages/group_discount_page.dart';
import '../../features/booking/presentation/pages/group_tickets_page.dart';
import '../../features/booking/presentation/pages/hold_expired_page.dart';
import '../../features/booking/presentation/pages/passenger_form_page.dart';
import '../../features/booking/presentation/pages/return_departures_page.dart';
import '../../features/booking/presentation/pages/seats_page.dart';
import '../../features/booking/presentation/pages/summary_page.dart';
import '../../features/booking/presentation/pages/ticket_by_reservation_page.dart';
import '../../features/booking/presentation/pages/ticket_detail_page.dart';
import '../../features/booking/presentation/pages/ticket_page.dart';
import '../../features/booking/presentation/pages/tickets_page.dart';
import '../../features/booking/presentation/widgets/seat_hold_watcher.dart';
import '../../features/colis/presentation/bloc/colis_draft_cubit.dart';
import '../../features/colis/presentation/pages/colis_checkout_args.dart';
import '../../features/colis/presentation/pages/colis_contacts_page.dart';
import '../../features/colis/presentation/pages/colis_content_page.dart';
import '../../features/colis/presentation/pages/colis_gateway_page.dart';
import '../../features/colis/presentation/pages/colis_incident_page.dart';
import '../../features/colis/presentation/pages/colis_momo_page.dart';
import '../../features/colis/presentation/pages/colis_page.dart';
import '../../features/colis/presentation/pages/colis_payment_page.dart';
import '../../features/colis/presentation/pages/colis_pending_page.dart';
import '../../features/colis/presentation/pages/colis_pickup_page.dart';
import '../../features/colis/presentation/pages/colis_preregistered_page.dart';
import '../../features/colis/presentation/pages/colis_quote_page.dart';
import '../../features/colis/presentation/pages/colis_receipt_page.dart';
import '../../features/colis/presentation/pages/colis_route_page.dart';
import '../../features/colis/presentation/pages/colis_shipments_page.dart';
import '../../features/colis/presentation/pages/colis_summary_page.dart';
import '../../features/colis/presentation/pages/colis_tracking_page.dart';
import '../../features/home/presentation/pages/city_picker_page.dart';
import '../../features/home/presentation/pages/date_picker_page.dart';
import '../../features/home/presentation/pages/home_page.dart';
import '../../features/home/presentation/pages/passengers_picker_page.dart';
import '../../features/notifications/presentation/pages/notifications_page.dart';
import '../../features/payment/domain/entities/payment_catalog.dart';
import '../../features/payment/presentation/bloc/payment_catalog_bloc.dart';
import '../../features/payment/presentation/bloc/payment_flow_bloc.dart';
import '../../features/payment/presentation/pages/booking_success_page.dart';
import '../../features/payment/presentation/pages/gateway_checkout_page.dart';
import '../../features/payment/presentation/pages/mobile_money_page.dart';
import '../../features/payment/presentation/pages/payment_args.dart';
import '../../features/payment/presentation/pages/payment_failed_page.dart';
import '../../features/payment/presentation/pages/payment_flow_args.dart';
import '../../features/payment/presentation/pages/payment_method_page.dart';
import '../../features/payment/presentation/pages/payment_pending_page.dart';
import '../../features/payment/presentation/pages/wallet_payment_page.dart';
import '../../features/profil/presentation/pages/account_deletion_page.dart';
import '../../features/profil/presentation/pages/birth_date_picker_page.dart';
import '../../features/profil/presentation/pages/change_pin_page.dart';
import '../../features/profil/presentation/pages/preferences_page.dart';
import '../../features/profil/presentation/pages/profil_page.dart';
import '../../features/profil/presentation/pages/profile_edit_page.dart';
import '../../features/profil/presentation/pages/security_page.dart';
import '../../features/support/domain/entities/support_chat_configuration.dart';
import '../../features/support/presentation/pages/contact_page.dart';
import '../../features/support/presentation/pages/help_page.dart';
import '../../features/support/presentation/pages/support_chat_page.dart';
import '../../features/support/presentation/widgets/support_chat_fab.dart';
import '../../features/wallet/presentation/pages/wallet_bonuses_page.dart';
import '../../features/wallet/presentation/pages/wallet_history_page.dart';
import '../../features/wallet/presentation/pages/wallet_page.dart';
import '../../features/wallet/presentation/pages/wallet_services_page.dart';
import '../../features/wallet/presentation/pages/wallet_top_up_method_page.dart';
import '../../features/wallet/presentation/pages/wallet_top_up_page.dart';
import '../../features/welcome/data/onboarding_store.dart';
import '../../features/welcome/presentation/pages/onboarding_page.dart';
import '../../features/welcome/presentation/pages/splash_page.dart';
import '../../features/welcome/presentation/pages/welcome_page.dart';
import '../di/injector.dart';
import '../l10n/app_localization.dart';
import 'auth_flow_navigator.dart';

/// Couche CORE : configuration centrale de la navigation avec `go_router`.
///
/// On utilise un [StatefulShellRoute.indexedStack] : c'est le pattern
/// recommandé par `go_router` pour une app avec une bottom nav bar. Chaque
/// onglet (Accueil / Voyages / Colis / Wallet / Profil) garde son propre
/// état de navigation même quand on change d'onglet (contrairement à un
/// simple `IndexedStack` géré à la main).
///
/// Pour naviguer vers un onglet depuis n'importe où dans l'app :
/// ```dart
/// context.go('/voyages');
/// ```
///
/// Pour ajouter un écran de détail au-dessus d'un onglet (ex: le détail
/// d'un trajet, accessible depuis Voyages) :
/// ```dart
/// GoRoute(
///   path: 'trip/:tripId',
///   builder: (context, state) => TripDetailPage(tripId: state.pathParameters['tripId']!),
/// ),
/// ```
/// à ajouter dans la liste `routes:` de la branche `/voyages` ci-dessous.
class AppRouter {
  const AppRouter._();

  /// Chemin de l'écran de connexion, accessible sans session (voir
  /// `_publicPaths` et `_redirect` ci-dessous).
  static const String loginPath = '/login';

  /// Écran de lancement : affiché le temps que `AuthBloc` connaisse la
  /// session, puis `_redirect` enchaîne sur l'onboarding à la première
  /// ouverture, sinon sur la bienvenue.
  static const String splashPath = '/splash';

  /// Les trois temps montrés à la première ouverture seulement.
  static const String onboardingPath = '/onboarding';

  static const String welcomePath = '/welcome';

  /// Première étape de la création de compte : la saisie de l'adresse
  /// e-mail, qui recevra le code de vérification.
  static const String signupPath = '/signup';

  /// Seconde étape : la vérification du code reçu par e-mail. L'adresse
  /// visée est transmise en `extra`.
  static const String otpPath = '/otp';

  /// Troisième étape : le choix du code PIN qui protégera le compte.
  static const String createPinPath = '/create-pin';
  static const String newDevicePath = '/new-device';
  static const String profileEditPath = '/profile/informations';
  static const String birthDatePath = '/profile/informations/naissance';
  static const String securityPath = '/profile/securite';
  static const String changePinPath = '/profile/securite/pin';
  static const String accountDeletionPath = '/profile/securite/suppression';
  static const String preferencesPath = '/profile/preferences';
  static const String notificationsPath = '/notifications';

  /// L'historique complet du Wallet (MEZ-160, point 3).
  static const String walletHistoryPath = '/wallet/historique';

  /// L'onglet Wallet.
  static const String walletPath = '/wallet';

  /// La recharge du Wallet (MEZ-160, point 2).
  static const String walletTopUpPath = '/wallet/recharge';

  /// Le choix du moyen de recharge.
  static const String walletTopUpMethodPath = '/wallet/recharge/moyen';

  /// Où dépenser son Wallet (MEZ-160).
  static const String walletServicesPath = '/wallet/utiliser';

  /// Les règles du Wallet.
  static const String walletRulesPath = '/wallet/regles';

  /// Les bonus fidélité.
  static const String walletBonusesPath = '/wallet/bonus';
  static const String helpPath = '/aide';
  static const String contactPath = '/aide/contact';

  /// La conversation avec un agent. La configuration tawk.to voyage
  /// dans `extra` : c'est l'écran de contact qui vient de l'obtenir du
  /// serveur, inutile de la redemander.
  static const String supportChatPath = '/aide/chat';
  static const String permissionsPath = '/permissions';

  /// Liste des départs, puis détail d'un départ.
  ///
  /// Déclarés **hors** de la bottom nav, comme les étapes du formulaire :
  /// une fois la recherche lancée, l'utilisateur est dans un tunnel de
  /// réservation, pas en train de naviguer entre les rubriques de l'app.
  static const String departuresPath = '/departures';

  /// Le choix du retour, deuxième temps d'un aller-retour. Une route à
  /// part : le voyageur doit pouvoir y revenir depuis le détail sans
  /// perdre son aller.
  static const String returnDeparturesPath = '/departures/return';

  static const String departureDetailPath = '/departure';
  static const String seatsPath = '/seats';
  static const String passengerInfoPath = '/passenger-info';
  static const String summaryPath = '/summary';

  /// Le délai est écoulé, les sièges sont retournés à la vente.
  static const String holdExpiredPath = '/hold-expired';

  /// La réservation confiée à une agence (MEZ-81) : le choix de l'agence,
  /// puis le suivi de la demande. Dans le tunnel : l'envoi bloque les
  /// sièges comme le paiement, et le suivi peut y revenir.
  static const String assistanceRequestPath = '/assistance/demande';
  static const String assistanceStatusPath = '/assistance/suivi';

  /// Le parcours d'envoi d'un colis — écrans 02 à 06 de la maquette.
  ///
  /// Un seul brouillon les traverse : le voyageur doit pouvoir revenir
  /// corriger une étape sans perdre les suivantes.
  static const String colisRoutePath = '/colis/envoi/itineraire';
  static const String colisContentPath = '/colis/envoi/contenu';
  static const String colisContactsPath = '/colis/envoi/contacts';
  static const String colisSummaryPath = '/colis/envoi/recapitulatif';
  static const String colisPreregisteredPath = '/colis/envoi/enregistre';
  static const String colisQuotePath = '/colis/envoi/devis';
  static const String colisPaymentPath = '/colis/envoi/paiement';
  static const String colisReceiptPath = '/colis/envoi/recu';

  /// Les trois écrans qui encaissent une expédition, un par
  /// passerelle — comme pour un billet. FedaPay pousse la demande
  /// sur le téléphone et réclame donc l'opérateur et le numéro ;
  /// KKiaPay et la carte se règlent dans la feuille du
  /// prestataire.
  static const String colisMomoPath = '/colis/envoi/paiement/mobile-money';
  static const String colisGatewayPath = '/colis/envoi/paiement/guichet';
  static const String colisCardPath = '/colis/envoi/paiement/carte';
  static const String colisPendingPath = '/colis/envoi/paiement/attente';

  /// Le suivi après paiement : la liste, le détail d'un envoi, et le
  /// retrait au guichet d'arrivée.
  static const String colisShipmentsPath = '/colis/envois';
  static const String colisTrackingPath = '/colis/envois/suivi';
  static const String colisPickupPath = '/colis/envois/retrait';

  /// Le signalement d'un incident sur un envoi suivi.
  ///
  /// Rattache aux envois, pas au parcours de creation : on signale
  /// ce qui roule deja, jamais ce qu'on est en train de composer.
  static const String colisIncidentPath = '/colis/envois/incident';

  /// Les écrans de paiement, dans le tunnel : le décompte doit y courir
  /// aussi.
  static const String paymentMethodPath = '/payment-method';
  static const String paymentMobileMoneyPath = '/payment/mobile-money';
  static const String paymentWalletPath = '/payment/wallet';
  static const String paymentCardPath = '/payment/card';

  /// Le Mobile Money encaissé dans la feuille de la passerelle.
  ///
  /// Distinct de [paymentMobileMoneyPath] parce que les deux passerelles
  /// ne demandent pas la même chose : FedaPay pousse la demande sur le
  /// téléphone et réclame donc l'opérateur et le numéro d'avance, là où
  /// KKiaPay les recueille elle-même dans sa fenêtre.
  static const String paymentGatewayMomoPath = '/payment/mobile-money/sheet';

  /// L'attente entre la demande et le verdict. Seul écran intermédiaire du
  /// paiement : on y arrive dès que la demande part, on en sort quand le
  /// serveur a tranché.
  static const String paymentPendingPath = '/payment/pending';

  /// Le paiement refusé. Un écran, et non un message : le refus arrête le
  /// parcours, et il faut dire ce qui reste possible.
  static const String paymentFailedPath = '/payment/failed';
  static const String paymentSuccessPath = '/payment/success';

  /// Formulaire de réservation en grand (`bus-search`), ouvert depuis
  /// « Réserver un voyage » de l'onglet Voyages.
  static const String busSearchPath = '/bus-search';

  /// Détail d'un billet. Le billet lui-même passe en `extra` : il vient
  /// de la liste déjà chargée, inutile de le redemander.
  /// L'onglet « Mes voyages ». Les notifications y mènent faute de mieux :
  /// billet émis, départ modifié, paiement confirmé s'y retrouvent tous.
  static const String tripsPath = '/voyages';

  static const String ticketDetailPath = '/ticket-detail';

  /// Le billet d'une réservation, désigné par son identifiant.
  ///
  /// La seule route que peut viser un lien profond : les autres attendent
  /// un objet déjà chargé, qu'une notification ne peut pas transporter.
  static const String ticketByReservationPath = '/voyages/:reservationUuid';

  /// Le chemin concret de [ticketByReservationPath] pour [reservationUuid].
  static String ticketByReservation(String reservationUuid) =>
      '/voyages/$reservationUuid';

  /// Le billet numérique, celui qu'on présente à l'embarquement.
  static const String ticketPath = '/billet';

  /// `multi-tickets` de la maquette (BUS-17) : un billet par voyageur du
  /// groupe.
  static const String groupTicketsPath = '/groupe/billets';

  /// Sélection de la ville de départ / d'arrivée.
  ///
  /// Déclarées **hors** de la bottom nav, contrairement à
  /// [departuresPath] : la maquette affiche ces écrans en plein
  /// (`noNav: true`), parce qu'ils sont une étape du formulaire de
  /// recherche et non une destination de navigation. On les ouvre avec
  /// `context.push`, qui renvoie la ville choisie :
  ///
  /// ```dart
  /// final city = await context.push<City>(AppRouter.originPath, extra: args);
  /// ```
  static const String originPath = '/origin';
  static const String destinationPath = '/destination';

  /// Choix de la date de départ et du nombre de voyageurs. Mêmes règles
  /// que [originPath] : écrans pleins, ouverts avec `push`, qui renvoient
  /// respectivement une `DateTime` et un `int`.
  static const String datePath = '/date';

  /// Le calendrier du retour. Même écran que [datePath], titre et plancher
  /// différents : une route à part garde les deux étapes distinctes dans
  /// la pile, donc chacune revient sur la bonne.
  static const String returnDatePath = '/return-date';
  static const String passengersPath = '/passengers';

  /// `group-booking` de la maquette (BUS-15) : le devis du groupe et le
  /// nombre exact de voyageurs, une fois le départ choisi.
  static const String groupBookingPath = '/groupe';

  /// `group-discount` (BUS-16) : la remise obtenue, une fois le départ
  /// choisi et le devis rendu.
  static const String groupDiscountPath = '/groupe/remise';

  /// Construit l'URL de la liste des départs à partir des critères saisis
  /// sur l'accueil.
  ///
  /// Les critères passent par la **query string** (et non par `extra`) pour
  /// que l'écran reste atteignable par lien profond et survive à un
  /// redémarrage à chaud. Les villes sont transmises par identifiant :
  /// c'est l'écran de destination qui les résout, comme il devra le faire
  /// avec l'API au Sprint 2.
  static String departuresUrlFor(SearchCriteria criteria) {
    final returnDate = criteria.returnDate;

    return Uri(
      path: departuresPath,
      queryParameters: <String, String>{
        'origin': criteria.origin.id,
        'destination': criteria.destination.id,
        // `toIso8601String()` embarque l'heure et le fuseau, inutiles ici :
        // seule la journée de départ compte.
        'date': _formatDay(criteria.date),
        'passengers': '${criteria.passengers}',
        // L'intention d'abord, la date ensuite : un aller-retour dont le
        // retour n'est pas encore daté reste un aller-retour, et l'oublier
        // ici le ramènerait à un aller simple entre le formulaire et les
        // résultats.
        if (criteria.isRoundTrip) 'trip_type': 'round_trip',
        if (returnDate != null) 'return_date': _formatDay(returnDate),
      },
    ).toString();
  }

  /// Construit l'écran de sélection de ville à partir des données passées
  /// en `extra`. Sans `extra` (lien profond, redémarrage à chaud), l'écran
  /// s'affiche vide plutôt que de planter : c'est un cul-de-sac visible,
  /// pas une exception.
  static Widget _cityPicker(GoRouterState state, {required CityField field}) {
    final args = state.extra as CityPickerArgs?;

    return CityPickerPage(
      field: field,
      cities: args?.cities ?? const [],
      recentCities: args?.recentCities ?? const [],
      selected: args?.selected,
      purpose: args?.purpose ?? CityPickerPurpose.trip,
    );
  }

  static String _formatDay(DateTime date) {
    final month = date.month.toString().padLeft(2, '0');
    final day = date.day.toString().padLeft(2, '0');
    return '${date.year}-$month-$day';
  }

  /// Guard de navigation global, réévalué par `go_router` à chaque
  /// changement de route.
  ///
  /// - Session ouverte et sur un écran d'authentification → `/home` : il
  ///   n'y a rien à y faire.
  /// - Pas de session et écran protégé → l'écran de bienvenue.
  /// - Tant que la session est **inconnue** (le stockage sécurisé n'a pas
  ///   encore répondu au démarrage) → aucune redirection : renvoyer
  ///   quelqu'un vers la connexion avant de savoir s'il l'était déjà
  ///   ferait clignoter l'app à chaque lancement.
  static String? _redirect(BuildContext context, GoRouterState state) {
    final auth = context.read<AuthBloc>().state;
    final location = state.matchedLocation;

    // Tant que le stockage sécurisé n'a pas répondu, le lancement garde
    // l'écran. C'est ce qui remplace le clignotement d'avant : l'app
    // s'ouvrait sur la bienvenue, puis sautait à l'accueil dès que la
    // session arrivait.
    if (auth.status == AuthStatus.unknown) {
      return location == splashPath ? null : splashPath;
    }

    final onPublicScreen = _publicPaths.contains(location);

    // Une session ouverte n'a rien à faire sur les écrans qui servent à
    // en ouvrir une. La vérification d'un appareil, elle, a du sens dans
    // les deux états : elle est aussi accessible depuis l'écran
    // Sécurité, où l'utilisateur est forcément connecté.
    if (auth.isAuthenticated) {
      return _entryPaths.contains(location) ? '/home' : null;
    }

    // Première ouverture : on explique l'app avant de demander un compte.
    // Le drapeau est local et ne dépend pas de la session — le revoir
    // après une déconnexion serait absurde, la marque n'a pas changé.
    if (!getIt<OnboardingStore>().hasSeen) {
      return location == onboardingPath ? null : onboardingPath;
    }

    // La session est connue et l'app présentée : ces deux écrans n'ont
    // plus rien à montrer. Sans cette règle le lancement gardait la main,
    // puisqu'il figure parmi les écrans publics.
    if (location == splashPath || location == onboardingPath) {
      return welcomePath;
    }

    return onPublicScreen ? null : welcomePath;
  }

  /// Écrans atteignables sans session.
  static const Set<String> _publicPaths = {
    splashPath,
    onboardingPath,
    welcomePath,
    loginPath,
    signupPath,
    otpPath,
    createPinPath,
    newDevicePath,
  };

  /// Le sous-ensemble qui n'a de sens que déconnecté : y arriver avec une
  /// session ouverte renvoie à l'accueil.
  static const Set<String> _entryPaths = {
    splashPath,
    onboardingPath,
    welcomePath,
    loginPath,
    signupPath,
    otpPath,
    createPinPath,
  };

  /// Construit à la première lecture, et non à l'initialisation de la
  /// classe : le routeur écoute [AuthBloc], qui n'existe qu'une fois le
  /// service locator prêt.
  static GoRouter? _instance;

  static GoRouter get router => _instance ??= _create();

  /// Reconstruit le routeur. Réservé aux tests : chacun repart d'un
  /// historique vierge et d'un [AuthBloc] vivant — sans ça, le second
  /// test hériterait de la position du premier et écouterait un bloc
  /// déjà fermé.
  @visibleForTesting
  static void resetForTesting() {
    _instance?.dispose();
    _instance = null;
  }

  static GoRouter _create() {
    return GoRouter(
      initialLocation: splashPath,
      redirect: _redirect,
      // Réévalue `redirect` dès que la session s'ouvre ou se ferme, sans
      // attendre une navigation : une déconnexion doit ramener sur la
      // bienvenue tout de suite, où que se trouve l'utilisateur.
      //
      // Seul le **statut** compte. Écouter tout `AuthBloc` faisait
      // re-parser la route au moindre changement de profil : l'écran
      // « Mes informations » se reconstruisait alors juste après avoir
      // demandé sa fermeture, et paraissait ne jamais se refermer.
      refreshListenable: GoRouterRefreshStream(
        getIt<AuthBloc>().stream.map((state) => state.status).distinct(),
      ),
      routes: [
        // Les quatre écrans du parcours partagent un seul AuthFlowBloc :
        // l'e-mail saisi, le défi en cours et le jeton de vérification
        // doivent survivre au passage d'un écran à l'autre.
        ShellRoute(
          builder: (context, state, child) => BlocProvider<AuthFlowBloc>(
            create: (_) => getIt<AuthFlowBloc>(),
            child: AuthFlowNavigator(child: child),
          ),
          routes: [
            GoRoute(
              path: loginPath,
              builder: (context, state) =>
                  LoginPage(identifier: state.extra as String?),
            ),
            GoRoute(
              path: signupPath,
              builder: (context, state) => SignupEmailPage(
                // Le compte existe déjà : on remplace l'inscription par
                // la connexion dans la pile, plutôt que d'empiler les
                // deux écrans l'un sur l'autre. Le parcours est remis à
                // zéro au passage, sinon l'écran de connexion afficherait
                // encore le message de l'inscription ratée.
                onSignIn: () {
                  context.read<AuthFlowBloc>().add(const AuthFlowReset());
                  context.pushReplacement(loginPath);
                },
              ),
            ),
            GoRoute(
              path: newDevicePath,
              builder: (context, state) => const NewDevicePage(),
            ),
            GoRoute(
              path: otpPath,
              builder: (context, state) => const OtpPage(),
            ),
            GoRoute(
              path: createPinPath,
              builder: (context, state) => CreatePinPage(
                // Le 409 « compte déjà créé » tombe ici, au dernier
                // appel du parcours : l'écran doit pouvoir rendre la
                // main à la connexion. `go` et non `push` — l'inscription
                // abandonnée n'a pas à rester sous la connexion, il n'y a
                // plus rien à y reprendre.
                onSignIn: () {
                  context.read<AuthFlowBloc>().add(const AuthFlowReset());
                  context.go(loginPath);
                },
              ),
            ),
          ],
        ),
        GoRoute(
          path: profileEditPath,
          builder: (context, state) => const ProfileEditPage(),
        ),
        GoRoute(
          path: birthDatePath,
          builder: (context, state) {
            final extra = state.extra;
            final args = extra is BirthDateArgs ? extra : null;

            return BirthDatePickerPage(
              selected: args?.selected ?? (extra is DateTime ? extra : null),
              eyebrow: args?.eyebrow,
            );
          },
        ),
        GoRoute(
          path: securityPath,
          builder: (context, state) => SecurityPage(
            onChangePin: () => context.push(changePinPath),
            // Le lien mène au parcours de vérification, qui a besoin du
            // compte : il vit dans le ShellRoute d'authentification.
            onNewDevice: () => context.push(newDevicePath),
            onDeleteAccount: () => context.push(accountDeletionPath),
          ),
        ),
        GoRoute(
          path: changePinPath,
          builder: (context, state) =>
              ChangePinPage(onDone: () => context.pop()),
        ),
        GoRoute(
          path: accountDeletionPath,
          builder: (context, state) => const AccountDeletionPage(),
        ),
        GoRoute(
          path: preferencesPath,
          builder: (context, state) => const PreferencesPage(),
        ),
        GoRoute(
          path: walletHistoryPath,
          builder: (context, state) => const WalletHistoryPage(),
        ),
        GoRoute(
          path: walletTopUpPath,
          builder: (context, state) => const WalletTopUpPage(),
        ),
        GoRoute(
          path: walletTopUpMethodPath,
          builder: (context, state) =>
              WalletTopUpMethodPage(amount: state.extra as int?),
        ),
        GoRoute(
          path: walletServicesPath,
          builder: (context, state) => const WalletServicesPage(),
        ),
        GoRoute(
          path: walletRulesPath,
          builder: (context, state) => const WalletRulesPage(),
        ),
        GoRoute(
          path: walletBonusesPath,
          builder: (context, state) => const WalletBonusesPage(),
        ),
        GoRoute(
          path: notificationsPath,
          builder: (context, state) => NotificationsPage(
            // Une notification qui nomme une réservation mène à son
            // billet ; les autres ramènent à la liste des voyages, qui
            // est le sujet de toutes aujourd'hui.
            onOpen: (notification) {
              final uuid = notification.resourceUuid;

              context.push(
                uuid == null || uuid.isEmpty
                    ? tripsPath
                    : ticketByReservation(uuid),
              );
            },
          ),
        ),
        GoRoute(
          path: helpPath,
          builder: (context, state) =>
              HelpPage(onContact: () => context.push(contactPath)),
        ),
        GoRoute(
          path: contactPath,
          builder: (context, state) => ContactPage(
            onOpenChat: (configuration) =>
                context.push(supportChatPath, extra: configuration),
          ),
        ),
        GoRoute(
          path: supportChatPath,
          builder: (context, state) {
            final configuration = state.extra;
            if (configuration is! SupportChatConfiguration) {
              // On n'arrive ici que par l'écran de contact, qui porte la
              // configuration. Un lien direct — restauration d'état,
              // notification — n'a rien à ouvrir.
              return const ContactPage();
            }

            final user = context.read<AuthBloc>().state.session?.user;

            return SupportChatPage(
              configuration: configuration,
              visitorName: user?.name,
              visitorEmail: user?.email,
            );
          },
        ),
        GoRoute(
          path: permissionsPath,
          builder: (context, state) =>
              PermissionsPage(onDone: () => context.go('/home')),
        ),
        GoRoute(
          path: busSearchPath,
          builder: (context, state) => BusSearchPage(
            // L'accueil passe son type de trajet ; « Aucun retour »
            // rapporte en plus le trajet déjà composé, pour ne pas le
            // faire ressaisir. Les autres entrées n'envoient rien.
            args: state.extra is BusSearchArgs
                ? state.extra! as BusSearchArgs
                : const BusSearchArgs(),
          ),
        ),
        GoRoute(
          path: ticketPath,
          builder: (context, state) =>
              TicketPage(args: state.extra as TicketArgs?),
        ),
        GoRoute(
          path: ticketByReservationPath,
          builder: (context, state) {
            final uuid = state.pathParameters['reservationUuid'] ?? '';

            return TicketByReservationPage(
              reservationUuid: uuid,
              getTickets: getIt<GetTicketsUseCase>(),
              onOpenTicket: (ticket, today) => context.pushReplacement(
                ticketDetailPath,
                extra: TicketDetailArgs(ticket: ticket, today: today),
              ),
              onFallback: () => context.go(tripsPath),
            );
          },
        ),
        GoRoute(
          path: ticketDetailPath,
          builder: (context, state) {
            final args = state.extra as TicketDetailArgs?;

            // Sans `extra` (lien profond, redémarrage à chaud), on renvoie
            // sur la liste plutôt que d'afficher un billet vide.
            if (args == null) return const SizedBox.shrink();

            return TicketDetailPage(ticket: args.ticket, today: args.today);
          },
        ),
        GoRoute(
          path: splashPath,
          builder: (context, state) => const SplashPage(),
        ),
        GoRoute(
          path: onboardingPath,
          builder: (context, state) => OnboardingPage(
            onDone: () async {
              await getIt<OnboardingStore>().markSeen();
              if (context.mounted) context.go(welcomePath);
            },
          ),
        ),
        GoRoute(
          path: welcomePath,
          builder: (context, state) => WelcomePage(
            // Les deux actions mènent aux écrans réels d'inscription et de
            // connexion (`signupPath`, `loginPath`).
            onCreateAccount: () => context.push(signupPath),
            onLogin: () => context.push(loginPath),
          ),
        ),
        GoRoute(
          path: originPath,
          builder: (context, state) =>
              _cityPicker(state, field: CityField.origin),
        ),
        GoRoute(
          path: destinationPath,
          builder: (context, state) =>
              _cityPicker(state, field: CityField.destination),
        ),
        GoRoute(
          path: datePath,
          builder: (context, state) {
            final args = state.extra as DatePickerArgs?;
            final today = args?.today ?? DateTime.now();

            return DatePickerPage(
              selected: args?.selected ?? today,
              today: today,
            );
          },
        ),
        GoRoute(
          path: returnDatePath,
          builder: (context, state) {
            final args = state.extra as DatePickerArgs?;
            // Le plancher est le jour du départ, pas aujourd'hui : on ne
            // rentre pas avant d'être parti.
            final earliest = args?.today ?? DateTime.now();

            return DatePickerPage(
              selected: args?.selected ?? earliest,
              today: earliest,
              title: context.l10n.homeReturnDateTitle,
            );
          },
        ),
        GoRoute(
          path: passengersPath,
          builder: (context, state) =>
              PassengersPickerPage(selected: (state.extra as int?) ?? 1),
        ),

        GoRoute(
          path: departuresPath,
          builder: (context, state) => DeparturesPage(
            originId: state.uri.queryParameters['origin'],
            destinationId: state.uri.queryParameters['destination'],
            date: state.uri.queryParameters['date'],
            passengers: state.uri.queryParameters['passengers'],
            returnDate: state.uri.queryParameters['return_date'],
            tripType: state.uri.queryParameters['trip_type'],
          ),
        ),
        // Les trois écrans du tunnel partagent un seul [SeatHoldBloc].
        //
        // Le blocage part du récapitulatif, le décompte suit le voyageur
        // jusqu'au paiement, et tous deux doivent survivre à un retour en
        // arrière. Fourni par chaque écran, il serait remis à zéro à chaque
        // navigation — et les sièges resteraient retenus côté serveur sans
        // que l'app le sache.
        ShellRoute(
          builder: (context, state, child) => MultiBlocProvider(
            providers: [
              BlocProvider<SeatHoldBloc>(create: (_) => getIt<SeatHoldBloc>()),
              // Le devis du serveur suit le tunnel : le récapitulatif le
              // détaille, et demain le plan des sièges l'affichera pendant
              // le choix. Le fournir par écran rappellerait l'API pour un
              // prix qui n'a pas bougé.
              BlocProvider<GroupQuoteCubit>(
                create: (_) => getIt<GroupQuoteCubit>(),
              ),
              // Le payeur se choisit sur l'écran de groupe et sert au
              // blocage, deux écrans plus loin : porté par le tunnel, il
              // survit aux retours en arrière.
              BlocProvider<GroupPayerCubit>(
                create: (_) => getIt<GroupPayerCubit>(),
              ),
              // Les billets du groupe, listés après paiement.
              BlocProvider<GroupTicketsCubit>(
                create: (_) => getIt<GroupTicketsCubit>(),
              ),
              // Le catalogue des moyens de paiement est demandé dès
              // l'entrée du tunnel, pas à l'ouverture de l'écran de
              // paiement : la réponse est ainsi déjà là quand le voyageur
              // arrive au choix du moyen, après avoir rempli ses
              // formulaires. Le charger plus tard ferait clignoter le
              // dernier écran du parcours, celui où l'hésitation coûte le
              // plus cher.
              BlocProvider<PaymentCatalogBloc>(
                create: (_) =>
                    getIt<PaymentCatalogBloc>()
                      ..add(const PaymentCatalogRequested()),
              ),
              // Le paiement lui-même vit au même niveau : lancé depuis
              // l'écran Mobile Money, il est attendu par l'écran de
              // validation puis par celui de confirmation. Le fournir par
              // écran le remettrait à zéro à chaque navigation — et la
              // relecture d'état s'arrêterait au moment précis où le
              // voyageur compose son code.
              BlocProvider<PaymentFlowBloc>(
                // La reprise se demande à l'entrée : si l'app a été
                // fermée pendant qu'un paiement attendait son verdict, le
                // serveur a pu le confirmer entre-temps — son webhook
                // n'attend pas que l'app revienne.
                create: (_) =>
                    getIt<PaymentFlowBloc>()..add(const PaymentFlowResumed()),
              ),
            ],
            child: SeatHoldWatcher(child: child),
          ),
          routes: [
            GoRoute(
              path: groupTicketsPath,
              builder: (context, state) =>
                  GroupTicketsPage(args: state.extra as GroupTicketsArgs?),
            ),
            GoRoute(
              path: groupBookingPath,
              builder: (context, state) =>
                  GroupBookingPage(args: state.extra as GroupBookingArgs?),
            ),
            GoRoute(
              path: groupDiscountPath,
              builder: (context, state) =>
                  GroupDiscountPage(args: state.extra as GroupDiscountArgs?),
            ),
            GoRoute(
              path: seatsPath,
              builder: (context, state) {
                final args = state.extra as SeatsArgs?;

                return SeatsPage(
                  journey: args?.journey,
                  passengers: args?.passengers ?? 1,
                );
              },
            ),
            GoRoute(
              path: passengerInfoPath,
              builder: (context, state) {
                final args = state.extra as PassengerFormArgs?;

                return PassengerFormPage(journey: args?.journey);
              },
            ),
            GoRoute(
              path: summaryPath,
              builder: (context, state) {
                final args = state.extra as SummaryArgs?;

                return SummaryPage(
                  journey: args?.journey,
                  passengers: args?.passengers ?? const [],
                );
              },
            ),
            GoRoute(
              path: holdExpiredPath,
              builder: (context, state) => const HoldExpiredPage(),
            ),
            // Le parcours Colis partage un brouillon : chaque écran le
            // relit et l'enrichit, et un retour n'efface rien.
            GoRoute(
              path: colisRoutePath,
              builder: (context, state) =>
                  const _ColisDraftScope(child: ColisRoutePage()),
            ),
            GoRoute(
              path: colisContentPath,
              builder: (context, state) =>
                  const _ColisDraftScope(child: ColisContentPage()),
            ),
            GoRoute(
              path: colisContactsPath,
              builder: (context, state) =>
                  const _ColisDraftScope(child: ColisContactsPage()),
            ),
            GoRoute(
              path: colisSummaryPath,
              builder: (context, state) =>
                  const _ColisDraftScope(child: ColisSummaryPage()),
            ),
            GoRoute(
              path: colisPreregisteredPath,
              builder: (context, state) =>
                  const _ColisDraftScope(child: ColisPreregisteredPage()),
            ),
            GoRoute(
              path: colisQuotePath,
              builder: (context, state) =>
                  const _ColisDraftScope(child: ColisQuotePage()),
            ),
            GoRoute(
              path: colisPaymentPath,
              builder: (context, state) =>
                  const _ColisDraftScope(child: ColisPaymentPage()),
            ),
            GoRoute(
              path: colisMomoPath,
              builder: (context, state) => _ColisDraftScope(
                child: ColisMomoPage(args: state.extra as ColisCheckoutArgs?),
              ),
            ),
            GoRoute(
              path: colisGatewayPath,
              builder: (context, state) => _ColisDraftScope(
                child: ColisGatewayPage(
                  isCard: false,
                  args: state.extra as ColisCheckoutArgs?,
                ),
              ),
            ),
            GoRoute(
              path: colisCardPath,
              builder: (context, state) => _ColisDraftScope(
                child: ColisGatewayPage(
                  isCard: true,
                  args: state.extra as ColisCheckoutArgs?,
                ),
              ),
            ),
            GoRoute(
              path: colisPendingPath,
              builder: (context, state) => _ColisDraftScope(
                child: ColisPendingPage(
                  args: state.extra as ColisCheckoutArgs?,
                ),
              ),
            ),
            GoRoute(
              path: colisReceiptPath,
              builder: (context, state) =>
                  const _ColisDraftScope(child: ColisReceiptPage()),
            ),
            // Le suivi n'a pas besoin du brouillon : il lit un envoi
            // déjà payé, désigné par sa référence.
            GoRoute(
              path: colisShipmentsPath,
              builder: (context, state) => const ColisShipmentsPage(),
            ),
            GoRoute(
              path: colisIncidentPath,
              builder: (context, state) =>
                  ColisIncidentPage(reference: state.extra as String?),
            ),
            GoRoute(
              path: colisTrackingPath,
              builder: (context, state) =>
                  ColisTrackingPage(reference: state.extra as String?),
            ),
            GoRoute(
              path: colisPickupPath,
              builder: (context, state) =>
                  ColisPickupPage(reference: state.extra as String?),
            ),
            GoRoute(
              path: assistanceRequestPath,
              builder: (context, state) =>
                  AssistanceRequestPage(args: state.extra as AssistanceArgs?),
            ),
            GoRoute(
              path: assistanceStatusPath,
              builder: (context, state) => AssistanceStatusPage(
                args: state.extra as AssistanceStatusArgs?,
              ),
            ),
            GoRoute(
              path: paymentMethodPath,
              builder: (context, state) =>
                  PaymentMethodPage(args: state.extra as PaymentArgs?),
            ),
            GoRoute(
              path: paymentMobileMoneyPath,
              builder: (context, state) =>
                  MobileMoneyPage(args: state.extra as PaymentArgs?),
            ),
            GoRoute(
              path: paymentWalletPath,
              builder: (context, state) =>
                  WalletPaymentPage(args: state.extra as PaymentArgs?),
            ),
            GoRoute(
              path: paymentCardPath,
              builder: (context, state) => GatewayCheckoutPage(
                method: PaymentCatalog.cardCode,
                args: state.extra as PaymentArgs?,
              ),
            ),
            GoRoute(
              path: paymentGatewayMomoPath,
              builder: (context, state) => GatewayCheckoutPage(
                method: PaymentCatalog.mobileMoneyCode,
                args: state.extra as PaymentArgs?,
              ),
            ),
            GoRoute(
              path: paymentPendingPath,
              builder: (context, state) =>
                  PaymentPendingPage(args: state.extra as PaymentFlowArgs?),
            ),
            GoRoute(
              path: paymentFailedPath,
              builder: (context, state) =>
                  PaymentFailedPage(args: state.extra as PaymentFlowArgs?),
            ),
            GoRoute(
              path: paymentSuccessPath,
              builder: (context, state) =>
                  BookingSuccessPage(args: state.extra as PaymentFlowArgs?),
            ),
          ],
        ),
        GoRoute(
          path: returnDeparturesPath,
          builder: (context, state) =>
              ReturnDeparturesPage(args: state.extra as ReturnDeparturesArgs?),
        ),
        GoRoute(
          path: departureDetailPath,
          builder: (context, state) {
            final args = state.extra as DepartureDetailArgs?;

            return DepartureDetailPage(
              itinerary: args?.itinerary,
              passengers: args?.passengers ?? 1,
              returnItinerary: args?.returnItinerary,
            );
          },
        ),
        StatefulShellRoute.indexedStack(
          builder: (context, state, navigationShell) {
            return _ScaffoldWithNavBar(navigationShell: navigationShell);
          },
          branches: [
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/home',
                  builder: (context, state) => const HomePage(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: tripsPath,
                  builder: (context, state) => TicketsPage(
                    onBookTrip: () => context.push(busSearchPath),
                    onOpenTicket: (ticket, today) => context.push(
                      ticketDetailPath,
                      extra: TicketDetailArgs(ticket: ticket, today: today),
                    ),
                    onOpenAssistance: (reservationId) => context.push(
                      assistanceStatusPath,
                      extra: AssistanceStatusArgs(reservationId: reservationId),
                    ),
                    onOpenNotifications: () => context.push(notificationsPath),
                  ),
                ),
              ],
            ),
            // Colis : la branche existe et l'écran est écrit, mais le
            // module n'ouvre pas encore. L'onglet garde sa place dans la
            // barre — en retirer un déplacerait tous les autres, et le
            // voyageur perdrait ses repères pour rien — et c'est la barre
            // qui refuse d'y mener.
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/colis',
                  builder: (context, state) => const ColisPage(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: walletPath,
                  builder: (context, state) => const WalletPage(),
                ),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(
                  path: '/profil',
                  builder: (context, state) => const ProfilPage(),
                ),
              ],
            ),
          ],
        ),
      ],
    );
  }
}

/// L'onglet Voyages dans la barre du bas, dans l'ordre des branches.
const int _voyagesBranch = 1;

/// L'onglet Colis, présent dans la barre mais sans écran à ouvrir.
const int _colisBranch = 2;

/// Le `Scaffold` commun à tous les onglets : il porte la [KoraBottomNav]
/// et affiche la branche active via [navigationShell].
class _ScaffoldWithNavBar extends StatelessWidget {
  const _ScaffoldWithNavBar({required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      extendBody: true,
      body: navigationShell,
      // Au-dessus de la barre de navigation, que le `Scaffold` prend en
      // compte tout seul : le bouton ne recouvre aucun onglet.
      floatingActionButton: SupportChatFab(
        onOpen: (configuration) =>
            context.push(AppRouter.supportChatPath, extra: configuration),
      ),
      bottomNavigationBar: Padding(
        padding: const EdgeInsets.fromLTRB(11, 0, 11, 11),
        child: KoraBottomNav(
          // Les libellés viennent des fichiers de langue de l'app : le
          // design system n'en porte aucun.
          labels: [
            l10n.navHome,
            l10n.navTrips,
            l10n.navParcels,
            l10n.navWallet,
            l10n.navProfile,
          ],
          currentIndex: navigationShell.currentIndex,
          onTap: (index) {
            // Colis s'annonce sans s'ouvrir : tant que le module n'est pas
            // livré, l'appui ne mène nulle part plutôt que sur un écran
            // qu'on ne veut pas encore montrer.
            if (index == _colisBranch) return;

            // Ouvrir Voyages relit les billets : l'émission suit le
            // paiement de quelques secondes, et la branche garde son
            // état — sans ça, le billet qu'on vient d'acheter n'y
            // apparaîtrait qu'après une reconnexion.
            if (index == _voyagesBranch &&
                getIt.isRegistered<BookingRefresh>()) {
              getIt<BookingRefresh>().notify();
            }

            navigationShell.goBranch(
              index,
              // Si on retape sur l'onglet déjà actif, on revient à sa racine
              // (comportement standard des apps mobiles).
              initialLocation: index == navigationShell.currentIndex,
            );
          },
        ),
      ),
    );
  }
}

/// Passerelle entre un flux Bloc et le `refreshListenable` de `go_router`,
/// qui attend un [Listenable].
class GoRouterRefreshStream extends ChangeNotifier {
  GoRouterRefreshStream(Stream<dynamic> stream) {
    _subscription = stream.asBroadcastStream().listen((_) => notifyListeners());
  }

  late final StreamSubscription<dynamic> _subscription;

  @override
  void dispose() {
    _subscription.cancel();
    super.dispose();
  }
}

/// Le brouillon d'envoi, partagé par les cinq écrans du parcours Colis.
///
/// Un singleton plutôt qu'un `extra` de `go_router` : le voyageur revient
/// corriger son itinéraire depuis le récapitulatif, et un `extra` l'aurait
/// obligé à ressaisir tout ce qui suit.
class _ColisDraftScope extends StatelessWidget {
  const _ColisDraftScope({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return BlocProvider<ColisDraftCubit>.value(
      value: getIt<ColisDraftCubit>(),
      child: child,
    );
  }
}
