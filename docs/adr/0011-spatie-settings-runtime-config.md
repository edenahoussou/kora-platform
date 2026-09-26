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
- L'administration expose un hub et une page Inertia par groupe : `/administration/settings`, `/administration/settings/general`, `/administration/settings/operations` et `/administration/settings/integrations`. La lecture et l'édition sont séparées par les permissions `settings.{group}.view` et `settings.{group}.manage` ; un utilisateur seulement lecteur reçoit des contrôles désactivés et ne reçoit jamais de secret.
- Les mutations sont explicites, groupe par groupe, via les DTO existants et des `FormRequest` dédiées. Les changements qui affectent les ventes, les départs ou les sessions OPS portent une confirmation serveur ; aucune écriture directe dans Spatie n'est permise depuis l'interface.
- Les secrets restent masqués dans les props Inertia : une valeur vide conserve le secret, un remplacement demande une nouvelle valeur et une suppression passe par une action explicite et auditée. Les probes e-MECeF, FedaPay et MTN MoMo utilisent uniquement des appels de lecture ou d'obtention de token et ne reçoivent jamais les valeurs non sauvegardées du formulaire.
- Les résultats de probe sont normalisés, assainis et enregistrés dans l'Activitylog sous `settings.connection_tested` (acteur, fournisseur, environnement, statut, horodatage, durée et message sûr). Aucun corps HTTP, token, URL secrète ou credential ne peut être exposé par l'événement.
- Les endpoints qui reçoivent des credentials sont limités aux domaines officiels e-MECeF/Genuka ou à l'hôte WAHA approuvé par le déploiement ; les délais et reprises des probes sont plafonnés. L'attribution cartographique est persistée et partagée comme texte afin de ne pas créer de sink HTML dans les vues Leaflet.

## Sécurité et conséquences

Les secrets d'intégration sont chiffrés par le package. Une chaîne vide lors d'une mise à jour conserve la valeur existante ; une suppression doit figurer dans la liste d'effacement explicite. L'audit `settings.updated` enregistre l'acteur, le groupe et les champs changés, avec les valeurs avant/après uniquement pour les champs non sensibles et un booléen de présence pour les secrets.

Le middleware HTTP applique le nom, la locale, la locale de secours et le fuseau du groupe général à chaque requête. Les jobs et adaptateurs relisent les Settings au moment de leur exécution afin d'éviter une configuration figée dans un singleton. La table et les anciennes variables d'environnement sont conservées pendant le déploiement pour permettre un rollback du code. `map_settings` reste donc une compatibilité temporaire, jamais la source de vérité après la migration.

Cette séparation permet de livrer les écrans MEZ-110, MEZ-111 et MEZ-79 sans exposer les paramètres de plateforme. Les cartes fournisseur affichent l'état configuré, les endpoints non sensibles et une aide opérationnelle courte ; la branche n'ajoute pas de transaction de test, de runner React ou de dépendance GSAP. Les rôles métier restent à définir dans les tickets d'interface correspondants.
