import 'package:get_it/get_it.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../app/config/app_config.dart';
import '../app/theme/theme_cubit.dart';
import '../core/network/network_client.dart';
import '../features/auth/data/datasources/auth_local_datasource.dart';
import '../features/auth/data/repositories/auth_repository_impl.dart';
import '../features/auth/domain/repositories/auth_repository.dart';
import '../features/auth/domain/usecases/get_current_device_info.dart';
import '../features/auth/domain/usecases/get_current_user.dart';
import '../features/auth/domain/usecases/get_saved_email.dart';
import '../features/auth/domain/usecases/logout_user.dart';
import '../features/auth/domain/usecases/send_otp.dart';
import '../features/auth/domain/usecases/verify_otp.dart';
import '../features/auth/domain/usecases/verify_pin.dart';
import '../features/auth/presentation/cubit/auth_cubit.dart';
import '../features/auth/presentation/cubit/login_cubit.dart';
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
///   NetworkClient / SharedPreferences
///         ↓
///   LocalDataSource
///         ↓
///   Repository (implémentation)
///         ↓
///   UseCase
///         ↓
///   Cubit/Bloc
///         ↓
///   UI
Future<void> configureDependencies() async {
  // --- Services externes ---
  final SharedPreferences preferences = await SharedPreferences.getInstance();
  getIt.registerSingleton<SharedPreferences>(preferences);

  getIt.registerSingleton<NetworkClient>(
    NetworkClient(
      baseUrl: AppConfig.instance.apiBaseUrl,
      enableLogging: AppConfig.instance.enableLogging,
    ),
  );

  // --- Thème (dark mode auto + bascule manuelle) ---
  getIt.registerSingleton<ThemeCubit>(ThemeCubit(getIt<SharedPreferences>()));

  // --- Feature: auth (PIN pur + OTP de confirmation d'appareil) ---
  getIt.registerLazySingleton<AuthLocalDataSource>(
    () => AuthLocalDataSource(getIt<SharedPreferences>()),
  );
  getIt.registerLazySingleton<AuthRepository>(
    () => AuthRepositoryImpl(getIt<AuthLocalDataSource>()),
  );
  getIt.registerLazySingleton<GetCurrentUser>(
      () => GetCurrentUser(getIt<AuthRepository>()));
  getIt.registerLazySingleton<GetSavedEmail>(
      () => GetSavedEmail(getIt<AuthRepository>()));
  getIt.registerLazySingleton<SendOtp>(() => SendOtp(getIt<AuthRepository>()));
  getIt.registerLazySingleton<VerifyOtp>(
      () => VerifyOtp(getIt<AuthRepository>()));
  getIt.registerLazySingleton<VerifyPin>(
      () => VerifyPin(getIt<AuthRepository>()));
  getIt.registerLazySingleton<GetCurrentDeviceInfo>(
    () => GetCurrentDeviceInfo(getIt<AuthRepository>()),
  );
  getIt.registerLazySingleton<LogoutUser>(
      () => LogoutUser(getIt<AuthRepository>()));

  getIt.registerSingleton<AuthCubit>(
    AuthCubit(
      getCurrentUser: getIt<GetCurrentUser>(),
      getSavedEmail: getIt<GetSavedEmail>(),
      logoutUser: getIt<LogoutUser>(),
    ),
  );

  getIt.registerSingleton<LoginCubit>(
    LoginCubit(
      getSavedEmail: getIt<GetSavedEmail>(),
      sendOtp: getIt<SendOtp>(),
      verifyOtp: getIt<VerifyOtp>(),
      verifyPin: getIt<VerifyPin>(),
      getCurrentDeviceInfo: getIt<GetCurrentDeviceInfo>(),
    ),
  );

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
