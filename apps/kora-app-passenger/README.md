# KORA App Passager Bus

Application mobile Flutter du parcours passager Bus de KORA EXPRESS
(Clean Architecture + Bloc). Emplacement du périmètre MVP Bus de KORA
App : à terme, KORA App pourra porter Bus, Ride, Colis et Wallet, mais
dans le MVP ce projet reste limité au parcours passager Bus (voir
[AGENTS.md](AGENTS.md)).

Pour un guide détaillé (tutoriels feature/Bloc, design system, mode
clair/sombre, génération de code...), voir [GUIDE.md](GUIDE.md).

## Prérequis

- Flutter 3.x / Dart 3.x (`sdk: ^3.12.2`, voir [pubspec.yaml](pubspec.yaml))
- Les packages partagés du monorepo doivent être présents à côté de cette
  app : `../../packages/flutter-design-system` et
  `../../packages/flutter-networking`

## Lancer le projet

```bash
# 1. Installer les dépendances
flutter pub get

# 2. Générer le code (freezed / json_serializable), requis pour que
#    core/network/models/user_model.dart compile
dart run build_runner build --delete-conflicting-outputs

# 3. Lancer l'application (environnement dev par défaut)
flutter run -t lib/main_dev.dart

# Autres environnements
flutter run -t lib/main_staging.dart
flutter run -t lib/main_prod.dart
```

Autres commandes utiles :

```bash
flutter analyze   # analyse statique (lint strict, voir analysis_options.yaml)
flutter test      # tests unitaires / widgets
```

## Structure de dossiers

```
lib/
├── main.dart                       # Point d'entrée par défaut (flutter run sans -t)
├── main_dev.dart                   # Point d'entrée environnement dev
├── main_staging.dart               # Point d'entrée environnement staging
├── main_prod.dart                  # Point d'entrée environnement prod
├── bootstrap.dart                  # Init commune (AppEnv + get_it) avant runApp, partagée par les 3 main_*.dart
├── app.dart                        # Widget racine : BlocProvider + MaterialApp.router
│
├── core/                           # Tout ce qui est PARTAGÉ entre les features
│   ├── constants/
│   │   ├── api_constants.dart      # Timeouts, chemins d'endpoints (pas d'URL ici, voir env/)
│   │   ├── app_constants.dart      # Constantes générales (pagination, cache...)
│   │   └── storage_keys.dart       # Clés SharedPreferences / FlutterSecureStorage
│   ├── di/
│   │   └── injector.dart           # Configuration get_it (service locator)
│   ├── env/
│   │   └── app_env.dart            # AppEnv : config par environnement (dev/staging/prod)
│   ├── error/
│   │   ├── failures.dart           # Failure (domain) — ServerFailure, NetworkFailure...
│   │   └── exceptions.dart         # Exception (data) — ServerException...
│   ├── network/
│   │   └── models/
│   │       └── user_model.dart     # Exemple de modèle freezed + json_serializable
│   ├── router/
│   │   ├── app_router.dart         # Config go_router (StatefulShellRoute, bottom nav, auth guard)
│   │   └── auth_guard.dart         # AuthGuard : vérifie la présence d'un token pour le redirect
│   └── usecases/
│       └── usecase.dart            # Classe de base UseCase<Type, Params>
│
└── features/                       # Un dossier par feature, en couches data/domain/presentation
    ├── theme/                      # Feature complète (référence à suivre pour toute nouvelle feature)
    │   ├── data/{datasources,repositories}/
    │   ├── domain/{entities,repositories}/
    │   └── presentation/{bloc,widgets}/
    ├── auth/presentation/pages/    # LoginPage : placeholder, destination du guard tant que la feature n'est pas écrite
    ├── home/presentation/pages/    # Onglet Accueil (compteur Flutter par défaut, volontairement — cf. home_page.dart)
    ├── voyages/presentation/pages/ # Placeholder "texte centré"
    ├── colis/presentation/pages/   # Placeholder "texte centré"
    ├── wallet/presentation/pages/  # Placeholder "texte centré"
    └── profil/presentation/pages/  # Placeholder "texte centré"

assets/
└── images/kora_logo.png

test/
└── widget_test.dart                # Test de fumée : l'app démarre, la bottom nav et Accueil s'affichent
```

Ce que **ce dossier ne contient pas**, car partagé avec les autres apps
Flutter du monorepo (`../../packages/`) :

- **Design system** (couleurs, typographie, composants `Kora*`, icônes
  SVG, dialogs) → `packages/flutter-design-system`, importé via
  `package:flutter_design_system`
- **Client réseau** (`DioClient`, `NetworkInfo`) → `packages/flutter-networking`,
  importé via `package:flutter_networking`

Voir [GUIDE.md §3](GUIDE.md#3-comprendre-larborescence) pour le détail
commenté de chaque fichier, et §5/§7/§9/§10 pour des tutoriels pas à pas
(design system, navigation, ajout d'une feature, ajout d'un Bloc).
