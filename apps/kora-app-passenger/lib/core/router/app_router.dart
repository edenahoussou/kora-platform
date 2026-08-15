import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/pages/create_pin_page.dart';
import '../../features/auth/presentation/pages/login_page.dart';
import '../../features/auth/presentation/pages/otp_page.dart';
import '../../features/auth/presentation/pages/signup_email_page.dart';
import '../../features/booking/domain/entities/search_criteria.dart';
import '../../features/booking/presentation/pages/departure_detail_page.dart';
import '../../features/booking/presentation/pages/departures_page.dart';
import '../../features/booking/presentation/pages/passenger_form_page.dart';
import '../../features/booking/presentation/pages/seats_page.dart';
import '../../features/booking/presentation/pages/summary_page.dart';
import '../../features/colis/presentation/pages/colis_page.dart';
import '../../features/home/presentation/pages/city_picker_page.dart';
import '../../features/home/presentation/pages/date_picker_page.dart';
import '../../features/home/presentation/pages/home_page.dart';
import '../../features/home/presentation/pages/passengers_picker_page.dart';
import '../../features/profil/presentation/pages/profil_page.dart';
import '../../features/voyages/presentation/pages/voyages_page.dart';
import '../../features/wallet/presentation/pages/wallet_page.dart';
import '../../features/welcome/presentation/pages/welcome_page.dart';
import 'auth_guard.dart';

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

  /// Chemin de l'écran de connexion — seule route accessible sans session
  /// active (voir `redirect` ci-dessous).
  static const String loginPath = '/login';

  /// Écran de bienvenue, premier affiché au lancement.
  ///
  /// Il se montre à **chaque** démarrage : mémoriser qu'il a déjà été vu
  /// demande de stocker un indicateur (`SharedPreferences`), ce que fera la
  /// feature `auth` en même temps qu'elle saura si quelqu'un est connecté.
  static const String welcomePath = '/welcome';

  /// Première étape de la création de compte : la saisie de l'adresse
  /// e-mail, qui recevra le code de vérification.
  static const String signupPath = '/signup';

  /// Seconde étape : la vérification du code reçu par e-mail. L'adresse
  /// visée est transmise en `extra`.
  static const String otpPath = '/otp';

  /// Troisième étape : le choix du code PIN qui protégera le compte.
  static const String createPinPath = '/create-pin';

  /// Liste des départs, puis détail d'un départ.
  ///
  /// Déclarés **hors** de la bottom nav, comme les étapes du formulaire :
  /// une fois la recherche lancée, l'utilisateur est dans un tunnel de
  /// réservation, pas en train de naviguer entre les rubriques de l'app.
  static const String departuresPath = '/departures';
  static const String departureDetailPath = '/departure';
  static const String seatsPath = '/seats';
  static const String passengerInfoPath = '/passenger-info';
  static const String summaryPath = '/summary';

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
  static const String passengersPath = '/passengers';

  /// Construit l'URL de la liste des départs à partir des critères saisis
  /// sur l'accueil.
  ///
  /// Les critères passent par la **query string** (et non par `extra`) pour
  /// que l'écran reste atteignable par lien profond et survive à un
  /// redémarrage à chaud. Les villes sont transmises par identifiant :
  /// c'est l'écran de destination qui les résout, comme il devra le faire
  /// avec l'API au Sprint 2.
  static String departuresUrlFor(SearchCriteria criteria) {
    return Uri(
      path: departuresPath,
      queryParameters: <String, String>{
        'origin': criteria.origin.id,
        'destination': criteria.destination.id,
        // `toIso8601String()` embarque l'heure et le fuseau, inutiles ici :
        // seule la journée de départ compte.
        'date': _formatDay(criteria.date),
        'passengers': '${criteria.passengers}',
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
  /// - Connecté et sur `/login` → redirigé vers `/home` (pas de raison de
  ///   revoir l'écran de connexion).
  /// - Sinon → `null`, pas de redirection, la navigation demandée a lieu.
  ///
  /// **La redirection "non connecté → `/login`" est volontairement
  /// désactivée pour l'instant** : `LoginPage` n'a pas encore de vrai
  /// formulaire (voir GUIDE.md §13), donc la forcer enfermerait tout le
  /// monde sur un écran sans issue, impossible de revenir sur la bottom
  /// nav. TOO(auth): réactiver `if (!authenticated && !onLoginPage) return
  /// loginPath;` dès que `LoginPage` permet réellement de se connecter.
  ///
  /// Volontairement pas de `refreshListenable` non plus : sans feature
  /// `auth` écrite, il n'existe pas encore de flux qui notifierait un
  /// changement de session en dehors d'une navigation. Le jour où
  /// `AuthBloc` existe, câbler `refreshListenable` dessus pour que
  /// `redirect` se réévalue aussi juste après un login/logout, sans
  /// attendre une navigation manuelle.
  static Future<String?> _redirect(
    BuildContext context,
    GoRouterState state,
  ) async {
    final onLoginPage = state.matchedLocation == loginPath;

    if (onLoginPage && await AuthGuard.isAuthenticated) return '/home';
    return null;
  }

  static final GoRouter router = GoRouter(
    initialLocation: welcomePath,
    redirect: _redirect,
    routes: [
      GoRoute(
        path: loginPath,
        builder: (context, state) => LoginPage(
          // Aucun PIN n'est vérifié tant que la feature `auth` n'existe
          // pas : la saisie de 4 chiffres ouvre l'app.
          // TODO(auth): brancher la vérification du PIN.
          onSuccess: () => context.go('/home'),
        ),
      ),
      GoRoute(
        path: signupPath,
        builder: (context, state) => SignupEmailPage(
          onSubmit: (email) => context.push(otpPath, extra: email),
        ),
      ),
      GoRoute(
        path: otpPath,
        builder: (context, state) => OtpPage(
          email: (state.extra as String?) ?? '',
          onVerified: () => context.push(createPinPath),
        ),
      ),
      GoRoute(
        path: createPinPath,
        builder: (context, state) => CreatePinPage(
          // La confirmation du PIN, puis les autorisations, complètent le
          // parcours d'inscription — hors de ce lot. Le code saisi n'est
          // stocké nulle part tant que la feature `auth` n'existe pas.
          // TODO(auth): enchaîner sur la confirmation du PIN.
          onSubmit: (_) => ScaffoldMessenger.of(context)
            ..hideCurrentSnackBar()
            ..showSnackBar(
              const SnackBar(
                content: Text('La confirmation du PIN arrive au prochain lot.'),
              ),
            ),
        ),
      ),
      GoRoute(
        path: welcomePath,
        builder: (context, state) => WelcomePage(
          // Les deux actions mènent à l'inscription et à la connexion, qui
          // n'existent pas encore (voir GUIDE.md §13). En attendant,
          // « Créer mon compte » entre dans l'app pour que le parcours Bus
          // reste accessible, et « J'ai déjà un compte » ouvre l'écran de
          // connexion, même s'il n'est qu'un placeholder.
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
        ),
      ),
      GoRoute(
        path: seatsPath,
        builder: (context, state) {
          final args = state.extra as SeatsArgs?;

          return SeatsPage(
            departure: args?.departure,
            passengers: args?.passengers ?? 1,
          );
        },
      ),
      GoRoute(
        path: passengerInfoPath,
        builder: (context, state) {
          final args = state.extra as PassengerFormArgs?;

          return PassengerFormPage(
            departure: args?.departure,
            seats: args?.seats ?? const [],
          );
        },
      ),
      GoRoute(
        path: summaryPath,
        builder: (context, state) {
          final args = state.extra as SummaryArgs?;

          return SummaryPage(
            departure: args?.departure,
            passengers: args?.passengers ?? const [],
          );
        },
      ),
      GoRoute(
        path: departureDetailPath,
        builder: (context, state) {
          final args = state.extra as DepartureDetailArgs?;

          return DepartureDetailPage(
            departure: args?.departure,
            passengers: args?.passengers ?? 1,
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
                path: '/voyages',
                builder: (context, state) => const VoyagesPage(),
              ),
            ],
          ),
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
                path: '/wallet',
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

/// Le `Scaffold` commun à tous les onglets : il porte la [KoraBottomNav]
/// et affiche la branche active via [navigationShell].
class _ScaffoldWithNavBar extends StatelessWidget {
  const _ScaffoldWithNavBar({required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBody: true,
      body: navigationShell,
      bottomNavigationBar: Padding(
        padding: const EdgeInsets.fromLTRB(11, 0, 11, 11),
        child: KoraBottomNav(
          currentIndex: navigationShell.currentIndex,
          onTap: (index) => navigationShell.goBranch(
            index,
            // Si on retape sur l'onglet déjà actif, on revient à sa racine
            // (comportement standard des apps mobiles).
            initialLocation: index == navigationShell.currentIndex,
          ),
        ),
      ),
    );
  }
}
