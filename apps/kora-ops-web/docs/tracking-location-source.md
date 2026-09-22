# Source de localisation — P501 et téléphone Android (MEZ-100)

Vérification et documentation demandées par le ticket MEZ-100 avant l'implémentation du
socle serveur de suivi de flotte. Concerne le **client** (app convoyeur, hors périmètre
backend de ce ticket) ; ce document fixe le contrat que le serveur attend, quel que soit
le matériel.

## Un seul contrat, deux matériels

`terminals_p501` porte déjà les deux matériels sous le même modèle (`TerminalType::P501`
et `TerminalType::Phone` — voir le ticket *"Supporter le scan par caméra téléphone en
alternative au P501"*). Le suivi de flotte hérite de ce principe directeur (voir
`ways-of-working.md`) : **aucun code serveur spécifique à un matériel**. L'endpoint
`POST /api/v1/ops/tracking/positions/batch` reçoit la même forme de payload quelle que
soit la source physique — c'est au client de la construire correctement.

| | P501 (boîtier dédié) | Téléphone Android (mode caméra) |
|---|---|---|
| Source GPS | Puce GNSS dédiée du boîtier | `FusedLocationProviderClient` (Google Play Services) — combine GPS, réseau mobile et Wi-Fi |
| `source` attendu | `gps` en usage normal | `gps` la plupart du temps, `fused`/`network` en zone urbaine dense ou tunnel — l'OS peut dégrader la source sans que l'app le sache toujours |
| Précision typique | 3–8 m en extérieur dégagé | 5–20 m selon le mode de source retenu par l'OS |
| État désactivé | Improbable en usage normal (boîtier dédié à cet usage) ; si le GPS matériel tombe, le firmware doit le signaler | Fréquent : l'utilisateur (ou une politique MDM) peut désactiver la localisation au niveau système |
| Perte de signal | Tunnel, zone forestière dense, boîtier métallique du bus | Mêmes causes, plus la mise en veille agressive de certains constructeurs Android qui suspend l'app en arrière-plan |

## Ce que le serveur exige du client

1. **Position normale (`status: "fix"`)** — coordonnées + `accuracy_meters` obligatoires.
   Le serveur ne rejette jamais une position pour cause de faible précision : il la
   conserve et la marque `degraded` au-delà de `tracking.degraded_accuracy_meters`
   (75 m par défaut) plutôt que de la jeter — une position dégradée reste utile pour la
   carte, tant qu'elle est présentée comme telle (voir `TrackingLiveState`).
2. **GPS désactivé ou signal perdu (`status: "gps_unavailable"`)** — le client envoie un
   *heartbeat* explicite, sans coordonnées, dès qu'il détecte l'état via
   `LocationManager.isProviderEnabled()` (Android) ou l'équivalent firmware P501. Ce
   heartbeat :
   - confirme que le terminal est toujours en ligne (évite un faux `terminal_offline`) ;
   - fait passer l'état affiché sur OPS Web à `gps_unavailable` sans effacer la dernière
     position réelle connue (le point sur la carte ne disparaît pas, il se grise).
3. **Séquence (`sequence`)** — entier strictement croissant par session côté client
   (compteur local, pas un timestamp). C'est ce qui permet au serveur de ne jamais
   laisser une position en retard (réseau) faire régresser l'état affiché, même si les
   lots arrivent dans le désordre.
4. **Horodatage (`recorded_at`)** — heure locale du terminal au moment de la mesure, pas
   celle de l'envoi. Le serveur rejette individuellement (pas tout le lot) un horodatage
   trop dans le futur (`tracking.max_clock_skew_seconds`, dérive d'horloge) ou trop
   ancien (`tracking.max_position_age_minutes`, purge de file locale hors ligne).

## Fréquence recommandée

Un point toutes les 10 à 15 secondes en circulation (`tracking-batch` est limité à
30 requêtes/minute/utilisateur — voir `AppServiceProvider`), envoyé par petits lots pour
absorber les micro-coupures réseau sans perdre de points intermédiaires. Le client peut
accumuler hors ligne et renvoyer un lot plus large au retour du réseau : l'ingestion est
idempotente et tolère les lots partiels (voir `IngestTrackingPositionsBatchAction`).
