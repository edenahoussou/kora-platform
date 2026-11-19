# Repository Guidelines

## Project Structure & Module Organization

This app carries KORA OPS Mobile/P501 for Bus drivers, convoyeurs, agencies, and field operations. It is not KORA Driver for Ride. Scope covers manifests, QR/short-code validation, offline boarding, printing, early drop-off, border manifests, incidents, synchronization, and operational status updates.

Code is feature-first under `lib/features/<feature>` (`manifeste`, `boarding`, `descente`, `border_manifest`, `printing`, `incidents`, `notifications`, `profil`, `app_update`, ...). App-level configuration, router, and theme live in `lib/app/`; SQLite storage, network, errors, and shared widgets live in `lib/core/`. Shared UI stays in `packages/flutter-design-system`.

## Development Commands

Run commands from `apps/kora-ops-mobile`.

```bash
flutter pub get
dart run build_runner build --delete-conflicting-outputs   # freezed / json_serializable
flutter analyze
flutter test
flutter run --dart-define=ENV=dev                          # dev / staging / prod
```

## Coding Style & Naming Conventions

Use `flutter_bloc` for state and keep domain logic independent from Android hardware. Isolate P501 scanner and printer integrations behind adapters so business flows remain testable, and keep offline persistence behind repositories backed by `lib/core/database`. Preserve KORA BUS vocabulary from `../../CONTEXT.md`, and never hardcode operational values that belong to OPS settings.

## Testing Guidelines

Prioritize tests around offline scan decisions, idempotent sync, manifest updates, segment release after early drop-off, and print payload generation. Use integration tests for device adapters when hardware or SDK simulators are available. Run `flutter analyze` and `flutter test` before opening a pull request.

## Configuration & Security

Environment selection uses `--dart-define=ENV=...` and lives in `lib/app/config/app_config.dart`; never hardcode an API base URL elsewhere. The offline ticket-verification public key is passed with `--dart-define=TICKET_SIGNING_PUBLIC_KEY=...` (exported from the backend with `php artisan tickets:public-key`) and is safe to distribute. Do not commit device credentials, Firebase secrets, API tokens, or local cache dumps. Treat offline boarding data as sensitive operational data.
