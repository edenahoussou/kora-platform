# Compte de revue store (Apple / Google) — MEZ-152

Le reviewer ne peut pas recevoir nos OTP (WhatsApp → SMS → email). Un code OTP **fixe**, stocké
uniquement en variable d'environnement, est accepté pour une **whitelist fermée** d'identifiants,
et seulement quand `STORE_REVIEW_ENABLED=true`. Aucune notification réelle n'est émise vers ces
identifiants.

Implémentation : `App\Modules\IdentityAccess\Services\StoreReviewGate`, branchée dans
`IssueOtpChallengeAction` (saut d'envoi) et `VerifyOtpChallengeAction` (acceptation du code fixe,
avec log d'audit `store_review.otp_code_accepted`).

---

## 1. Variables d'environnement

| Variable | Rôle | Exemple |
|---|---|---|
| `STORE_REVIEW_ENABLED` | Ouvre la fonctionnalité (`false` par défaut) | `true` |
| `STORE_REVIEW_OTP_CODE` | Code fixe à 6 chiffres (secret, jamais commité) | `481902` |
| `STORE_REVIEW_IDENTIFIERS` | Whitelist CSV : qui peut utiliser le code fixe | `apple-review@koraexpress.app,apple-review+signup@koraexpress.app` |
| `STORE_REVIEW_LOGIN_IDENTIFIER` | Le compte que le seeder crée ; doit figurer dans la whitelist | `apple-review@koraexpress.app` |
| `STORE_REVIEW_PIN` | PIN 4 chiffres du compte de revue | `2468` |

Règles :

- un **code différent par environnement** (staging ≠ prod) et par campagne de revue ;
- `STORE_REVIEW_ENABLED=false` hors fenêtre de revue, partout ;
- le code ne doit jamais apparaître dans un commit, une capture ou un ticket.

---

## 2. Comptes

| Identifiant | Créé par le seeder | Usage |
|---|---|---|
| `apple-review@koraexpress.app` | Oui | Connexion (PIN + code fixe) |
| `apple-review+signup@koraexpress.app` | **Non** | Parcours d'inscription (doit rester vierge) |

Le seeder ne crée **jamais** le second compte : s'il existait déjà avec un PIN, l'inscription
renverrait `account_exists` (409) et le reviewer serait bloqué. Voir §5 pour le remettre à zéro
entre deux soumissions.

---

## 3. Ouvrir la fenêtre (staging puis production)

### 3.1 Staging — répétition complète

```bash
ssh kora-vps
cd /opt/kora-staging

# 1. Éditer .env.staging (bloc STORE_REVIEW_*), avec un code de campagne staging.

# 2. Recréer le conteneur : env_file est lu au démarrage.
docker compose -f docker-compose.staging.yml up -d

# 3. Recharger la config et les workers.
docker compose -f docker-compose.staging.yml exec app php artisan config:cache
docker compose -f docker-compose.staging.yml exec app php artisan queue:restart

# 4. Créer/réinitialiser le compte de revue (et les données démo hors prod).
docker compose -f docker-compose.staging.yml exec app php artisan db:seed --class=StoreReviewAccountSeeder --force

# 5. Vérifier que la fonctionnalité est active (sans afficher le code).
docker compose -f docker-compose.staging.yml exec app php artisan tinker --execute="var_dump(config('services.store_review.enabled'));"
```

Recette : voir §6.

### 3.2 Production — build réellement soumis aux stores

```bash
ssh kora-vps
cd /opt/kora-prod

# 1. Éditer .env.prod : code de campagne prod, whitelist, PIN, ENABLED=true.

# 2. Recréer + recharger.
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan queue:restart

# 3. Créer le compte (mode « compte seul » : aucune donnée démo en prod).
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --class=StoreReviewAccountSeeder --force

# 4. Vérifier l'activation.
docker compose -f docker-compose.prod.yml exec app php artisan tinker --execute="var_dump(config('services.store_review.enabled'));"
```

Le compte doit exister **avant** la soumission de la fiche : sans identifiants valides,
Apple/Google rejette la revue.

---

## 4. Renseigner la fiche App Store / Play Store

Champ **App Review Information** :

- *Sign-in required* : oui
- *Username* : `apple-review@koraexpress.app`
- *Password* : `2468` (le PIN demandé par l'app)
- *Notes* :

> L'application demande un PIN à 4 chiffres, puis, si un code à 6 chiffres est demandé depuis un
> nouvel appareil, saisir `XXXXXX` (le code de cette campagne de revue). Un second compte vierge
> `apple-review+signup@koraexpress.app` permet de tester l'inscription avec le même code.
> Le compte de démonstration n'a pas de voyage en cours : utilisez la recherche de trajets pour
> tester le parcours de réservation.

Remplacer `XXXXXX` par `STORE_REVIEW_OTP_CODE` de la campagne.

---

## 5. Remettre à zéro entre deux soumissions

Le reviewer peut avoir créé `apple-review+signup@…`, changé le PIN ou enregistré des appareils de
confiance. Avant chaque nouvelle campagne :

```bash
# Réinitialise le PIN, l'email vérifié et la whitelist côté compte de connexion.
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --class=StoreReviewAccountSeeder --force
```

Supprimer le compte d'inscription (uniquement s'il a été créé par une revue précédente) :

```bash
docker compose -f docker-compose.prod.yml exec app php artisan tinker --execute="
\App\Models\User::query()->where('email', 'apple-review+signup@koraexpress.app')->get()->each->delete();
"
```

Vérifier qu'il n'existe plus :

```bash
docker compose -f docker-compose.prod.yml exec app php artisan tinker --execute="
var_dump(\App\Models\User::query()->where('email', 'apple-review+signup@koraexpress.app')->exists());
"
```

`false` attendu.

---

## 6. Recette

```bash
API=https://<host>/api/v1

# 1. Login nouvel appareil → 202 requires_otp
curl -s -X POST "$API/auth/login" -H 'Content-Type: application/json' -d '{
  "identifier":"apple-review@koraexpress.app",
  "pin":"2468",
  "device_name":"App Review iPhone",
  "platform":"ios",
  "device_fingerprint":"app-review-fingerprint-0001"
}'

# 2. Vérification avec le code fixe → 200 + verification_token
curl -s -X POST "$API/auth/otp/verify" -H 'Content-Type: application/json' -d '{
  "otp_challenge_uuid":"<uuid>",
  "purpose":"login_new_device",
  "code":"XXXXXX"
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
```

Contrôles :

- log `store_review.otp_code_accepted` présent dans les logs applicatifs ;
- aucune tentative WhatsApp/email vers les adresses de revue ;
- en staging : écran « Mes voyages » peuplé (données démo) ; en prod : état vide attendu.

---

## 7. Fermer la fenêtre (obligatoire après acceptation de la fiche)

```bash
ssh kora-vps
cd /opt/kora-prod

# 1. .env.prod : STORE_REVIEW_ENABLED=false et STORE_REVIEW_OTP_CODE= (vider le code)

# 2. Recréer + recharger.
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan queue:restart

# 3. Vérifier que le code fixe est refusé (422 attendu) sur un identifiant de revue.
```

Répéter la même fermeture en staging. Le compte de revue peut rester en base (c'est un vrai compte
passager), mais il ne doit plus être whitelisté et le code doit être tourné à chaque campagne.

---

## 8. Dépannage

| Symptôme | Cause probable | Action |
|---|---|---|
| `422 code incorrect` avec le code fixe | `STORE_REVIEW_ENABLED=false`, code mal saisi, code ≠ 6 chiffres, identifiant hors whitelist | Vérifier `config('services.store_review.enabled')`, le contenu de `.env`, et la whitelist |
| La config ne change pas après édition de `.env` | `env_file` lu au démarrage + cache de config | `docker compose up -d`, puis `config:cache` et `queue:restart` |
| `404 user_not_found` au login | Compte de revue absent | Lancer `db:seed --class=StoreReviewAccountSeeder --force` |
| `409 account_exists` à l'inscription | `apple-review+signup@…` existe déjà (revue précédente) | Voir §5 |
| Une notification part quand même | Identifiant absent de la whitelist, ou flag désactivé | Corriger `.env`, recharger, revérifier |
| Le seeder échoue | `STORE_REVIEW_IDENTIFIERS` vide, `login_identifier` hors liste, PIN ≠ 4 chiffres | Corriger `.env` (le message d'erreur indique le champ) |
| Code refusé après fermeture de la fenêtre | Comportement attendu | — |

---

## 9. Sécurité

- Whitelist fermée : aucun autre utilisateur ne peut utiliser le code fixe, même flag activé.
- Comparaison `hash_equals` (timing-safe) ; le code n'est jamais stocké en base ni renvoyé par l'API.
- Throttles inchangés : `auth-otp` (3/min) et `auth-login` (5/min).
- Le code fixe n'exonère d'aucune garde : expiration 5 min, 5 tentatives max, challenge `pending`,
  `verification_token` à usage unique.
- Fenêtre bornée : `enabled=false` hors revue, code tourné à chaque campagne, log d'audit pour
  tracer les usages.
