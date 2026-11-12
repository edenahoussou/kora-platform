# Reprises des recharges Wallet et financement en points

La recharge possède sa propre référence métier. Elle est distincte d'une réservation et utilise l'UUID du paiement pour rapprocher les échanges avec l'opérateur. L'écriture cash est produite une seule fois, dans la transaction qui confirme le paiement.

Une clé d'idempotence appartient au Wallet et à une demande normalisée. Les demandes identiques retrouvent la recharge active. Toute nouvelle clé ainsi rencontrée reste liée à cette recharge, même après sa clôture. Une nouvelle tentative explicite après un échec certain utilise une nouvelle clé.

Un délai opérateur peut survenir après l'encaissement. La recharge reste alors en attente jusqu'à une vérification. Pour FedaPay, le serveur peut reprendre la génération du lien ou du jeton sur une collecte identifiée. Après l'envoi de la demande Mobile Money, il vérifie la collecte avant toute décision. Un encaissement supplémentaire vérifié déclenche un signalement OPS. Le plafond s'applique aux nouvelles demandes et inclut l'encours. Un encaissement déjà vérifié est crédité et tout dépassement est signalé.

Le financement d'un billet fige le cash Wallet, les points dépensés, leur taux, leur valeur d'usage et le complément externe. Les paiements historiques utilisent la parité effectivement appliquée par l'ancien calcul. Un remboursement partiel cible la part cumulée du financement initial. Les fractions de point sont reportées au remboursement suivant et un remboursement complet restitue toutes les unités d'origine.

## Exploitation

Exécuter `php artisan wallet:reconcile-topups --dry-run` après la migration pour inventorier les dossiers. La commande sans option ferme les échecs dont le non-démarrage est établi et vérifie les autres. Le planificateur la lance toutes les cinq minutes avec un délai croissant par dossier. Les cas non résolus et encaissements supplémentaires sont repérables dans les journaux OPS par UUID du paiement. Pour un encaissement supplémentaire, vérifier la référence chez l'opérateur et suivre le processus de remboursement opérateur. Pour une tentative ambiguë sans référence locale, chercher l'UUID de paiement dans les métadonnées ou références marchandes de l'opérateur. Conserver la preuve du résultat dans le dossier d'incident.
