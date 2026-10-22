# ADR 0019 — Passerelles de paiement multi-pays : KKiaPay (widget), FedaPay et MTN MoMo

## Statut

Accepté — 22 octobre 2026

## Contexte

MEZ-146. Le module `Payments` disposait de deux intégrations serveur-à-serveur (FedaPay SDK, MTN MoMo Collection BJ) et d'un catalogue mono-pays (`country: 'BJ'`). L'activité KORA opère sur le Bénin, le Togo et la Côte d'Ivoire, et exige la couverture carte bancaire en plus du Mobile Money. KKiaPay — agrégateur demandé par le ticket — n'offre pas d'API de push serveur : son modèle est un widget client (`k.js` / SDK mobiles) qui rend un `transactionId` à vérifier côté serveur.

## Décisions

### KKiaPay = widget client + vérification serveur (`sdk_widget`)

`KKiaPayAdapter::initiate()` ne lance aucun appel réseau : il retourne la configuration du widget (clé publique, `sandbox`, `amount`, `partnerId = payment.uuid`, `paymentmethod: [momo, card, wallet]`, `countries`) persistée dans `provider_payload.widget` et exposée par `PaymentResource`. Le client (app passager, puis tout autre frontend) ouvre le widget, récupère le `transactionId` et le soumet à `POST /payments/{uuid}/verify` qui le lie à `provider_transaction_id` — la liaison est définitive et un identifiant divergent est rejeté (422). La confirmation réelle passe toujours par `POST {base}/api/v1/transactions/status` avec les trois clés (`X-API-KEY`, `X-PRIVATE-KEY`, `X-SECRET-KEY`), jamais par la seule réponse du widget.

Le webhook `POST /payments/webhook/kkiapay` est authentifié par l'en-tête `x-kkiapay-secret` (hash configuré sur le tableau de bord marchand, comparé en `hash_equals`) ; le payload fournit `partnerId` et `transactionId`, puis la vérification serveur reste la source de vérité. La confirmabilité est idempotente : un webhook ou un poll en double sur un paiement terminal est un no-op.

### Réseaux Mobile Money suffixés par pays

`MobileMoneyNetwork` étendu avec des cas par pays (`mtn_momo_ci`, `wave_ci`, `orange_money_ci`, `moov_flooz_ci`, `moov_flooz_tg`, `tmoney_tg`) ; `country()` déduit le pays du réseau, ce qui évite un champ `payer_country` séparé et les divergences réseau/pays. MTN MoMo direct reste limité au Bénin (`mtnbenin`) ; la couverture TG/CI passe exclusivement par les agrégateurs. FedaPay résout le `phone_number.country` depuis le réseau choisi et ses `mobileMoneyModes` couvrent les neuf réseaux.

### `PaymentMethod::Card`

Nouveau moyen `card` dans le catalogue : le widget KKiaPay autorise `momo | card | wallet` et le `source` retourné distingue les trois. La carte n'est exposée que via `kkiapay` en V1 ; le `wallet` du catalogue reste le Wallet KORA désactivé (distinct du wallet KKiaPay interne au widget).

### Pending n'est pas un échec

`ConfirmPaymentAction` traitait toute réponse non-confirmée comme un échec : un `verify`/`webhook` revenu `pending` basculait le paiement et sa réservation à l'échec. Une réponse `pending` rafraîchit désormais le `provider_payload` sans changer le statut — préalable indispensable à KKiaPay (le widget n'a rendu aucun `transactionId` tant que le client n'a pas payé) et correct pour MTN MoMo dont les callbacks arrivent tôt.

### Caisse inchangée, credentials chiffrés

Le parcours guichet (encaissement manuel sur les comptes Mobile Money d'agence) n'est pas modifié : les passerelles intégrées ne servent que les paiements initiés par les passagers. Les clés KKiaPay (`private`, `secret`, `webhook`) rejoignent `IntegrationsSettings::encrypted()` ; la clé publique reste lisible car exposée au widget. Le périmètre OPS Web du ticket est l'écran Intégrations (champs + sélecteur de pays + probe de connexion).

## Conséquences

- L'app passager consomme `methods[].interaction = 'sdk_widget'` + `data.widget` pour ouvrir le widget, puis poll `verify` en fournissant `transaction_id`.
- Les modes `sendNow` FedaPay TG/CI (`mtn_ci`, `moov_ci`, `wave_ci`, `orange_ci`, `moov_tg`, `tmoney`) sont des réglages : un identifiant invalide se traduit en échec `provider_initiation_failed`, à ajuster selon la console FedaPay réelle.
- Refunds (`/transactions/revert`) hors scope V1 — à suivre dans un ticket dédié.

## Implémentation

`KKiaPayAdapter` (HTTP client, mêmes conventions que `MtnMomoAdapter`), binding `AppServiceProvider`, `PaymentManager::catalog()` multi-pays (`country` rétro-compatible + `countries` + `countries` par provider), `VerifyPaymentAction` (liaison `transaction_id`), `KKiaPayConnectionProbe`, écran Intégrations, `KKiaPayPaymentTest` (widget, statuts, partnerId, webhook, idempotence).
