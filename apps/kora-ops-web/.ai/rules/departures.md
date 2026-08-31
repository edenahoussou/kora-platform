---
paths:
  - 'app/Modules/Departures/**'
---

# Departures

## Programme occurrences are separate from sellable departures
Prepare recurring occurrences in planned_departures; create ordinary departures only through PublicationService after locking resources and validating bus, seats, fares and rotations. Preserve template versions and generated exceptions. Never revise a published departure with active reservations, payments or tickets. Canonical scheduled_at/arrival_at use UtcDateTime, including databaseValue for range predicates, to preserve UTC across SQLite and PostgreSQL session time zones.
