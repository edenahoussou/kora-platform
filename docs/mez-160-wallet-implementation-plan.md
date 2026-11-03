# Plan d'implémentation — Module Wallet KORA BUS (MEZ-160)

> Ticket : [MEZ-160 — Backend Module Wallet](https://linear.app/mezabodev/issue/MEZ-160/backend-module-wallet-kora-bus-ledger-cashpoints-fidelite-recharge) · Sprint 7 — Wallet & Fidélité
> Sources métier : [MEZ-49](mez-49-compte-voyageur-wallet-fidelite.md) (décisions §2–5, hypothèses §9, scénarios §7, validations §10) · [ADR 0004](adr/0004-wallet-fidelite-bus.md) · CDC §9.
> Décisions de conception actées avec le produit le 2 novembre 2026 (section 11).

## 1. Règle d'or : aucune valeur métier en dur

Toutes les valeurs des règles métier vivent dans `WalletSettings` (spatie/laravel-settings, groupe `operations`, enregistrée dans `config/settings.php`, éditée depuis la section « Wallet & fidélité » de l'écran Paramètres OPS, mutations auditées).

| Propriété | Défaut | Rôle |
|---|---|---|
| `wallet_cash_enabled` | `false` | Gate le solde cash : recharge, débit cash, catalogue `wallet`. Le lancement cash attend la validation MEZ-49 §10. |
| `wallet_points_enabled` | `true` | Gate le solde points et le moteur de fidélité. |
| `loyalty_earn_rate_basis_points` | `200` (2 %) | Taux de points sur la part monétaire des allocations figées. |
| `point_value_fcfa` | `1` | Valeur d'usage d'un point en FCFA. |
| `points_min_spend` | `100` | Minimum d'utilisation de points par paiement. |
| `bonus_welcome_points` | `500` | Bonus bienvenue, 1× par compte, après sa première jambe effectuée. |
| `bonus_referral_points` | `200` | Au parrain quand le filleul utilise son premier billet. |
| `bonus_anniversary_points` | `100` | À l'anniversaire de création du compte. |
| `bonus_usage_nth` | `5` | Rang de l'utilisation déclenchant le bonus mensuel. |
| `bonus_usage_points` | `50` | Montant du bonus de Nième utilisation. |
| `points_expiry_months` | `6` | Fenêtre d'expiration globale sans transaction qualifiante. |
| `points_expiry_reminder_days` | `[30, 7, 1]` | Jalons de rappel avant expiration. |
| `wallet_hold_ttl_minutes` | `15` | Durée de vie d'un hold de fonds. |
| `topup_min_amount` / `topup_max_amount` | `null` | Bornes de recharge (sans plafond par défaut). |
| `cash_balance_cap` | `null` | Plafond du solde cash (sans plafond par défaut). |
| `topup_amount_presets` | `[2000, 5000, 10000, 20000]` | Paliers rapides de l'écran de recharge. |

## 2. Architecture du module

Module unique `app/Modules/Wallet` — cash et points partagent le même ledger, les mêmes holds et la même interface.

```
app/Modules/Wallet/
  Actions/
    FindOrCreateWalletAction.php            # compte par téléphone normalisé → wallet
    PlaceWalletHoldAction.php               # hold cash+points (idempotent)
    CaptureWalletHoldAction.php
    ReleaseWalletHoldAction.php
    InitiateWalletTopUpAction.php           # recharge via PaymentManager
    ConfirmWalletTopUpAction.php            # crédit après confirmation provider
    DebitWalletForSaleAction.php            # débit atomique (app confirm + guichet)
    CreditLegPointsAction.php               # points d'une jambe effectuée
    ProcessDepartureLoyaltyAction.php       # toutes les jambes d'un départ clôturé
    GrantLoyaltyBonusAction.php             # welcome / referral / anniversary / usage_nth
    ExpireWalletPointsAction.php            # expiration globale
    SendPointsExpiryRemindersAction.php     # rappels J-x
    RouteRefundToWalletAction.php           # remboursement prorata financement initial
    ApplyLoyaltyCorrectionAction.php        # reprise indus + compensation future
    RequestRestitutionAction.php
    ApproveRestitutionAction.php
    RejectRestitutionAction.php
    MarkRestitutionPaidAction.php           # versement tracé → débit cash
    RecordWalletAdjustmentAction.php        # écriture corrective auditée
    AttachReferralCodeAction.php            # rattachement du code parrain
  Services/
    WalletLedgerService.php                 # couture : écritures append-only, recalcul
                                          # soldes, invariants (pas de négatif,
                                          # hold ≤ disponible, 1 écriture/opération)
  Data/
    WalletBalanceType.php                   # cash | points
    WalletTransactionReason.php             # topup, purchase, refund_cash, refund_points,
                                            # points_earn, bonus_welcome, bonus_referral,
                                            # bonus_anniversary, bonus_usage,
                                            # points_expire, points_correction,
                                            # compensation_offset, restitution, adjustment
    WalletHoldStatus.php                    # active | captured | released
    RestitutionStatus.php                   # requested | approved | rejected | paid
    WalletTopUpStatus.php                   # pending | confirmed | failed
    DTO/ WalletSplit.php, WalletSummary.php, LegPointsCredit.php
  Models/  Wallet, WalletTransaction, WalletHold, WalletTopUp,
           WalletRestitution, WalletBonusGrant
  Http/
    Controllers/  WalletController, WalletTransactionController,
                  WalletTopUpController, WalletReferralController,
                  OpsWalletLookupController, OpsWalletDebitController,
                  OpsWalletRestitutionController
    Requests/     InitiateTopUpRequest, AttachReferralRequest,
                  OpsWalletDebitRequest, StoreRestitutionRequest,
                  RejectRestitutionRequest, MarkRestitutionPaidRequest,
                  WalletAdjustmentRequest
    Resources/    WalletResource, WalletTransactionResource,
                  WalletTopUpResource, WalletRestitutionResource
  Jobs/
    CreditDepartureLoyaltyJob.php           # un départ clôturé → jambes créditées
    ExpireWalletPointsJob.php
    SendPointsExpiryRemindersJob.php
    GrantAnniversaryBonusJob.php
    ReleaseExpiredWalletHoldsJob.php
  Console/
    wallet:credit-pending-legs              # rattrapage idempotent (sync tardive)
    wallet:expire-points
    wallet:expiry-reminders
    wallet:anniversary-bonuses
    wallet:release-expired-holds
```

## 3. Modèle de données

Une migration `create_wallet_tables` :

- **`wallets`** : `uuid` unique · `user_id` unique → `users` · `cash_balance` unsignedBigInteger · `points_balance` unsignedBigInteger · `points_expires_at` timestamp nullable · `points_compensation_due` unsignedInteger default 0 · `referral_code` string unique · `referred_by_user_id` nullable → `users` · timestamps.
- **`wallet_transactions`** : `uuid` unique · `wallet_id` → wallets · `balance_type` (cash|points) · `direction` (credit|debit) · `amount` unsignedBigInteger · `reason` string enum · `reference_type`/`reference_id` nullable (polymorphe : payment, reservation, segment, topup, restitution) · `idempotency_key` string unique · `actor_user_id` nullable (agent OPS le cas échéant) · `agency_id` nullable · `meta` json nullable · `created_at` (pas d'updated_at — append-only). Index `(wallet_id, created_at)`, `(reference_type, reference_id)`.
- **`wallet_holds`** : `uuid` unique · `wallet_id` · `cash_amount` · `points_amount` · `reservation_id` nullable · `payment_id` nullable · `status` (active|captured|released) · `expires_at` · `idempotency_key` unique. Index `(wallet_id, status)`.
- **`wallet_topups`** : `uuid` unique · `wallet_id` · `payment_id` → payments (réutilise le cycle provider existant) · `amount` · `status` · `idempotency_key` unique.
- **`wallet_restitutions`** : `uuid` unique · `wallet_id` · `amount` · `status` · `requested_by_user_id` · `approved_by_user_id` nullable · `approved_at`/`rejected_at`/`paid_at` nullable · `rejection_reason` nullable · `payout_reference` nullable · `payout_channel` nullable · `note` nullable.
- **`wallet_bonus_grants`** : `uuid` unique · `wallet_id` · `kind` (welcome|referral|anniversary|usage) · `period_key` string (ex. `2026-11`, `2027` — garantit l'unicité par période) · `transaction_id` → wallet_transactions · unique `(wallet_id, kind, period_key)`.

Soldes matérialisés sur `wallets`, reconstruisibles depuis `wallet_transactions`. Toute correction est une écriture inverse, jamais une suppression.

## 4. Interface du module (couture)

`WalletLedgerService` est le seul point d'écriture du ledger : verrouille le wallet (`lockForUpdate`), vérifie les invariants, insère la transaction, met à jour le solde, retourne la transaction. Les actions publiques n'écrivent jamais directement.

Invariants appliqués au verrou :
- jamais de solde négatif (débit > disponible → `ValidationException`) ;
- un hold ne dépasse jamais le disponible (solde − holds actifs) ;
- `idempotency_key` déjà présente → retour de l'écriture existante (replay), jamais de double écriture ;
- un hold `captured` ou `released` ne peut pas être recapturé ni relibéré.

## 5. Flux détaillés

### 5.1 Provisionnement
`FindOrCreateWalletAction` : retrouve l'utilisateur par téléphone normalisé, sinon le crée (rôle `passenger`, sans accès activé), puis le wallet associé. Réutilisée par Booking/OPS lors de l'émission de billet et par le lookup guichet. Fusion de comptes, changement de numéro et réattribution hors scope (MEZ-55).

### 5.2 Recharge (`POST /v1/wallet/topups`)
Montant validé contre `topup_min/max_amount` et `cash_balance_cap` → `wallet_topups` + `payments` (méthode `mobile_money`/`card`, `reservation_id` null, `payable` topup) → `PaymentManager->resolve()->initiate()` → webhook/callback existant (`ConfirmPaymentAction`) branche `ConfirmWalletTopUpAction` quand le paiement porte un topup → crédit cash `reason=topup`, activité qualifiante (recharge confirmée prolonge les points). Idempotent par `idempotency_key` + référence provider.

### 5.3 Paiement billet mixte (app)
`InitiatePaymentRequest` accepte déjà `points_amount`, `wallet_amount`, `mobile_money_amount` — ajouter la validation : `points_amount ≥ points_min_spend` si > 0, disponible suffisant, réservation payable par le wallet (payeur = titulaire ou son propre wallet). `InitiatePaymentAction` → `PlaceWalletHoldAction` (hold cash+points lié au paiement). `ConfirmPaymentAction` : succès → `CaptureWalletHoldAction` (débit `reason=purchase`, split conservé) ; échec/expiration → `ReleaseWalletHoldAction`. `PaymentManager::catalog()` : `wallet.enabled` et `availability` pilotés par `wallet_cash_enabled`/`wallet_points_enabled`.

### 5.4 Débit au guichet sans app (OPS)
`StoreCounterSalePaymentRequest` accepte `method=wallet` + `wallet_holder_phone` + `points_amount`/`wallet_amount`. Écran `sale.tsx` : champ téléphone → `GET /ops/wallets/lookup` → affiche titulaire, billet, total, répartition points/cash → l'agent confirme → `RecordCounterSalePaymentAction` branche wallet : hold+capture atomique via `DebitWalletForSaleAction`, écriture avec `actor_user_id` (agent) + `agency_id` + référence billet, puis notification au titulaire (montant + référence de contestation). Permission `wallet.debit-counter`. Aucun OTP par débit : l'accord verbal + contrôle visuel est la règle actée (risque documenté MEZ-49 §3).

### 5.5 Points par jambe effectuée
Déclencheurs : (a) dans la transition de départ vers `arrived` (`ProgressDepartureAction`/`ChangeDepartureStatusAction`) → `CreditDepartureLoyaltyJob` ; (b) `wallet:credit-pending-legs` quotidien — rattrapage des départs `arrived` non traités (sync tardive P501).
`CreditLegPointsAction` par segment `boarded` non crédité : base = allocation figée de la jambe (`total_snapshot['legs']` / `passenger_allocations`) × part monétaire (allocation − part points du financement) ; `floor(base × rate / 10000)` ; le voyageur doit avoir un compte (téléphone personnel) sinon rien. Crédite aussi le compteur d'utilisation (1 billet = 1 usage à sa première jambe) et évalue `bonus_usage`, `bonus_welcome`, `bonus_referral`. Le cash Wallet compte comme de l'argent dans l'assiette (décision actée).

### 5.6 Bonus
`GrantLoyaltyBonusAction` écrit via `wallet_bonus_grants` (unique par kind+période) : `welcome` à la 1ʳᵉ jambe ; `referral` au parrain à la 1ʳᵉ utilisation du filleul ; `anniversary` par `GrantAnniversaryBonusJob` quotidien (date de création du compte, `Africa/Porto-Novo`) ; `usage` au `bonus_usage_nth`ᵉ usage du mois calendaire. Une recharge n'est jamais un usage. Si `points_compensation_due` > 0, tout gain (earn ou bonus) est d'abord absorbé par la compensation (`compensation_offset`).

### 5.7 Expiration
`wallet:expire-points` quotidien : wallets sans transaction qualifiante (achat confirmé ou recharge confirmée) depuis `points_expiry_months` → débit `reason=points_expire`, solde à 0. `wallet:expiry-reminders` : aux jalons `points_expiry_reminder_days` → notification contenant solde + suggestion de service (règle CDC). Une restitution de points (`refund_points`) réarme `points_expires_at`. L'activation tardive de l'app ne réarme rien et ne ressuscite pas l'expiré.

### 5.8 Remboursements et corrections
`CancelReservationAction` : destination `wallet_cash` par défaut pour un payeur personnel identifié → `RouteRefundToWalletAction` : prorata du financement initial (parts points/cash figées dans `provider_payload` du paiement + allocations) → `refund_points` (réarme l'expiration) + `refund_cash` ; crédit entreprise inchangé ; versement manuel en fallback sans compte. `ApplyLoyaltyCorrectionAction` : recalcule le droit à points après remboursement tardif → reprise sur le disponible (`points_correction`), reliquat dans `points_compensation_due`. Idempotent par annulation.

### 5.9 Restitution exceptionnelle
`POST /ops/wallet-restitutions` (demande + motif + contrôle titulaire) → approbation/rejet par `wallet.manage-restitutions` (rôles `daf`, `operations_director`, `system_admin`) → l'agent verse en Mobile Money hors système → `mark-paid` avec `payout_reference` → débit `reason=restitution`. Écran OPS « Restitutions Wallet » (liste, filtres, validation, saisie de la référence).

### 5.10 Ajustements
`RecordWalletAdjustmentAction` : crédit/débit `reason=adjustment` avec motif obligatoire, `wallet.adjust` réservé `daf`/`system_admin`. Audité, jamais de suppression.

## 6. Permissions et rôles

Ajout à `SystemRole::allPermissions()` + attribution rôles (`KoraBusRolePermissionSeeder`) :

| Permission | Rôles | Usage |
|---|---|---|
| `wallet.debit-counter` | `agency_agent`, `agency_manager`, `kora_point_agent`, `kora_point_supervisor`, admins dir. | Débit Wallet au guichet |
| `wallet.manage-restitutions` | `daf`, `operations_director`, `system_admin` | Approbation restitutions |
| `wallet.adjust` | `daf`, `system_admin` | Écritures correctives |
| `settings.operations.manage` (existante) | — | Paramètres Wallet |

## 7. API

```
# Passager (auth:sanctum)
GET  /v1/wallet                        → WalletResource (soldes, points_expires_at,
                                         referral_code, paramètres UX, enabled flags)
GET  /v1/wallet/transactions           → paginé curseur, ?balance_type=cash|points
POST /v1/wallet/topups                 → initie la recharge provider
GET  /v1/wallet/topups/{uuid}          → statut
POST /v1/wallet/referral               → attache un code parrain

# OPS (auth:sanctum + permission)
GET  /ops/wallets/lookup?phone=        → titulaire + soldes (wallet.debit-counter)
POST /ops/wallets/{uuid}/debit         → débit atomique (wallet.debit-counter)
GET|POST /ops/wallet-restitutions      → liste/demande
POST /ops/wallet-restitutions/{uuid}/approve | /reject | /mark-paid
```

Exposition : uuid uniquement. Les montants sont toujours calculés serveur.

## 8. Notifications

Événements via `PassengerNotificationPublisher` (méthodes dédiées wallet) — canal téléphone : **WhatsApp principal, SMS repli, email si téléphone indisponible** :

| Événement | Contenu |
|---|---|
| Débit guichet | Montant, billet, référence de contestation, agent/agence |
| Recharge confirmée | Montant crédité, nouveau solde |
| Points crédités | Points gagnés, jambe concernée, solde |
| Bonus accordé | Type, montant, solde |
| Rappel expiration | Solde de points, date, suggestion de service (règle CDC J-30) |
| Expiration | Points expirés |
| Restitution versée | Montant, référence du versement |

La livraison reste reprenable indépendamment de l'écriture économique (`whatsapp:retry-skipped` existant).

## 9. Ordonnancement (`routes/console.php`)

```
wallet:release-expired-holds   → everyMinute
wallet:credit-pending-legs     → dailyAt('03:10')  (rattrapage sync tardive)
wallet:expire-points           → dailyAt('03:20')
wallet:expiry-reminders        → dailyAt('08:00')  (heure métier, Africa/Porto-Novo)
wallet:anniversary-bonuses     → dailyAt('08:10')
```

## 10. Points de contact dans l'existant

| Fichier | Modification |
|---|---|
| `config/settings.php` | Enregistrer `WalletSettings` |
| `app/Modules/Payments/PaymentManager.php` | `wallet.enabled` piloté par les flags settings ; retirer le rejet dans `assertAvailable` |
| `InitiatePaymentAction` | `PlaceWalletHoldAction` après création du paiement |
| `ConfirmPaymentAction` | capture hold / crédit topup ; release sur échec |
| `RecordCounterSalePaymentAction` | branche `method=wallet` (débit + audit agent/agence) |
| `CancelReservationAction` | routage `wallet_cash` + prorata points |
| `ProgressDepartureAction` / statut `arrived` | dispatch `CreditDepartureLoyaltyJob` |
| `SystemRole`, `KoraBusRolePermissionSeeder` | nouvelles permissions |
| `StoreCounterSalePaymentRequest`, `InitiatePaymentRequest` | champs wallet |
| `PassengerNotificationPublisher` | méthodes wallet |
| `routes/api.php`, `routes/web.php`, `routes/console.php` | routes + schedules |
| `resources/js/pages/sale.tsx`, `settings/*`, nouvelle page restitutions | UI OPS |

## 11. Décisions actées (grilling du 2 nov. 2026)

1. Débit guichet complet : endpoint + écran OPS dans le ticket.
2. Recharge cash incluse via providers existants, flux dédié idempotent.
3. Restitution exceptionnelle incluse : workflow demande → approbation → versement hors système tracé → débit.
4. Find-or-create minimal du compte par téléphone dans le scope ; identité lourde à MEZ-55.
5. Module `Wallet` unique (cash + points + fidélité dans un même ledger).
6. Parrainage : mécanisme backend (code auto + rattachement) + UI mobile minimale.
7. Remboursements personnels → Wallet cash par défaut.
8. `WalletSettings` dédiée, groupe `operations`.
9. Points : hook clôture + job de rattrapage ; `floor` déterministe ; cash Wallet = argent dans l'assiette.
10. Hold→capture→release pour tout débit.
11. Flags `wallet_cash_enabled` (défaut off) et `wallet_points_enabled` (défaut on).
12. Notifications : WhatsApp → SMS → email (téléphone uniquement, email en repli).
13. Validateurs restitutions : `daf`, `operations_director`, `system_admin`.
14. UX mobile : chips + montant libre, deux contrôles manuels points/cash, expiration permanente + bannière, historique unique filtrable.

## 12. Découpage TDD (ordre des slices)

1. Ledger + `WalletSettings` + provisionnement (écritures, invariants, idempotence)
2. API lecture (`GET /v1/wallet`, `GET /v1/wallet/transactions`)
3. Recharge (initiate + confirm + plafonds)
4. Paiement billet app (hold/capture/release, catalogue, échecs)
5. Débit guichet (lookup + écran + audit + notification)
6. Moteur fidélité : earn par jambe + rattrapage
7. Bonus (4) + compteur d'usage + compensation
8. Expiration + rappels
9. Remboursements prorata + corrections
10. Restitution + ajustements + écrans OPS

## 13. Tests

Les 13 scénarios MEZ-49 §7 deviennent des feature tests Pest (`tests/Feature/Wallet/`) : nommage par situation (`WalletCounterDebitTest`, `LoyaltyLegCreditTest`, `LoyaltyBonusTest`, `PointsExpiryTest`, `WalletRefundRoutingTest`, `LoyaltyCorrectionTest`, `WalletRestitutionTest`, `WalletTopUpTest`, `WalletHoldConcurrencyTest`, `WalletIdempotencyTest`). Plus : débits concurrents sur verrou, replay de webhooks, sync tardive, arrondi, expiration partielle.
