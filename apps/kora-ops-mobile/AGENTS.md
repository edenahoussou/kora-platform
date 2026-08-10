# Repository Guidelines

## Project Structure & Module Organization

This app will host KORA OPS Mobile/P501 for Bus drivers, convoyeurs, agencies, and field operations. It is not KORA Driver for Ride. MVP scope covers manifests, QR/short-code validation, offline boarding, printing, early drop-off, synchronization, and operational status updates.

## Development Commands

Flutter has not been generated here yet. Once initialized, keep standard commands documented in this file, for example:

```bash
flutter pub get
flutter analyze
flutter test
flutter run
```

## Coding Style & Naming Conventions

Use feature-first folders such as `lib/features/boarding`, `lib/features/manifest`, `lib/features/printing`, and `lib/features/sync`. Isolate P501 scanner and printer integrations behind adapters so business flows remain testable. Preserve KORA BUS vocabulary from `../../CONTEXT.md`.

## Testing Guidelines

Prioritize tests around offline scan decisions, idempotent sync, manifest updates, segment release after early drop-off, and print payload generation. Use integration tests for device adapters when hardware or SDK simulators are available.

## Configuration & Security

Do not commit device credentials, Firebase secrets, API tokens, or local cache dumps. Treat offline boarding data as sensitive operational data.
