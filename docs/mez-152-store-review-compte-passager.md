# MEZ-152 — Compte de revue store : code OTP whitelisté + compte passager de test

> Plan d'implémentation de l'issue [#265](https://github.com/edenahoussou/kora-platform/issues/265)
> (Linear [MEZ-152](https://linear.app/mezabodev/issue/MEZ-152/compte-de-revue-store-applegoogle-code-otp-whiteliste-compte-passager)),
> Sprint 6, Urgent.
>
> Périmètre : backend `apps/kora-ops-web` + configuration d'environnement (**staging et production**)
> + runbook. Aucun changement Flutter, aucune migration, aucun changement de contrat d'API.

---

## 1. Objectif

Permettre à un reviewer Apple/Google de se connecter à l'app passager malgré l'OTP :

- un **code OTP fixe** (`STORE_REVIEW_OTP_CODE`, secret d'environnement) est accepté ;
- uniquement pour une **liste fermée d'identifiants** (`STORE_REVIEW_IDENTIFIERS`) ;
- uniquement quand `STORE_REVIEW_ENABLED=true` ;
- **sans aucun envoi réel** de notification pour ces identifiants.

Deux comptes sont prévus :

| Compte | Usage | Créé par le seeder ? |
|---|---|---|
| `apple-review@koraexpress.app` | Connexion (login + PIN + OTP fixe) | Oui |
| `apple-review+signup@koraexpress.app` | Parcours d'inscription par le reviewer | **Non** (doit rester vierge) |

### Deux environnements, deux rôles

La revue se déroule sur le **build réellement soumis** aux stores, qui est un build de production
(`release-mobile.yml` construit `main_prod.dart` sur tag `v*`). La production est donc une cible à
part entière, pas seulement le staging.

| Environnement | Rôle | Configuration |
|---|---|---|
| **Staging** (`/opt/kora-staging`, `.env.staging`) | Répétition complète : gate, seeder, données démo, recette `curl`, parcours app | `STORE_REVIEW_ENABLED=true` en continu pendant la phase de préparation |
| **Production** (`/opt/kora-prod`, `.env.prod`) | Fenêtre de revue ouverte sur le build soumis : le reviewer se connecte, s'inscrit, consulte ses billets | `STORE_REVIEW_ENABLED=true` **temporairement**, uniquement pendant la revue, puis rotation + désactivation |

Conséquences :

- le compte de revue doit exister **en production** (le seeder y tourne aussi, en mode « compte
  seul ») ;
- les données démo (réservations/billets) restent **hors production**, conformément au ticket :
  en prod, le compte de revue est un vrai compte passager ;
- la fenêtre prod est bornée : ouverture avant soumission, fermeture immédiate après acceptation,
  avec un code différent par campagne.

---

## 2. État des lieux (code existant)

| Fichier | Rôle actuel | Impact |
|---|---|---|
| `config/services.php` | Blocs de configuration tiers (`genuka`, `waha`, OAuth…) | **Modifié** : ajout du bloc `store_review` |
| `app/Modules/IdentityAccess/Actions/IssueOtpChallengeAction.php` | Crée le challenge, expire les pending, envoie l'OTP en synchrone (`notifyNow`) | **Modifié** : saut de l'envoi pour les identifiants review |
| `app/Modules/IdentityAccess/Actions/VerifyOtpChallengeAction.php` | Gardes (expiration 5 min, statut pending, 5 tentatives) puis `Hash::check` | **Modifié** : repli sur le code fixe après échec, log d'audit |
| `app/Modules/IdentityAccess/Services/AuthIdentifierResolver.php` | Normalisation (email en minuscules, téléphone E.164) | **Réutilisé** (pas modifié) |
| `app/Modules/IdentityAccess/Services/AuthSessionService.php` | Login, unlock, reset PIN, inscription : déclenchent tous `IssueOtpChallengeAction` | **Non modifié** (bénéficie du gate) |
| `app/Modules/IdentityAccess/Http/Controllers/AuthOtpController.php` | `POST otp/request` (202) et `POST otp/verify` (200) | **Non modifié** |
| `app/Providers/AppServiceProvider.php` | Rate limiters `auth-otp` (3/min), `auth-login` (5/min) | **Non modifié** (throttles conservés) |
| `database/seeders/KoraDemoUserSeeder.php` | Pattern de création d'utilisateurs sans Faker (`firstOrCreate` + rôle) | **Modèle** pour le nouveau seeder |
| `tests/Feature/Ticketing/PassengerTicketIndexTest.php` | Pattern `createTicketScenario()` (réservation + passager + segment + billet) | **Modèle** pour les données démo |
| `apps/kora-app-passenger` | Consomme `otp/request` / `otp/verify` sans hypothèse sur l'origine du code | **Aucun changement** |

Point d'attention central : le challenge ne porte pas toujours l'email.

```php
$normalizedIdentifier = $user !== null
    ? (string) ($user->phone ?: Str::lower((string) $user->email))
    : $this->identifierResolver->normalize((string) $identifier);
```

Si le compte de revue possède un téléphone, `identifier` devient le téléphone et une whitelist
par email ne matcherait plus. Deux garde-fous sont donc prévus (voir D2).

---

## 3. Décisions de conception

### D1 — Whitelist CSV + compte de connexion désigné par une clé dédiée (tranché)

Deux variables, deux responsabilités distinctes :

| Variable | Répond à la question | Exemple |
|---|---|---|
| `STORE_REVIEW_IDENTIFIERS` (CSV) | **Qui** a le droit d'utiliser le code fixe | `apple-review@koraexpress.app,apple-review+signup@koraexpress.app` |
| `STORE_REVIEW_LOGIN_IDENTIFIER` | **Quel** compte le seeder crée | `apple-review@koraexpress.app` |

```php
'identifiers' => /* CSV éclaté, trim + minuscules */,
'login_identifier' => strtolower(trim((string) env('STORE_REVIEW_LOGIN_IDENTIFIER', 'apple-review@koraexpress.app'))),
```

Garde-fous : le seeder lève une `RuntimeException` si `login_identifier` n'appartient pas à
`identifiers` ; les autres identifiants de la liste sont considérés comme des comptes de test
d'inscription et ne sont **jamais** créés (sinon `register/complete` renvoie `account_exists` 409).

> Convention « premier du CSV » écartée : invisible dans le code, cassante au premier
> réordonnancement de la ligne, et l'échec se produit pendant la revue, pas au déploiement.

#### Concrètement : que fait le CSV ?

« CSV » désigne **une seule variable d'environnement contenant plusieurs valeurs séparées par des
virgules** (pas un fichier sur disque, pas un fichier `.env` au format CSV). Un `.env` ne stocke
que des chaînes scalaires : on met donc la liste dans une chaîne et on l'éclate au chargement de la
config.

```dotenv
STORE_REVIEW_IDENTIFIERS=apple-review@koraexpress.app,apple-review+signup@koraexpress.app
```

Après `config('services.store_review.identifiers')` :

```php
['apple-review@koraexpress.app', 'apple-review+signup@koraexpress.app']
```

Le `trim` + `strtolower` à la lecture absorbent les espaces après virgule et les variations de
casse (`Apple-Review+Signup@KoraExpress.app` matche). Les entrées vides (virgule finale) sont
filtrées. La comparaison finale est **exacte**, sans wildcard ni sous-chaîne : un identifiant
absent de la liste suit le parcours OTP normal, même flag activé.

Limites à connaître : whitelister un téléphone exigerait sa forme normalisée E.164 exacte
(`+2290197421860`) ; et toute modification de `.env` exige `docker compose up -d` + `config:cache`
(`env_file` est lu au démarrage du conteneur).

### D2 — Le gate regarde aussi l'email du compte
`isReviewIdentifier($challenge->identifier)` **ou** `isReviewIdentifier($challenge->user?->email)`.
Cela couvre le cas où le compte de revue aurait un téléphone renseigné (le challenge porte alors
le téléphone), sans élargir la whitelist : seuls les emails listés sont acceptés.

En complément, le seeder laisse `phone = null` sur le compte de revue pour que le challenge
porte bien l'email (traçabilité et lisibilité des logs).

### D3 — Ne pas stocker le code fixe en base
Le challenge conserve un `code_hash` aléatoire (invariant existant : aucun code en clair, et un
hash bcrypt d'un secret statique ne traîne pas en base). Le code fixe n'est comparé qu'à la
configuration, avec `hash_equals` (timing-safe).

### D4 — Pas de nouvelle route ni de paramètre caché
Aucun endpoint « bypass », aucun flag dans le payload : le comportement est invisible côté client
et désactivé par défaut. Un reviewer suit exactement le parcours d'un vrai utilisateur.

### D5 — Le code fixe n'exonère d'aucune garde
Expiration 5 min, statut `pending`, maximum 5 tentatives, throttles `auth-otp` / `auth-login` et
consommation one-shot du `verification_token` restent inchangés. Seule la comparaison du code
change.

### D6 — Données démo : hors production uniquement (règle du ticket)
Le seeder crée des réservations/billets seulement si `! app()->isProduction()`. En production, il ne
crée que le compte : le reviewer manipule un vrai compte passager, sans fausses réservations.

Conséquence assumée : l'écran « Mes voyages » est vide en prod au premier lancement (voir §8.2 et
risque associé). Ne pas déroger à cette règle sans validation explicite.

### D7 — La production est une cible de première classe
Le ticket ne cite que `.env` staging dans sa checklist, mais le paragraphe Sécurité précise que
« en prod, le compte review est un vrai compte passager » : la production est donc concernée.

- Le gate, le code et le seeder sont identiques dans les deux environnements.
- La prod reste **fermée par défaut** (`STORE_REVIEW_ENABLED=false`) et n'est ouverte que pendant
  la fenêtre de revue, avec un code propre à la campagne.
- Un code distinct par environnement : un code staging ne doit jamais fonctionner en prod.

---

## 4. Implémentation détaillée

### Étape 1 — Configuration

**Fichier : `config/services.php`** (ajout en fin de tableau, après le bloc `maps`)

```php
    /*
    |--------------------------------------------------------------------------
    | Compte de revue App Store / Play Store (MEZ-152)
    |--------------------------------------------------------------------------
    |
    | Le reviewer ne peut pas recevoir nos OTP (WhatsApp/SMS/email). Un code
    | fixe, stocké uniquement en variable d'environnement, est accepté pour
    | les seuls identifiants listés, et uniquement si `enabled` vaut true.
    | Désactivé par défaut : aucun effet hors fenêtre de revue.
    |
    */

    'store_review' => [
        'enabled' => filter_var(env('STORE_REVIEW_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'otp_code' => env('STORE_REVIEW_OTP_CODE'),
        'identifiers' => array_values(array_filter(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            explode(',', (string) env('STORE_REVIEW_IDENTIFIERS', '')),
        ))),
        'login_identifier' => strtolower(trim((string) env('STORE_REVIEW_LOGIN_IDENTIFIER', 'apple-review@koraexpress.app'))),
        'pin' => (string) env('STORE_REVIEW_PIN', '2468'),
    ],
```

Notes :

- `filter_var(..., FILTER_VALIDATE_BOOLEAN)` et non `(bool) env(...)` : `(bool) 'false' === true`.
- Les identifiants sont normalisés (trim + minuscules) à la lecture : une coquille de casse ou
  d'espace dans `.env` ne doit pas désactiver silencieusement la revue.
- `pin` doit rester 4 chiffres (`digits:4` dans `LoginRequest` / `CompleteRegistrationRequest`).

---

### Étape 2 — Service `StoreReviewGate`

**Nouveau fichier : `app/Modules/IdentityAccess/Services/StoreReviewGate.php`**

```php
<?php

namespace App\Modules\IdentityAccess\Services;

use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use Illuminate\Support\Str;

/**
 * Porte d'entrée du compte de revue store (MEZ-152).
 *
 * Whitelist fermée : seuls les identifiants configurés, et uniquement quand
 * la fonctionnalité est explicitement activée, peuvent utiliser le code fixe.
 */
class StoreReviewGate
{
    public function enabled(): bool
    {
        return (bool) config('services.store_review.enabled', false)
            && $this->otpCode() !== null
            && $this->identifiers() !== [];
    }

    public function isReviewIdentifier(?string $identifier): bool
    {
        if (! $this->enabled() || $identifier === null || trim($identifier) === '') {
            return false;
        }

        return in_array(Str::lower(trim($identifier)), $this->identifiers(), true);
    }

    /**
     * Le challenge porte le téléphone du compte quand il existe : on teste
     * donc aussi l'email du compte pour ne pas rater le compte de revue.
     */
    public function isReviewChallenge(AuthOtpChallenge $challenge): bool
    {
        return $this->isReviewIdentifier($challenge->identifier)
            || $this->isReviewIdentifier($challenge->user?->email);
    }

    public function acceptsCode(AuthOtpChallenge $challenge, string $code): bool
    {
        $expected = $this->otpCode();

        return $expected !== null
            && $this->isReviewChallenge($challenge)
            && hash_equals($expected, $code);
    }

    private function otpCode(): ?string
    {
        $code = (string) config('services.store_review.otp_code', '');

        return preg_match('/^\d{6}$/', $code) === 1 ? $code : null;
    }

    /** @return list<string> */
    private function identifiers(): array
    {
        $identifiers = config('services.store_review.identifiers', []);

        if (is_string($identifiers)) {
            $identifiers = explode(',', $identifiers);
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => Str::lower(trim((string) $value)),
            (array) $identifiers,
        ), static fn (string $value): bool => $value !== ''));
    }
}
```

Contraintes de qualité : `composer test` exécute Pint **et** PHPStan — les types de retour et
l'absence de `mixed` non maîtrisé sont à soigner d'emblée.

---

### Étape 3 — Saut de l'envoi réel

**Fichier : `app/Modules/IdentityAccess/Actions/IssueOtpChallengeAction.php`**

1. Injecter `StoreReviewGate` dans le constructeur :

```php
    public function __construct(
        private readonly AuthIdentifierResolver $identifierResolver,
        private readonly StoreReviewGate $storeReviewGate,
    ) {}
```

2. Après la création du challenge et avant la construction de la notification :

```php
        // Compte de revue store (MEZ-152) : le code fixe sera accepté à la
        // vérification. Aucun envoi réel — le reviewer ne peut pas recevoir
        // WhatsApp/SMS/email, et on ne veut pas notifier une boîte réelle.
        if ($this->storeReviewGate->isReviewChallenge($challenge)) {
            return $challenge;
        }
```

Conséquences attendues : plus de `otp_delivery_unavailable` pour ces identifiants, plus d'appel
WAHA/Resend, plus de job en file.

---

### Étape 4 — Acceptation du code fixe

**Fichier : `app/Modules/IdentityAccess/Actions/VerifyOtpChallengeAction.php`**

- Injecter `StoreReviewGate` (constructeur).
- Remplacer le bloc `Hash::check` par :

```php
        $challenge->increment('attempts');

        $usedStoreReviewCode = false;

        if (! Hash::check($code, $challenge->code_hash)) {
            if (! $this->storeReviewGate->acceptsCode($challenge, $code)) {
                throw ValidationException::withMessages(['code' => ['Le code est incorrect ou expiré.']]);
            }

            $usedStoreReviewCode = true;
        }

        if ($usedStoreReviewCode) {
            Log::info('store_review.otp_code_accepted', [
                'otp_challenge_uuid' => $challenge->uuid,
                'purpose' => $challenge->purpose->value,
                'identifier' => $challenge->identifier,
            ]);
        }
```

- Le reste (génération du `verification_token`, passage en `Verified`) ne bouge pas.
- Ne **jamais** logger le code, ni en cas de succès ni en cas d'échec.

---

### Étape 5 — Tests du gate et du parcours OTP

**Nouveau fichier : `tests/Feature/IdentityAccess/StoreReviewOtpTest.php`**

```php
beforeEach(function (): void {
    Notification::fake();

    config([
        'services.store_review.enabled' => true,
        'services.store_review.otp_code' => '654321',
        'services.store_review.identifiers' => [
            'apple-review@koraexpress.app',
            'apple-review+signup@koraexpress.app',
        ],
        'services.store_review.login_identifier' => 'apple-review@koraexpress.app',
        'services.store_review.pin' => '2468',
    ]);
});
```

Cas à couvrir :

1. **Login nouvel appareil** : `POST auth/login` (PIN 2468, `device_fingerprint` inconnu) → 202
   `requires_otp=true` → `POST auth/otp/verify` avec `654321` → 200 + `verification_token` →
   re-`POST auth/login` avec `otp_challenge_uuid` + `verification_token` → 200 session.
2. **Inscription** : `POST auth/otp/request` (`apple-review+signup@…`, `registration`) → 202 →
   verify `654321` → `POST auth/register/complete` (PIN, device) → utilisateur créé, rôle
   `passenger`, `email_verified_at` non nul.
3. **Aucun envoi réel** : sur les cas 1 et 2, `Notification::assertCount(0)` (couvre aussi les
   notifications *on-demand* utilisées pour l'inscription, contrairement à `assertNothingSent`)
   complété par `Notification::assertNothingSent()` pour l'intention.
4. **Refus hors whitelist** : `other@example.com` + `654321` → 422, challenge toujours `pending`.
5. **Refus si `enabled=false`** : mêmes identifiants, flag à `false` → 422 ; et
   `Notification::assertSentOnDemand(OtpCodeNotification::class)` (ou `assertCount(1)`) pour
   prouver que l'envoi reprend.
6. **Code mal formé en config** (`'abc'`, `'12345'`, `''`) → gate inactif, code refusé.
7. **Gardes conservées** : challenge expiré + code fixe → 422 ; 5 échecs puis code fixe → 422.
8. **Compte avec téléphone** : utilisateur de revue avec `phone` renseigné → le challenge porte le
   téléphone, le code fixe est quand même accepté et aucune notification n'est envoyée (D2).
9. **Purposes couverts** : dataset `login_new_device`, `pin_reset`, `account_unlock` → code fixe
   accepté (l'inscription a son test dédié).
10. **Audit** : `Log::spy()` (ou `Log::shouldReceive('info')->once()`) sur le message
    `store_review.otp_code_accepted`.

Les helpers `pendingOtpChallenge()` et `pinUser()` sont aujourd'hui des fonctions globales définies dans
`tests/Feature/IdentityAccess/AuthApiTest.php` (donc réutilisables). Recommandation : les déplacer
dans `tests/Pest.php` pour ne pas faire dépendre un nouveau fichier d'un autre fichier de test.

---

### Étape 6 — Seeder du compte de revue

**Nouveau fichier : `database/seeders/StoreReviewAccountSeeder.php`**

Structure :

```php
class StoreReviewAccountSeeder extends Seeder
{
    public function run(): void
    {
        $user = $this->seedAccount();

        if (app()->isProduction()) {
            $this->command?->info('Store review : compte seul (données démo désactivées en production).');

            return;
        }

        $this->seedDemoTrips($user);
    }
}
```

**6.1 — Compte (idempotent)**

- Lire `login_identifier`, `identifiers`, `pin` depuis `services.store_review`.
- Échouer **bruyamment** (`RuntimeException`) si :
  - `identifiers` vide,
  - `login_identifier` absent de `identifiers`,
  - `pin` ne matche pas `/^\d{4}$/`.
- `User::query()->firstOrCreate(['email' => $email], [...])` avec les valeurs par défaut du
  pattern `KoraDemoUserSeeder` (`uuid`, `country_code`, `status`, `remember_token`, …) puis
  `forceFill()` pour les champs à (ré)appliquer à chaque exécution :

```php
        $user->forceFill([
            'name' => 'App Store Review',
            'phone' => null,                 // le challenge doit porter l'email
            'pin_hash' => Hash::make($pin),  // ré-exécuter le seeder = réinitialiser le PIN
            'status' => UserStatus::Active,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if ($user->roles()->doesntExist()) {
            $user->assignRole(SystemRole::Passenger->value);
        }
```

- **Ne jamais** créer les autres identifiants (dont `+signup`), sinon l'inscription renverra
  `account_exists` (409).

**6.2 — Données démo (hors production)**

- Choisir un départ existant exploitable :

```php
        $departure = Departure::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->with(['line.stops', 'bus.seats'])
            ->orderBy('scheduled_at')
            ->first();
```

  Si aucun départ (ou aucun siège libre) → `$this->command?->warn(...)` et sortie propre : le
  seeder ne doit pas casser un environnement sans planning.

- Créer **1 à 2** réservations payées, avec une clé d'idempotence déterministe. `reservations.reference`
  est déjà `unique` : l'utiliser plutôt qu'un UUID v5 (pas de dépendance supplémentaire) :

```php
        $reference = 'REV-'.strtoupper(substr(hash('sha256', $email.'#'.$index), 0, 12));

        $reservation = Reservation::query()->firstOrCreate(
            ['reference' => $reference],
            [
                'uuid' => (string) Str::uuid(),
                'created_by_user_id' => $user->id,
                'status' => 'ticketed',
                'ticket_issuance_status' => 'issued',
                'ticket_issued_at' => now(),
                'total_amount' => $amount,
                'currency' => 'XOF',
                'channel' => ReservationChannel::Online->value,
                'total_snapshot' => ['passenger_allocations' => [$amount]],
            ],
        );

        if (! $reservation->wasRecentlyCreated) {
            continue; // idempotence : ne pas recréer paiement / segment / billet
        }
```

- Puis, pour chaque réservation créée :
  - `Payment` confirmé (`provider` `cash`, `idempotency_key` déterministe, `confirmed_at` now) ;
  - `ReservationPassenger` (`first_name`/`last_name` lisibles, `phone` = téléphone du compte ou
    `null`, `passenger_index` 0, `status` actif) ;
  - `ReservationSegment` sur le départ retenu, avec `origin_stop` = premier arrêt de la ligne,
    `destination_stop` = dernier, `stop_order` correspondants, siège libre (exclure les
    `seat_id` déjà pris sur ce départ via `ReservationSegment`) ;
  - `Ticket` (`short_code` unique, `status` `issued`, `issued_at` now) puis QR signé :
    `app(BuildTicketQrPayloadAction::class)->execute($reservation, $ticket)` — indispensable pour
    que le QR s'affiche/vérifie correctement dans l'app et sur le P501. En cas d'absence de clé
    de signature (env non configuré), dégrader en `Str::uuid()` avec un warning.

Points de vigilance :

- titres des sièges : prendre un siège du bus du départ, `sellable = true`, non utilisé sur ce
  départ ;
- ne pas réutiliser `createTicketScenario()` (helper de test privé) : dupliquer la mécanique dans
  le seeder, comme le font `KoraOpsDeparturesDemoSeeder` / `KoraManifestDemoSeeder` ;
- ne pas ajouter le seeder à `DatabaseSeeder` (il ne doit pas tourner sur un `db:seed` générique).

---

### Étape 7 — Tests du seeder

**Nouveau fichier : `tests/Feature/IdentityAccess/StoreReviewAccountSeederTest.php`**

- `seed(KoraBusRolePermissionSeeder::class)` en `beforeEach`, config `store_review` de test.
- Cas :
  1. crée le compte de connexion, rôle `passenger`, `Hash::check('2468')` vrai,
     `email_verified_at` non nul, `phone` nul ;
  2. **ne crée pas** `apple-review+signup@…` (assertion `exists` = false) ;
  3. idempotent : deux exécutions → un seul utilisateur, un seul jeu de réservations/billets ;
  4. données démo : avec un départ fixture (helper `lineWithStops()` + bus/sièges/départ),
     1–2 réservations `ticketed` + paiements confirmés + billets `issued` rattachés au compte ;
  5. `app()->detectEnvironment(fn () => 'production')` → aucune réservation créée, compte seul ;
  6. config incomplète (`identifiers` vide, PIN non numérique) → `RuntimeException`.

---

### Étape 8 — Environnement, runbook, documentation

**8.1 — `apps/kora-ops-web/.env.example`** (bloc commenté, valeurs vides)

```dotenv
# Compte de revue App Store / Play Store (MEZ-152) — désactivé hors fenêtre de revue
STORE_REVIEW_ENABLED=false
# Code à 6 chiffres communiqué au reviewer (secret, jamais commité)
STORE_REVIEW_OTP_CODE=
# CSV des identifiants autorisés
STORE_REVIEW_IDENTIFIERS=
# Compte pré-créé pour la connexion (doit figurer dans STORE_REVIEW_IDENTIFIERS)
STORE_REVIEW_LOGIN_IDENTIFIER=apple-review@koraexpress.app
# PIN 4 chiffres du compte de revue
STORE_REVIEW_PIN=2468
```

**8.2 — Staging : `.env.staging` (VPS `/opt/kora-staging`, non commité)**

```dotenv
STORE_REVIEW_ENABLED=true
STORE_REVIEW_OTP_CODE=<6 chiffres de la campagne staging, différent de la prod>
STORE_REVIEW_IDENTIFIERS=apple-review@koraexpress.app,apple-review+signup@koraexpress.app
STORE_REVIEW_LOGIN_IDENTIFIER=apple-review@koraexpress.app
STORE_REVIEW_PIN=2468
```

Puis :

```bash
cd /opt/kora-staging
docker compose -f docker-compose.staging.yml up -d          # recharge env_file
docker compose -f docker-compose.staging.yml exec app php artisan config:cache
docker compose -f docker-compose.staging.yml exec app php artisan db:seed --class=StoreReviewAccountSeeder --force
docker compose -f docker-compose.staging.yml exec app php artisan queue:restart
```

**8.3 — Production : `.env.prod` (VPS `/opt/kora-prod`, non commité) — fenêtre de revue**

Même bloc que le staging, avec un **code différent** :

```dotenv
STORE_REVIEW_ENABLED=true
STORE_REVIEW_OTP_CODE=<6 chiffres de la campagne prod>
STORE_REVIEW_IDENTIFIERS=apple-review@koraexpress.app,apple-review+signup@koraexpress.app
STORE_REVIEW_LOGIN_IDENTIFIER=apple-review@koraexpress.app
STORE_REVIEW_PIN=2468
```

Séquence d'ouverture (avant soumission) :

```bash
cd /opt/kora-prod
docker compose -f docker-compose.prod.yml up -d            # recharge env_file
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --class=StoreReviewAccountSeeder --force
docker compose -f docker-compose.prod.yml exec app php artisan queue:restart
```

Points de vigilance prod :

- `env_file` est lu au démarrage du conteneur : un simple `config:cache` **ne suffit pas**,
  il faut `docker compose up -d` (recréation) ;
- `queue:restart` après `config:cache` : les workers `queue:work` chargent la config au boot, et
  l'OTP est envoyé en synchrone mais d'autres jobs lisent la config ;
- le seeder en prod crée **uniquement le compte** (D6) et réapplique le PIN : il sert aussi de
  procédure de réinitialisation ;
- vérifier ensuite `config('services.store_review.enabled')` dans le conteneur prod
  (`php artisan tinker --execute="var_dump(config('services.store_review.enabled'));"`), sans
  afficher le code ;
- le compte doit être créé **avant** la soumission, sinon Apple/Google rejette la fiche.

Séquence de fermeture (après acceptation de la fiche) :

```bash
cd /opt/kora-prod
# 1. STORE_REVIEW_ENABLED=false dans .env.prod, et retirer/mettre à blanc STORE_REVIEW_OTP_CODE
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan queue:restart
# 2. Vérifier que le code fixe est refusé (422) sur un identifiant de revue
```

Le compte de revue reste en base (c'est un vrai compte passager) : le supprimer n'est pas
nécessaire, mais il faut retirer ses identifiants de la whitelist et tourner le code à chaque
nouvelle campagne.

**8.4 — `docs/runbooks/store-review.md`** (nouveau)

Contenu :

- but et principe (whitelist fermée, code fixe, désactivé par défaut) ;
- tableau des variables + où les définir (staging **et** prod), avec un code distinct par env ;
- procédure d'ouverture/fermeture de la fenêtre de revue (staging puis prod), incluant
  `docker compose up -d`, `config:cache`, `queue:restart` ;
- exécution du seeder, réinitialisation du PIN (relancer le seeder) ;
- remise à zéro du compte d'inscription entre deux soumissions (snippet `php artisan tinker`
  ou SQL pour supprimer `apple-review+signup@…` et ses devices) ;
- texte prêt à coller dans **App Review Information** :
  - *Username* : `apple-review@koraexpress.app`
  - *Password* : `2468` (le PIN demandé par l'app)
  - *Notes* : « L'application demande un PIN à 4 chiffres, puis, si un code à 6 chiffres est
    demandé depuis un nouvel appareil, saisir `XXXXXX`. Un second compte vierge
    `apple-review+signup@koraexpress.app` permet de tester l'inscription avec le même code.
    Le compte de démonstration n'a pas de voyage en cours : utilisez la recherche de trajets
    pour tester le parcours de réservation. »
- rotation : générer un nouveau code par campagne, remettre `STORE_REVIEW_ENABLED=false` en prod
  dès la fiche acceptée ;
- dépannage : vérifier `config('services.store_review.enabled')`, `config:cache`, le redémarrage
  du conteneur, les logs `store_review.otp_code_accepted`, 422 vs 404 vs 429.

**8.5 — Renvois** : ajouter un lien depuis `docs/runbooks/mobile-release.md` (section publication
store) et une section « Compte de revue store » dans `docs/runbooks/deployment.md` (prod).

---

## 5. Matrice critères d'acceptation → preuves

| Critère du ticket | Preuve |
|---|---|
| Login `apple-review@` + PIN depuis un nouvel appareil → 202 → code fixe → session | Test §5.1 + recette manuelle §6 |
| Inscription `apple-review+signup@` + code fixe → compte créé | Test §5.2 |
| Code fixe refusé pour tout autre identifiant | Test §5.4 |
| Code fixe refusé si `enabled=false` | Test §5.5 |
| Aucune notification réelle pour les identifiants whitelistés | Tests §5.3 (`Notification::fake` + `assertCount(0)`) |
| Code fixe opérationnel **en production** pendant la fenêtre de revue | Recette prod §6 + log `store_review.otp_code_accepted` |
| Code fixe refusé en production après fermeture de la fenêtre | Vérification §8.1 (422) |
| `composer test` vert | Gate CI (Pint + PHPStan + Pest) |

---

## 6. Recette manuelle (staging puis production)

La recette complète (login nouvel appareil, inscription, refus hors whitelist) se déroule d'abord
en **staging**. Ensuite, un **smoke test** identique (étapes 1 à 3 uniquement) est rejoué sur le
build de **production** une fois la fenêtre ouverte, avec le code de la campagne prod.

```bash
API=https://<staging-host>/api/v1

# 1. Login depuis un "nouvel appareil" → 202
curl -s -X POST "$API/auth/login" -H 'Content-Type: application/json' -d '{
  "identifier":"apple-review@koraexpress.app",
  "pin":"2468",
  "device_name":"App Review iPhone",
  "platform":"ios",
  "device_fingerprint":"app-review-fingerprint-0001"
}'

# 2. Vérification avec le code fixe → 200 + verification_token
curl -s -X POST "$API/auth/otp/verify" -H 'Content-Type: application/json' -d '{
  "otp_challenge_uuid":"<uuid reçu>",
  "purpose":"login_new_device",
  "code":"654321"
}'

# 3. Login complet → 200 + token
curl -s -X POST "$API/auth/login" -H 'Content-Type: application/json' -d '{
  "identifier":"apple-review@koraexpress.app",
  "pin":"2468",
  "device_name":"App Review iPhone",
  "platform":"ios",
  "device_fingerprint":"app-review-fingerprint-0001",
  "otp_challenge_uuid":"<uuid>",
  "verification_token":"<token>"
}'

# 4. Négatif : même code sur un autre compte → 422
curl -s -o /dev/null -w '%{http_code}\n' -X POST "$API/auth/otp/verify" \
  -H 'Content-Type: application/json' -d '{"otp_challenge_uuid":"<uuid autre compte>","purpose":"login_new_device","code":"654321"}'
```

Vérifier ensuite dans les logs serveur : présence de `store_review.otp_code_accepted` et **aucune**
tentative WAHA/Resend vers les adresses de revue. Contrôle final dans l'app : connexion, « Mes
voyages » (billets démo hors prod), inscription avec le compte `+signup`.

**Recette production** (fenêtre ouverte, code prod) :

```bash
API=https://<prod-host>/api/v1
# rejouer les étapes 1 à 3 avec STORE_REVIEW_OTP_CODE de la campagne prod
```

Puis, après la fermeture de la fenêtre, rejouer l'étape 2 avec le code de campagne et vérifier un
`422` (le code n'est plus accepté) — c'est la preuve d'acceptation « code refusé si `enabled=false` »
en conditions réelles.

---

## 7. Découpage, ordre et estimation

| Commit | Contenu | Estimation |
|---|---|---|
| 1 | `feat: accept whitelisted store review otp code` — config + `StoreReviewGate` + 2 actions + `StoreReviewOtpTest` | 0,75 j |
| 2 | `feat: seed store review passenger account` — seeder + `StoreReviewAccountSeederTest` | 0,75 j |
| 3 | `docs: add store review runbook` — `.env.example`, `docs/runbooks/store-review.md` (staging + prod), renvois | 0,25 j |
| 4 | Recette staging, ouverture/fermeture de la fenêtre prod, capture des preuves pour la soumission | 0,5 j |

Total : ~2,25 jours. Ordre imposé : 1 → 2 (le seeder dépend du gate pour la recette) → 3 → 4.
L'activation prod se fait **après** la recette staging validée, et la fermeture est planifiée dès
l'ouverture (voir §8.1).

Avant PR : `cd apps/kora-ops-web && composer test` (inclut Pint + PHPStan + Pest). Pas de
`npm run build` nécessaire (aucun fichier front touché), sauf si un composant Inertia documente la
fonctionnalité.

---

## 8. Risques et points de vigilance

### 8.1 — La fenêtre prod doit être bornée

Activer le dispositif en production est prévu par le ticket, mais c'est une exception de sécurité
temporaire. Garde-fous :

- code propre à la campagne prod (jamais le même qu'en staging), stocké uniquement dans
  `.env.prod` ;
- ouverture juste avant la soumission, fermeture dès l'acceptation de la fiche ;
- vérification post-fermeture : code fixe refusé (422) sur un identifiant de revue ;
- log d'audit `store_review.otp_code_accepted` pour dater les usages et repérer tout usage
  anormal après la fenêtre ;
- ne jamais laisser le code dans la fiche de revue au-delà de la campagne (le tourner à chaque
  soumission).

### 8.2 — Écran « Mes voyages » vide en production (assumé)

Le ticket impose des données démo **hors prod uniquement** : en production, le compte de revue n'a
aucun billet au premier lancement. Risque : un reviewer peut juger l'app vide (Guideline 2.1).

Mitigations retenues, sans déroger à la règle du ticket :

1. note explicite dans App Review Information (« le compte de démonstration n'a pas de voyage en
   cours, utilisez la recherche de trajets ») ;
2. captures d'écran « Mes voyages » fournies dans la fiche store ;
3. au besoin, faire réserver un trajet par le reviewer lui-même (parcours complet) — à condition
   que le paiement soit testable côté store, point à vérifier séparément.

Si le métier veut malgré tout des billets en prod, il faudra une validation explicite et un flag
dédié (`STORE_REVIEW_SEED_DEMO_DATA`), puis un nettoyage après la revue : hors périmètre de ce
ticket.

### 8.3 — Points à confirmer avant la soumission

- Le compte de revue prod est bien créé par le seeder (accès VPS + `--force`) et non via l'UI
  OPS, pour garantir un `pin_hash` et un `email_verified_at` identiques à la recette.
- Le code de campagne prod est généré, saisi dans `.env.prod`, et **jamais** réutilisé depuis le
  staging.
- La note App Review contient bien le code de campagne et l'explication du PIN à 4 chiffres.

### Autres risques

| Risque | Mitigation |
|---|---|
| Compte de revue avec téléphone → whitelist email inopérante | D2 (email testé en plus) + `phone = null` dans le seeder |
| Le reviewer crée `+signup` puis une nouvelle soumission échoue (`account_exists`) | Procédure de reset documentée dans le runbook |
| PIN modifié par le reviewer | Relancer le seeder (réapplique `pin_hash`) |
| Code fixe oublié après la revue | Runbook : rotation + `enabled=false` ; log d'audit pour repérer l'usage |
| `.env` non rechargé (staging ou prod) | `docker compose up -d` (env_file lu au démarrage) puis `config:cache` et `queue:restart` |
| Secret commité | `.env.example` avec valeurs vides ; `pin` par défaut non secret ; revue de PR |
| Throttle `auth-otp` (3/min) atteint par le reviewer | Comportement inchangé et attendu ; documenter « attendre une minute » |
| Brute force du code fixe | Whitelist fermée + throttles ; code jamais renvoyé par l'API |
| Code staging réutilisé en prod | Générer un code distinct par environnement, jamais partagé |
| Oubli de fermeture de la fenêtre prod | Checklist de fermeture §8.1 + vérification 422 + log d'audit |
| Compte prod absent au moment de la soumission | Exécuter le seeder en prod **avant** de soumettre la fiche |
| PHPStan/Pint rouges | Typage strict du gate, exécution de `composer test` en local avant PR |

---

## 9. Hors périmètre

- Aucune modification des contrats OpenAPI/Scribe (le code fixe n'est pas exposé).
- Aucun changement dans `apps/kora-app-passenger` (les écrans OTP restent génériques).
- Aucune migration, aucun nouveau rôle, aucun nouveau canal de notification.
- Pas de gestion multi-campagnes (un seul code actif à la fois).
- Pas de contournement du paiement pour le reviewer (à traiter séparément si nécessaire).
