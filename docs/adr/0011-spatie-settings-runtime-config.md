# ADR 0011 — Paramètres runtime KORA BUS avec Spatie Laravel Settings

## Statut

Accepté — 21 septembre 2026

## Contexte

Les tickets MEZ-110, MEZ-111 et MEZ-79 ont besoin d'un socle commun pour administrer les valeurs métier actuellement dispersées dans `config/` et `.env`. Une simple lecture directe de l'environnement ne permettrait ni la persistance d'une modification, ni une piste d'audit, ni une prise en compte déterministe par les workers.

## Décisions

- `spatie/laravel-settings:^3.9` fournit le repository base de données, le cache optionnel piloté par `SETTINGS_CACHE_ENABLED` et le chiffrement des propriétés sensibles. Les classes sont enregistrées explicitement dans `config/settings.php` et leurs migrations vivent dans `database/settings`.
- Trois groupes typés et indépendants sont la seule source de vérité métier après la migration initiale : `GeneralSettings` (`general`), `OperationsSettings` (`operations`) et `IntegrationsSettings` (`integrations`). Les paramètres de plateforme restent dans `.env`/la configuration de déploiement.
- La migration initialise chaque propriété à partir de la valeur actuellement résolue par `config()`/`.env` (avec `Africa/Porto-Novo` et `XOF` comme valeurs métier déterministes). Après cette capture unique, aucun fallback permanent vers `.env` n'est utilisé.
- `MapSetting` est repris dans `IntegrationsSettings.mapProvider`. La table `map_settings` reste conservée temporairement pour compatibilité et rollback, mais elle ne décide plus du fournisseur runtime. `PricingSetting` et `GroupBookingSetting` restent propriétaires de leurs modules.
- Les contrôleurs futurs passent par `SettingsCoordinator`, un seam indépendant du package. Le coordinator impose la permission de groupe, une allowlist et une validation typed, puis exécute mise à jour, verrouillage SQL du groupe, cache et audit dans une transaction. Les DTO d'update sont immuables et les snapshots ne contiennent jamais de plaintext secret.
- Les six permissions (`view`/`manage` pour chaque groupe) sont créées par migration et attribuées initialement seulement à `system_admin`. Les rôles métier seront décidés dans les tickets d'interface.

## Sécurité et conséquences

Les secrets d'intégration sont chiffrés par le package. Une chaîne vide lors d'une mise à jour conserve la valeur existante ; une suppression doit figurer dans la liste d'effacement explicite. L'audit `settings.updated` enregistre l'acteur, le groupe et les champs changés, avec les valeurs avant/après uniquement pour les champs non sensibles et un booléen de présence pour les secrets.

Le middleware HTTP applique le nom, la locale, la locale de secours et le fuseau du groupe général à chaque requête. Les jobs et adaptateurs relisent les Settings au moment de leur exécution afin d'éviter une configuration figée dans un singleton. La table et les anciennes variables d'environnement sont conservées pendant le déploiement pour permettre un rollback du code.

Cette séparation reporte les écrans, la gestion fine des rôles métier et les tests de connexion fournisseur aux tickets MEZ-110, MEZ-111 et MEZ-79 ; MEZ-109 livre uniquement le backend partagé et sa migration.
