import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/constants/storage_keys.dart';

/// Retient que l'onboarding a été vu.
///
/// Une seule clé, non sensible, donc `SharedPreferences` : elle survit aux
/// redémarrages mais pas à une désinstallation, ce qui est le comportement
/// attendu — quelqu'un qui réinstalle l'app découvre à nouveau ce qu'elle
/// fait.
///
/// **Ne dépend pas de la session.** L'onboarding se voit avant d'avoir un
/// compte, et le revoir après une déconnexion serait absurde : la marque
/// n'a pas changé entre-temps.
class OnboardingStore {
  const OnboardingStore(this._prefs);

  final SharedPreferences _prefs;

  bool get hasSeen => _prefs.getBool(StorageKeys.onboardingSeen) ?? false;

  Future<void> markSeen() => _prefs.setBool(StorageKeys.onboardingSeen, true);
}
