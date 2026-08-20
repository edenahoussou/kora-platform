import '../../domain/entities/app_language.dart';
import '../../domain/repositories/language_repository.dart';
import '../datasources/language_local_data_source.dart';

/// Couche DATA : traduit entre l'entité [AppLanguage] et le code stocké.
class LanguageRepositoryImpl implements LanguageRepository {
  LanguageRepositoryImpl(this._localDataSource);

  final LanguageLocalDataSource _localDataSource;

  @override
  Future<AppLanguage> getLanguage() async {
    return AppLanguage.fromCode(await _localDataSource.getSavedLanguageCode());
  }

  @override
  Future<void> setLanguage(AppLanguage language) async {
    await _localDataSource.saveLanguageCode(language.code);
  }
}
