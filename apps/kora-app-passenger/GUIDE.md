# KORA App — Guide du projet Flutter

Ce document explique ce qui a été mis en place, pourquoi, et comment
continuer à construire l'application en suivant les mêmes principes.
Écrit pour un développeur qui découvre la Clean Architecture — chaque
section montre un exemple concret à copier-coller.

---

## Sommaire

1. Vue d'ensemble
2. Lancer le projet
3. Comprendre l'arborescence
4. Les constantes (API, storage...)
5. Le design system (couleurs, textes, composants, icônes SVG)
6. Le mode clair / sombre
7. La navigation (go_router)
8. Les dialogs (pro_dialog)
9. Créer une nouvelle feature — tutoriel complet
10. Créer un Bloc — tutoriel pas à pas
11. Génération de code (freezed / json_serializable)
12. Erreurs fréquentes à éviter
13. Ce qui n'est PAS encore fait

---

## 1. Vue d'ensemble

Ce projet est la fondation technique complète de l'application KORA :

- Clean Architecture (couches `data` / `domain` / `presentation`)
- `flutter_bloc` pour la gestion d'état
- `dio` + `dartz` pour les appels réseau et la gestion d'erreurs
  fonctionnelle (`Either<Failure, T>`)
- `go_router` pour la navigation (bottom nav avec état préservé par
  onglet)
- `get_it` pour l'injection de dépendances
- `shared_preferences` (données non sensibles) et
  `flutter_secure_storage` (tokens, données sensibles)
- `freezed` / `json_serializable` pour des modèles immuables et
  sérialisables
- Un design system fidèle au prototype HTML/CSS, avec des icônes SVG
  exactes (via `flutter_svg`) reprises du prototype original
- `pro_dialog` pour des dialogs (succès/erreur/confirmation/chargement)
  cohérents dans toute l'app
- La gestion du mode clair / sombre

L'app est livrée et en exploitation : le parcours Bus complet
(recherche, départs, sièges, passagers, paiement, billets, assistance,
groupes), le compte voyageur, le Wallet Bus (cash, points, recharge,
historique, règles, bonus, parrainage), les notifications, le support et
la gestion du profil sont branchés sur l'API. Seul l'onglet Colis reste
un écran d'attente, le temps de la phase Colis (voir `../../README.md`).

### Pourquoi la Clean Architecture ?

| Couche | Rôle | Exemples dans ce projet |
|---|---|---|
| domain | Le "métier" pur, zéro dépendance technique | `AppThemeMode`, `ThemeRepository` (interface), `UseCase` |
| data | Parle aux vraies sources (API via Dio, stockage local...) | `ThemeLocalDataSource`, `ThemeRepositoryImpl`, `UserModel` |
| presentation | Ce que l'utilisateur voit et touche | `ThemeBloc`, les pages, `KoraBottomNav` |

Règle d'or : une couche ne connaît que la couche juste en dessous d'elle.
Le Bloc ne parle jamais directement à Dio ou à `FlutterSecureStorage` —
il passe toujours par un repository (lui-même appelé via un `UseCase`).

---

## 2. Lancer le projet

```bash
# 1. Installer les dépendances
flutter pub get

# 2. Générer le code (freezed / json_serializable) — voir section 11
dart run build_runner build --delete-conflicting-outputs

# 3. Lancer l'application
flutter run
```

L'étape 2 est indispensable. Sans elle, `UserModel` (et tout futur
modèle `freezed`) ne compile pas, car ses fichiers `.freezed.dart` /
`.g.dart` n'existent pas encore. Le reste de l'app (thème, navigation,
accueil) fonctionne même sans cette étape.

---

## 3. Comprendre l'arborescence

```
lib/
├── main.dart                       # Point d'entrée par défaut (flutter run sans -t)
├── main_dev.dart                   # Point d'entrée environnement dev
├── main_staging.dart               # Point d'entrée environnement staging
├── main_prod.dart                  # Point d'entrée environnement prod
├── bootstrap.dart                  # Init commune (AppEnv + get_it) avant runApp, partagée par les 3 main_*.dart
├── app.dart                        # Widget racine : BlocProvider + MaterialApp.router
│
├── core/                           # Tout ce qui est PARTAGÉ entre les features de CETTE app
│   ├── constants/                  # Endpoints, pagination, clés de stockage
│   ├── di/injector.dart            # Configuration get_it (service locator)
│   ├── env/app_env.dart            # AppEnv : config par environnement (dev/staging/prod)
│   ├── error/                      # Failure, Exception, mapping des erreurs API
│   ├── l10n/                       # Textes de l'interface (assets/languages/*.json)
│   ├── network/                    # Helpers JSON, expiration de session
│   ├── push/                       # Firebase, notifications locales, listeners
│   ├── router/                     # go_router, bottom nav, garde d'authentification
│   ├── services/                   # Permissions système
│   ├── update/                     # Force-update et gate de version
│   ├── usecases/                   # Classe de base UseCase<Type, Params>
│   ├── utils/                      # Dates fr_FR, téléphone, couleurs de titre
│   └── widgets/                    # Composants transverses (en-têtes, calendrier...)
│
└── features/                       # Un dossier par feature, en couches data/domain/presentation
    ├── welcome/                    # Splash et onboarding
    ├── auth/                       # Connexion, inscription, OTP, PIN, nouvel appareil
    ├── home/                       # Accueil et recherche de départ
    ├── booking/                    # Départs, sièges, passagers, billets, assistance, groupes
    ├── payment/                    # Paiement Mobile Money et retours de paiement
    ├── wallet/                     # Solde cash, points, recharge, historique, règles, bonus
    ├── colis/                      # Écran d'attente de la phase Colis
    ├── notifications/              # Centre de notifications
    ├── support/                    # Aide, contact, chat support
    ├── profil/                     # Compte, préférences, sécurité, suppression
    ├── language/                   # Choix de langue
    └── theme/                      # Mode clair / sombre — feature de référence à suivre
        ├── data/{datasources,repositories}/
        ├── domain/{entities,repositories}/
        └── presentation/{bloc,widgets}/

assets/
└── images/kora_logo.png
```

`DioClient` / `NetworkInfo` (client réseau) et tout le design system
(couleurs, typographie, composants `Kora*`, icônes SVG, `KoraDialogs`) ne
vivent **pas** dans ce dossier : ce sont des packages partagés avec les
autres apps Flutter du monorepo.

```
packages/
├── flutter-networking/lib/src/
│   ├── dio_client.dart             # Config Dio + intercepteur (token, logs)
│   └── network_info.dart           # NetworkInfo / NetworkInfoImpl (connexion internet)
│
└── flutter-design-system/lib/src/
    ├── colors/app_colors.dart      # Palette de couleurs KORA (fixe)
    ├── colors/kora_colors.dart     # Couleurs contextuelles clair/sombre (ThemeExtension)
    ├── typography/app_text_styles.dart
    ├── theme/app_theme.dart        # ThemeData clair et sombre
    ├── dialogs/kora_dialogs.dart   # KoraDialogs : wrapper pro_dialog
    ├── icons/kora_vehicle_icon.dart
    ├── icons/kora_nav_icon.dart
    └── widgets/
        ├── kora_card.dart
        ├── kora_button.dart
        ├── kora_icon_button.dart
        ├── kora_tag.dart
        ├── kora_bottom_nav.dart    # Bottom nav flottante (icônes SVG)
        └── placeholder_page.dart   # Page "texte centré" réutilisable
```

Importés dans l'app via les deux barrel files uniquement (jamais un
fichier sous `lib/src/` directement) :

```dart
import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
```

---

## 4. Les constantes (API, storage...)

C'est la réponse directe à "il ne faut pas que la base_url et compagnie
restent en dur quelque part" : toutes les valeurs de configuration sont
centralisées, jamais éparpillées dans le code.

### ApiConstants (core/constants/api_constants.dart)

```dart
ApiConstants.connectTimeoutMs
ApiConstants.login          // '/auth/login'
ApiConstants.busSearch      // '/bus/search'
ApiConstants.walletBalance  // '/wallet/balance'
// ... voir le fichier pour la liste complète
```

L'URL de base n'est PAS dans `ApiConstants` : elle dépend de
l'environnement (dev/staging/prod) et vit dans `AppEnv.current.apiBaseUrl`
(voir `core/env/app_env.dart`). Les chemins suivent la spécification
OpenAPI du dépôt (`docs/mintlify/api/openapi.yaml`) et la documentation
d'authentification (`docs/mintlify/authentication.mdx`) ; tout le code
réseau (`DioClient`, injecté dans `injector.dart`) s'appuie dessus
automatiquement.

### StorageKeys (core/constants/storage_keys.dart)

Centralise les clés utilisées pour `SharedPreferences` (non sensible) et
`FlutterSecureStorage` (sensible : tokens...). Ne jamais écrire une clé de
stockage "en dur" dans un data source : toujours passer par cette classe.

### AppConstants (core/constants/app_constants.dart)

Constantes générales sans lien avec l'API (taille de page par défaut,
durée de validité du cache, longueur d'un code OTP...).

---

## 5. Le design system

Tout ce qui suit vit dans `packages/flutter-design-system` (voir §3), pas
dans cette app — importé via `package:flutter_design_system`.

### 5.1 Les couleurs — AppColors et KoraColors

Voir les fichiers `flutter-design-system/lib/src/colors/app_colors.dart`
(couleurs fixes de la marque) et
`flutter-design-system/lib/src/colors/kora_colors.dart` (couleurs
contextuelles clair/sombre, accessibles via `context.koraColors`). Ne
jamais écrire de couleur en dur dans un widget.

### 5.2 Les composants prêts à l'emploi

| Composant | Fichier (dans `flutter-design-system/lib/src/`) | Usage |
|---|---|---|
| KoraCard | widgets/kora_card.dart | Card avec variantes surface/navy/gold/mist |
| KoraButton | widgets/kora_button.dart | Bouton primary/secondary/ghost/danger |
| KoraIconButton | widgets/kora_icon_button.dart | Bouton rond avec icône |
| KoraTag | widgets/kora_tag.dart | Badge/pastille de statut |
| KoraBottomNav | widgets/kora_bottom_nav.dart | Barre de navigation flottante (5 onglets fixes) |
| PlaceholderPage | widgets/placeholder_page.dart | Page "texte centré" pour un onglet pas encore développé |

### 5.3 Les icônes SVG — fidèles au prototype HTML au pixel près

Icônes véhicules (`flutter-design-system/lib/src/icons/kora_vehicle_icon.dart`) :
les fichiers `.svg` dans `assets/icons/vehicles/` (du package design
system) sont une copie exacte des tracés `<svg>` du prototype (retrouvés
dans `js/app.js`, fonction `mobilityPictogram`). Rendu 100% identique à
l'original.

```dart
KoraVehicleIcon(type: KoraVehicleType.moto, size: 40)
KoraVehicleIcon(type: KoraVehicleType.comfort, size: 40) // avec l'étoile dorée
```

Icônes de navigation (`flutter-design-system/lib/src/icons/kora_nav_icon.dart`) :
le prototype dessine ces icônes en CSS pur (pas de vrai SVG source), donc
on les a redessinées à la main en `.svg` en conservant exactement la même
silhouette (`assets/icons/nav/`). Contrairement aux icônes véhicules,
elles sont teintables dynamiquement (actif = doré, inactif = gris) :

```dart
KoraNavIcon(type: KoraNavIconType.home, color: AppColors.gold, size: 20)
```

### 5.4 Ajouter une nouvelle icône SVG

1. Place le fichier `.svg` dans `assets/icons/nav/` ou
   `assets/icons/vehicles/` (ou un nouveau sous-dossier si besoin — pense
   à le déclarer dans `pubspec.yaml`, section `flutter: assets:`)
2. Si l'icône doit être teintable dynamiquement, utilise
   `stroke="#000000"` (ou une couleur neutre) dans le fichier : le widget
   appliquera un `ColorFilter` par-dessus
3. Si l'icône a des couleurs fixes (comme l'étoile dorée du véhicule
   "confort"), écris directement les bonnes couleurs dans le `.svg`, et
   n'applique pas de `colorFilter` au moment de l'afficher
4. Ajoute une entrée dans l'enum correspondant
   (`KoraVehicleType`/`KoraNavIconType`) et dans la `Map` `_assetPaths`

---

## 6. Le mode clair / sombre

Inchangé par rapport à la version précédente du projet : `ThemeBloc`
(events `ThemeStarted`/`ThemeModeChanged`/`ThemeToggled`), persistance via
`SharedPreferences`, widget `ThemeSwitch` prêt à l'emploi.

```dart
context.read<ThemeBloc>().add(const ThemeToggled());
context.read<ThemeBloc>().add(const ThemeModeChanged(AppThemeMode.dark));
```

---

## 7. La navigation (go_router)

La navigation utilise `StatefulShellRoute.indexedStack`
(`core/router/app_router.dart`) — le pattern recommandé par `go_router`
pour une app avec bottom nav : chaque onglet garde son propre historique
de navigation, même en changeant d'onglet.

### Naviguer vers un onglet

```dart
context.go('/voyages');
```

### Ajouter un écran de détail

Les onglets sont des routes simples ; les écrans de détail (détail d'un
départ, billet, historique Wallet...) sont déclarés comme routes de
premier niveau, puis ouverts avec `context.push`.

```dart
GoRoute(
  path: '/departure',
  builder: (context, state) => const DepartureDetailPage(),
),
```

Puis, pour y naviguer : `context.push(AppRouter.departureDetailPath);`

### Le guard d'authentification

`AppRouter.router` a un `redirect` global (`_redirect` dans
`core/router/app_router.dart`), piloté par `AuthBloc` et réévalué à
chaque navigation et à chaque changement de session :

- session encore inconnue au démarrage → `/splash`, le temps que le
  stockage sécurisé réponde (évite le clignotement au lancement) ;
- session ouverte sur un écran d'entrée (`/welcome`, `/login`,
  `/signup`, `/otp`, `/create-pin`...) → `/home` ;
- pas de session et écran protégé → `/onboarding` à la première
  ouverture, puis `/welcome`.

Les écrans accessibles sans session sont listés dans `_publicPaths`. Les
chemins d'authentification (`loginPath`, `signupPath`, `otpPath`,
`createPinPath`, `newDevicePath`) sont des constantes de `AppRouter` :
toujours les utiliser plutôt qu'une chaîne écrite en dur.

---

## 8. Les dialogs (pro_dialog)

Aucun écran de l'app n'importe `pro_dialog` directement : tout passe par
`KoraDialogs` (`flutter-design-system/lib/src/dialogs/kora_dialogs.dart`,
exporté via `package:flutter_design_system`). Ça centralise le style
(thème, animations) et permet de changer de librairie de dialogs plus
tard sans toucher aux écrans.

```dart
// Succès
KoraDialogs.success(context, title: 'Trajet réservé', description: 'À bientôt !');

// Erreur
KoraDialogs.error(context, title: 'Échec du paiement');

// Confirmation (attend la réponse de l'utilisateur)
final confirmed = await KoraDialogs.confirm(
  context,
  title: 'Annuler le trajet ?',
  description: 'Cette action est irréversible.',
);
if (confirmed) { /* ... */ }

// Chargement (à utiliser en paire show/hide)
KoraDialogs.showLoading(context, message: 'Paiement en cours...');
// ... appel réseau ...
KoraDialogs.hideLoading(context);
```

---

## 9. Créer une nouvelle feature — tutoriel complet

Exemple fil rouge : la feature `wallet` (récupérer le solde depuis
l'API), déjà implémentée. Les étapes montrent le cheminement complet, à
reproduire pour toute nouvelle feature ; le code réel se trouve dans
`lib/features/wallet/`, et `features/theme/` reste la référence minimale.

### Étape 1 — Domain : entité + contrat

```dart
// lib/features/wallet/domain/entities/wallet_balance.dart
class WalletBalance {
  const WalletBalance({required this.amount, required this.currency});
  final int amount;
  final String currency;
}
```

```dart
// lib/features/wallet/domain/repositories/wallet_repository.dart
import 'package:dartz/dartz.dart';
import '../../../../core/error/failures.dart';
import '../entities/wallet_balance.dart';

abstract class WalletRepository {
  Future<Either<Failure, WalletBalance>> getBalance();
}
```

### Étape 2 — Domain : le use case

```dart
// lib/features/wallet/domain/usecases/get_wallet_balance_usecase.dart
import 'package:dartz/dartz.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/usecases/usecase.dart';
import '../entities/wallet_balance.dart';
import '../repositories/wallet_repository.dart';

class GetWalletBalanceUseCase implements UseCase<WalletBalance, NoParams> {
  GetWalletBalanceUseCase(this._repository);
  final WalletRepository _repository;

  @override
  Future<Either<Failure, WalletBalance>> call(NoParams params) {
    return _repository.getBalance();
  }
}
```

### Étape 3 — Data : le modèle (freezed), le data source, le repository

```dart
// lib/features/wallet/data/models/wallet_balance_model.dart
// (suit le pattern de core/network/models/user_model.dart — voir section 11)
```

```dart
// lib/features/wallet/data/datasources/wallet_remote_data_source.dart
import 'package:dio/dio.dart';
import '../../../../core/constants/api_constants.dart';
import '../../../../core/error/exceptions.dart';
import '../models/wallet_balance_model.dart';

abstract class WalletRemoteDataSource {
  Future<WalletBalanceModel> getBalance();
}

class WalletRemoteDataSourceImpl implements WalletRemoteDataSource {
  WalletRemoteDataSourceImpl(this._dio);
  final Dio _dio;

  @override
  Future<WalletBalanceModel> getBalance() async {
    try {
      final response = await _dio.get(ApiConstants.walletBalance);
      return WalletBalanceModel.fromJson(response.data);
    } on DioException catch (e) {
      throw ServerException(e.message ?? 'Erreur serveur');
    }
  }
}
```

```dart
// lib/features/wallet/data/repositories/wallet_repository_impl.dart
import 'package:dartz/dartz.dart';
import 'package:flutter_networking/flutter_networking.dart';
import '../../../../core/error/exceptions.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/wallet_balance.dart';
import '../../domain/repositories/wallet_repository.dart';
import '../datasources/wallet_remote_data_source.dart';

class WalletRepositoryImpl implements WalletRepository {
  WalletRepositoryImpl(this._remoteDataSource, this._networkInfo);
  final WalletRemoteDataSource _remoteDataSource;
  final NetworkInfo _networkInfo;

  @override
  Future<Either<Failure, WalletBalance>> getBalance() async {
    if (!await _networkInfo.isConnected) {
      return const Left(NetworkFailure());
    }
    try {
      final model = await _remoteDataSource.getBalance();
      return Right(model); // WalletBalanceModel extends WalletBalance
    } on ServerException catch (e) {
      return Left(ServerFailure(e.message));
    }
  }
}
```

### Étape 4 — Presentation : le Bloc (voir section 10)

### Étape 5 — Enregistrer dans get_it

Dans `injector.dart`, ajoute une méthode `_registerWalletFeature()` (même
modèle que `_registerThemeFeature()`) et appelle-la dans `setupInjector()`.

### Étape 6 — Brancher l'écran

Déclare la route (`walletPath`) dans `app_router.dart`, puis monte le
`BlocProvider` créé via `getIt<WalletBloc>()` autour du vrai écran
`WalletPage` — c'est le câblage utilisé par l'onglet Wallet aujourd'hui.

---

## 10. Créer un Bloc — tutoriel pas à pas

Un Bloc, c'est toujours 3 fichiers : `xxx_event.dart`, `xxx_state.dart`,
`xxx_bloc.dart` (liés par `part of`). Différence pour une feature
"sérieuse" comme Wallet par rapport à `ThemeBloc` : le Bloc appelle un
use case (pas directement le repository) :

```dart
// wallet_event.dart
part of 'wallet_bloc.dart';

sealed class WalletEvent extends Equatable {
  const WalletEvent();
  @override
  List<Object?> get props => [];
}

class WalletBalanceRequested extends WalletEvent {
  const WalletBalanceRequested();
}
```

```dart
// wallet_state.dart
part of 'wallet_bloc.dart';

enum WalletStatus { initial, loading, success, failure }

class WalletState extends Equatable {
  const WalletState({
    this.status = WalletStatus.initial,
    this.balance,
    this.errorMessage,
  });

  final WalletStatus status;
  final WalletBalance? balance;
  final String? errorMessage;

  WalletState copyWith({
    WalletStatus? status,
    WalletBalance? balance,
    String? errorMessage,
  }) {
    return WalletState(
      status: status ?? this.status,
      balance: balance ?? this.balance,
      errorMessage: errorMessage,
    );
  }

  @override
  List<Object?> get props => [status, balance, errorMessage];
}
```

```dart
// wallet_bloc.dart
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/usecases/usecase.dart';
import '../../domain/entities/wallet_balance.dart';
import '../../domain/usecases/get_wallet_balance_usecase.dart';

part 'wallet_event.dart';
part 'wallet_state.dart';

class WalletBloc extends Bloc<WalletEvent, WalletState> {
  WalletBloc(this._getBalanceUseCase) : super(const WalletState()) {
    on<WalletBalanceRequested>(_onBalanceRequested);
  }

  final GetWalletBalanceUseCase _getBalanceUseCase;

  Future<void> _onBalanceRequested(
    WalletBalanceRequested event,
    Emitter<WalletState> emit,
  ) async {
    emit(state.copyWith(status: WalletStatus.loading));
    final result = await _getBalanceUseCase(const NoParams());
    result.fold(
      (failure) => emit(state.copyWith(
        status: WalletStatus.failure,
        errorMessage: failure.message,
      )),
      (balance) => emit(state.copyWith(
        status: WalletStatus.success,
        balance: balance,
      )),
    );
  }
}
```

`result.fold(...)` vient de `dartz` : le premier callback gère l'échec
(`Failure`), le second le succès. C'est la façon idiomatique de consommer
un `Either<Failure, T>`.

### Utiliser le Bloc dans l'écran

```dart
BlocConsumer<WalletBloc, WalletState>(
  listener: (context, state) {
    if (state.status == WalletStatus.failure) {
      KoraDialogs.error(context, title: 'Erreur', description: state.errorMessage);
    }
  },
  builder: (context, state) {
    if (state.status == WalletStatus.loading) {
      return const Center(child: CircularProgressIndicator());
    }
    return Text('Solde : ${state.balance?.amount ?? 0} ${state.balance?.currency ?? ''}');
  },
)
```

---

## 11. Génération de code (freezed / json_serializable)

`core/network/models/user_model.dart` est un exemple à suivre pour
chaque nouveau modèle API :

```dart
@freezed
class UserModel with _$UserModel {
  const factory UserModel({
    required String id,
    required String fullName,
    required String email,
  }) = _UserModel;

  factory UserModel.fromJson(Map<String, dynamic> json) => _$UserModelFromJson(json);
}
```

Après avoir écrit (ou modifié) un fichier comme celui-ci, lance :

```bash
dart run build_runner build --delete-conflicting-outputs
```

Ça génère automatiquement `user_model.freezed.dart` (copyWith, ==,
hashCode, toString) et `user_model.g.dart` (fromJson/toJson). Ces
fichiers générés ne sont pas fournis dans le zip (ils sont volumineux et
se régénèrent en une commande) — c'est normal que `flutter analyze`
signale une erreur sur `user_model.dart` tant que tu n'as pas lancé
cette commande une première fois.

Pendant le développement, `dart run build_runner watch
--delete-conflicting-outputs` régénère automatiquement le code à chaque
sauvegarde.

---

## 12. Erreurs fréquentes à éviter

- Couleur/URL en dur → utiliser `AppColors.xxx` / `ApiConstants.xxx`
- Bloc qui appelle directement Dio/SharedPreferences → toujours passer
  par repository → use case → Bloc
- Attraper `DioException` dans le Bloc → le data source la transforme en
  `Exception` (couche data), le repository la transforme en `Failure`
  (couche domain), le Bloc ne voit qu'un `Either<Failure, T>`
- `showProDialog` appelé directement dans un écran → passer par
  `KoraDialogs`
- Naviguer avec `Navigator.push` dans une app go_router → utiliser
  `context.go('/route')` ou `context.push('/route')` pour empiler un
  écran au-dessus de la bottom nav
- Oublier `dart run build_runner build` après avoir ajouté/modifié un
  modèle `@freezed` → erreur de compilation sur ce fichier précis
- Oublier d'enregistrer une nouvelle classe dans `get_it` → erreur
  "Object/factory not registered" au runtime

---

## 13. Ce qui n'est PAS encore fait

- L'onglet Colis : l'écran existe (`ComingSoonPage` ouvert par la branche
  `/colis` de `app_router.dart`), mais la feature n'a ni domaine, ni
  source de données. Elle arrivera avec la phase Colis (voir
  `../../README.md`).
- KORA Ride et KORA Driver : hors périmètre tant que ces phases ne sont
  pas ouvertes.

Le reste du parcours (Bus, paiement, billets, Wallet, compte, support,
notifications) est branché sur l'API ; toute nouvelle feature suit les
principes détaillés dans ce guide.
