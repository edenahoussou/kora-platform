# Conserver l’agence de vente dans l’historique

L’agence de vente est figée sur la réservation au moment de la confirmation du paiement guichet, depuis la session de caisse ou l’agence du vendeur pour le crédit entreprise. Une mutation du vendeur ne doit modifier ni l’attribution commerciale des anciens billets, ni leur visibilité pour les agences. Les ventes en ligne et mobiles sans contexte commercial d’agence restent sans attribution.

Les ventes sont attribuées à partir du contexte commercial disponible au moment du paiement. Aucune reprise automatique des anciennes réservations n’est prévue pendant la phase de développement : les données de développement peuvent être réinitialisées après l’évolution du schéma, tandis que les cas futurs sans contexte commercial restent sans agence de vente.

L’agence du personnel connecté prime sur une éventuelle permission réseau. La permission `tickets.view-network`, réservée aux rôles centraux, permet aux utilisateurs sans agence de consulter le réseau ; `tickets.view` seule ne suffit pas. La caisse reste personnelle et exige une agence, sans exception administrateur. L’API passager conserve son périmètre personnel.
