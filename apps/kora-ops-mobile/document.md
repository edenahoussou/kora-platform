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

Routes déclarées dans `AppRoutes` (`app/router/app_routes.dart`). `AuthGuard` (`ChangeNotifier` alimenté par le flux `AuthCubit`) ne redirige automatiquement que depuis `/` (splash) et `/home` (déconnexion) :

- état en cours de résolution → reste sur `/` (splash)
- non authentifié, aucun email enregistré → `/login/email`
- non authentifié, email déjà enregistré → `/login/pin`
- authentifié → `/home`

Une fois entré dans le parcours `/login/*` (email → OTP → PIN → nouvel appareil), chaque écran gère sa propre navigation explicite (`context.push`/`context.go`) — voir § 17 pour le détail du parcours.

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

| Variable CSS / rôle | Token Flutter | Valeur |
|---|---|---|
| `--kora` (marine, en-têtes, écrans de sécurité) | `AppColors.primary` | `#001D4E` |
| `--gold` (**CTA principal** — voir ci-dessous) | `AppColors.accent` | `#F6B000` |
| `--digital` | `AppColors.secondary` | `#0057B8` |
| `--mist` | `AppColors.background` | `#F4F6F8` |
| `--graphite` | `AppColors.textPrimary` | `#111827` |
| `--muted` | `AppColors.textSecondary` | `rgba(17,24,39,.6)` |
| `--line` | `AppColors.border` | `rgba(0,29,78,.11)` |
| Police titres | `AppTypography` (`_displayFontFamily`) | `"Aptos Display", Aptos, "Segoe UI"` |
| Police texte | `AppTypography` (`_bodyFontFamily`) | `Aptos, "Segoe UI", Arial` |

**Correction issue des maquettes d'authentification** (5 écrans fournis) : le bouton d'action principal de l'app est **doré** (`AppColors.accent`), pas marine — le marine est réservé aux en-têtes et aux écrans de sécurité (PIN, nouvel appareil). `AppButtonVariant.primary` et le thème `ElevatedButton` par défaut utilisent donc `accent`/`onAccent`.

### Variantes de bouton (`AppButton`, `core/widgets/app_button.dart`)

| Variante | Couleur de fond | Usage |
|---|---|---|
| `primary` | `accent` (or) | CTA principal — la grande majorité des écrans |
| `secondary` | `secondary` (bleu digital) | Action secondaire pleine couleur |
| `outline` | transparent, bordure `primary`/blanc selon le thème | Action secondaire discrète ("Ce n'est pas moi", "Modifier l'email"…) |
| `success` | `success` | Confirmation/validation |
| `warning` | `warning` (ambre, **distinct** de `accent` pour rester identifiable) | Alerte non bloquante |
| `danger` | `error` | Action destructive/bloquante |

"Aptos" n'étant pas une police système Android/iOS, un `fontFamilyFallback` est déclaré. Si les fichiers de police sont fournis par la marque, les ajouter dans `pubspec.yaml > flutter > fonts` et adapter `AppTypography`.

Fichiers : `app/theme/app_colors.dart`, `app_typography.dart`, `app_spacing.dart`, `app_radius.dart`, `app_theme.dart`. Aucune couleur codée en dur (`Color(0xFF...)`) ne doit apparaître dans un écran.

## 10bis. Dark mode

Bascule **automatique (système) et manuelle**, pilotée par `ThemeCubit` (`app/theme/theme_cubit.dart`, singleton GetIt) :

- Par défaut : `ThemeMode.system` — suit les réglages de l'appareil.
- Bascule manuelle : bouton dans l'`AppBar` de `HomePage` (icône soleil/lune/auto) qui fait défiler `system → light → dark → system` via `ThemeCubit.cycle()`. Le choix est persisté dans `SharedPreferences` (`app.theme_mode`) et relu au lancement suivant.
- `AppTheme.light` et `AppTheme.dark` partagent la même structure (`AppTheme._build(...)`) et ne diffèrent que par les couleurs de surface/texte (`AppColors.*Dark`).

**Cas particulier — écran PIN KORA** (`PinEntryPage`) : son fond bleu marine plein écran est un choix de **design de marque fixe**, indépendant de `ThemeMode` (il reste marine que l'app soit en thème clair ou sombre) — ce n'est pas un mode sombre activable/désactivable, conformément à la maquette fournie. Idem pour le bandeau marine de `NewDeviceDetectedPage`.

`pro_dialog` gère nativement le dark mode (il lit `Theme.of(context)`), aucune adaptation manuelle n'a été nécessaire dans `DialogService`.

## 11. Widgetbook

Module indépendant dans `/widgetbook`, dépendance `path: ../` vers l'app principale — les composants affichés sont les vrais composants de `lib/`, jamais des copies.

```bash
cd widgetbook
flutter pub get
flutter run -t lib/main.dart
```

Use cases fournis : Buttons (les 6 variantes), Inputs (enabled/error/obscured), Cards, Loading, Empty state, Error state, Dialogs ProDialog (success/error/warning/info/confirmation/loading), Colors, Typography — le `ThemeAddon` propose "KORA Clair" et "KORA Sombre".

## 12. ProDialog

Intégration réelle du package **[pro_dialog](https://pub.dev/packages/pro_dialog)** (v1.0.9, vérifié sur pub.dev — Flutter ≥3.22, Dart ≥3.4, compatible avec ce projet), suite à la demande explicite du client. `core/services/dialog_service.dart` expose :

```dart
DialogService.showSuccess(...)
DialogService.showError(...)
DialogService.showWarning(...)
DialogService.showInfo(...)
DialogService.showConfirmation(...)  // retourne Future<bool>
DialogService.showLoading(...)
```

Implémentation : `showProDialog` avec `DialogType.success/error/warning/info/question`, thème commun `ProDialogTheme(borderRadius: AppRadius.lg, animationStyle: scale, iconAnimationStyle: pulse)`. `showConfirmation` utilise un `Completer<bool>` complété par les `DialogButton` (annuler/confirmer), suivant le pattern documenté par le package. Aucun écran n'appelle `showDialog()` ni un dialogue natif directement — tout passe par `DialogService`.

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
├── app/theme/theme_cubit_test.dart                     # bascule dark mode
├── features/auth/domain/auth_usecases_test.dart        # UseCases OTP/PIN
├── features/auth/data/auth_repository_impl_test.dart   # Repository mocké
├── features/auth/presentation/auth_cubit_test.dart     # session globale (bloc_test)
├── features/auth/presentation/login_cubit_test.dart    # flux email→OTP→PIN (bloc_test)
├── features/printing/…                                 # voir § 17bis
└── features/home_page_widget_test.dart                 # Widget
```

```bash
flutter test
```

## 16. Analyse & Lint

`analysis_options.yaml` étend `flutter_lints` avec des règles strictes supplémentaires (`avoid_print`, `always_declare_return_types`, `avoid_dynamic_calls`, etc.).

```bash
flutter analyze
```

## 17. Flux d'authentification (écrans mockés)

Reproduction fidèle des 5 maquettes fournies par le client — voir `features/auth/`. Toutes les données sont mockées ce sprint (`AuthLocalDataSource`) ; la connexion aux API réelles est prévue au Sprint 5.

### Parcours

```
Splash
  │
  ├─ AuthGuard : email déjà enregistré sur l'appareil ?
  │      NON → EmailEntryPage (/login/email)
  │      OUI → PinEntryPage (/login/pin)
  │
EmailEntryPage ──"Recevoir mon code"──► OtpVerificationPage (/login/otp, purpose=accountCreation)
  │                                           │
  │                                    code correct → Home (authentifié)
  │                                    code incorrect → OtpInvalidPage (/login/otp-invalid)
  │                                                        │
  │                                          "Recevoir un nouveau code" → retour OtpVerificationPage
  │                                          "Modifier l'email" → retour EmailEntryPage
  │
PinEntryPage ──PIN correct──► NewDeviceDetectedPage (/login/new-device)
  │                                  │
  │                     "Recevoir le code OTP" → OtpVerificationPage (purpose=deviceConfirmation) → Home
  │                     "Ce n'est pas moi" → confirmation ProDialog → retour EmailEntryPage
  │
  └─PIN incorrect → DialogService.showError + réinitialisation du clavier
```

### Données mockées (`AuthLocalDataSource`)

- Code OTP accepté : **`583187`** (toute autre valeur à 6 chiffres → `OtpInvalidPage`)
- PIN accepté : **`1234`** (toute autre valeur à 4 chiffres → erreur inline + clavier réinitialisé)
- Appareil mocké pour `NewDeviceDetectedPage` : `Tecno CAMON · Cotonou · À l'instant`

### Écrans (fidèles aux 5 maquettes fournies)

| Écran | Route | Fichier |
|---|---|---|
| Votre adresse email | `/login/email` | `presentation/pages/email_entry_page.dart` |
| Code de vérification (OTP) | `/login/otp` | `presentation/pages/otp_verification_page.dart` |
| Code non valide | `/login/otp-invalid` | `presentation/pages/otp_invalid_page.dart` |
| Votre PIN KORA (fond marine) | `/login/pin` | `presentation/pages/pin_entry_page.dart` |
| Nouvel appareil détecté | `/login/new-device` | `presentation/pages/new_device_detected_page.dart` |

### Architecture Cubit

- **`AuthCubit`** (singleton) : état de session global (`AuthInitial`/`AuthLoading`/`AuthAuthenticated`/`AuthUnauthenticated(savedEmail)`), seule source de vérité pour `AuthGuard`.
- **`LoginCubit`** (singleton, partagé entre toutes les routes `/login/*` via `BlocProvider.value`) : orchestre les étapes (email, OTP, PIN, nouvel appareil) dans un état unique (`LoginState` avec `status`/`email`/`otpPurpose`/`deviceInfo`/`user`). Ses méthodes retournent `Future<bool>` en plus d'émettre un état : l'écran utilise le booléen pour naviguer (`context.push`), l'état pour l'affichage (spinner, erreur).
- Une fois l'OTP vérifié avec succès (création de compte ou confirmation d'appareil), l'écran appelle `AuthCubit.authenticate(user)` avant de naviguer vers `/home`, pour que `AuthGuard` laisse passer.

Widgets réutilisables : `OtpCodeInput` (`core/widgets/`, 6 cases, avance automatique), `NumericKeypad` et `AuthScreenHeader` (`features/auth/presentation/widgets/`).

## 17bis. Adaptateurs P501 — scanner QR et imprimante thermique 58mm

Feature `lib/features/printing/`, même structure Clean Architecture que les autres :

```
features/printing/
├── data/
│   ├── formatters/ticket_formatter.dart   # formatage 58mm, testé unitairement
│   └── services/
│       ├── mock_barcode_scanner.dart      # défaut dev/staging
│       ├── mock_thermal_printer.dart      # défaut dev/staging
│       ├── p501_barcode_scanner.dart      # MethodChannel 'kora.ops/p501_scanner'
│       └── p501_thermal_printer.dart      # MethodChannel 'kora.ops/p501_printer'
├── domain/
│   ├── entities/ticket_payload.dart
│   ├── services/barcode_scanner.dart      # interface BarcodeScanner
│   ├── services/thermal_printer.dart      # interface ThermalPrinter
│   └── usecases/scan_barcode.dart, print_ticket.dart
└── presentation/
    ├── cubit/printing_cubit.dart
    └── pages/printer_test_page.dart       # test manuel sans hardware
```

### Interfaces

```dart
abstract interface class BarcodeScanner {
  Future<String> scan();
}

abstract interface class ThermalPrinter {
  Future<bool> printTicket(TicketPayload payload);
}
```

### Switch Mock / P501

Piloté par `AppConfig.useMockHardware` (`app/config/app_config.dart`), résolu ainsi :

- Par défaut : `true` en `dev`/`staging`, `false` en `prod`.
- Override explicite : `--dart-define=USE_MOCK_HARDWARE=true|false`.

```bash
# Forcer le mock même en prod (démo, recette sans terminal)
flutter run --dart-define=ENV=prod --dart-define=USE_MOCK_HARDWARE=true

# Forcer le matériel réel en dev (test sur un vrai P501)
flutter run --dart-define=ENV=dev --dart-define=USE_MOCK_HARDWARE=false
```

L'enregistrement DI correspondant est dans `injection/injection.dart` — un seul point de bascule, aucun `if` dispersé ailleurs dans le code.

### Format du ticket 58mm

`TicketFormatter` (`data/formatters/ticket_formatter.dart`) produit des lignes de **32 caractères maximum** (largeur standard imprimante 58mm) :

1. En-tête "KORA EXPRESS" centré
2. Ligne de séparation
3. Trajet, date, heure
4. Ligne de séparation
5. Passager, siège
6. Ligne de séparation
7. Marqueur `[ QR CODE ]` centré — le QR réel est généré côté SDK natif à partir de `TicketPayload.qrData`, ce marqueur ASCII ne sert qu'à la prévisualisation écran et aux tests
8. Ligne de séparation
9. Pied de page fiscal

`TicketFormatter.isValid(lines)` vérifie ce format ; `TicketFormatter.formatAsText(payload)` assemble le tout pour affichage/logs.

### Implémentation P501 (MethodChannel)

`P501BarcodeScanner` et `P501ThermalPrinter` appellent respectivement les canaux `kora.ops/p501_scanner` (méthode `scan`) et `kora.ops/p501_printer` (méthode `printTicket`, arguments `{lines: List<String>, qrData: String}`). **Aucun SDK P501 n'ayant été fourni au moment de ce scaffold**, le côté natif (`android/.../MainActivity.kt`) reste à implémenter — les deux classes Dart sont prêtes à l'emploi dès que le handler natif existe, et lèvent une `ScannerException`/`PrinterException` explicite (`MissingPluginException`) tant que ce n'est pas le cas.

### Tester sans hardware P501

1. **Tests unitaires** (aucun appareil requis) :
   ```bash
   flutter test test/features/printing/
   ```
   Couvrent : format du ticket (largeur, centrage, troncature), `MockBarcodeScanner`/`MockThermalPrinter`, UseCases (`ScanBarcode`, `PrintTicket`), `PrintingCubit`.

2. **Test manuel dans l'app** : lancer en mode Mock (par défaut), depuis `HomePage` ouvrir la tuile **Impression** → écran "Test scanner & imprimante" (`PrinterTestPage`) : bouton "Scanner (mock)" renvoie un code de test cyclique, bouton "Imprimer un ticket de test" affiche l'aperçu formaté à l'écran (police monospace, 32 colonnes) et confirme le succès simulé.

## 18. Build

```bash
flutter build apk --dart-define=ENV=prod
flutter build appbundle --dart-define=ENV=prod
```

## 19. Git

`.gitignore` Flutter standard déjà présent (généré par `flutter create`) : `.dart_tool/`, `build/`, `.idea/`, `*.iml` exclus.

## 20. Prochaines étapes suggérées

- Créer les écrans restants du ticket [OPS Mobile] écrans mockés : Manifeste (liste passagers avec `PassengerListTile`, `StatutBadge`), Scan boarding (caméra/scanner + feedback valide/refusé), Détail passager, Descente anticipée (`SeatMap`), Synchro (indicateur de connexion, file d'attente) — modèles mockés `Depart`/`Passenger`/`Ticket`/`Seat` à créer.
- Ajouter la sélection du départ à gérer (hors périmètre de ce sprint, voir échange du 14/08/2026).
- Remplacer `AuthLocalDataSource` par un vrai flux API (`AuthRemoteDataSource` + `NetworkClient`) quand le backend sera prêt, y compris un vrai fingerprint d'appareil pour `NewDeviceDetectedPage`.
- Intégrer les vrais packages `flutter-networking` / `flutter-design-system` du monorepo s'ils existent, à la place des abstractions temporaires actuelles.
- Ajouter les polices Aptos en assets si fournies par la marque.
- Déplacer la bascule manuelle du thème (§ 10bis) vers un écran Paramètres dédié.
- Implémenter le handler natif Android/iOS des canaux `kora.ops/p501_scanner` et `kora.ops/p501_printer` (§ 17bis) une fois le SDK P501 disponible.

