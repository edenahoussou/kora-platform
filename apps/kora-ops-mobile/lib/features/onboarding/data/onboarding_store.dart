import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/constants/storage_keys.dart';

/// Retient que l'onboarding a été vu.
///
/// Copie conforme du `OnboardingStore` de `kora-passager` : une seule clé,
/// non sensible, donc `SharedPreferences` — elle survit aux redémarrages
/// mais pas à une désinstallation, ce qui est le comportement attendu.
///
/// **Ne dépend pas de la session.** L'onboarding se voit avant la
/// connexion, et le revoir après une déconnexion (changement d'agent sur
/// le même terminal, par exemple) serait absurde : la marque n'a pas
/// changé entre-temps.
class OnboardingStore {
  const OnboardingStore(this._prefs);

  final SharedPreferences _prefs;

  bool get hasSeen => _prefs.getBool(StorageKeys.onboardingSeen) ?? false;

  Future<void> markSeen() => _prefs.setBool(StorageKeys.onboardingSeen, true);
}
