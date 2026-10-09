# ADR 0012 — Monitoring et observabilité KORA OPS

## Statut

Accepté — octobre 2026

## Contexte

KORA OPS est un monolithe dockerisé déployé sur un VPS unique (php-fpm, worker de queue, scheduler sous Supervisor, PostgreSQL, Redis, Nginx). Le debug y est pénible : les erreurs sont éclatées entre `docker logs`, les fichiers `storage/logs` et les logs Supervisor, et rien n'indique d'un coup d'œil si l'application et ses dépendances sont saines. L'équipe veut un écran de monitoring interne dont l'interface reste entièrement sous contrôle KORA, sans réimplémenter la collecte, le parsing et le filtrage des données. Les tickets MEZ-130 à MEZ-135 découlent de cette décision.

## Décisions

- **Sentry SaaS pour le suivi des erreurs** (MEZ-130). Le SDK Laravel remonte les exceptions backend et le SDK React les erreurs frontend/Inertia, tagués par `APP_ENV`, avec `send_default_pii=false` et des source maps uploadées en CI. GlitchTip self-hosted a été écarté : il ajouterait plusieurs containers à maintenir sur le VPS unique, et l'outil censé signaler une panne du VPS ne doit pas tourner sur ce même VPS. Le volume MVP tient dans le free tier.
- **`spatie/laravel-health` en couche de checks headless.** Les checks (base de données, cache, Redis, disque, mode debug) sont enregistrés dans `AppServiceProvider`, exécutés à la demande par `MonitoringController` et planifiés via `health:check` toutes les minutes pour l'historique et les notifications futures. L'interface packagée n'est pas utilisée : les résultats sont sérialisés en props Inertia explicites pour la page custom.
- **`opcodesio/log-viewer` en couche de logs headless.** Le package gère la découverte des fichiers, le parsing (Laravel, Supervisor, Nginx, PHP-FPM, PostgreSQL, Redis, Horizon), la recherche plein texte, les filtres par niveau et la pagination via son API `/log-viewer/api/*`. La vue est un composant React/Inertia KORA (`LogBrowser`) qui consomme cette API ; aucune logique de parsing n'est réécrite. `LogViewer::auth` gate l'API et l'UI packagée derrière `monitoring.view` ; l'UI packagée reste accessible comme outil de secours, jamais liée dans la navigation.
- **Laravel Horizon pour les queues** (MEZ-133). Le dashboard officiel `/horizon` sera gaté par la même permission et remplacera `queue:work` dans supervisord, ajoutant métriques et gestion des failed jobs sans infrastructure supplémentaire. La page monitoring affichera un résumé et un lien profond.
- **Monitoring externe de disponibilité** (MEZ-132). Un pinger gratuit (UptimeRobot ou Better Stack) interroge l'endpoint public existant `GET /api/v1/health` (staging et production) toutes les 5 minutes et alerte par mail en cas de `503` ou d'indisponibilité. C'est le seul composant capable de détecter une panne complète du VPS, de Nginx ou du container — l'écran interne ne peut pas se surveiller lui-même. L'endpoint public reste inchangé.
- **Permission dédiée `monitoring.view`.** L'écran, l'API log-viewer et Horizon partagent une seule permission, créée par le seeder et attribuée initialement au seul rôle `system_admin`. Elle isolera un futur rôle « support ops » des permissions settings.
- **Alerting mail uniquement dans un premier temps** (MEZ-135). Les notifications d'échec de check de `laravel-health` seront activées avec un canal mail throttle (1 h) vers les destinataires ops. Slack et WhatsApp sont différés ; le canal `slack` de `config/logging.php` reste disponible sans refonte.

## Sécurité et conséquences

Les logs applicatifs peuvent contenir des contextes sensibles (PII passagers, payloads métier). La permission `monitoring.view` est donc restrictive et l'accès reste limité aux comptes ops actifs ; aucun log n'est exposé publiquement. Sentry est configuré sans PII par défaut et les mots de passe/tokens sont déjà exclus des contextes par le handler Laravel.

L'écran interne dépend de l'application : si l'app ou le VPS tombe, il devient inaccessible — d'où le pinger externe comme filet de sécurité. Les checks s'exécutent à la demande à chaque affichage de la page, ce qui garantit des données fraîches au prix d'une requête légèrement plus lente ; la commande planifiée `health:check` conserve l'historique pour les tendances et notifications.

Le parsing de logs et la persistance des checks ne sont pas réécrits, ce qui supprime deux chantiers de maintenance. En contrepartie, KORA dépend des formats de sortie des deux packages ; un changement majeur d'API devra être absorbé dans `LogBrowser` et `MonitoringController`, qui sont les seams prévus à cet effet.
