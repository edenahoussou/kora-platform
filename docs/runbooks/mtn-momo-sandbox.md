# Runbook — MTN MoMo sandbox (Lot K)

## Objet

Ce runbook décrit la mise en service, la vérification et l’exploitation du paiement
MTN MoMo en **sandbox uniquement**. Il ne couvre ni la bascule production, ni le tag
de release, ni l’activation d’un marché réel.

Le Lot K fournit :

- un adaptateur MTN MoMo durci (`MtnMomoAdapter`) ;
- un webhook dont le statut n’est **jamais** une source de vérité ;
- une vérification serveur systématique (`GET /collection/v1_0/requesttopay/{reference}`) ;
- une réconciliation planifiée toutes les 5 minutes ;
- une validation stricte des Settings et un probe de connexion ;
- des tests déterministes couvrant l’idempotence, les réponses ambiguës et les mismatchs.

## Principe de confiance

```text
Client / OPS
   │ initiate()
   ▼
KORA ── POST /collection/v1_0/requesttopay ──► MTN
   │                                            │
   │◄──────────── callback (une seule fois) ────┘
   │
   ├─ le callback sert uniquement de signal
   ├─ GET /collection/v1_0/requesttopay/{reference}
   ├─ contrôle externalId + amount + currency
   └─ application idempotente du statut vérifié
```

Règles non négociables :

1. Le statut du callback n’est jamais appliqué directement.
2. Un `PENDING` ou une réponse ambiguë reste `Pending`.
3. Un mismatch `externalId`, `amount` ou `currency` reste `Pending` avec un
   `verification_issue` ; aucun statut terminal n’est appliqué.
4. Une confirmation n’est appliquée qu’une seule fois : pas de double crédit wallet,
   pas de double reçu, pas de double ticket.
5. Le callback MTN peut être perdu : la réconciliation serveur est le filet de sécurité.

## Prérequis MTN

### Compte et credentials

- Compte développeur MTN MoMo avec une souscription **Collection API** en sandbox.
- Un API User sandbox et sa clé API pour le marché cible.
- La clé de souscription (`Ocp-Apim-Subscription-Key`) associée à cette souscription.
- Un MSISDN de test sandbox fourni par le portail MTN.

### `providerCallbackHost`

À la création de l’API User, le host déclaré doit être **exactement** le host public
qui recevra le callback :

- HTTPS obligatoire, certificat TLS valide ;
- **pas de sous-domaine ajouté** après coup : le host doit correspondre caractère
  pour caractère ;
- POST et PUT doivent être autorisés par l’ingress / le WAF ;
- aucune authentification, redirection ou challenge HTML devant la route ;
- si le host change, recréer un API User : modifier le host existant ne suffit pas.

Exemple de host accepté pour `https://staging.kora.example` :

```text
staging.kora.example
```

Le chemin du callback est fourni par KORA :

```text
https://staging.kora.example/api/v1/payments/webhook/mtn-momo
```

KORA ajoute automatiquement un jeton opaque au chemin lors de l’appel MTN. La valeur
saisie dans les Settings est donc **l’URL de base sans jeton**.

### Base URL MTN

Par défaut, l’adaptateur utilise les endpoints legacy :

- sandbox : `https://sandbox.momodeveloper.mtn.com`
- production : `https://momodeveloper.mtn.com`

MTN a annoncé une migration « One API » vers `https://momoapi.momo.africa`.
Pour un compte déjà migré, définir la variable non secrète :

```dotenv
MTN_MOMO_BASE_URL=https://momoapi.momo.africa
```

Sans cette variable, le comportement legacy est conservé. La valeur est appliquée à
l’adaptateur et au probe. Elle ne contient aucun secret et peut être versionnée dans
l’environnement de déploiement.

## Configuration KORA

### Settings à renseigner

Dans **Administration → Paramètres → Intégrations** :

| Champ | Valeur sandbox |
| --- | --- |
| MTN MoMo activé | oui, uniquement sur l’environnement de sandbox |
| Environnement | `sandbox` |
| Clé de souscription | clé Collection sandbox |
| API User ID | UUID de l’API User sandbox |
| Clé API | clé de l’API User sandbox |
| URL de callback | `https://<host-public>/api/v1/payments/webhook/mtn-momo` |
| Environnement cible | marché réel, ex. `mtnbenin`, `mtnghana`, `mtnivorycoast` |
| Devise sandbox | `EUR` |
| Devise production | devise locale, ex. `XOF` |
| Secret webhook | chaîne aléatoire de **32 caractères minimum** |

Marchés cibles reconnus par la validation serveur :

```text
mtnuganda, mtnghana, mtnivorycoast, mtnzambia, mtncameroon, mtnbenin,
mtncongo, mtnswaziland, mtnguineaconakry, mtnsouthafrica, mtnliberia,
mtnsouthsudan, mtnnigeria, mtnrwanda
```

La liste est fermée et validée côté serveur. Togo n’y figure pas.

### Génération du secret webhook

```bash
openssl rand -hex 32
```

Le secret reste dans les Settings chiffrés. Il ne doit jamais être :

- commité dans Git ;
- copié dans un ticket, une capture d’écran ou un message ;
- affiché dans les logs ;
- réutilisé entre environnements.

### Validation automatique

Quand MTN MoMo est activé, l’enregistrement est refusé si :

- l’URL de callback n’est pas une URL HTTPS valide ;
- le secret webhook fait moins de 32 caractères ;
- l’environnement cible n’est pas dans la liste serveur ;
- les credentials sont incomplets.

Les erreurs sont volontairement génériques et ne reprennent aucun secret.

## Vérification de connexion

1. Enregistrer la configuration.
2. Lancer le probe depuis l’écran d’intégration.
3. Résultat attendu : `MTN MoMo répond correctement.`

Le probe contrôle **avant** l’appel token :

- présence des credentials ;
- présence de l’URL de callback et du secret webhook ;
- appartenance de l’environnement cible à la liste serveur.

Puis il appelle `POST /collection/token/` avec :

- `Authorization: Basic <userId:apiKey>` ;
- `Ocp-Apim-Subscription-Key` ;
- `X-Target-Environment`.

Un refus HTTP est reporté comme échec de connexion, pas comme indisponibilité.
Un timeout ou une erreur réseau est reporté comme indisponibilité.

## Vérification publique du callback

Depuis l’extérieur du réseau KORA :

```bash
curl -i -X POST https://<host-public>/api/v1/payments/webhook/mtn-momo
```

Attendu : une réponse HTTP contrôlée (400 ou 403 selon le jeton absent), **pas** une
page de challenge, une redirection ou une erreur TLS. Le corps ne doit contenir aucun
secret.

Ne pas appeler la route avec un vrai payload de production. Le sandbox ne doit
recevoir que des données de test.

## Smoke test sandbox

### Préconditions

- host public HTTPS disponible et stable ;
- `providerCallbackHost` identique au host du callback ;
- credentials sandbox et MSISDN de test disponibles ;
- Settings enregistrés et probe vert ;
- file d’attente et scheduler actifs ;
- aucun tag production, aucune bascule production.

### Scénario wallet

1. Créer un top-up wallet avec le MSISDN sandbox.
2. Vérifier que le paiement est `Pending` et porte une `provider_reference`.
3. Approuver la demande dans l’interface USSD / sandbox MTN.
4. Vérifier l’arrivée du callback dans les logs applicatifs (sans payload ni PII).
5. Vérifier que la vérification serveur confirme le paiement.
6. Vérifier qu’un seul crédit wallet et un seul reçu sont créés.
7. Rejouer le même callback : aucun second crédit, aucun second reçu.

### Scénario colis ou bus

1. Créer un paiement colis ou bus avec le MSISDN sandbox.
2. Approuver côté MTN.
3. Vérifier la confirmation unique et l’absence de double ticket ou double reçu.
4. Rejouer le callback : le statut reste terminal et aucun traitement n’est dupliqué.

### Scénario callback perdu

1. Couper temporairement l’accès au callback, ou simuler un callback non délivré.
2. Laisser le paiement `Pending`.
3. Exécuter la réconciliation :

```bash
php artisan payments:reconcile-mtn-momo --dry-run
php artisan payments:reconcile-mtn-momo --limit=10
```

4. Vérifier que le paiement est confirmé par la vérification serveur, sans callback.

La planification normale tourne toutes les 5 minutes avec `withoutOverlapping()` et
`onOneServer()`.

## Exploitation

### Commandes utiles

```bash
php artisan schedule:list
php artisan payments:reconcile-mtn-momo --dry-run
php artisan payments:reconcile-mtn-momo --limit=10 --stale-minutes=5 --max-age-hours=24
```

Options :

| Option | Défaut | Effet |
| --- | --- | --- |
| `--limit` | 50 | borne entre 1 et 200 |
| `--stale-minutes` | 5 | ignore les paiements vérifiés récemment |
| `--max-age-hours` | 24 | ignore les paiements hors horizon |
| `--dry-run` | faux | liste les candidats sans appel MTN ni écriture |

La commande ne sélectionne que :

- `provider = mtn_momo` ;
- `status = Pending` ;
- `provider_reference` non nul ;
- paiement dans l’horizon ;
- `last_verified_at` nul ou ancien.

### Logs

À surveiller :

- `MTN MoMo reconciliation interrupted` : exception inattendue, paiement re-marqué
  comme vérifié pour éviter une boucle chaude ;
- absence de callback malgré un paiement approuvé : vérifier l’ingress et le host ;
- `verification_issue` : mismatch ou vérification incomplète, ne pas forcer la
  confirmation.

Interdits dans les logs :

- clé de souscription, API key, secret webhook ;
- jeton de callback ;
- numéro de téléphone complet en clair ;
- payload brut du provider.

Le jeton de callback est dans le chemin de la route. Les logs d’accès de l’ingress
doivent masquer ce segment ou ne pas journaliser cette route. Les logs applicatifs ne
le journalisent pas.

## Diagnostic

| Symptôme | Cause probable | Action |
| --- | --- | --- |
| Probe : identifiants incomplets | Settings partiels | compléter les trois credentials |
| Probe : callback incomplet | URL ou secret absent | renseigner les deux champs |
| Probe : environnement cible non reconnu | code hors liste | utiliser un code officiel |
| Token 401 / 403 | credentials invalides, mauvais marché | vérifier API User et `X-Target-Environment` |
| Callback 404 | `providerCallbackHost` différent du host réel | recréer l’API User avec le bon host |
| Callback 403 | jeton absent, invalide ou secret changé | vérifier le secret et l’URL enregistrée |
| `409` à l’initiation | `X-Reference-Id` déjà utilisé | l’adaptateur re-vérifie ; ne pas créer un second UUID |
| Paiement bloqué en `Pending` | callback perdu ou MTN indisponible | lancer la réconciliation, vérifier l’horizon |
| Mismatch montant / devise / externalId | payload incohérent | garder `Pending`, inspecter, ne jamais forcer |
| Sandbox en XOF | devise sandbox mal configurée | utiliser `EUR` en sandbox |
| Paiement production en EUR | logique sandbox appliquée | corriger la devise production avant toute bascule |

## Rotation et incident

### Rotation du secret webhook

1. Générer un nouveau secret de 32 caractères minimum.
2. L’enregistrer dans les Settings.
3. Relancer le probe.
4. Les callbacks en vol avec l’ancien jeton seront refusés ; la réconciliation
   serveur reste la source de vérité.

### Rotation de la clé API

1. Régénérer la clé côté portail MTN.
2. Mettre à jour les Settings.
3. Relancer le probe.
4. Ne pas modifier l’`X-Reference-Id` des paiements existants.

### Désactivation

1. Désactiver MTN MoMo dans les Settings.
2. Vérifier que les nouveaux top-ups ne proposent plus MTN MoMo.
3. Ne supprimer aucun paiement ni reçu.
4. Laisser la réconciliation terminer les paiements en cours, ou la suspendre
   explicitement si le provider est totalement retiré.

## Preuves à conserver

Pour chaque exécution sandbox :

- identifiant du paiement de test ;
- `provider_reference` ;
- statut avant / après vérification ;
- preuve qu’un second callback ne crée pas de second crédit / reçu / ticket ;
- résultat du probe ;
- résultat de `payments:reconcile-mtn-momo --dry-run` ;
- version de l’application et variable `MTN_MOMO_BASE_URL` utilisée ;
- confirmation qu’aucun secret n’apparaît dans les logs ou les captures.

## Statut E2E

L’E2E sandbox réel n’est exécuté que si les trois prérequis suivants sont réunis :

1. host HTTPS public conforme à `providerCallbackHost` ;
2. credentials sandbox Collection valides ;
3. MSISDN de test sandbox.

Sinon, le statut est **documenté seulement**. Les preuves déterministes (tests
d’adaptateur, webhook, réconciliation, probe) restent la référence.

## Hors périmètre

- bascule production ;
- tag de release ;
- activation d’un marché réel ;
- migration des paiements historiques ;
- correctifs P1/D3 et backlog D4 ;
- modification de la liste des marchés sans validation serveur.
