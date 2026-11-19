# Repository Guidelines

## Project Structure & Module Organization

This app carries the KORA App passenger experience for KORA EXPRESS: Bus search, reservation, Mobile Money and Wallet payment, ticket display, bus tracking, assistance, group bookings, the traveller account, and the Bus Wallet (cash, points, top-up, history, rules, bonuses, referral). The Colis tab is a waiting screen (`ComingSoonPage`) and stays that way until the Colis phase: do not add Colis, Ride, or Driver features without an approved change request recorded in `README.md` and `CONTEXT.md`.

Code is feature-first under `lib/features/<feature>` with `data/`, `domain/`, and `presentation/` layers. Shared technical code lives in `lib/core/` (router, DI, environment, errors, localization, push, update gate). Do not duplicate shared UI or networking: they come from `packages/flutter-design-system` and `packages/flutter-networking`.

## API Contract

The passenger mobile API is documented on Mintlify at **[kora-express.mintlify.app](https://kora-express.mintlify.app)**. The committed OpenAPI spec lives in `docs/mintlify/api/openapi.yaml` and is regenerated from `apps/kora-ops-web` with `composer docs:generate`. Never invent an endpoint, field, or error code: read the documentation first, and update it when a contract changes.

## Development Commands

Run commands from `apps/kora-app-passenger`.

```bash
flutter pub get
dart run build_runner build --delete-conflicting-outputs   # freezed / json_serializable
flutter analyze
flutter test
flutter run -t lib/main_dev.dart                            # dev / staging / prod entrypoints
```

## Coding Style & Naming Conventions

Keep the Clean Architecture rule: a layer only knows the layer below it, and the Bloc never talks to Dio or secure storage directly. Use `flutter_bloc` for state, `get_it` for injection (register each feature in `core/di/injector.dart`), `freezed` for immutable models, and `go_router` paths declared as constants in `core/router/app_router.dart`.

Never hardcode user-facing text: add the key to `assets/languages/fr.json` and `assets/languages/en.json`, expose a getter in `lib/core/l10n/app_localization.dart`, and read it through `context.l10n`. Preserve KORA vocabulary from `../../CONTEXT.md` and reuse the design-system components rather than hand-rolled widgets.

## Testing Guidelines

Match the existing layout: `test/features/<feature>` mirrors `lib/features/<feature>`, with `bloc_test` and `mocktail` fakes. Cover booking, payment return states, wallet balances and top-up flows, QR ticket display, and offline-friendly ticket access. Run `flutter analyze` and `flutter test` before opening a pull request.

## Configuration & Security

Environment URLs live in `lib/core/env/app_env.dart`; endpoints live in `lib/core/constants/api_constants.dart`. Do not commit production base URLs, Firebase secrets, payment keys, or user tokens. Keep tokens in `flutter_secure_storage` through `StorageKeys`, never in `SharedPreferences`.
