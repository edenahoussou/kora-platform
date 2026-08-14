import 'package:get_it/get_it.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../app/config/app_config.dart';
import '../core/network/network_client.dart';
import '../core/services/dialog_service.dart';
import '../features/auth/data/datasources/auth_local_datasource.dart';
import '../features/auth/data/repositories/auth_repository_impl.dart';
import '../features/auth/domain/repositories/auth_repository.dart';
import '../features/auth/domain/usecases/get_current_user.dart';
import '../features/auth/domain/usecases/login_user.dart';
import '../features/auth/domain/usecases/logout_user.dart';
import '../features/auth/presentation/cubit/auth_cubit.dart';

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

  getIt.registerSingleton<DialogService>(DialogService());

  // --- Feature: auth ---
  getIt.registerLazySingleton<AuthLocalDataSource>(
    () => AuthLocalDataSource(getIt<SharedPreferences>()),
  );
  getIt.registerLazySingleton<AuthRepository>(
    () => AuthRepositoryImpl(getIt<AuthLocalDataSource>()),
  );
  getIt.registerLazySingleton<LoginUser>(() => LoginUser(getIt<AuthRepository>()));
  getIt.registerLazySingleton<LogoutUser>(() => LogoutUser(getIt<AuthRepository>()));
  getIt.registerLazySingleton<GetCurrentUser>(() => GetCurrentUser(getIt<AuthRepository>()));

  getIt.registerFactory<AuthCubit>(
    () => AuthCubit(
      loginUser: getIt<LoginUser>(),
      logoutUser: getIt<LogoutUser>(),
      getCurrentUser: getIt<GetCurrentUser>(),
    ),
  );
}

/// Raccourci pour accéder au [DialogService] depuis un widget sans
/// `getIt<DialogService>()` répété partout.
extension GetItDialogServiceX on GetIt {
  DialogService get dialogService => get<DialogService>();
}
