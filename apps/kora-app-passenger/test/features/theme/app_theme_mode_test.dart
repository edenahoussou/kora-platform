// Le thème au démarrage.
//
// KORA s'ouvre en gare, souvent en plein jour : le clair est le défaut,
// pas le réglage du téléphone. Un choix explicite, lui, doit survivre.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/theme/domain/entities/app_theme_mode.dart';
import 'package:kora_app_passenger/features/theme/presentation/bloc/theme_bloc.dart';

void main() {
  group('lecture du stockage', () {
    test('rien de stocké donne le mode clair', () {
      // Le cas de la toute première ouverture.
      expect(AppThemeMode.fromStorageValue(null), AppThemeMode.light);
      expect(AppThemeMode.fromStorageValue(''), AppThemeMode.light);
    });

    test('une valeur inconnue retombe sur le clair', () {
      // Une préférence écrite par une version antérieure, ou corrompue.
      expect(AppThemeMode.fromStorageValue('midnight'), AppThemeMode.light);
    });

    test('un choix explicite est relu tel quel', () {
      // Celui qui a choisi le sombre le garde : le défaut ne s'applique
      // qu'à ceux qui n'ont rien décidé.
      expect(AppThemeMode.fromStorageValue('dark'), AppThemeMode.dark);
      expect(AppThemeMode.fromStorageValue('light'), AppThemeMode.light);
      expect(AppThemeMode.fromStorageValue('system'), AppThemeMode.system);
    });

    test('ce qui est écrit se relit', () {
      for (final mode in AppThemeMode.values) {
        expect(AppThemeMode.fromStorageValue(mode.toStorageValue()), mode);
      }
    });
  });

  test('l’état initial part sur le clair', () {
    // Le temps que la préférence stockée arrive. Partir sur `system`
    // faisait clignoter l'app en sombre avant de repasser au clair.
    expect(const ThemeState().mode, AppThemeMode.light);
  });
}
