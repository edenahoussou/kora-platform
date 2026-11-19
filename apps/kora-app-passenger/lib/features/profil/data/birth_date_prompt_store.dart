import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/constants/storage_keys.dart';

/// Retient que l'invitation à renseigner sa date de naissance a été vue.
///
/// Une seule clé, non sensible, donc `SharedPreferences` — même choix
/// que l'onboarding. Ce qu'on retient ici n'est pas « la date est
/// remplie » (ça, le profil le dit) mais « on a déjà posé la question » :
/// une invitation qui revient à chaque lancement se fait fermer sans
/// être lue, et finit par agacer plus qu'elle ne convainc.
class BirthDatePromptStore {
  const BirthDatePromptStore(this._prefs);

  final SharedPreferences _prefs;

  bool get hasSeen => _prefs.getBool(StorageKeys.birthDatePrompt) ?? false;

  Future<void> markSeen() => _prefs.setBool(StorageKeys.birthDatePrompt, true);
}
