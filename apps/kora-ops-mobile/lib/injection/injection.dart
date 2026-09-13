import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show DioClient, NetworkConfig, AuthInterceptor;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:get_it/get_it.dart';
import 'package:logger/logger.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../app/config/app_config.dart';
import '../app/theme/theme_cubit.dart';
import '../core/network/network_client.dart';
import '../core/network/session_expiry_interceptor.dart';
import '../features/auth/data/datasources/auth_local_datasource.dart';
import '../features/auth/data/datasources/auth_remote_datasource.dart';
import '../features/auth/data/datasources/device_info_datasource.dart';
import '../features/auth/data/repositories/auth_repository_impl.dart';
import '../features/auth/domain/repositories/auth_repository.dart';
import '../features/auth/presentation/bloc/auth_flow_bloc.dart';
import '../features/auth/presentation/cubit/auth_cubit.dart';
import '../features/manifeste/presentation/cubit/manifeste_cubit.dart';
import '../features/ops/presentation/cubit/selected_depart_cubit.dart';
import '../features/printing/data/services/mock_barcode_scanner.dart';
import '../features/printing/data/services/mock_thermal_printer.dart';
import '../features/printing/data/services/p501_barcode_scanner.dart';
import '../features/printing/data/services/p501_thermal_printer.dart';
import '../features/printing/domain/services/barcode_scanner.dart';
import '../features/printing/domain/services/thermal_printer.dart';
import '../features/printing/domain/usecases/print_ticket.dart';
import '../features/printing/domain/usecases/scan_barcode.dart';
import '../features/printing/presentation/cubit/printing_cubit.dart';
import '../features/scan/presentation/cubit/scan_boarding_cubit.dart';
import '../features/synchro/presentation/cubit/sync_cubit.dart';

final GetIt getIt = GetIt.instance;

/// Flux d'injection (voir document.md § Dependency Injection) :
///
///   FlutterSecureStorage / SharedPreferences / DeviceInfoPlugin
///         ↓
///   LocalDataSource / DeviceInfoDataSource
///         ↓
///   DioClient (flutter_networking) → RemoteDataSource
///         ↓
///   Repository (implémentation)
///         ↓
///   Cubit/Bloc — appelé directement, sans UseCase intermédiaire pour
///   l'auth (voir docstring d'AuthFlowBloc)
///         ↓
///   UI
Future<void> configureDependencies() async {
  // --- Services externes ---
  final SharedPreferences preferences = await SharedPreferences.getInstance();
  getIt.registerSingleton<SharedPreferences>(preferences);

  const FlutterSecureStorage secureStorage = FlutterSecureStorage();
  getIt.registerSingleton<FlutterSecureStorage>(secureStorage);

  getIt.registerSingleton<DeviceInfoPlugin>(DeviceInfoPlugin());

  // --- Feature: auth ---
  //
  // Le token vit dans FlutterSecureStorage (jamais SharedPreferences, et
  // jamais en dur dans le code — voir périmètre du ticket). AuthLocalDataSource
  // est construit avant le client réseau : c'est lui qui fournit
  // `getAccessToken` au DioClient, sans dépendance circulaire (il ne
  // dépend lui-même que du stockage sécurisé, pas du réseau).
  getIt.registerLazySingleton<AuthLocalDataSource>(
    () => AuthLocalDataSourceImpl(getIt<FlutterSecureStorage>()),
  );
  getIt.registerLazySingleton<DeviceInfoDataSource>(
    () => DeviceInfoDataSourceImpl(
      getIt<DeviceInfoPlugin>(),
      getIt<FlutterSecureStorage>(),
    ),
  );

  final DioClient authDioClient = DioClient(
    config: NetworkConfig(
      baseUrl: AppConfig.instance.apiBaseUrl,
      enableLogging: AppConfig.instance.enableLogging,
    ),
    getAccessToken: () async {
      final session = await getIt<AuthLocalDataSource>().readSession();
      return session?.token;
    },
    logger: Logger(),
  );

  // Un 401 sur N'IMPORTE QUEL appel passant par ce client déclenche la
  // purge de session — un seul point pour toute l'app, voir docstring de
  // SessionExpiryInterceptor. Ajouté après la construction : Dio exécute
  // les intercepteurs d'erreur dans l'ordre inverse de leur ajout, donc
  // celui-ci voit l'erreur avant que les autres ne la transforment — d'où
  // la vérification sur le code HTTP brut plutôt que sur le type déjà
  // traduit.
  authDioClient.dio.interceptors.add(
    SessionExpiryInterceptor(
      onSessionExpired: () => getIt<AuthCubit>().sessionExpired(),
    ),
  );

  getIt.registerLazySingleton<AuthRemoteDataSource>(
    () => AuthRemoteDataSourceImpl(authDioClient.dio),
  );

  getIt.registerLazySingleton<AuthRepository>(
    () => AuthRepositoryImpl(
      getIt<AuthRemoteDataSource>(),
      getIt<AuthLocalDataSource>(),
      getIt<DeviceInfoDataSource>(),
    ),
  );

  getIt.registerSingleton<AuthCubit>(AuthCubit(getIt<AuthRepository>()));
  getIt.registerLazySingleton<AuthFlowBloc>(
    () => AuthFlowBloc(getIt<AuthRepository>()),
  );

  // --- Client réseau historique (features encore mockées : ops,
  // manifeste, scan, synchro, impression — voir MEZ-11). Même
  // AuthInterceptor que le client dédié à l'auth : dès qu'une de ces
  // features branchera un vrai endpoint, l'en-tête Authorization suit
  // automatiquement, sans changement à faire ici.
  final NetworkClient legacyNetworkClient = NetworkClient(
    baseUrl: AppConfig.instance.apiBaseUrl,
    enableLogging: AppConfig.instance.enableLogging,
  );
  legacyNetworkClient.addInterceptor(
    AuthInterceptor(
      getAccessToken: () async {
        final session = await getIt<AuthLocalDataSource>().readSession();
        return session?.token;
      },
    ),
  );
  getIt.registerSingleton<NetworkClient>(legacyNetworkClient);

  // --- Thème (dark mode auto + bascule manuelle) ---
  getIt.registerSingleton<ThemeCubit>(ThemeCubit(getIt<SharedPreferences>()));

  // --- Feature: printing (adaptateurs P501 — MEZ-10) ---
  //
  // Switch Mock/P501 piloté par AppConfig.useMockHardware, lui-même résolu
  // depuis --dart-define=USE_MOCK_HARDWARE (voir app/config/app_config.dart
  // et document.md § Adaptateurs P501). L'app compile et tourne avec le
  // Mock par défaut en dev/staging.
  if (AppConfig.instance.useMockHardware) {
    getIt.registerLazySingleton<BarcodeScanner>(MockBarcodeScanner.new);
    getIt.registerLazySingleton<ThermalPrinter>(MockThermalPrinter.new);
  } else {
    getIt.registerLazySingleton<BarcodeScanner>(P501BarcodeScanner.new);
    getIt.registerLazySingleton<ThermalPrinter>(P501ThermalPrinter.new);
  }

  getIt.registerLazySingleton<ScanBarcode>(
      () => ScanBarcode(getIt<BarcodeScanner>()));
  getIt.registerLazySingleton<PrintTicket>(
      () => PrintTicket(getIt<ThermalPrinter>()));

  getIt.registerFactory<PrintingCubit>(
    () => PrintingCubit(
        scanBarcode: getIt<ScanBarcode>(), printTicket: getIt<PrintTicket>()),
  );

  // --- Feature: ops / manifeste / scan / synchro (MEZ-11 — écrans mockés) ---
  //
  // Tous singletons : partagés entre les 5 onglets de la bottom nav et les
  // écrans empilés au-dessus (détail passager, résultat de scan) — voir
  // document.md § Navigation et app/app.dart.
  getIt.registerSingleton<SelectedDepartCubit>(SelectedDepartCubit());
  getIt.registerSingleton<SyncCubit>(SyncCubit());
  getIt.registerSingleton<ManifesteCubit>(
    ManifesteCubit(
        selectedDepartCubit: getIt<SelectedDepartCubit>(),
        syncCubit: getIt<SyncCubit>()),
  );
  getIt.registerSingleton<ScanBoardingCubit>(
    ScanBoardingCubit(
        scanBarcode: getIt<ScanBarcode>(),
        manifesteCubit: getIt<ManifesteCubit>()),
  );
}
