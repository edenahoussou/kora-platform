import '../../domain/entities/app_theme_mode.dart';
import '../../domain/repositories/theme_repository.dart';
import '../datasources/theme_local_data_source.dart';

/// Couche DATA : implémentation concrète de [ThemeRepository].
///
/// Elle fait le lien entre le "monde métier" (AppThemeMode, une entité
/// simple) et le "monde technique" (le data source, qui ne connaît que des
/// String). C'est le rôle d'un repository : traduire entre les deux.
class ThemeRepositoryImpl implements ThemeRepository {
  ThemeRepositoryImpl(this._localDataSource);

  final ThemeLocalDataSource _localDataSource;

  @override
  Future<AppThemeMode> getThemeMode() async {
    final saved = await _localDataSource.getSavedThemeMode();
    return AppThemeMode.fromStorageValue(saved);
  }

  @override
  Future<void> setThemeMode(AppThemeMode mode) async {
    await _localDataSource.saveThemeMode(mode.toStorageValue());
  }
}
