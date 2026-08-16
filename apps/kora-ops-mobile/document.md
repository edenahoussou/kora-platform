# KORA OPS Mobile (P501) — Documentation technique

## 1. Présentation

**KORA OPS Mobile / P501** est l'application terrain de **KORA EXPRESS**, destinée aux chauffeurs bus, convoyeurs et agences. Elle couvre : manifestes, validation par QR/code court, embarquement, impression, descente anticipée, synchronisation et statuts opérationnels.

Ce n'est **pas** KORA Driver (future application des conducteurs Ride) ni l'app passagers **kora-passager** (dont ce projet reprend le design system et les patterns — voir § 10).

**Pas d'auto-inscription** : les agents KORA OPS sont provisionnés par un administrateur. L'app n'a donc ni écran de bienvenue, ni création de compte — l'entrée unique est l'écran PIN (voir § 17).

## 2. Important — limites de cet environnement de génération

Ce projet a été écrit entièrement à la main (pas de `flutter create`/`pub get`/`analyze`/`test` exécutés ici, le SDK Flutter n'étant pas disponible dans l'environnement qui a produit ce code). **Avant tout développement**, exécuter impérativement :

```bash
flutter pub get
flutter analyze
flutter test
```

et corriger les éventuels écarts de version d'API entre les packages listés dans `pubspec.yaml` et les versions réellement résolues par pub.

## 3. Stack technique

| Domaine | Choix | Justification |
|---|---|---|
| State management | `flutter_bloc` (Bloc/Cubit) | Séparation stricte UI / logique, testable via `bloc_test` |
| Navigation | `go_router` (`StatefulShellRoute.indexedStack`) | Bottom nav à état préservé par onglet — voir § 6 |
| DI | `get_it` | Simple, sans génération de code obligatoire |
| Réseau | `dio` | Intercepteurs, timeouts, gestion d'erreurs typée |
| Résultat métier | `Result<Failure, T>` maison | Voir § 8 |
| Stockage local | `shared_preferences` / `flutter_secure_storage` | Session non sensible / tokens sensibles |
| Logs | `logger` | Remplace `print()` (interdit par le lint) |
| Responsivité | `flutter_screenutil` + helpers maison | Voir § 9 |
| Squelettes de chargement | `shimmer` | Voir § 10.4 |
| Dialogues | `pro_dialog` (via `KoraDialogs`) | Voir § 12 — jamais de `SnackBar`/dialogue natif |
| Scanner caméra | `mobile_scanner` | Caméra réelle, Scan boarding — voir § 17bis |
| QR code affiché | `qr_flutter` | `TicketCard` — voir § 17bis |
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

Les features `manifeste`, `scan`, `descente`, `synchro`, `home` (Accueil) partagent des entités communes vivant dans `features/ops/domain/` (`Depart`, `Passenger`, `Ticket`, `Seat`) plutôt qu'un Clean Architecture complet par feature — proportionné au périmètre "écrans mockés" du ticket MEZ-11 (voir § 17bis pour le détail).

### Créer une nouvelle feature

Dupliquer la structure ci-dessus dans `lib/features/<nom>/`. Isoler les intégrations matérielles (scanner P501, imprimante thermique) derrière des adaptateurs dans `data/datasources/` pour garder la logique métier testable sans hardware (voir § 18, MEZ-10).

## 5. State management — Bloc vs Cubit

- **Cubit** : flux simples, un état résultant d'une action directe (`AuthCubit`, `LoginCubit`, `ManifesteCubit`, `SyncCubit`, `SelectedDepartCubit`, `ScanBoardingCubit`, `ThemeCubit`, `PrintingCubit`). Utilisé par défaut dans ce projet.
- **Bloc** : dès qu'il faut réagir à des événements multiples/asynchrones combinés. Pas encore nécessaire ici — à réévaluer si un flux de scan continu en rafale est ajouté.

Les widgets ne contiennent aucune logique métier : `BlocConsumer`/`BlocBuilder`/`context.watch` seulement.

## 6. Navigation — GoRouter, AuthGuard & bottom nav

Routes déclarées dans `AppRoutes` (`app/router/app_routes.dart`). `AuthGuard` (`ChangeNotifier` alimenté par `AuthCubit`) ne redirige automatiquement que depuis `/` (splash) et `/home` (déconnexion) :

- état en cours de résolution → reste sur `/` (splash)
- non authentifié → `/login/pin` (entrée unique, voir § 1 et § 17)
- authentifié → `/home`

Une fois entré dans `/login/*` (pin → otp → nouvel appareil), chaque écran gère sa propre navigation explicite (`context.push`/`context.go`).

### Bottom nav (5 onglets)

`StatefulShellRoute.indexedStack` (`app/router/app_router.dart`), pattern recommandé par `go_router` : chaque onglet garde son propre historique de navigation même en changeant d'onglet. Portée par `KoraBottomNav` (`core/widgets/kora_bottom_nav.dart`), fidèle au style flottant de `kora-passager` :

| Onglet | Route | Écran |
|---|---|---|
| Accueil | `/home` | `AccueilPage` — tableau de bord, départ géré, accès rapides |
| Manifeste | `/manifeste` | `ManifestePage` |
| Scan | `/scan` | `ScanBoardingPage` |
| Descente | `/descente-anticipee` | `DescenteAnticipeePage` |
| Synchro | `/synchro` | `SynchroPage` |

Écrans empilés au-dessus de la bottom nav (`context.push`) : `SelectionDepartPage` (`/depart/selection`), `DetailPassagerPage` (`/manifeste/passager`), `ScanResultPage` (`/scan/resultat`), les écrans `/login/*`, et `PrinterTestPage` (`/printer-test`, MEZ-10).

**Important** : les Cubits partagés entre onglets ET écrans empilés (`SelectedDepartCubit`, `SyncCubit`, `ManifesteCubit`, `ScanBoardingCubit`) sont fournis en singleton au niveau racine (`app/app.dart`, `MultiBlocProvider`), pas localement dans une route — un écran poussé via `context.push` (ex. `ScanResultPage`) n'est **pas** un descendant du `builder` de sa route sœur dans l'arbre `go_router`, donc un `BlocProvider` local n'y serait pas visible.

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
Cubit/Bloc (singleton si partagé entre plusieurs écrans — voir § 6)
      ↓
UI
```

`configureDependencies()` est appelé dans `main()` avant `runApp()`.

## 8. Gestion des erreurs

Stratégie retenue : un type **`Result<Failure, T>`** maison (`core/result/result.dart`) plutôt que `dartz`'s `Either` (utilisé côté `kora-passager`) — choix conservé pour limiter les dépendances côté OPS. Les datasources lèvent des `AppException` (`core/exceptions`), les repositories les convertissent en `Failure` (`core/errors`) — jamais d'exception ne remonte à Presentation.

## 9. Responsivité

Deux mécanismes complémentaires :

1. **`flutter_screenutil`** (`ScreenUtilInit` dans `app/app.dart`, design de référence 375×812) pour la mise à l'échelle proportionnelle des tailles/polices — utile pour les petits terminaux P501.
2. **`core/helpers/responsive.dart`** (`Responsive`, `ResponsiveContextX`) : breakpoints mobile/tablette/desktop, `Responsive.value(...)`. Ne dépend d'aucun package tiers.

## 10. Design System KORA

**Nomenclature entièrement alignée sur `packages/flutter-design-system` de `kora-passager`** (voir échange du 16/08/2026) : mêmes noms de tokens et de composants, pour que les deux apps du monorepo restent uniformes. Le code source de ce package n'étant pas disponible dans cet environnement (dépendance `path: ../../packages/flutter-design-system` du monorepo), les équivalents ont été **recréés localement** dans `kora-ops-mobile` avec la même API — à remplacer par une vraie dépendance partagée si/quand le monorepo est accessible.

### 10.1 Couleurs — `AppColors` (fixes) et `KoraColors` (contextuelles)

`app/theme/app_colors.dart` — couleurs de marque, identiques clair/sombre :

| Token | Valeur | Rôle |
|---|---|---|
| `AppColors.kora` | `#001D4E` | Marine — en-têtes, écran PIN, cartes `navy` |
| `AppColors.gold` | `#F6B000` | **CTA principal** (voir maquettes d'authentification) |
| `AppColors.digital` | `#0057B8` | Bleu secondaire |
| `AppColors.onGold` | `#1A1400` | Texte sur fond `gold`/`warning` |
| `AppColors.success` / `warning` / `danger` | `#2D7A51` / `#C77700` / `#9C2430` | États sémantiques — `warning` est volontairement distinct de `gold` |

`app/theme/kora_colors.dart` — `KoraColors extends ThemeExtension`, accessible via `context.koraColors.{surface,background,textPrimary,textMuted,line,shadow}`, avec des valeurs différentes en clair (`KoraColors.light`) et en sombre (`KoraColors.dark`), enregistrée dans `AppTheme` (§ 10bis).

### 10.2 Typographie — `AppTextStyles`

`app/theme/app_text_styles.dart` — API **par fonction** (`AppTextStyles.eyebrow(color)`, `heading1(color)`, `heading2(color)`, `titleMedium(color)`, `lead(color)`, `body(color)`, `caption(color)`, `button(color)`), la couleur étant passée par l'appelant (`context.koraColors.textPrimary`/`textMuted` selon le contexte) plutôt que figée dans le style — fidèle à `kora-passager`. Police "Aptos Display"/"Aptos" avec `fontFamilyFallback` (police non fournie en asset ce sprint).

### 10.3 Composants

| Composant | Fichier | Usage |
|---|---|---|
| `KoraButton` | `core/widgets/kora_button.dart` | `primary`(or)/`secondary`/`ghost`/`success`/`warning`/`danger` |
| `KoraCard` | `core/widgets/kora_card.dart` | `surface`/`navy`/`gold`/`mist` |
| `KoraIconButton` | `core/widgets/kora_icon_button.dart` | Bouton rond (retour, actions compactes) |
| `KoraTag` | `core/widgets/kora_tag.dart` | Badge/pastille — base de `StatutBadge` |
| `ScreenHeader` | `core/widgets/screen_header.dart` | **`AppBar`** (`PreferredSizeWidget`) commun aux écrans empilés — se passe en `Scaffold(appBar: ScreenHeader(...))`, jamais dans le `body` |
| `PinDots` / `PinKeypad` | `core/widgets/pin_pad.dart` | Écran PIN |
| `KoraBottomNav` | `core/widgets/kora_bottom_nav.dart` | Bottom nav 5 onglets (§ 6) — fond clair flottant, icône/libellé actifs sombres+gras sur fond doré discret, inactifs gris (fidèle trait pour trait à `kora-passager`, voir échange du 16/08/2026) |
| `TicketCard` | `core/widgets/ticket_card.dart` | Affichage du billet en app (§ 17bis) |

Aucune couleur codée en dur (`Color(0xFF...)`) ni bouton/carte/AppBar natif Flutter ne doit apparaître dans un écran — toujours passer par ces composants.

### 10.4 Squelettes de chargement (shimmer)

`core/widgets/kora_skeleton.dart`, sur le package **[shimmer](https://pub.dev/packages/shimmer)** (`^3.0.0`) : `KoraShimmer` (enveloppe colorée selon `context.koraColors`), `SkeletonBox`, `SkeletonListTile`, `SkeletonList` (Manifeste), `SkeletonCard` (Descente anticipée). Utilisés pour tout état `loading` d'écran avec données mockées asynchrones.

## 10bis. Dark mode

Bascule **automatique (système) et manuelle**, pilotée par `ThemeCubit` (`app/theme/theme_cubit.dart`, singleton GetIt) :

- Par défaut : `ThemeMode.system`.
- Bascule manuelle : bouton dans l'`AppBar` d'`AccueilPage` (icône soleil/lune/auto), `system → light → dark → system` via `ThemeCubit.cycle()`, persisté dans `SharedPreferences`.
- `AppTheme.light`/`dark` (`app/theme/app_theme.dart`) partagent la même structure et enregistrent `KoraColors.light`/`dark` comme `ThemeExtension`.

**Cas particulier — écran PIN** (`PinEntryPage`) : fond bleu marine plein écran fixe, indépendant de `ThemeMode` (choix de marque, pas un mode sombre désactivable), fidèle à `kora-passager`.

`pro_dialog` gère nativement le dark mode (il lit `Theme.of(context)`).

## 11. Widgetbook

Module indépendant dans `/widgetbook`, dépendance `path: ../` vers l'app principale.

```bash
cd widgetbook
flutter pub get
flutter run -t lib/main.dart
```

Use cases : Buttons (6 variantes), Inputs, Cards (surface/navy/gold), Loading & States, Dialogs (`KoraDialogs`), Colors, Typography — `ThemeAddon` "KORA Clair"/"KORA Sombre".

## 12. Dialogues — `KoraDialogs` (pro_dialog)

Intégration réelle du package **[pro_dialog](https://pub.dev/packages/pro_dialog)** (v1.0.9, vérifié sur pub.dev). `core/services/kora_dialogs.dart` expose (noms alignés sur `KoraDialogs` de `kora-passager`) :

```dart
KoraDialogs.success(context, title: ..., description: ...)
KoraDialogs.error(context, title: ..., description: ...)
KoraDialogs.warning(context, title: ..., description: ...)
KoraDialogs.info(context, title: ..., description: ...)
KoraDialogs.confirm(context, title: ..., description: ...)   // Future<bool>
KoraDialogs.showLoading(context, message: ...)
KoraDialogs.hideLoading(context)                              // toujours en paire avec showLoading
```

**Aucun écran n'utilise `showDialog()`, un dialogue natif Flutter, ni `SnackBar`/`ScaffoldMessenger`** — y compris pour les messages transitoires ("code renvoyé", "statut mis à jour"…) qui utiliseraient un `SnackBar` ailleurs. Tout passe par `KoraDialogs`.

## 13. Environnements

`app/config/environment.dart` + `app/config/app_config.dart` (URL API, nom d'app, flags debug/logging, `useMockHardware` — voir § 18).

```bash
flutter run --dart-define=ENV=dev
flutter run --dart-define=ENV=staging
flutter run --dart-define=ENV=prod
```

## 14. Code generation

`json_annotation`/`json_serializable` déclarés pour de futurs modèles générés. Si des classes `@JsonSerializable()` sont ajoutées :

```bash
dart run build_runner build --delete-conflicting-outputs
```

Aucun modèle du projet actuel n'en a besoin (tous écrits à la main, volontairement simples).

## 15. Tests

```
test/
├── app/theme/theme_cubit_test.dart
├── features/auth/domain/auth_usecases_test.dart
├── features/auth/data/auth_repository_impl_test.dart
├── features/auth/presentation/auth_cubit_test.dart
├── features/auth/presentation/login_cubit_test.dart      # PIN + OTP (pas de submitEmail)
├── features/ops/data/mock_ops_data_source_test.dart
├── features/synchro/presentation/cubit/sync_cubit_test.dart
└── features/printing/…                                    # voir § 18
```

```bash
flutter test
```

Couverture manquante à ce stade (voir § 20) : `ManifesteCubit`, `ScanBoardingCubit`, widgets `SeatMap`/`PassengerListTile`/`KoraBottomNav`.

## 16. Analyse & Lint

`analysis_options.yaml` étend `flutter_lints` avec des règles strictes supplémentaires.

```bash
flutter analyze
```

## 17. Flux d'authentification — PIN pur + OTP (MEZ-11)

**Pas d'auto-inscription** (voir § 1) : contrairement à `kora-passager` (qui a un parcours `Welcome → SignupEmail → Otp → CreatePin` pour les nouveaux clients), KORA OPS Mobile n'a **qu'un seul point d'entrée : l'écran PIN**. L'OTP ne sert qu'à confirmer un appareil non reconnu, jamais à créer un compte.

### Parcours

```
Splash
  │
  └─ AuthGuard : non authentifié → toujours /login/pin
       │
PinEntryPage (fond marine, fidèle à LoginPage de kora-passager)
  │
  ├─ PIN correct → NewDeviceDetectedPage (systématique, pour la démo —
  │                un vrai fingerprint arrivera avec l'API)
  │                    │
  │      "Recevoir le code OTP" → OtpVerificationPage
  │                                    │
  │                     code correct → authenticate() + /home
  │                     code incorrect → OtpInvalidPage
  │                                        │
  │                          "Recevoir un nouveau code" → retour OTP
  │                          "Retour à la connexion" → /login/pin
  │      "Ce n'est pas moi" → confirmation KoraDialogs → /login/pin
  │
  └─ PIN incorrect → KoraDialogs.error + clavier réinitialisé
```

### Données mockées (`AuthLocalDataSource`)

- **Compte provisionné par défaut** (pas de saisie) : `aicha.hounkpe@kora-express.com` / *Aïcha Hounkpé* / *Convoyeur* — écrit en `SharedPreferences` au premier lancement (`_seedProvisionedAccountIfNeeded`)
- PIN accepté : **`1234`**
- Code OTP accepté : **`583187`**
- Appareil mocké (`NewDeviceDetectedPage`) : `Tecno CAMON · Cotonou · À l'instant`

### Écrans

| Écran | Route | Fichier |
|---|---|---|
| Votre PIN KORA OPS (fond marine) | `/login/pin` | `pin_entry_page.dart` |
| Code de vérification | `/login/otp` | `otp_verification_page.dart` |
| Code non valide | `/login/otp-invalid` | `otp_invalid_page.dart` |
| Nouvel appareil détecté | `/login/new-device` | `new_device_detected_page.dart` |

### Cubits

- **`AuthCubit`** (singleton) : état de session global, seule source de vérité pour `AuthGuard`.
- **`LoginCubit`** (singleton, partagé entre les routes `/login/*`) : `submitPin`, `requestDeviceConfirmationOtp`, `submitOtp`, `resendOtp`, `loadSavedEmail`, `reset`. Méthodes `Future<bool>` (navigation) + état émis (affichage).
- Une fois l'OTP vérifié, l'écran appelle `AuthCubit.authenticate(user)` avant `context.go('/home')`.

## 17bis. Écrans OPS mockés (MEZ-11) — Manifeste, Scan, Descente anticipée, Synchro, Accueil

### Modèles (`features/ops/domain/entities/`)

- **`Depart`** — id, route, date, heure, immatriculation, places totales/occupées
- **`Passenger`** — id, nom, siège, `BoardingStatus` (`enAttente`/`embarque`/`refuse`/`descenduAnticipe`), segment O/D, ticketId, téléphone
- **`Ticket`** — numéro, passager, départ, siège, segment, `qrData` (cohérent avec `TicketPayload` de MEZ-10)
- **`Seat`** — numéro, `SeatStatus` (`occupe`/`libere`/`reserve`), passager, segment de libération

`features/ops/data/mock_ops_data_source.dart` génère 3 départs et des listes de passagers déterministes (seed = id du départ) — remplacé par de vraies API au Sprint 5.

### Sélection du départ à gérer

Accessible **des deux côtés**, comme demandé : écran plein `SelectionDepartPage` (`/depart/selection`, depuis `AccueilPage` ou le bouton "Changer de départ") **et** sélecteur compact dans l'en-tête de `ManifestePage`. Les deux écrivent dans `SelectedDepartCubit` (singleton), qui notifie `ManifesteCubit` pour recharger automatiquement.

### Manifeste (`features/manifeste/`)

`ManifestePage` : liste `PassengerListTile` (nom, siège, `StatutBadge`, segment O/D), squelette `SkeletonList` pendant le chargement, états vide/erreur (`AppStatePlaceholder`), `RefreshIndicator`. `ManifesteCubit` recharge automatiquement au changement de départ et expose `updateStatut(passengerId, statut)`, qui journalise l'opération dans `SyncCubit` (voir Synchro ci-dessous).

`DetailPassagerPage` (`/manifeste/passager`, poussé depuis une ligne du Manifeste) : infos billet, segment, siège, statut, actions Embarquer/Refuser/Descente anticipée.

### Scan boarding (`features/scan/`)

`ScanBoardingPage` allume une **vraie caméra en direct** (package **[mobile_scanner](https://pub.dev/packages/mobile_scanner)** `^7.4.0`, vérifié sur pub.dev) dès l'ouverture de l'écran — pas de bouton à presser, la détection de QR code est continue (`MobileScannerController`, formats restreints à `qrCode`). Permissions déclarées : `android.permission.CAMERA` (`AndroidManifest.xml`) et `NSCameraUsageDescription` (`Info.plist`).

Chaque QR détecté appelle `ScanBoardingCubit.handleDetectedCode(code)`. Un bouton secondaire "Scanner (P501)" reste disponible pour déclencher l'adaptateur matériel dédié `ScanBarcode` de MEZ-10 (`BarcodeScanner`, Mock par défaut) — utile sur le terminal P501 en production ou pour tester sans caméra. Les deux chemins convergent vers la même logique de validation (`ScanBoardingCubit._processCode`) : le code lu (caméra ou SDK matériel) ne correspondant pas à nos identifiants de billets mockés, la validation se fait contre le **prochain passager `enAttente` du manifeste courant** : trouvé → `ScanResultPage` "Billet valide" (marque le passager `embarque`, propose "Voir le billet") ; aucun passager en attente → "Billet refusé".

### Affichage du billet — `TicketCard`

`core/widgets/ticket_card.dart` : présentation "carte d'embarquement" intégrée au design system, reprenant le contenu requis par MEZ-10 (header KORA, infos départ, passager, siège, **QR code réel** via **[qr_flutter](https://pub.dev/packages/qr_flutter)** `^4.1.0`, footer fiscal), avec une séparation perforée imitant un billet à détacher. Accessible via un bouton "Voir le billet" (bottom sheet) depuis `DetailPassagerPage` et `ScanResultPage` (après un scan valide). Distinct de `TicketFormatter` (MEZ-10, 58mm/32 caractères) qui reste l'unique format pour l'**impression physique** — `TicketCard` est la présentation à l'écran.

### Descente anticipée (`features/descente/`)

`DescenteAnticipeePage` : `SeatMap` (`core/widgets/seat_map.dart`) — plan des sièges du véhicule, légende Libéré/Se libère bientôt/Occupé, squelette `SkeletonCard` pendant le chargement.

### Synchro (`features/synchro/`)

**Mocké** (voir § 20) : `SyncCubit` (singleton) gère `isOnline`, `isSyncing`, `pending: List<PendingOperation>`. `ManifesteCubit.updateStatut` enfile une opération à chaque changement de statut. `SynchroPage` : bascule manuelle en/hors-ligne, file d'attente, "Synchroniser maintenant", **déconnexion** (voir § Accueil ci-dessous). `SyncStatusBanner` (`core/widgets`, pastille compacte ou bandeau) est visible sur les 5 onglets (intégrée à `_ScaffoldWithBottomNav`) et sur les écrans empilés via `ScreenHeader.action`.

### Accueil (`features/home/`)

`AccueilPage` reproduit trait pour trait la mise en page de l'accueil `kora-passager` (voir échange du 16/08/2026), adaptée au métier OPS :

- **En-tête marine incurvé** (coins arrondis en bas) : logo KORA à gauche, bascule de thème à droite (pastille dorée si des opérations de synchro sont en attente) ; salutation + accroche 2 lignes ("Votre départ à gérer vous attend.")
- **Carte blanche flottante** qui chevauche l'en-tête (`Stack` + `Positioned` à décalage négatif) : bandeau "Départ géré aujourd'hui" (au lieu de "Où souhaitez-vous voyager ?"), DÉPART/ARRIVÉE (au lieu d'un formulaire de recherche), bouton rond doré pour changer de départ (au lieu du swap origine/destination), DATE/EMBARQUÉS (au lieu de DATE/PASSAGERS)
- Bouton doré pleine largeur **"Voir le manifeste"** (position équivalente à "Rechercher un voyage")
- **Accès rapides** : 4 tuiles Manifeste (mise en avant, bordure dorée) / Scan / Descente / Synchro, même ordre que la bottom nav
- **Bandeau promo marine** : raccourci vers `PrinterTestPage` (test des adaptateurs P501, "affiche le résultat immédiatement")

La **déconnexion** n'apparaît pas sur l'Accueil (absente de la maquette source) : elle est sur `SynchroPage`, en bas, pour rester accessible sans casser la fidélité visuelle de l'accueil.

## 18. Adaptateurs P501 — scanner QR et imprimante thermique 58mm (MEZ-10)

Feature `lib/features/printing/` — interfaces `BarcodeScanner`/`ThermalPrinter`, implémentations `Mock*`/`P501*` (MethodChannel `kora.ops/p501_scanner` et `kora.ops/p501_printer`, handler natif Android/iOS à écrire), `TicketFormatter` (58mm, 32 caractères, QR centré — impression physique uniquement, voir § 17bis pour l'affichage à l'écran). Switch Mock/P501 via `AppConfig.useMockHardware` (`--dart-define=USE_MOCK_HARDWARE=true|false`, défaut Mock en dev/staging).

Testé sans hardware via `flutter test test/features/printing/` et l'écran `PrinterTestPage` (`/printer-test`, accessible depuis le bandeau promo de l'Accueil). Le scan d'embarquement (§ 17bis) réutilise directement `ScanBarcode` de cette feature comme chemin matériel alternatif à la caméra.

## 19. Build

```bash
flutter build apk --dart-define=ENV=prod
flutter build appbundle --dart-define=ENV=prod
```

## 20. Prochaines étapes suggérées

- Écrire les tests manquants : `ManifesteCubit`, `ScanBoardingCubit`, `SeatMap`, `PassengerListTile`, `KoraBottomNav`.
- Remplacer `AuthLocalDataSource`/`MockOpsDataSource` par de vrais flux API au Sprint 5.
- Remplacer la Synchro mockée (bascule manuelle) par une vraie détection réseau (`connectivity_plus`, déjà utilisé côté `kora-passager`) et un vrai appel de synchronisation.
- Intégrer les vrais packages `flutter-networking`/`flutter-design-system` du monorepo s'ils deviennent accessibles, à la place des équivalents locaux (§ 10).
- Ajouter les polices Aptos en assets si fournies par la marque.
- Implémenter le handler natif Android/iOS des canaux P501 (§ 18) une fois le SDK disponible.
- Déplacer la bascule manuelle du thème vers un écran Paramètres dédié.
