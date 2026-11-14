# KORA App Passager Bus

Application mobile Flutter du parcours passager de KORA EXPRESS
(Clean Architecture + Bloc), dans KORA App. Le socle Bus est livré et en
exploitation : recherche et réservation, paiement Mobile Money ou Wallet,
billet, suivi du bus, assistance, groupes. L'app porte aussi le compte
voyageur et le Wallet Bus (solde cash, points, recharge, historique,
règles, bonus et parrainage). L'onglet Colis est un écran d'attente,
en attendant la phase Colis (voir [AGENTS.md](AGENTS.md)).

Documentation API mobile : [kora-express.mintlify.app](https://kora-express.mintlify.app)
(authentification, parcours passager, endpoints et exemples Dart).

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
│   ├── constants/                  # Endpoints, pagination, clés de stockage
│   ├── di/injector.dart            # Configuration get_it (service locator)
│   ├── env/app_env.dart            # AppEnv : config par environnement (dev/staging/prod)
│   ├── error/                      # Failure, Exception, mapping des erreurs API
│   ├── l10n/                       # Localisation de l'interface
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
    └── theme/                      # Mode clair / sombre

assets/
└── images/kora_logo.png

test/
├── core/                           # Tests des briques transverses
├── features/                       # Tests par feature (booking, wallet, auth...)
├── support/                        # Fakes, fixtures et helpers
└── widget_test.dart                # Test de fumée : l'app démarre et la bottom nav s'affiche
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
