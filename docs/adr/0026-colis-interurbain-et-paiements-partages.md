# KORA Colis interurbain et paiements partagés

La livraison post-MVP couvre les envois de hub KORA à hub KORA sur un départ Bus direct. La collecte et la livraison à domicile dépendent de la phase VTC. Le dépôt et le retrait en KORA POINT relèvent d’une livraison ultérieure.

Le module Colis possède les demandes d’envoi, la garde et l’historique des affectations. Un hub relie une agence interne à un arrêt. Le départ reste un objet du module Bus. Un retour réutilise les opérations de transport avec sa propre affectation.

Les charges Colis sont des objets payables distincts reliés par clé étrangère aux paiements et aux factures. Elles réutilisent les fournisseurs, webhooks, registres de caisse et services fiscaux existants. La pesée précède l’encaissement et chaque paiement confirmé règle une seule charge. Toute confirmation reçue après expiration ou remplacement du devis ouvre un rapprochement.

Le QR de l’étiquette identifie le colis pour les scans logistiques. Le code de retrait est distinct, vérifié en ligne et consommé lors de la remise physique.
