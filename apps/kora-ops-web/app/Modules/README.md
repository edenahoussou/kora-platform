# Laravel Modules

Chaque module suit le meme vocabulaire DDD pragmatique :

- `Http`: controleurs API ou Inertia tres courts.
- `Requests`: validations HTTP.
- `Actions`: operations metier atomiques et testables.
- `Services`: orchestration applicative d'un cas d'usage.
- `Models`: modeles Eloquent propres au module.
- `Policies`: autorisations serveur.
- `Events`: evenements metier internes.
- `Jobs`: traitements asynchrones.
- `Data`: DTO, enums et value objects simples.

Modules MVP Bus :

- `IdentityAccess`
- `BusNetwork`
- `Departures`
- `SeatInventory`
- `DynamicPricing`
- `Booking`
- `Payments`
- `Ticketing`
- `Fiscalization`
- `Boarding`
- `Cash`
- `FleetLite`
- `Notifications`
- `Audit`
- `Sync`

