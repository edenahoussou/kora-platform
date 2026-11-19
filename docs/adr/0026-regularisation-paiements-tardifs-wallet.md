# Régularisation des paiements tardifs et politique de hold Wallet

Lors d'un achat de billet par financement mixte (Wallet cash/points + complément externe Mobile Money), un hold est posé sur le Wallet pour réserver la part du portefeuille. Le cycle de vie de ce hold ne doit pas diverger de l'intention de paiement externe.

Tant que le paiement externe auprès de l'opérateur (KKiaPay, FedaPay, MTN MoMo) est en cours (`pending`) et que la réservation associée est active (`expires_at > now()`), le hold Wallet ne peut être libéré unilatéralement par le planificateur d'expiration des holds. Les délais de libération du hold Wallet sont ainsi synchronisés avec la durée de validité de la réservation.

Dès que la réservation expire sans confirmation reçue, le planificateur passe les paiements en attente à l'état `failed` (`failure_code = reservation_expired`), ce qui rend le hold Wallet immédiatement libérable par `wallet:release-expired-holds`. Aucun fonds ne peut ainsi rester séquestré au-delà de la durée de la réservation.

Lorsqu'un paiement externe est confirmé avec succès par le prestataire mais que le hold Wallet a préalablement été libéré (délai opérateur anormalement long) :

1. **Auto-capture nominale** : Si le solde disponible du Wallet du passager permet de couvrir la part Wallet d'origine et que les sièges de la réservation sont toujours libres ou la réservation encore active, le système ré-établit le hold, procède à la capture, confirme le paiement et émet le billet automatiquement sans friction pour le voyageur.
2. **Auto-régularisation Wallet en cas d'impossibilité de voyage** : Si le voyage ne peut être honoré (sièges rachetés par un tiers ou solde Wallet vidé entre-temps par le client), la réservation est clôturée sans émission de billet, et l'intégralité du montant externe encaissé par Mobile Money est immédiatement créditée sur le solde cash du Wallet du voyageur avec le motif comptable immuable `unconfirmed_booking_settlement`. Aucun remboursement externe n'est initié auprès de l'opérateur ni en espèces. Le voyageur reçoit une notification immédiate (SMS et message in-app) l'informant du crédit sur son portefeuille.

Cette décision élimine tout encours bloqué indéfiniment en base de données, préserve la balance comptable et garantit qu'aucun voyageur ayant payé un complément externe ne reste sans contrepartie financière immédiate.

## Exploitation

Les régularisations d'incidents de billetterie apparaissent dans l'historique du grand livre sous le motif `unconfirmed_booking_settlement`. Les journaux d'audit tracent chaque dossier avec les propriétés `payment_uuid`, `reservation_reference`, `provider_amount` et `settlement_action` (`auto_captured` ou `refunded_to_wallet`). Dans KORA OPS, le filtre de supervision des paiements permet de suivre les paiements ayant fait l'objet d'une régularisation vers le Wallet.
