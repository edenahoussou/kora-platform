import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart' show kDebugMode;
import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:get_it/get_it.dart';
import 'package:shared_preferences/shared_preferences.dart';

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
