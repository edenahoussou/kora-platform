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
import '../../features/booking/data/datasources/booking_data_source.dart';
import '../../features/booking/data/repositories/booking_repository_impl.dart';
import '../../features/booking/domain/repositories/booking_repository.dart';
import '../../features/booking/domain/usecases/get_cities_usecase.dart';
import '../../features/booking/domain/usecases/get_next_trip_usecase.dart';
import '../../features/booking/domain/usecases/get_recent_cities_usecase.dart';
import '../../features/booking/domain/usecases/get_seats_usecase.dart';
import '../../features/booking/domain/usecases/search_departures_usecase.dart';
import '../../features/booking/presentation/bloc/departures_bloc.dart';
import '../../features/booking/presentation/bloc/seats_bloc.dart';
import '../../features/home/presentation/bloc/home_bloc.dart';
import '../../features/theme/data/datasources/theme_local_data_source.dart';
import '../../features/theme/data/repositories/theme_repository_impl.dart';
import '../../features/theme/domain/repositories/theme_repository.dart';
import '../../features/theme/presentation/bloc/theme_bloc.dart';
import '../constants/api_constants.dart';
import '../constants/storage_keys.dart';
import '../env/app_env.dart';

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
  _registerBookingFeature();
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

  getIt.registerLazySingleton<FlutterSecureStorage>(
    () => const FlutterSecureStorage(),
  );

  getIt.registerLazySingleton<Connectivity>(() => Connectivity());

  // Lit le modèle et l'identifiant de l'appareil, exigés par l'API à
  // chaque connexion (voir DeviceInfoDataSource).
  getIt.registerLazySingleton<DeviceInfoPlugin>(() => DeviceInfoPlugin());
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
/// **C'est ici, et nulle part ailleurs, que l'app décide de travailler sur
/// des données mockées.** Au Sprint 2, seule la ligne
/// [BookingMockDataSource] deviendra `BookingRemoteDataSourceImpl(getIt())` :
/// repository, use cases, Blocs et écrans resteront inchangés.
void _registerBookingFeature() {
  getIt.registerLazySingleton<BookingDataSource>(() => BookingMockDataSource());

  getIt.registerLazySingleton<BookingRepository>(
    () => BookingRepositoryImpl(getIt()),
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

  getIt.registerFactory<HomeBloc>(() => HomeBloc(getIt(), getIt(), getIt()));
  getIt.registerFactory<DeparturesBloc>(() => DeparturesBloc(getIt(), getIt()));
  getIt.registerFactory<SeatsBloc>(() => SeatsBloc(getIt()));
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
