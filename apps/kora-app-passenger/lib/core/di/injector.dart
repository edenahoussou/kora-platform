import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart' show kDebugMode;
import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:get_it/get_it.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../features/auth/data/datasources/auth_local_data_source.dart';
import '../../features/auth/data/datasources/auth_remote_data_source.dart';
import '../../features/auth/data/datasources/device_info_data_source.dart';
import '../../features/auth/data/repositories/auth_repository_impl.dart';
import '../../features/auth/domain/repositories/auth_repository.dart';
import '../../features/auth/presentation/bloc/auth_bloc.dart';
import '../../features/auth/presentation/bloc/auth_flow_bloc.dart';
import '../../features/booking/data/datasources/assisted_booking_local_data_source.dart';
import '../../features/booking/data/datasources/assisted_booking_remote_data_source.dart';
import '../../features/booking/data/datasources/booking_cache_local_data_source.dart';
import '../../features/booking/data/datasources/booking_data_source.dart';
import '../../features/booking/data/datasources/booking_remote_data_source.dart';
import '../../features/booking/data/datasources/reservation_remote_data_source.dart';
import '../../features/booking/data/datasources/ticket_document_remote_data_source.dart';
import '../../features/booking/data/datasources/ticket_document_store.dart';
import '../../features/booking/data/repositories/assisted_booking_repository_impl.dart';
import '../../features/booking/data/repositories/booking_repository_impl.dart';
import '../../features/booking/data/repositories/departure_repository_impl.dart';
import '../../features/booking/data/repositories/reservation_repository_impl.dart';
import '../../features/booking/data/repositories/ticket_document_repository_impl.dart';
import '../../features/booking/domain/repositories/assisted_booking_repository.dart';
import '../../features/booking/domain/repositories/booking_repository.dart';
import '../../features/booking/domain/repositories/departure_repository.dart';
import '../../features/booking/domain/repositories/reservation_repository.dart';
import '../../features/booking/domain/repositories/ticket_document_repository.dart';
import '../../features/booking/domain/usecases/get_cities_usecase.dart';
import '../../features/booking/domain/usecases/get_next_trip_usecase.dart';
import '../../features/booking/domain/usecases/get_recent_cities_usecase.dart';
import '../../features/booking/domain/usecases/get_seats_usecase.dart';
import '../../features/booking/domain/usecases/get_tickets_usecase.dart';
import '../../features/booking/domain/usecases/search_departures_usecase.dart';
import '../../features/booking/presentation/bloc/assistance_cubits.dart';
import '../../features/booking/presentation/bloc/cancel_ticket_cubit.dart';
import '../../features/booking/presentation/bloc/departures_bloc.dart';
import '../../features/booking/presentation/bloc/group_payer_cubit.dart';
import '../../features/booking/presentation/bloc/group_quote_cubit.dart';
import '../../features/booking/presentation/bloc/group_tickets_cubit.dart';
import '../../features/booking/presentation/bloc/seat_hold_bloc.dart';
import '../../features/booking/presentation/bloc/seats_bloc.dart';
import '../../features/booking/presentation/bloc/ticket_document_cubit.dart';
import '../../features/booking/presentation/bloc/tickets_bloc.dart';
import '../../features/home/presentation/bloc/home_bloc.dart';
import '../../features/language/data/datasources/language_local_data_source.dart';
import '../../features/language/data/repositories/language_repository_impl.dart';
import '../../features/language/domain/repositories/language_repository.dart';
import '../../features/language/presentation/bloc/language_bloc.dart';
import '../../features/payment/data/datasources/payment_remote_data_source.dart';
import '../../features/payment/data/repositories/payment_repository_impl.dart';
import '../../features/payment/domain/repositories/payment_repository.dart';
import '../../features/payment/presentation/bloc/payment_catalog_bloc.dart';
import '../../features/payment/presentation/bloc/payment_flow_bloc.dart';
import '../../features/theme/data/datasources/theme_local_data_source.dart';
import '../../features/theme/data/repositories/theme_repository_impl.dart';
import '../../features/theme/domain/repositories/theme_repository.dart';
import '../../features/theme/presentation/bloc/theme_bloc.dart';
import '../../features/welcome/data/onboarding_store.dart';
import '../constants/api_constants.dart';
import '../constants/storage_keys.dart';
import '../env/app_env.dart';
import '../services/app_permissions.dart';
import '../services/oauth_sign_in.dart';

/// [GetIt] est notre "service locator" : un registre central qui sait
/// comment construire chaque objet de l'application (data sources,
/// repositories, blocs, clients réseau...).
///
/// Pourquoi c'est utile en Clean Architecture ?
/// Sans ça, pour créer un `ThemeBloc`, il faudrait écrire à la main :
/// `ThemeBloc(ThemeRepositoryImpl(ThemeLocalDataSourceImpl(prefs)))`
/// à chaque endroit où on en a besoin. Avec `getIt`, on écrit ça UNE FOIS
/// ici, et ensuite on récupère l'objet prêt à l'emploi avec
/// `getIt<ThemeBloc>()` n'importe où dans l'app.
final getIt = GetIt.instance;

/// À appeler une seule fois, au tout début de `main()`, avant de lancer
/// `runApp`.
Future<void> setupInjector() async {
  await _registerExternalDependencies();
  _registerCore();
  _registerThemeFeature();
  _registerLanguageFeature();
  _registerBookingFeature();
  _registerPaymentFeature();
  _registerAuthFeature();

  // Quand on ajoutera une nouvelle feature (ex: "auth"), on suit le même
  // schéma que _registerThemeFeature() ci-dessous :
  // getIt.registerLazySingleton<AuthRemoteDataSource>(() => AuthRemoteDataSourceImpl(getIt()));
  // getIt.registerLazySingleton<AuthRepository>(() => AuthRepositoryImpl(getIt(), getIt()));
  // getIt.registerFactory<AuthBloc>(() => AuthBloc(getIt()));
}

/// Dépendances fournies par des packages tiers (pas de logique KORA ici).
Future<void> _registerExternalDependencies() async {
  final sharedPreferences = await SharedPreferences.getInstance();
  getIt.registerLazySingleton<SharedPreferences>(() => sharedPreferences);

  // Lu par le garde de navigation à chaque changement de route : il doit
  // répondre sans attendre, d'où la lecture synchrone de SharedPreferences.
  getIt.registerLazySingleton<OnboardingStore>(() => OnboardingStore(getIt()));

  getIt.registerLazySingleton<FlutterSecureStorage>(
    () => const FlutterSecureStorage(),
  );

  getIt.registerLazySingleton<Connectivity>(() => Connectivity());

  // Lit le modèle et l'identifiant de l'appareil, exigés par l'API à
  // chaque connexion (voir DeviceInfoDataSource).
  getIt.registerLazySingleton<DeviceInfoPlugin>(() => DeviceInfoPlugin());

  // Autorisations système (position, notifications), demandées par
  // l'écran de fin d'inscription.
  getIt.registerLazySingleton<AppPermissions>(() => const AppPermissionsImpl());

  // SDK Google et Facebook, derrière notre propre interface : les écrans
  // ne dépendent pas des paquets, et les tests s'en passent.
  getIt.registerLazySingleton<OAuthSignIn>(() => OAuthSignInImpl());
}

/// Briques partagées par toutes les features (réseau, connectivité...).
///
/// [DioClient] (package `flutter_networking`) ne connaît ni l'URL de
/// l'API ni où est stocké le token : c'est ICI, et seulement ici, qu'on
/// fait le pont entre la config d'environnement ([AppEnv.current]) et le
/// stockage sécurisé de l'app ([FlutterSecureStorage]).
void _registerCore() {
  getIt.registerLazySingleton<NetworkInfo>(() => NetworkInfoImpl(getIt()));

  getIt.registerLazySingleton<DioClient>(
    () => DioClient(
      config: NetworkConfig(
        baseUrl: AppEnv.current.apiBaseUrl,
        connectTimeout: const Duration(
          milliseconds: ApiConstants.connectTimeoutMs,
        ),
        receiveTimeout: const Duration(
          milliseconds: ApiConstants.receiveTimeoutMs,
        ),
        sendTimeout: const Duration(milliseconds: ApiConstants.sendTimeoutMs),
        // En dev/staging on veut voir les requêtes dans la console ; en
        // prod jamais, même en profile/release "de test" (kDebugMode
        // couvre ce cas, AppEnv.current.enableNetworkLogs le confirme).
        enableLogging: kDebugMode && AppEnv.current.enableNetworkLogs,
      ),
      getAccessToken: () =>
          getIt<FlutterSecureStorage>().read(key: StorageKeys.accessToken),
    ),
  );

  // On expose directement `Dio` aussi : la plupart des data sources n'ont
  // besoin que de `Dio`, pas de tout `DioClient`.
  getIt.registerLazySingleton<Dio>(() => getIt<DioClient>().dio);
}

/// Feature "theme" (mode clair/sombre) — voir features/theme/.
void _registerThemeFeature() {
  getIt.registerLazySingleton<ThemeLocalDataSource>(
    () => ThemeLocalDataSourceImpl(getIt()),
  );

  getIt.registerLazySingleton<ThemeRepository>(
    () => ThemeRepositoryImpl(getIt()),
  );

  // `registerFactory` (et non singleton) : convention recommandée pour les
  // Blocs, même si dans notre cas on ne le crée qu'une fois (voir app.dart).
  getIt.registerFactory<ThemeBloc>(() => ThemeBloc(getIt()));
}

/// Feature "booking" (parcours de réservation Bus) — voir features/booking/.
///
/// **C'est ici, et nulle part ailleurs, que l'app décide d'où viennent les
/// données du parcours Bus.** La recherche (villes + départs) tape
/// désormais sur l'API ; les écrans qui n'ont pas encore d'endpoint
/// documenté (plan des sièges, prochain voyage, billets) continuent de
/// lire le jeu de démonstration, passé en repli au data source réseau.
/// Repository, use cases, Blocs et écrans, eux, n'ont pas bougé — c'est
/// tout l'intérêt d'avoir gardé cette décision à un seul endroit.
void _registerBookingFeature() {
  getIt.registerLazySingleton<BookingDataSource>(
    () => BookingRemoteDataSourceImpl(
      getIt(),
      pending: BookingMockDataSource(),
      // Les billets et les villes déjà chargés restent lisibles hors
      // réseau.
      cache: BookingCacheLocalDataSource(getIt()),
    ),
  );

  // La recherche a son propre contrat (`DepartureRepository`) : c'est le
  // seul morceau du parcours branché sur le catalogue Bus public, sans
  // session ni panier. Les deux dépôts partagent la même source, qui
  // décide seule d'où viennent les données.
  getIt.registerLazySingleton<DepartureRepository>(
    () => DepartureRepositoryImpl(getIt()),
  );

  getIt.registerLazySingleton<BookingRepository>(
    () => BookingRepositoryImpl(getIt()),
  );

  // Le blocage des sièges a son propre contrat, et sa propre source :
  // devis, blocage et libération exigent une session, là où tout le
  // catalogue est public. Les mêler aurait donné un dépôt dont la moitié
  // des méthodes réclament un jeton.
  getIt.registerLazySingleton<ReservationRemoteDataSource>(
    () => ReservationRemoteDataSource(getIt()),
  );
  getIt.registerLazySingleton<ReservationRepository>(
    () => ReservationRepositoryImpl(
      getIt(),
      // Les billets émis, gardés sur le téléphone : à l'embarquement, le
      // réseau manque souvent.
      BookingCacheLocalDataSource(getIt()),
    ),
  );

  // La réservation assistée (MEZ-80) : l'API pour l'envoi et le suivi, le
  // téléphone pour retrouver les demandes — l'API ne les liste pas.
  getIt.registerLazySingleton<AssistedBookingRepository>(
    () => AssistedBookingRepositoryImpl(
      AssistedBookingRemoteDataSource(getIt()),
      AssistedBookingLocalDataSource(getIt()),
    ),
  );

  getIt.registerLazySingleton<GetCitiesUseCase>(
    () => GetCitiesUseCase(getIt()),
  );
  getIt.registerLazySingleton<GetRecentCitiesUseCase>(
    () => GetRecentCitiesUseCase(getIt()),
  );
  getIt.registerLazySingleton<GetNextTripUseCase>(
    () => GetNextTripUseCase(getIt()),
  );

  getIt.registerLazySingleton<SearchDeparturesUseCase>(
    () => SearchDeparturesUseCase(getIt()),
  );
  getIt.registerLazySingleton<GetSeatsUseCase>(() => GetSeatsUseCase(getIt()));
  getIt.registerLazySingleton<GetTicketsUseCase>(
    () => GetTicketsUseCase(getIt()),
  );

  getIt.registerFactory<HomeBloc>(() => HomeBloc(getIt(), getIt(), getIt()));
  getIt.registerFactory<DeparturesBloc>(() => DeparturesBloc(getIt(), getIt()));
  getIt.registerFactory<SeatsBloc>(() => SeatsBloc(getIt()));
  getIt.registerFactory<SeatHoldBloc>(() => SeatHoldBloc(getIt()));
  getIt.registerFactory<TicketsBloc>(() => TicketsBloc(getIt()));

  getIt.registerFactory<GroupQuoteCubit>(() => GroupQuoteCubit(getIt()));

  getIt.registerFactory<GroupPayerCubit>(() => GroupPayerCubit(getIt()));

  getIt.registerFactory<GroupTicketsCubit>(() => GroupTicketsCubit(getIt()));

  // Le signal « une réservation a changé » : l'onglet Voyages s'y accroche
  // pour relire ses billets après une annulation.
  getIt.registerLazySingleton<BookingRefresh>(BookingRefresh.new);
  getIt.registerFactory<CancelTicketCubit>(
    () => CancelTicketCubit(getIt(), getIt()),
  );

  getIt.registerFactory<AssistanceRequestCubit>(
    () => AssistanceRequestCubit(getIt()),
  );
  getIt.registerFactory<AssistanceStatusCubit>(
    () => AssistanceStatusCubit(getIt()),
  );
  getIt.registerFactory<AssistanceRequestsCubit>(
    () => AssistanceRequestsCubit(getIt()),
  );

  // Le PDF officiel des billets — contrat provisoire, en attente de MEZ-82.
  // Un seul cubit pour toute l'app : la liste, le détail et les billets du
  // groupe voient le même téléchargement, et n'en lancent jamais deux pour
  // le même billet.
  getIt.registerLazySingleton<TicketDocumentRemoteDataSource>(
    () => TicketDocumentRemoteDataSource(getIt()),
  );
  getIt.registerLazySingleton<TicketDocumentStore>(TicketDocumentStore.new);
  getIt.registerLazySingleton<TicketDocumentRepository>(
    () => TicketDocumentRepositoryImpl(remote: getIt(), store: getIt()),
  );
  getIt.registerLazySingleton<TicketDocumentCubit>(
    () => TicketDocumentCubit(getIt()),
  );
}

/// Feature `payment` : le catalogue des moyens de paiement.
///
/// Une seule chaine pour l'instant — data source, dépôt, Bloc — parce que
/// seul `GET payments/methods` est branché. L'initiation et la
/// vérification viendront s'ajouter ici, sur le même data source.
void _registerPaymentFeature() {
  getIt.registerLazySingleton<PaymentRemoteDataSource>(
    () => PaymentRemoteDataSource(getIt()),
  );

  getIt.registerLazySingleton<PaymentRepository>(
    () => PaymentRepositoryImpl(getIt()),
  );

  getIt.registerFactory<PaymentCatalogBloc>(() => PaymentCatalogBloc(getIt()));

  getIt.registerFactory<PaymentFlowBloc>(() => PaymentFlowBloc(getIt()));
}

/// Feature `language` : même chaîne que le thème — un data source
/// SharedPreferences, un repository, un bloc.
void _registerLanguageFeature() {
  getIt.registerLazySingleton<LanguageLocalDataSource>(
    () => LanguageLocalDataSourceImpl(getIt()),
  );

  getIt.registerLazySingleton<LanguageRepository>(
    () => LanguageRepositoryImpl(getIt()),
  );

  getIt.registerFactory<LanguageBloc>(() => LanguageBloc(getIt()));
}

/// Feature `auth` : le flux OTP + PIN documenté dans
/// `docs/mintlify/authentication.mdx`.
///
/// Deux blocs, deux durées de vie : [AuthBloc] porte la session tant que
/// l'app tourne (d'où le singleton — le routeur et les écrans lisent le
/// même), [AuthFlowBloc] ne vit que le temps d'un parcours d'inscription
/// ou de connexion (d'où la fabrique : chaque parcours repart propre).
void _registerAuthFeature() {
  getIt.registerLazySingleton<AuthRemoteDataSource>(
    () => AuthRemoteDataSourceImpl(getIt()),
  );

  getIt.registerLazySingleton<AuthLocalDataSource>(
    () => AuthLocalDataSourceImpl(getIt()),
  );

  getIt.registerLazySingleton<DeviceInfoDataSource>(
    () => DeviceInfoDataSourceImpl(getIt(), getIt()),
  );

  getIt.registerLazySingleton<AuthRepository>(
    () => AuthRepositoryImpl(getIt(), getIt(), getIt()),
  );

  getIt.registerLazySingleton<AuthBloc>(() => AuthBloc(getIt()));
  getIt.registerFactory<AuthFlowBloc>(() => AuthFlowBloc(getIt()));
}
