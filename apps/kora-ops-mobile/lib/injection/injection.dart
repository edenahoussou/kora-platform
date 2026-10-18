import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show DioClient, NetworkConfig, AuthInterceptor;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:get_it/get_it.dart';
import 'package:logger/logger.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../app/config/app_config.dart';
import '../app/config/terminal_mode.dart';
import '../app/theme/theme_cubit.dart';
import '../core/database/app_database.dart';
import '../core/network/network_client.dart';
import '../core/network/session_expiry_interceptor.dart';
import '../core/network/terminal_header_interceptor.dart';
import '../core/services/push_notification_service.dart';
import '../features/auth/data/datasources/auth_local_datasource.dart';
import '../features/auth/data/datasources/auth_remote_datasource.dart';
import '../features/auth/data/datasources/device_info_datasource.dart';
import '../features/auth/data/repositories/auth_repository_impl.dart';
import '../features/auth/domain/repositories/auth_repository.dart';
import '../features/auth/presentation/bloc/auth_flow_bloc.dart';
import '../features/auth/presentation/cubit/auth_cubit.dart';
import '../features/boarding/data/datasources/boarding_local_datasource.dart';
import '../features/boarding/data/datasources/boarding_remote_datasource.dart';
import '../features/boarding/data/repositories/boarding_repository_impl.dart';
import '../features/boarding/data/services/ed25519_ticket_qr_verifier.dart';
import '../features/boarding/domain/repositories/boarding_repository.dart';
import '../features/boarding/domain/services/ticket_qr_verifier.dart';
import '../features/border_manifest/data/datasources/border_manifest_pdf_remote_datasource.dart';
import '../features/border_manifest/data/datasources/border_manifest_print_local_datasource.dart';
import '../features/border_manifest/data/repositories/border_manifest_pdf_repository_impl.dart';
import '../features/border_manifest/data/repositories/border_manifest_print_repository_impl.dart';
import '../features/border_manifest/domain/repositories/border_manifest_pdf_repository.dart';
import '../features/border_manifest/domain/repositories/border_manifest_print_repository.dart';
import '../features/border_manifest/presentation/cubit/border_manifest_print_cubit.dart';
import '../features/incidents/data/datasources/incident_local_datasource.dart';
import '../features/incidents/data/datasources/incident_remote_datasource.dart';
import '../features/incidents/data/repositories/incident_repository_impl.dart';
import '../features/incidents/domain/repositories/incident_repository.dart';
import '../features/manifeste/data/datasources/manifeste_local_datasource.dart';
import '../features/manifeste/data/datasources/manifeste_remote_datasource.dart';
import '../features/manifeste/data/datasources/passenger_correction_local_datasource.dart';
import '../features/manifeste/data/datasources/passenger_correction_remote_datasource.dart';
import '../features/manifeste/data/repositories/manifeste_repository_impl.dart';
import '../features/manifeste/data/repositories/passenger_correction_repository_impl.dart';
import '../features/manifeste/domain/repositories/manifeste_repository.dart';
import '../features/manifeste/domain/repositories/passenger_correction_repository.dart';
import '../features/manifeste/presentation/cubit/manifeste_cubit.dart';
import '../features/notifications/data/datasources/notification_local_datasource.dart';
import '../features/notifications/data/datasources/notification_remote_datasource.dart';
import '../features/notifications/data/repositories/notification_repository_impl.dart';
import '../features/notifications/domain/repositories/notification_repository.dart';
import '../features/notifications/presentation/cubit/notification_cubit.dart';
import '../features/notifications/presentation/cubit/sent_notification_cubit.dart';
import '../features/onboarding/data/onboarding_store.dart';
import '../features/ops/data/datasources/departure_remote_datasource.dart';
import '../features/ops/data/datasources/selected_departure_local_datasource.dart';
import '../features/ops/data/repositories/departure_repository_impl.dart';
import '../features/ops/domain/repositories/departure_repository.dart';
import '../features/ops/presentation/cubit/departure_list_cubit.dart';
import '../features/ops/presentation/cubit/selected_depart_cubit.dart';
import '../features/printing/data/datasources/boarding_pdf_remote_datasource.dart';
import '../features/printing/data/repositories/boarding_pdf_repository_impl.dart';
import '../features/printing/data/services/mock_barcode_scanner.dart';
import '../features/printing/data/services/mock_thermal_printer.dart';
import '../features/printing/data/services/p501_barcode_scanner.dart';
import '../features/printing/data/services/p501_thermal_printer.dart';
import '../features/printing/data/services/phone_camera_barcode_scanner.dart';
import '../features/printing/domain/repositories/boarding_pdf_repository.dart';
import '../features/printing/domain/services/barcode_scanner.dart';
import '../features/printing/domain/services/thermal_printer.dart';
import '../features/printing/domain/usecases/print_ticket.dart';
import '../features/printing/domain/usecases/scan_barcode.dart';
import '../features/printing/presentation/cubit/printing_cubit.dart';
import '../features/scan/presentation/cubit/scan_boarding_cubit.dart';
import '../features/seat_release/data/datasources/seat_release_local_datasource.dart';
import '../features/seat_release/data/datasources/seat_release_remote_datasource.dart';
import '../features/seat_release/data/repositories/seat_release_repository_impl.dart';
import '../features/seat_release/domain/repositories/seat_release_repository.dart';
import '../features/support_chat/data/support_chat_remote_datasource.dart';
import '../features/synchro/data/datasources/sync_remote_datasource.dart';
import '../features/synchro/data/repositories/sync_repository_impl.dart';
import '../features/synchro/domain/repositories/sync_repository.dart';
import '../features/synchro/presentation/cubit/sync_cubit.dart';
import '../features/terminal/data/datasources/terminal_local_datasource.dart';
import '../features/terminal/data/datasources/terminal_remote_datasource.dart';
import '../features/terminal/data/repositories/terminal_repository_impl.dart';
import '../features/terminal/domain/repositories/terminal_repository.dart';
import '../features/terminal/presentation/cubit/terminal_cubit.dart';
import '../features/tracking/data/datasources/tracking_local_datasource.dart';
import '../features/tracking/data/repositories/tracking_repository_impl.dart';
import '../features/tracking/data/services/geolocator_location_provider.dart';
import '../features/tracking/data/services/mock_location_provider.dart';
import '../features/tracking/domain/repositories/tracking_repository.dart';
import '../features/tracking/domain/services/location_provider.dart';
import '../features/tracking/presentation/cubit/tracking_cubit.dart';

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

  getIt.registerLazySingleton<OnboardingStore>(
    () => OnboardingStore(getIt<SharedPreferences>()),
  );

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

  // --- Feature: terminal P501 (MEZ-91) ---
  //
  // Base sqlite locale (manifeste en cache, file de sync, journal
  // d'activité — voir AppDatabase, tables ajoutées au fil des étapes
  // MEZ-91). Un seul singleton, comme SharedPreferences/FlutterSecureStorage.
  getIt.registerSingleton<AppDatabase>(AppDatabase());

  getIt.registerLazySingleton<TerminalLocalDataSource>(
    () => TerminalLocalDataSourceImpl(
      getIt<FlutterSecureStorage>(),
      getIt<AppDatabase>(),
    ),
  );

  // Client réseau dédié aux endpoints `ops/*` et `ops-mobile/terminal/*`
  // (même convention que `authDioClient`) : Authorization (Bearer, comme
  // l'auth) + X-Terminal-Serial (dès qu'un terminal est lié — voir
  // TerminalHeaderInterceptor). Remplace progressivement `legacyNetworkClient`
  // pour les features ops/manifeste/scan/synchro au fil des étapes MEZ-91.
  final DioClient opsDioClient = DioClient(
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
  opsDioClient.dio.interceptors.add(
    TerminalHeaderInterceptor(
      serialNumberProvider: () =>
          getIt<TerminalLocalDataSource>().readSerialNumber(),
    ),
  );
  // Même raison que pour authDioClient : un 401 ici doit aussi purger la
  // session (ex. token expiré pendant un scan hors ligne prolongé).
  opsDioClient.dio.interceptors.add(
    SessionExpiryInterceptor(
      onSessionExpired: () => getIt<AuthCubit>().sessionExpired(),
    ),
  );

  getIt.registerLazySingleton<TerminalRemoteDataSource>(
    () => TerminalRemoteDataSourceImpl(opsDioClient.dio),
  );

  getIt.registerLazySingleton<TerminalRepository>(
    () => TerminalRepositoryImpl(
      getIt<TerminalRemoteDataSource>(),
      getIt<TerminalLocalDataSource>(),
      getIt<DeviceInfoDataSource>(),
    ),
  );

  getIt.registerSingleton<TerminalCubit>(
    TerminalCubit(getIt<TerminalRepository>(), getIt<AuthCubit>()),
  );

  // --- Client réseau historique — plus aucune feature ne le consomme
  // depuis cette étape (synchro est passée à opsDioClient ci-dessus,
  // comme manifeste/scan avant elle — voir MEZ-91/MEZ-88). Laissé
  // enregistré plutôt que supprimé : Tournée/Descente anticipée (MEZ-11,
  // encore mockées) pourraient s'en servir le temps de leur propre
  // réalisation, ou basculer directement sur opsDioClient — à ta
  // convenance. Aucun risque à le laisser tel quel, il n'est plus résolu
  // nulle part.
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
  } else if (AppConfig.instance.terminalMode == TerminalMode.phone) {
    getIt.registerLazySingleton<BarcodeScanner>(PhoneCameraBarcodeScanner.new);
    // Pas d'imprimante thermique en mode téléphone (voir
    // BoardingPdfRepository, qui remplace l'impression pour le flux réel
    // de boarding). ThermalPrinter reste nécessaire à PrinterTestPage
    // (écran de diagnostic P501 uniquement) : Mock ici pour ne pas le
    // faire planter en mode téléphone, où aucun matériel réel n'existe.
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

  // --- Feature: ops (MEZ-92 — départs réels : GET /ops/departures,
  // GET /ops/departures/{uuid}) ---
  getIt.registerLazySingleton<DepartureRemoteDataSource>(
    () => DepartureRemoteDataSourceImpl(opsDioClient.dio),
  );
  getIt.registerLazySingleton<DepartureRepository>(
    () => DepartureRepositoryImpl(getIt<DepartureRemoteDataSource>()),
  );
  getIt.registerSingleton<DepartureListCubit>(
    DepartureListCubit(getIt<DepartureRepository>()),
  );

  getIt.registerLazySingleton<SelectedDepartureLocalDataSource>(
    () => SelectedDepartureLocalDataSourceImpl(getIt<FlutterSecureStorage>()),
  );
  getIt.registerSingleton<SelectedDepartCubit>(
    SelectedDepartCubit(
      getIt<SelectedDepartureLocalDataSource>(),
      getIt<AuthCubit>(),
    ),
  );

  // --- Feature: boarding (MEZ-91 — réel : POST ops/boarding, avec
  // anti-double-scan local, vérification d'authenticité hors ligne
  // (Ed25519, voir TicketQrVerifier) et file hors ligne — voir
  // BoardingRepositoryImpl). Enregistrée avant Synchro/Manifeste : toutes
  // deux en dépendent.
  getIt.registerLazySingleton<BoardingRemoteDataSource>(
    () => BoardingRemoteDataSourceImpl(opsDioClient.dio),
  );
  getIt.registerLazySingleton<BoardingLocalDataSource>(
    () => BoardingLocalDataSourceImpl(getIt<AppDatabase>()),
  );
  getIt.registerLazySingleton<TicketQrVerifier>(
    () => Ed25519TicketQrVerifier(
      publicKeyBase64: AppConfig.instance.ticketSigningPublicKeyBase64,
    ),
  );
  getIt.registerLazySingleton<BoardingRepository>(
    () => BoardingRepositoryImpl(
      getIt<BoardingRemoteDataSource>(),
      getIt<BoardingLocalDataSource>(),
      getIt<TicketQrVerifier>(),
    ),
  );

  // PDF de confirmation d'embarquement — mode téléphone uniquement, voir
  // ticket "Supporter le scan par caméra téléphone en alternative au
  // P501". Même opsDioClient que ci-dessus (Authorization + X-Terminal-
  // Serial déjà branchés).
  getIt.registerLazySingleton<BoardingPdfRemoteDataSource>(
    () => BoardingPdfRemoteDataSourceImpl(opsDioClient.dio),
  );
  getIt.registerLazySingleton<BoardingPdfRepository>(
    () => BoardingPdfRepositoryImpl(getIt<BoardingPdfRemoteDataSource>()),
  );

  // --- Feature: border_manifest (MEZ-94 — manifeste frontière) ---
  //
  // P501 : impression thermique 58 mm, 3 exemplaires, reprise sans doublon
  // (jobs persistés dans sqlite — voir BorderManifestPrintRepositoryImpl).
  // Mode téléphone : PDF A4 généré par le serveur (même opsDioClient que le
  // PDF de confirmation d'embarquement ci-dessus).
  getIt.registerLazySingleton<BorderManifestPrintLocalDataSource>(
    () => BorderManifestPrintLocalDataSourceImpl(getIt<AppDatabase>()),
  );
  getIt.registerLazySingleton<BorderManifestPrintRepository>(
    () => BorderManifestPrintRepositoryImpl(
      getIt<BorderManifestPrintLocalDataSource>(),
    ),
  );
  getIt.registerLazySingleton<BorderManifestPdfRemoteDataSource>(
    () => BorderManifestPdfRemoteDataSourceImpl(opsDioClient.dio),
  );
  getIt.registerLazySingleton<BorderManifestPdfRepository>(
    () => BorderManifestPdfRepositoryImpl(
      getIt<BorderManifestPdfRemoteDataSource>(),
    ),
  );
  // Un cubit par ouverture de l'écran (factory) : l'état d'impression
  // durable vit dans sqlite, pas dans le cubit.
  getIt.registerFactory<BorderManifestPrintCubit>(
    () => BorderManifestPrintCubit(
      printer: getIt<ThermalPrinter>(),
      repository: getIt<BorderManifestPrintRepository>(),
      terminalRepository: getIt<TerminalRepository>(),
      currentUserUuid: () async {
        final AuthState auth = getIt<AuthCubit>().state;
        return auth is AuthAuthenticated ? auth.user.uuid : null;
      },
    ),
  );

  // --- Feature: seat_release (MEZ-87 — réel : POST ops/seat-releases,
  // POST ops/seat-releases/no-show-closure, avec anti-doublon local et
  // file hors ligne — voir SeatReleaseRepositoryImpl). Enregistrée avant
  // Synchro : elle en dépend, comme Boarding.
  getIt.registerLazySingleton<SeatReleaseRemoteDataSource>(
    () => SeatReleaseRemoteDataSourceImpl(opsDioClient.dio),
  );
  getIt.registerLazySingleton<SeatReleaseLocalDataSource>(
    () => SeatReleaseLocalDataSourceImpl(getIt<AppDatabase>()),
  );
  getIt.registerLazySingleton<SeatReleaseRepository>(
    () => SeatReleaseRepositoryImpl(
      getIt<SeatReleaseRemoteDataSource>(),
      getIt<SeatReleaseLocalDataSource>(),
    ),
  );

  // --- Feature: incidents (MEZ-98 — POST ops/incidents, idempotent via
  // incident_uuid, file hors ligne — voir IncidentRepositoryImpl).
  // Enregistrée avant Synchro : elle en dépend, comme Boarding/SeatRelease.
  getIt.registerLazySingleton<IncidentRemoteDataSource>(
    () => IncidentRemoteDataSourceImpl(opsDioClient.dio),
  );
  getIt.registerLazySingleton<IncidentLocalDataSource>(
    () => IncidentLocalDataSourceImpl(getIt<AppDatabase>()),
  );
  getIt.registerLazySingleton<IncidentRepository>(
    () => IncidentRepositoryImpl(
      getIt<IncidentRemoteDataSource>(),
      getIt<IncidentLocalDataSource>(),
    ),
  );

  // --- Feature: synchro (MEZ-88 — réel : POST ops/sync/batches, envoi
  // par lot des files boarding_operations + seat_release_operations en
  // un seul lot, détection de connectivité continue et reprise
  // automatique — voir SyncCubit.bootstrap, appelé depuis SplashPage) ---
  getIt.registerLazySingleton<SyncRemoteDataSource>(
    () => SyncRemoteDataSourceImpl(opsDioClient.dio),
  );
  getIt.registerLazySingleton<SyncRepository>(
    () => SyncRepositoryImpl(
      getIt<SyncRemoteDataSource>(),
      getIt<BoardingRepository>(),
      getIt<SeatReleaseRepository>(),
    ),
  );
  getIt.registerSingleton<SyncCubit>(
    SyncCubit(
      getIt<SyncRepository>(),
      getIt<BoardingRepository>(),
      getIt<SeatReleaseRepository>(),
      getIt<IncidentRepository>(),
    ),
  );

  // --- Feature: tracking (MEZ-101 — suivi de position du convoyeur en
  // arrière-plan, consomme le lot MEZ-100 : POST ops/tracking/positions/
  // batch). GeolocatorLocationProvider sert IDENTIQUEMENT P501 et
  // téléphone (voir sa docstring) — seule useMockHardware bascule vers le
  // Mock, comme pour BarcodeScanner/ThermalPrinter ci-dessus.
  // TrackingRepositoryImpl.bootstrap() (appelé depuis SplashPage) reprend
  // un suivi resté actif après un crash/redémarrage.
  getIt.registerLazySingleton<LocationProvider>(
    AppConfig.instance.useMockHardware
        ? MockLocationProvider.new
        : GeolocatorLocationProvider.new,
  );
  getIt.registerLazySingleton<TrackingLocalDataSource>(
    () => TrackingLocalDataSourceImpl(getIt<AppDatabase>()),
  );
  getIt.registerLazySingleton<TrackingRepository>(
    () => TrackingRepositoryImpl(
      getIt<TrackingLocalDataSource>(),
      getIt<LocationProvider>(),
    ),
  );
  getIt.registerSingleton<TrackingCubit>(
    TrackingCubit(
      getIt<TrackingRepository>(),
      getIt<TerminalLocalDataSource>(),
      getIt<SelectedDepartCubit>(),
    ),
  );

  // --- Feature: manifeste (MEZ-91 — réel : GET ops/departures/{uuid}/
  // manifest, avec repli automatique sur le cache sqlite hors ligne) ---
  getIt.registerLazySingleton<ManifesteRemoteDataSource>(
    () => ManifesteRemoteDataSourceImpl(opsDioClient.dio),
  );
  getIt.registerLazySingleton<ManifesteLocalDataSource>(
    () => ManifesteLocalDataSourceImpl(getIt<AppDatabase>()),
  );
  getIt.registerLazySingleton<ManifesteRepository>(
    () => ManifesteRepositoryImpl(
      getIt<ManifesteRemoteDataSource>(),
      getIt<ManifesteLocalDataSource>(),
      isOnline: () async => getIt<SyncCubit>().state.isOnline,
    ),
  );
  // Correction des informations passager par le convoyeur (avant/après
  // embarquement) — envoi immédiat, file locale si hors ligne.
  getIt.registerLazySingleton<PassengerCorrectionRemoteDataSource>(
    () => PassengerCorrectionRemoteDataSourceImpl(opsDioClient.dio),
  );
  getIt.registerLazySingleton<PassengerCorrectionLocalDataSource>(
    () => PassengerCorrectionLocalDataSourceImpl(getIt<AppDatabase>()),
  );
  getIt.registerLazySingleton<PassengerCorrectionRepository>(
    () => PassengerCorrectionRepositoryImpl(
      getIt<PassengerCorrectionRemoteDataSource>(),
      getIt<PassengerCorrectionLocalDataSource>(),
      getIt<ManifesteLocalDataSource>(),
    ),
  );
  getIt.registerSingleton<ManifesteCubit>(
    ManifesteCubit(
      selectedDepartCubit: getIt<SelectedDepartCubit>(),
      syncCubit: getIt<SyncCubit>(),
      repository: getIt<ManifesteRepository>(),
      passengerCorrectionRepository: getIt<PassengerCorrectionRepository>(),
    ),
  );

  getIt.registerSingleton<ScanBoardingCubit>(
    ScanBoardingCubit(
      scanBarcode: getIt<ScanBarcode>(),
      boardingRepository: getIt<BoardingRepository>(),
      manifesteCubit: getIt<ManifesteCubit>(),
      selectedDepartCubit: getIt<SelectedDepartCubit>(),
      terminalRepository: getIt<TerminalRepository>(),
    ),
  );

  // --- Feature: notifications (MEZ-97 — alertes opérationnelles, contrat
  // MEZ-96 : GET /notifications, GET /notifications/unread-count,
  // POST /notifications/{uuid}/read, POST /notifications/read-all).
  // Même opsDioClient que le reste des features ops (Authorization +
  // X-Terminal-Serial + purge de session sur 401 déjà branchés).
  getIt.registerLazySingleton<NotificationRemoteDataSource>(
    () => NotificationRemoteDataSourceImpl(opsDioClient.dio),
  );
  getIt.registerLazySingleton<NotificationLocalDataSource>(
    () => NotificationLocalDataSourceImpl(getIt<AppDatabase>()),
  );
  getIt.registerLazySingleton<NotificationRepository>(
    () => NotificationRepositoryImpl(
      getIt<NotificationRemoteDataSource>(),
      getIt<NotificationLocalDataSource>(),
    ),
  );
  getIt.registerSingleton<NotificationCubit>(
    NotificationCubit(getIt<NotificationRepository>(), getIt<AuthCubit>()),
  );
  getIt.registerLazySingleton<SupportChatRemoteDataSource>(
    () => SupportChatRemoteDataSourceImpl(opsDioClient.dio),
  );
  getIt.registerLazySingleton<PushNotificationService>(
    () => PushNotificationService(
      opsDioClient.dio,
      getIt<AuthLocalDataSource>(),
      getIt<AuthCubit>(),
      getIt<NotificationCubit>(),
    ),
  );
  getIt.registerSingleton<SentNotificationCubit>(
    SentNotificationCubit(getIt<NotificationRepository>()),
  );
}
