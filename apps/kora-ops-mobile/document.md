# KORA OPS Mobile (P501) — Documentation technique

## 1. Présentation

**KORA OPS Mobile / P501** est l'application terrain de **KORA EXPRESS**, destinée aux chauffeurs bus, convoyeurs et agences. Elle couvre : manifestes, validation par QR/code court, embarquement en mode offline, impression, dépose anticipée, synchronisation et statuts opérationnels.

Ce n'est **pas** KORA Driver (future application des conducteurs Ride) ni l'app passagers KORA EXPRESS (prototype web `KORA_App_Final.zip`).

## 2. Important — limites de cet environnement de génération

Ce scaffolding a été écrit entièrement à la main (pas de `flutter create`/`pub get`/`analyze`/`test` exécutés ici, le SDK Flutter n'étant pas disponible dans l'environnement qui a produit ce code). **Avant tout développement**, exécuter impérativement :

```bash
flutter pub get
flutter analyze
flutter test
```

et corriger les éventuels écarts de version d'API entre les packages listés dans `pubspec.yaml` (versions choisies à la date de rédaction) et les versions réellement résolues par pub.

## 3. Stack technique

| Domaine | Choix | Justification |
|---|---|---|
| State management | `flutter_bloc` (Bloc/Cubit) | Séparation stricte UI / logique, testable via `bloc_test` |
| Navigation | `go_router` | Navigation déclarative + redirections (AuthGuard) |
| DI | `get_it` | Simple, sans génération de code obligatoire |
| Réseau | `dio` | Intercepteurs, timeouts, gestion d'erreurs typée |
| Résultat métier | `Result<Failure, T>` maison | Voir § 8 |
| Stockage local | `shared_preferences` / `flutter_secure_storage` | Session non sensible / tokens sensibles |
| Logs | `logger` | Remplace `print()` (interdit par le lint) |
| Responsivité | `flutter_screenutil` + helpers maison | Voir § 9 |
| Documentation UI | `widgetbook` (module séparé) | Voir § 11 |

## 4. Architecture

```
Presentation → Domain ← Data
```

La couche **Domain** (`entities`, `repositories` abstraits, `usecases`) ne dépend ni de Flutter ni de Data. Chaque feature suit :

```
features/<nom>/
├── data/
│   ├── datasources/
│   ├── models/
│   └── repositories/
├── domain/
│   ├── entities/
│   ├── repositories/
│   └── usecases/
└── presentation/
    ├── cubit/ (ou bloc/)
    ├── pages/
    └── widgets/
```

### Créer une nouvelle feature

Dupliquer la structure ci-dessus dans `lib/features/<nom>/`. Exemple prévu par `AGENTS.md` : `boarding`, `manifest`, `printing`, `sync`. Isoler les intégrations matérielles (scanner P501, imprimante thermique) derrière des adaptateurs dans `data/datasources/` pour garder la logique métier testable sans hardware.

## 5. State management — Bloc vs Cubit

- **Cubit** : flux simples, un état résultant d'une action directe (ex. `AuthCubit` : login/logout/checkAuthStatus). Utilisé par défaut.
- **Bloc** : dès qu'il faut réagir à des événements multiples/asynchrones combinés (ex. scan continu + timer de synchronisation offline). À privilégier pour les futures features `boarding`/`sync`.

Les widgets ne contiennent aucune logique métier : `BlocConsumer`/`BlocBuilder` seulement.

## 6. Navigation — GoRouter & AuthGuard

Routes déclarées dans `AppRoutes` (`app/router/app_routes.dart`). `AuthGuard` (`ChangeNotifier` alimenté par le flux `AuthCubit`) redirige :

- état en cours de résolution → reste sur `/` (splash)
- non authentifié → `/login`
- authentifié → `/home`

Flux : `Splash → (auth check) → Login ou Home`, avec logout ramenant vers `Login`.

## 7. Dependency Injection — GetIt

Flux d'enregistrement (`lib/injection/injection.dart`) :

```
NetworkClient / SharedPreferences
      ↓
LocalDataSource
      ↓
Repository (implémentation)
      ↓
UseCase
      ↓
Cubit/Bloc
      ↓
UI
```

`configureDependencies()` est appelé dans `main()` avant `runApp()`.

## 8. Gestion des erreurs

Stratégie retenue : un type **`Result<Failure, T>`** maison (`core/result/result.dart`) plutôt que `dartz`'s `Either`, pour limiter les dépendances et le vocabulaire fonctionnel. Les datasources lèvent des `AppException` (`core/exceptions`), les repositories les convertissent en `Failure` (`core/errors`) — jamais d'exception ne remonte à Presentation.

## 9. Responsivité

Deux mécanismes complémentaires :

1. **`flutter_screenutil`** (`ScreenUtilInit` dans `app/app.dart`, design de référence 375×812) pour la mise à l'échelle proportionnelle des tailles/polices (`.w`, `.h`, `.sp`, `.r`) — utile pour les petits terminaux P501.
2. **`core/helpers/responsive.dart`** (`Responsive`, `ResponsiveContextX`) : breakpoints mobile/tablette/desktop, `Responsive.value(...)` pour choisir une valeur selon la largeur d'écran, `Responsive.gridColumns(...)` pour les grilles (voir `HomePage`). Ne dépend d'aucun package tiers.

## 10. Design System KORA

Tokens extraits des variables CSS du prototype web passagers (`KORA_App_Final.zip` → `css/style.css`), afin que KORA OPS Mobile partage la même identité visuelle bien que ce soit une app distincte :

| Variable CSS | Token Flutter | Valeur |
|---|---|---|
| `--kora` | `AppColors.primary` | `#001D4E` |
| `--gold` | `AppColors.accent` | `#F6B000` |
| `--digital` | `AppColors.secondary` | `#0057B8` |
| `--mist` | `AppColors.background` | `#F4F6F8` |
| `--graphite` | `AppColors.textPrimary` | `#111827` |
| `--muted` | `AppColors.textSecondary` | `rgba(17,24,39,.6)` |
| `--line` | `AppColors.border` | `rgba(0,29,78,.11)` |
| Police titres | `AppTypography` (`_displayFontFamily`) | `"Aptos Display", Aptos, "Segoe UI"` |
| Police texte | `AppTypography` (`_bodyFontFamily`) | `Aptos, "Segoe UI", Arial` |

"Aptos" n'étant pas une police système Android/iOS, un `fontFamilyFallback` est déclaré. Si les fichiers de police sont fournis par la marque, les ajouter dans `pubspec.yaml > flutter > fonts` et adapter `AppTypography`.

Fichiers : `app/theme/app_colors.dart`, `app_typography.dart`, `app_spacing.dart`, `app_radius.dart`, `app_theme.dart`. Aucune couleur codée en dur (`Color(0xFF...)`) ne doit apparaître dans un écran.

## 11. Widgetbook

Module indépendant dans `/widgetbook`, dépendance `path: ../` vers l'app principale — les composants affichés sont les vrais composants de `lib/`, jamais des copies.

```bash
cd widgetbook
flutter pub get
flutter run -t lib/main.dart
```

Use cases fournis : Buttons (enabled/disabled/loading/secondary/outline), Inputs (enabled/error/obscured), Cards, Loading, Empty state, Error state, Dialogs ProDialog (success/error/info/confirmation/loading), Colors, Typography.

## 12. ProDialog

**Décision documentée** : le package "ProDialog" demandé n'a pas de version publique vérifiable pour ce projet au moment de la génération de ce scaffold (impossible de valider sa compatibilité sans exécuter `pub get`). Pour ne pas bloquer l'architecture, `core/services/dialog_service.dart` expose déjà l'API cible :

```dart
DialogService.showSuccess(...)
DialogService.showError(...)
DialogService.showConfirmation(...)
DialogService.showInfo(...)
DialogService.showLoading(...)
```

et l'implémente pour l'instant avec des dialogues Material stylés au Design System KORA. **Action de suivi** : une fois le vrai package ProDialog confirmé et ajouté à `pubspec.yaml`, seule l'implémentation interne de `DialogService` doit changer — aucun appelant n'a besoin d'être modifié.

## 13. Environnements

`app/config/environment.dart` (enum `dev`/`staging`/`prod`) + `app/config/app_config.dart` (URL API, nom d'app, flags debug/logging). Aucune URL n'est dispersée dans le code.

```bash
flutter run --dart-define=ENV=dev
flutter run --dart-define=ENV=staging
flutter run --dart-define=ENV=prod
```

## 14. Code generation

`json_annotation`/`json_serializable` sont déclarés pour les futurs modèles générés. Si des classes `@JsonSerializable()` sont ajoutées :

```bash
dart run build_runner build --delete-conflicting-outputs
```

Le modèle `UserModel` actuel est écrit à la main (`fromJson`/`toJson` manuels) car trop simple pour justifier la génération.

## 15. Tests

```
test/
├── features/auth/domain/login_user_test.dart        # UseCase
├── features/auth/data/auth_repository_impl_test.dart # Repository mocké
├── features/auth/presentation/auth_cubit_test.dart   # Cubit (bloc_test)
└── features/home_page_widget_test.dart               # Widget
```

```bash
flutter test
```

## 16. Analyse & Lint

`analysis_options.yaml` étend `flutter_lints` avec des règles strictes supplémentaires (`avoid_print`, `always_declare_return_types`, `avoid_dynamic_calls`, etc.).

```bash
flutter analyze
```

## 17. Écrans mockés

`SplashPage → (AuthGuard) → LoginPage ou HomePage`, avec logout (`AppBar` de `HomePage`) ramenant vers `LoginPage`. Authentification simulée en mémoire/`shared_preferences` (`AuthLocalDataSource`) — aucun backend réel à ce stade.

## 18. Build

```bash
flutter build apk --dart-define=ENV=prod
flutter build appbundle --dart-define=ENV=prod
```

## 19. Git

`.gitignore` Flutter standard déjà présent (généré par `flutter create`) : `.dart_tool/`, `build/`, `.idea/`, `*.iml` exclus.

## 20. Prochaines étapes suggérées

- Créer les features `boarding`, `manifest`, `printing`, `sync` (voir `AGENTS.md`) en suivant la structure Clean Architecture ci-dessus.
- Remplacer `AuthLocalDataSource` par un vrai flux API (`AuthRemoteDataSource` + `NetworkClient`) quand le backend sera prêt.
- Intégrer les vrais packages `flutter-networking` / `flutter-design-system` du monorepo s'ils existent, à la place des abstractions temporaires actuelles.
- Confirmer et intégrer le vrai package ProDialog (§ 12).
- Ajouter les polices Aptos en assets si fournies par la marque.
