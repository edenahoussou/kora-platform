---
paths:
  - 'app/Modules/Departures/**'
---

# Departures

## Programme occurrences are separate from sellable departures
Prepare recurring occurrences in planned_departures; create ordinary departures only through PublicationService after locking resources and validating bus, seats, fares and rotations. Preserve template versions and generated exceptions. Never revise a published departure with active reservations, payments or tickets. Canonical scheduled_at/arrival_at use UtcDateTime, including databaseValue for range predicates, to preserve UTC across SQLite and PostgreSQL session time zones.

## Agency is a commercial entity, not an operational resource
Buses, departures, and planned departures do NOT have an agency_id. The agency entity exists for commercial and financial flows only: users (guichetiers, supervisors), cash sessions, agency payment accounts, and P501 terminals. Never re-introduce agency_id on buses, departures, or planned_departures — it is an artificial dependency that conflates the stop (where the bus halts) with the agency (where money changes hands).
