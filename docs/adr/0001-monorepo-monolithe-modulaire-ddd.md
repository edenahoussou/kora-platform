# Monorepo et monolithe modulaire DDD pour le MVP Bus

Le MVP KORA BUS est realise dans un monorepo avec Laravel en monolithe modulaire, des frontieres metier explicites et une approche DDD pragmatique. Ce choix respecte le CDC valide, limite la charge DevOps d'une petite equipe et garde une coherence transactionnelle forte pour les reservations, paiements, billets et operations terrain.

## Considered Options

- Microservices des le MVP.
- Repositories separes par application.
- Monolithe Laravel non module.

## Consequences

Les modules Laravel restent separes par domaine, avec controleurs minces, services applicatifs, actions testables, evenements internes, jobs et contrats API versionnes. Les microservices sont exclus du MVP.
