# ADR 0014 — Canal SMS transactionnel

## Statut

Accepté — 19 octobre 2026

## Contexte

Le catalogue de notifications ne prévoyait aucun fournisseur SMS : le canal restait hors périmètre tant qu'aucun modèle, consentement ou coût n'était défini. Les messages critiques d'un trajet (confirmation, annulation, retard) et le repli des codes OTP dépendent aujourd'hui de WhatsApp (Genuka puis WAHA), qui peut être indisponible ou bloqué pour un contact donné. Conexteo fournit une API SMS directe, sans sandbox, facturée au crédit.

## Décisions

### SMS transactionnel, pas un canal d'inbox

- Le SMS est un canal de livraison externe, comme WhatsApp : il ne crée aucune notification dans l'inbox Passager.
- Le canal Laravel `sms` route la notification via `Notification::route('sms', $phone)` vers une `PassengerSmsNotification` dédiée, jamais vers un compte utilisateur.

### Destinataire et types routés

- Destinataire : téléphone du payeur (`payment.payer_phone`, sinon `companyAccount.phone` pour une réservation entreprise, sinon `payer.phone`), normalisé au format international `+229`.
- Trois types sont routés : `reservation_confirmed`, `departure_cancelled`, `departure_delayed`. Les autres types Passager restent dans l'inbox et le push.
- Les templates sont courts, normalisés GSM-7 et limités à 160 caractères pour tenir sur un seul crédit.

### Préférence et idempotence

- `user_preferences.sms_notifications` (défaut `true`) coupe le SMS pour un numéro rattaché à un compte connu. Un payeur entreprise sans compte reçoit toujours le SMS transactionnel.
- `SendSmsJob` envoie de façon asynchrone (3 essais, backoff 15/90/300 s), relit les settings à l'exécution et utilise `external_id = sha256(dedupeKey)`. Un `409` est traité comme déjà livré.
- Chaque envoi est journalisé dans `notification_logs` (`channel='sms'`, `template`, `status`, `payload` avec `message_id`, `credits_used`, `duplicate` ou `error`).

### OTP

- Le repli OTP devient WhatsApp → SMS → email. Le SMS n'est tenté que si Conexteo est activé et le téléphone présent ; l'envoi est synchrone avec un timeout court pour ne pas bloquer l'authentification.
- La préférence `sms_notifications` ne s'applique pas à l'OTP : c'est un canal de sécurité, pas une communication de confort.

### Administration et coût

- Les identifiants Conexteo (App ID, clé API chiffrée, expéditeur) sont saisis dans les paramètres d'intégration, jamais dans le dépôt.
- Le probe de connexion utilise `?resumeBefore=true` : il résume les crédits sans envoyer de SMS ni consommer de crédit.
- Aucun plafond de coût n'est imposé ; `credits_used` est journalisé pour la réconciliation.

## Conséquences

Une migration de settings et une migration `user_preferences` sont requises. Les annulations massives de départs peuvent consommer beaucoup de crédits. Une panne WhatsApp génère désormais un coût SMS par authentification. Les identifiants doivent être saisis via l'interface et faire l'objet d'une rotation si le ticket Linear est partagé.
