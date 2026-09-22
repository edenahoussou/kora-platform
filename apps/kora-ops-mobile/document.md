# KORA OPS Mobile (P501) — Documentation technique

## 1. Présentation

**KORA OPS Mobile / P501** est l'application terrain de **KORA EXPRESS**, destinée aux chauffeurs bus, convoyeurs et agences. Elle couvre : manifestes, validation par QR/code court, embarquement, impression, descente anticipée, synchronisation et statuts opérationnels.

Ce n'est **pas** KORA Driver (future application des conducteurs Ride) ni l'app passagers **kora-passager** (dont ce projet reprend le design system et les patterns — voir § 10).

**Pas d'auto-inscription** : les agents KORA OPS sont provisionnés par un administrateur — ils ne créent jamais de compte dans l'app. Ils s'authentifient avec email + mot de passe **une seule fois par appareil** ; ensuite, seul le PIN est demandé (voir § 17).

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
| Connectivité | `connectivity_plus` | Vérification réseau au splash (non bloquante) — voir § 6bis |
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
- non authentifié, email jamais activé sur cet appareil → `/login/email` (voir § 17)
- non authentifié, email déjà activé sur cet appareil → `/login/pin`
- authentifié → `/home`

Une fois entré dans `/login/*`, chaque écran gère sa propre navigation explicite (`context.push`/`context.go`).

### Bottom nav (5 onglets)

`StatefulShellRoute.indexedStack` (`app/router/app_router.dart`), pattern recommandé par `go_router` : chaque onglet garde son propre historique de navigation même en changeant d'onglet. Portée par `KoraBottomNav` (`core/widgets/kora_bottom_nav.dart`), fidèle au style flottant de `kora-passager` :

| Onglet | Route | Écran |
|---|---|---|
| Accueil | `/home` | `AccueilPage` — tableau de bord, départ géré, accès rapides |
| Tournée | `/tournee` | `TourneePage` — liste des courses de l'agent |
| Scan | `/scan` | `ScanBoardingPage` |
| Descente | `/descente-anticipee` | `DescenteAnticipeePage` |
| Profil | `/profil` | `ProfilPage` |

**Le Manifeste n'est plus un onglet** (voir échange du 17/08/2026) : on y accède en sélectionnant une course depuis l'onglet Tournée, qui enregistre le départ choisi (`SelectedDepartCubit.select`) puis pousse `ManifestePage` (`/tournee/manifeste`).

Écrans empilés au-dessus de la bottom nav (`context.push`) : `ManifestePage`, `DetailPassagerPage` (`/tournee/manifeste/passager`), `ScanResultPage` (`/scan/resultat`), `SynchroPage` (`/synchro`, accessible depuis Profil), les écrans `/login/*`, et `PrinterTestPage` (`/printer-test`, MEZ-10).

**Important** : les Cubits partagés entre onglets ET écrans empilés (`SelectedDepartCubit`, `SyncCubit`, `ManifesteCubit`, `ScanBoardingCubit`) sont fournis en singleton au niveau racine (`app/app.dart`, `MultiBlocProvider`), pas localement dans une route — un écran poussé via `context.push` (ex. `ScanResultPage`) n'est **pas** un descendant du `builder` de sa route sœur dans l'arbre `go_router`, donc un `BlocProvider` local n'y serait pas visible.

## 6bis. Splash

`features/splash/presentation/pages/splash_page.dart` (déplacé hors de `features/auth/`, voir échange du 17/08/2026) : fond blanc, logo KORA bien visible, affiché **3 secondes minimum** à chaque lancement (`Stopwatch` + `Future.delayed` du temps restant).

En parallèle, vérifie la connexion réseau (`connectivity_plus`) pour initialiser `SyncCubit.goOnline()`/`goOffline()` — **jamais bloquant** : une base de données locale est prévue pour le travail hors-ligne avec synchronisation ultérieure (voir § 17bis Synchro), donc l'absence de réseau au démarrage n'empêche jamais de continuer vers la connexion. En cas d'erreur de vérification (émulateur sans capteur réseau, etc.), l'app démarre simplement en ligne par défaut.

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
| `AppColors.kora` | `#001D4E` | Marine — **CTA principal** (voir échange du 17/08/2026), en-têtes, écran PIN, cartes `navy` |
| `AppColors.gold` | `#F6B000` | Accent — badges, mises en avant ponctuelles |
| `AppColors.digital` | `#0057B8` | Bleu secondaire |
| `AppColors.onGold` | `#1A1400` | Texte sur fond `gold`/`warning` |
| `AppColors.success` / `warning` / `danger` | `#2D7A51` / `#C77700` / `#9C2430` | États sémantiques — `danger` seul reste utilisé comme couleur de bouton (voir § 10.3) |

`app/theme/kora_colors.dart` — `KoraColors extends ThemeExtension`, accessible via `context.koraColors.{surface,background,textPrimary,textMuted,line,shadow}`, avec des valeurs différentes en clair (`KoraColors.light`) et en sombre (`KoraColors.dark`), enregistrée dans `AppTheme` (§ 10bis).

**Aucun texte noir/gris-noir** (voir échange du 17/08/2026) : `KoraColors.light.textPrimary`/`textMuted` utilisent `AppColors.kora` (et ses dérivés en transparence) plutôt qu'un gris proche du noir — tout le texte de l'app est bleu KORA en mode clair, blanc/clair en mode sombre.

### 10.2 Typographie — `AppTextStyles`

`app/theme/app_text_styles.dart` — API **par fonction** (`AppTextStyles.eyebrow(color)`, `heading1(color)`, `heading2(color)`, `titleMedium(color)`, `lead(color)`, `body(color)`, `caption(color)`, `button(color)`), la couleur étant passée par l'appelant (`context.koraColors.textPrimary`/`textMuted` selon le contexte) plutôt que figée dans le style — fidèle à `kora-passager`. Police "Aptos Display"/"Aptos" avec `fontFamilyFallback` (police non fournie en asset ce sprint).

### 10.3 Composants

| Composant | Fichier | Usage |
|---|---|---|
| `KoraButton` | `core/widgets/kora_button.dart` | **3 variantes seulement** (voir échange du 17/08/2026) : `primary` (fond bleu nuit KORA), `ghost` (contour, actions secondaires), `danger` (fond rouge) |
| `KoraCard` | `core/widgets/kora_card.dart` | `surface`/`navy`/`gold`/`mist` |
| `KoraIconButton` | `core/widgets/kora_icon_button.dart` | Bouton rond (retour, actions compactes) |
| `KoraTag` | `core/widgets/kora_tag.dart` | Badge/pastille — base de `StatutBadge` |
| `ScreenHeader` | `core/widgets/screen_header.dart` | `AppBar` (`PreferredSizeWidget`) des écrans **empilés** — bouton retour, surtitre + titre |
| `MainAppBar` | `core/widgets/main_app_bar.dart` | `AppBar` des écrans **racines des 5 onglets** — logo KORA à gauche, surtitre + titre, `NotificationButton` à droite |
| `NotificationButton` | `core/widgets/notification_button.dart` | Icône notification (état vide via `KoraDialogs`, pastille dorée si opérations de synchro en attente) |
| `PinDots` / `PinKeypad` | `core/widgets/pin_pad.dart` | Écran PIN |
| `KoraBottomNav` | `core/widgets/kora_bottom_nav.dart` | Bottom nav 5 onglets (§ 6) |
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
├── features/auth/presentation/login_cubit_test.dart      # email+mdp, PIN, OTP
├── features/ops/data/mock_ops_data_source_test.dart
├── features/synchro/presentation/cubit/sync_cubit_test.dart
└── features/printing/…                                    # voir § 18
```

```bash
flutter test
```

Couverture manquante à ce stade (voir § 20) : `ManifesteCubit`, `ScanBoardingCubit`, widgets `SeatMap`/`PassengerListTile`/`KoraBottomNav`, `ProfilPage`, `TourneePage`.

## 16. Analyse & Lint

`analysis_options.yaml` étend `flutter_lints` avec des règles strictes supplémentaires.

```bash
flutter analyze
```

## 17. Flux d'authentification — email + mot de passe, PIN, OTP (MEZ-11)

**Pas d'auto-inscription** (voir § 1) : les agents sont provisionnés par un administrateur. L'email + mot de passe ne sert **qu'à activer un nouvel appareil**, une seule fois — ensuite, seul le PIN est demandé.

### Parcours

```
Splash (§ 6bis)
  │
  └─ AuthGuard : email jamais activé sur cet appareil ?
       │
       ├─ OUI → LoginEmailPage (/login/email)
       │           │
       │  email + mot de passe valides
       │           │
       │           ▼
       │      PinEntryPage (/login/pin) ── pendingOtpActivation=true
       │           │
       │      PIN correct → requestActivationOtp() → OtpVerificationPage (/login/otp)
       │           │                                       │
       │           │                        code correct → authenticate() + /home
       │           │                        code incorrect → OtpInvalidPage (/login/otp-invalid)
       │           │                                             │
       │           │                          "Recevoir un nouveau code" → retour OTP
       │           │                          "Retour à la connexion" → /login/pin
       │           │
       │      PIN incorrect → KoraDialogs.error + clavier réinitialisé
       │
       └─ NON (email déjà activé) → PinEntryPage (/login/pin) directement
                   │
              PIN correct → authenticate() + /home directement, PAS d'OTP
                   │
              PIN incorrect → KoraDialogs.error + clavier réinitialisé

"Mot de passe oublié ?" (depuis LoginEmailPage) → ForgotPasswordPage
  (/login/forgot-password) → email → KoraDialogs.success + retour
```

`LoginState.pendingOtpActivation` (voir `LoginCubit`) distingue les deux parcours : `true` juste après un `submitEmailPassword` réussi (première connexion, OTP à venir après le PIN), `false` après un `loadSavedEmail` (reconnexion, PIN seul). `PinEntryPage._submit()` bifurque sur ce booléen.

### Données mockées (`AuthLocalDataSource`)

- Email/mot de passe acceptés : **`aicha.hounkpe@kora-express.com`** / **`Kora@2026`**
- PIN accepté : **`1234`**
- Code OTP accepté : **`583187`**
- L'email n'est enregistré comme "activé sur cet appareil" (`getSavedEmail()` non-null) qu'**après un OTP réussi** — jamais au moment du login email/mot de passe lui-même.
- `logout()` efface la session courante mais conserve l'email activé, pour retomber sur le PIN (pas email/mot de passe) à la reconnexion.

### Écrans

| Écran | Route | Fichier |
|---|---|---|
| Se connecter (email + mot de passe) | `/login/email` | `login_email_page.dart` |
| Mot de passe oublié | `/login/forgot-password` | `forgot_password_page.dart` |
| Votre PIN KORA OPS (fond marine) | `/login/pin` | `pin_entry_page.dart` |
| Code de vérification | `/login/otp` | `otp_verification_page.dart` |
| Code non valide | `/login/otp-invalid` | `otp_invalid_page.dart` |

### Cubits

- **`AuthCubit`** (singleton) : état de session global (`AuthUnauthenticated(savedEmail)`), seule source de vérité pour `AuthGuard`.
- **`LoginCubit`** (singleton, partagé entre toutes les routes `/login/*`) : `submitEmailPassword`, `submitForgotPassword`, `loadSavedEmail`, `submitPin`, `requestActivationOtp`, `submitOtp`, `resendOtp`, `reset`. Méthodes `Future<bool>` (navigation) + état émis (affichage). **`reset()` est appelé à la déconnexion** (`ProfilPage`, via `getIt<LoginCubit>()`) pour ne pas laisser fuiter `pendingOtpActivation` d'une session à l'autre.
- Authentification finale : l'écran (PIN direct pour une reconnexion, ou OTP pour une première connexion) appelle `AuthCubit.authenticate(user)` avant `context.go('/home')`.

## 17bis. Écrans OPS mockés (MEZ-11) — Tournée, Manifeste, Scan, Descente anticipée, Profil, Accueil

### Modèles (`features/ops/domain/entities/`)

- **`Depart`** — id, route, date, heure, immatriculation, places totales/occupées
- **`Passenger`** — id, nom, siège, `BoardingStatus` (`enAttente`/`embarque`/`refuse`/`descenduAnticipe`), segment O/D, ticketId, téléphone
- **`Ticket`** — numéro, passager, départ, siège, segment, `qrData` (cohérent avec `TicketPayload` de MEZ-10)
- **`Seat`** — numéro, `SeatStatus` (`occupe`/`libere`/`reserve`), passager, segment de libération

`features/ops/data/mock_ops_data_source.dart` génère 3 départs et des listes de passagers déterministes (seed = id du départ) — remplacé par de vraies API au Sprint 5.

### Tournée (`features/tournee/`)

`TourneePage` (onglet, voir échange du 17/08/2026) : liste des courses de l'agent, remplace l'ancien écran "Sélection du départ". Sélectionner une course l'enregistre dans `SelectedDepartCubit` **et** pousse directement `ManifestePage` — on prend désormais par la tournée avant d'accéder au manifeste, plus l'inverse.

### Manifeste (`features/manifeste/`)

`ManifestePage` (**écran empilé**, `/tournee/manifeste`, plus un onglet) : liste `PassengerListTile` (nom, siège, `StatutBadge`, segment O/D), squelette `SkeletonList` pendant le chargement, états vide/erreur (`AppStatePlaceholder`), `RefreshIndicator`. `ManifesteCubit` recharge automatiquement au changement de départ (écoute `SelectedDepartCubit`) et expose `updateStatut(passengerId, statut)`, qui journalise l'opération dans `SyncCubit`.

`DetailPassagerPage` (`/tournee/manifeste/passager`, poussé depuis une ligne du Manifeste) : infos billet, segment, siège, statut, actions Embarquer (`primary`)/Refuser (`danger`)/Descente anticipée (`ghost`), bouton "Voir le billet" (`TicketCard`).

### Scan boarding (`features/scan/`)

`ScanBoardingPage` allume une **vraie caméra en direct** (package **[mobile_scanner](https://pub.dev/packages/mobile_scanner)** `^7.4.0`, vérifié sur pub.dev) dès l'ouverture de l'écran — pas de bouton à presser, la détection de QR code est continue (`MobileScannerController`, formats restreints à `qrCode`). Permissions déclarées : `android.permission.CAMERA` (`AndroidManifest.xml`) et `NSCameraUsageDescription` (`Info.plist`).

Chaque QR détecté appelle `ScanBoardingCubit.handleDetectedCode(code)`. Un bouton secondaire "Scanner (P501)" reste disponible pour déclencher l'adaptateur matériel dédié `ScanBarcode` de MEZ-10 (`BarcodeScanner`, Mock par défaut) — utile sur le terminal P501 en production ou pour tester sans caméra. Les deux chemins convergent vers la même logique de validation (`ScanBoardingCubit._processCode`) : le code lu (caméra ou SDK matériel) ne correspondant pas à nos identifiants de billets mockés, la validation se fait contre le **prochain passager `enAttente` du manifeste courant** : trouvé → `ScanResultPage` "Billet valide" (marque le passager `embarque`, propose "Voir le billet") ; aucun passager en attente → "Billet refusé".

### Affichage du billet — `TicketCard`

`core/widgets/ticket_card.dart` : présentation "carte d'embarquement" intégrée au design system, reprenant le contenu requis par MEZ-10 (header KORA, infos départ, passager, siège, **QR code réel** via **[qr_flutter](https://pub.dev/packages/qr_flutter)** `^4.1.0`, footer fiscal), avec une séparation perforée imitant un billet à détacher. Accessible via un bouton "Voir le billet" (bottom sheet) depuis `DetailPassagerPage` et `ScanResultPage` (après un scan valide). Distinct de `TicketFormatter` (MEZ-10, 58mm/32 caractères) qui reste l'unique format pour l'**impression physique** — `TicketCard` est la présentation à l'écran.

### Descente anticipée (`features/descente/`)

`DescenteAnticipeePage` : `SeatMap` (`core/widgets/seat_map.dart`) — **plan de bus** (voir échange du 17/08/2026) : places Chauffeur/Convoyeur à l'avant, porte, puis rangées de sièges 1+2 séparées par l'allée centrale (1 siège à gauche, 2 à droite, fidèle au séquençage `MockOpsDataSource` : A à gauche, B/C à droite). Légende Libéré/Se libère bientôt/Occupé, squelette `SkeletonCard` pendant le chargement.

### Synchro (`features/synchro/`)

**Mocké** (voir § 20) : `SyncCubit` (singleton) gère `isOnline`, `isSyncing`, `pending: List<PendingOperation>`, initialisé au splash (§ 6bis) puis mis à jour par `ManifesteCubit.updateStatut` (une opération enfilée à chaque changement de statut). `SynchroPage` (**écran empilé**, `/synchro`, accessible depuis Profil — plus un onglet dédié, voir échange du 17/08/2026) : bascule manuelle en/hors-ligne, file d'attente, "Synchroniser maintenant". `SyncStatusBanner` (`core/widgets`, pastille compacte ou bandeau) reste visible sur les 4 onglets qui en ont besoin (`ScreenHeader.action` ou ligne dédiée sous `MainAppBar`) et sur les écrans empilés.

### Profil (`features/profil/`)

`ProfilPage` (onglet, remplace Synchro dans la bottom nav — voir échange du 17/08/2026) : carte agent (avatar initiales, nom, rôle, email), section Préférences avec la **bascule de thème clair/sombre** (déplacée ici depuis l'Accueil), accès à Synchronisation et aux Adaptateurs P501, et le bouton **Se déconnecter** (confirmation `KoraDialogs`, réinitialise aussi `LoginCubit` — voir § 17).

### Accueil (`features/home/`)

`AccueilPage` reproduit trait pour trait la mise en page de l'accueil `kora-passager` (voir échanges des 16 et 17/08/2026), adaptée au métier OPS :

- **En-tête marine incurvé** (coins arrondis en bas) : logo KORA à gauche, `NotificationButton` à droite (pastille dorée si des opérations de synchro sont en attente — la bascule clair/sombre n'est **plus** ici, voir § Profil) ; salutation + accroche 2 lignes ("Votre départ à gérer vous attend.")
- **Bloc "trajet" soudé en 3 pièces** (voir échange du 17/08/2026) : header bleu ("Départ géré aujourd'hui" / "KORA OPS") et footer (DATE/EMBARQUÉS, fond légèrement plus sombre qu'un blanc pur) tous deux en retrait, la **carte blanche** (DÉPART/ARRIVÉE) occupant toute la largeur du bloc — les trois empilés sans espace, header/footer visuellement soudés à la carte. Bouton switch doré entièrement à l'extérieur de la carte, centré sur le séparateur départ/arrivée, ouvre l'onglet Tournée.
- Bouton pleine largeur **"Voir le manifeste"** (ou "Choisir une course" si aucun départ sélectionné)
- **Accès rapides** : 4 tuiles Tournée (mise en avant, bordure dorée) / Scan / Descente / (plus de tuile Synchro, remplacée par l'accès Profil via la bottom nav)
- **Bandeau promo marine** : raccourci vers `PrinterTestPage` (test des adaptateurs P501, affiche le résultat immédiatement)

## 18. Adaptateurs P501 — scanner QR et imprimante thermique 58mm (MEZ-10)

Feature `lib/features/printing/` — interfaces `BarcodeScanner`/`ThermalPrinter`, implémentations `Mock*`/`P501*` (MethodChannel `kora.ops/p501_scanner` et `kora.ops/p501_printer`, handler natif Android/iOS à écrire), `TicketFormatter` (58mm, 32 caractères, QR centré — impression physique uniquement, voir § 17bis pour l'affichage à l'écran). Switch Mock/P501 via `AppConfig.useMockHardware` (`--dart-define=USE_MOCK_HARDWARE=true|false`, défaut Mock en dev/staging).

Testé sans hardware via `flutter test test/features/printing/` et l'écran `PrinterTestPage` (`/printer-test`, accessible depuis le bandeau promo de l'Accueil). Le scan d'embarquement (§ 17bis) réutilise directement `ScanBarcode` de cette feature comme chemin matériel alternatif à la caméra.

## 18bis. Manifeste frontière — impression P501 / PDF téléphone (MEZ-94)

Bouton **« Imprimer le manifeste frontière »** en bas de `ManifestePage` (visible si le manifeste est chargé, non vide, et que le départ n'est pas connu comme national). Feature `lib/features/border_manifest/`, formatage et adaptateur dans `lib/features/printing/`.

**Deux modes, selon `AppConfig.terminalMode`** :

- **P501** → `BorderManifestPrintPage` (`/tournee/manifeste/frontiere`) : **3 exemplaires** (`kBorderManifestCopies`) sur commande explicite, état de chaque exemplaire (en attente / en cours / imprimé / échec / à confirmer), reprise sans doublon. Format 58 mm, 32 caractères, ASCII (`BorderManifestFormatter`) : **2 lignes par passager** (`01 DIOP FATOU` / `SN A1234567 > Porto-Novo 12A`), texte replié et **jamais tronqué** (nom, n° de document).
- **Téléphone** → `BorderManifestPdfPage` (`/tournee/manifeste/frontiere/pdf`) : un PDF A4 généré par le serveur (`GET /ops/departures/{uuid}/manifest/pdf`, Browsershot, même mécanisme que les billets), affiché/partagé/imprimé via le système. Pas de notion de « 3 exemplaires ».

**Refus si incomplet** — `BorderManifestBuilder` (miroir de la règle serveur, 422 `manifest_incomplete`) exige, pour chaque passager encore du voyage : nom, nationalité, n° de document, siège, destination. Un départ non international, ou dont le type est inconnu (cache antérieur à MEZ-94 → « rafraîchissez le manifeste »), est refusé. Sont exclus du manifeste les passagers `refuse` (annulé/no-show) et `descenduAnticipe`.

**Reprise sans doublon** — table sqlite `border_manifest_print_jobs` (schéma v5). Séquence par exemplaire : `markCopyStarted` (persisté **avant** l'appel imprimante) → impression → `markCopyConfirmed` / `markCopyFailed`. Machine à états dans `BorderManifestPrintRepositoryImpl` (transition interdite = `StateError`, jamais plus d'exemplaires que demandé, jamais deux jobs non terminés pour un même départ).

| Situation | Comportement |
| --- | --- |
| Papier absent / imprimante absente | Job « interrompu », exemplaire non compté, message dédié (`PrinterFailureKind`), reprise à l'exemplaire en échec |
| App tuée pendant un exemplaire | Au redémarrage : « l'exemplaire N est-il sorti ? » — l'app ne réimprime **jamais** seule ; le constat du convoyeur tranche |
| Impression déjà terminée | Réimpression seulement après confirmation explicite |
| Double appui | Ignoré (`_running`) |

**Journalisation** — `TerminalRepository.logActivity(print, …)` à chaque exemplaire, fin d'impression, constat après crash et refus : terminal, utilisateur, heure, nombre d'exemplaires, résultat. Jamais de nom, nationalité ni n° de document dans le journal.

**Code natif Android à écrire** (`MainActivity.kt`, hors périmètre — SDK P501 requis) : handler `printBorderManifest` du canal `kora.ops/p501_printer` — voir la docstring de `P501ThermalPrinter` pour le contrat exact (arguments, codes d'erreur `NO_PRINTER`/`PRINTER_DISCONNECTED`/`OUT_OF_PAPER`/`NO_PAPER`/`OVERHEAT`).

Tests : `flutter test test/features/border_manifest test/features/printing test/features/manifeste`.

## 18ter. Suivi de position du convoyeur en arrière-plan (MEZ-101)

Feature `lib/features/tracking/` — consomme le contrat d'ingestion de [MEZ-100](https://linear.app/mezabodev/issue/MEZ-100) (`POST /ops/tracking/positions/batch`) ; aucun WebSocket, uniquement des lots HTTP.

**Déclenchement** — `TrackingCubit` écoute `SelectedDepartCubit` (pas d'action explicite du convoyeur) : `depart.status == 'departed'` → démarre, `'arrived'`/`'cancelled'`/désélection → arrête. Il ne mesure jamais de position lui-même.

**Deux isolats** :

- **Principal (UI)** — `TrackingRepositoryImpl` : vérifie permission/GPS/précision (`LocationProvider`), persiste l'état de session (`tracking_session_state`, sqlite) puis démarre le service premier plan Android (`flutter_foreground_task`). `bootstrap()` (appelé depuis `SplashPage`) relit cet état et redémarre le service s'il a été tué par l'OS pendant que l'app était fermée.
- **Service premier plan** — `TrackingTaskHandler` (fonction top-level `trackingStartCallback`, isolate séparé, notification persistante conforme aux règles Android, tourne écran verrouillé/app fermée). Reconstruit ses propres `AppDatabase`/`Dio` (aucun état de `get_it` ne traverse un isolate) ; écoute le flux GPS (`LocationProvider.watchPosition`), les changements d'état du service de localisation et la connectivité, et délègue toute décision d'enfilage/d'envoi à `TrackingSyncEngine`.

**`LocationProvider`** — une seule abstraction, une seule implémentation réelle (`GeolocatorLocationProvider`) pour **P501 et téléphone** : le P501 est lui-même un terminal Android, sa puce GPS passe par le même `FusedLocationProviderClient` qu'un téléphone (voir `docs/tracking-location-source.md` côté backend, MEZ-100). `MockLocationProvider` sert le mode `AppConfig.useMockHardware` (même bascule que `BarcodeScanner`/`ThermalPrinter`, § 18) et les tests.

**`TrackingSyncEngine`** (`lib/features/tracking/data/tracking_sync_engine.dart`) — le cœur testable, délibérément extrait de `TrackingTaskHandler` pour ne dépendre que d'interfaces mockables (`TrackingLocalDataSource`/`TrackingRemoteDataSource`), jamais de `flutter_foreground_task` : enfilage local (table `tracking_positions_queue`, sqlite), envoi par lots de 50, idempotent par `position_uuid` (un rejeu ne crée jamais de doublon côté serveur), aucune perte de point sur coupure réseau (reste `pending` jusqu'au prochain envoi réussi).

**Événements immédiats** (hors cycle du minuteur, 15 s) — premier point d'une session (démarrage), heartbeat `gpsUnavailable` dès que l'OS signale le GPS désactivé (perte de signal), et flush au retour de connectivité (reconnexion). L'arrêt (`TrackingRepositoryImpl.stop`) envoie `'stop'` au TaskHandler, qui flush le dernier point connu puis efface la session et arrête le service lui-même.

**Reprise après crash** — la séquence (`tracking_session_state.last_sequence`) et la session active sont en base, pas en mémoire. Si le service est tué par l'OS sans arrêt volontaire (`_stopRequested` resté faux dans `TrackingTaskHandler.onDestroy`), la session reste `active` : `TrackingRepositoryImpl.bootstrap()` la redémarre au prochain lancement de l'app, sans perte des points déjà en file ni régression de séquence.

**États affichés** (`TrackingStatusBanner`, même gabarit que `SyncStatusBanner`) — `TrackingAvailability` (permission refusée, GPS désactivé, précision insuffisante — bloquants, tapables pour corriger) côté démarrage, `TrackingState.isOnline`/`isSyncing`/`pendingCount` côté suivi actif (réseau indisponible n'est jamais bloquant : la mesure et la mise en file continuent hors ligne).

**Permissions Android** — `ACCESS_FINE_LOCATION`/`ACCESS_COARSE_LOCATION` (`geolocator`), `ACCESS_BACKGROUND_LOCATION` demandée séparément en deux temps (`permission_handler`, flux recommandé Android 10+ — `geolocator` seul ne la redemande jamais), `FOREGROUND_SERVICE`/`FOREGROUND_SERVICE_LOCATION`, `POST_NOTIFICATIONS` (Android 13+), `REQUEST_IGNORE_BATTERY_OPTIMIZATIONS` (certains constructeurs tuent sinon le service malgré le premier plan).

Tests : `flutter test test/features/tracking/` — `TrackingSyncEngine` (idempotence, perte réseau, découpage en lots, rejet définitif) contre des doubles en mémoire ; `TrackingCubit` (démarrage/arrêt selon le statut du départ, permissions, réseau/synchro) contre un `TrackingRepository` mocké et un vrai `SelectedDepartCubit`. Le flux GPS réel, le service premier plan et la notification Android ne sont pas couverts par des tests automatisés (nécessitent un appareil/émulateur) — à valider manuellement (voir § 20).

## 19. Build

```bash
flutter build apk --dart-define=ENV=prod
flutter build appbundle --dart-define=ENV=prod
```

## 20. Prochaines étapes suggérées

- Écrire les tests manquants : `ManifesteCubit`, `ScanBoardingCubit`, `SeatMap`, `PassengerListTile`, `KoraBottomNav`, `ProfilPage`, `TourneePage`.
- Remplacer `AuthLocalDataSource`/`MockOpsDataSource` par de vrais flux API au Sprint 5 — en particulier remplacer les identifiants/PIN/OTP mockés (§ 17) par un vrai provisioning administrateur.
- Remplacer la Synchro mockée (bascule manuelle) par un vrai appel de synchronisation vers le serveur, en s'appuyant sur la base de données locale prévue (voir § 6bis) et la détection réseau déjà en place (`connectivity_plus`, § 6bis).
- Intégrer les vrais packages `flutter-networking`/`flutter-design-system` du monorepo s'ils deviennent accessibles, à la place des équivalents locaux (§ 10).
- Ajouter les polices Aptos en assets si fournies par la marque.
- Implémenter le handler natif Android/iOS des canaux P501 (§ 18) une fois le SDK disponible.
- Ajouter un bouton retour désactivé/masqué sur `LoginEmailPage` (écran d'entrée sans route précédente) plutôt que de laisser le comportement par défaut de `ScreenHeader`.
- Valider sur appareil réel (§ 18ter, MEZ-101) : suivi écran verrouillé/app fermée, notification premier plan conforme sur Android 13/14, boîtes de dialogue permission arrière-plan et optimisation batterie, et reprise effective après un crash forcé (`adb shell am kill`) ou un redémarrage du téléphone — non couvert par les tests automatisés.
- Intégrer `TrackingStatusBanner` (§ 18ter) sur les écrans qui affichent déjà `SyncStatusBanner` au-delà de `TourneePage` (Manifeste, Scan, Descente anticipée…), si souhaité.
