import '../../../app/config/terminal_mode.dart';
import '../../auth/domain/entities/auth_user.dart';

/// Le compte connecté doit-il lier un terminal P501 avant d'accéder à
/// l'app ?
///
/// - Convoyeur (et tout rôle terrain autre que chauffeur) : oui, toujours.
/// - Chauffeur seul : non — il travaille depuis son téléphone (planning,
///   feuille de route, carnet de bord, incidents), sa session est portée
///   par son jeton.
/// - Les deux sont forcés si le serveur l'impose
///   (`OPS_FORCE_TERMINAL_FOR_ALL=true`, renvoyé dans `requires_terminal`)
///   ou si ce build l'impose (`--dart-define=FORCE_TERMINAL_FOR_ALL=true`,
///   voir [kForceTerminalForAll]).
///
/// Seule source de vérité partagée par `AuthGuard`,
/// `resolveAuthDestination` et le Profil.
bool needsTerminal(AuthUser user) =>
    kForceTerminalForAll || user.requiresTerminal;
