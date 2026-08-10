# Repository Guidelines

## Project Structure & Module Organization

This app will host the Flutter passenger Bus scope of KORA App. The MVP passenger app should remain limited to Bus search, reservation, payment handoff, ticket display, and bus tracking. Do not add Ride, Colis, or Wallet screens without an approved change request.

## Development Commands

Flutter has not been generated here yet. Once initialized, keep standard commands documented in this file, for example:

```bash
flutter pub get
flutter analyze
flutter test
flutter run
```

## Coding Style & Naming Conventions

Use feature-first folders once the app exists, for example `lib/features/booking`, `lib/features/tickets`, and `lib/features/tracking`. Keep shared UI in `packages/flutter-design-system` when it must be reused by OPS Mobile. Preserve KORA BUS vocabulary from `../../CONTEXT.md`.

## Testing Guidelines

Add unit tests for domain/state logic and widget tests for critical passenger flows. Prioritize booking, payment return states, QR ticket display, and offline-friendly ticket access.

## Configuration & Security

Do not commit API base URLs for production, Firebase secrets, payment keys, or user tokens. Use environment-specific configuration files excluded from Git when needed.
