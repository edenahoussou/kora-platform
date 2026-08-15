# KORA OPS Mobile (P501)

Application Flutter terrain pour chauffeurs bus, convoyeurs et agences KORA EXPRESS (manifestes, embarquement, impression, synchronisation). Ce n'est pas KORA Driver (Ride).

Documentation complète : voir **[document.md](document.md)**.

## Démarrage rapide

```bash
flutter --version   # Flutter 3.x / Dart 3.x requis
flutter pub get

# Génération de code (si des modèles Freezed/json_serializable sont ajoutés)
dart run build_runner build --delete-conflicting-outputs

flutter analyze
flutter test
flutter run --dart-define=ENV=dev
```

## Widgetbook

```bash
cd widgetbook
flutter pub get
flutter run -t lib/main.dart
```

## Environnements

```bash
flutter run --dart-define=ENV=dev
flutter run --dart-define=ENV=staging
flutter run --dart-define=ENV=prod
```
