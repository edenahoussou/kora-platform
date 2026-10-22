# ADR 0015 — Périmètre et sources de la rentabilité

## Statut

Accepté — 19 octobre 2026

## Contexte

L'écran `/profitability` n'était qu'un placeholder « V2 ». La maquette de référence présente une rentabilité consolidée par bus, ligne, départ et agence, mais ses chiffres ne s'additionnent pas : le hero affiche 5,42 M de résultat net à 21,8 % quand la structure P&L affiche 8,57 M à 36,6 %. Elle reste une **référence visuelle**, pas une spécification de calcul.

Les sources réellement disponibles dans KORA OPS sont hétérogènes : paiements confirmés et annulations reconnues (module Recettes), dépenses opérationnelles approuvées (module Dépenses), positions GPS des sessions de tracking, départs et affectations bus/ligne. Les coûts CassKai (RH, main-d'œuvre), le module Colis et les commissions KORA POINT ne sont pas livrés.

## Décisions

### Base temporelle : période de service

- La rentabilité se lit sur la **période de service** : les départs dont `scheduled_at` tombe dans la période (fuseau `Africa/Porto-Novo`).
- Les paiements retenus sont les paiements confirmés en XOF rattachés aux réservations de ces départs, quelle que soit leur date d'encaissement. Une vente anticipée pour un départ futur appartient donc à la période du départ, pas à celle de l'encaissement.
- Les coûts retenus sont les dépenses `approved` en XOF dont `occurred_at` tombe dans la période.
- Ce choix diffère volontairement de la vue **Recettes**, qui reste une vue de trésorerie par date de confirmation. Le bandeau de rapprochement de l'écran expose l'écart de timing entre les deux vues ; il ne recompte jamais la caisse.

### Recette nette de service

- Recette brute = paiements confirmés des réservations rattachées aux départs de la période.
- Déduction = annulations reconnues sur ces paiements, plafonnées par paiement, y compris celles créées après la fin de période (vue accrual). Un contrôle séparé isole les annulations postérieures à la période.
- La reconnaissance et le plafonnement par paiement sont partagés avec Recettes via `PaymentRefundRecognizer` : une seule implémentation, deux lectures temporelles.

### Sources absentes : jamais de faux zéros

- Chaque poste du P&L porte un statut de source : **Disponible**, **En suspens** ou **Hors périmètre V1**.
- CA colis : hors périmètre V1, badge « Module Colis non livré ».
- RH CassKai et commissions KORA POINT : en suspens, montant affiché `—`, jamais `0`.
- Les coûts non affectés restent visibles au niveau réseau et agence ; ils ne sont jamais poussés dans la marge d'un axe opérationnel.

### TCO opérationnel

- Le TCO affiché est un **TCO opérationnel enregistré** : somme des dépenses approuvées affectées au bus (carburant, maintenance, péages, assurance, leasing, pneus, fournitures, services, autres).
- Il exclut l'acquisition, l'amortissement, le financement et la paie. Le libellé de l'écran le rappelle explicitement.

### Colis hors V1

Le module Colis n'est pas livré. Aucune recette colis n'est simulée ni extrapolée ; un ticket séparé portera l'intégration.

## Conséquences

- L'écran Rentabilité et l'écran Recettes peuvent afficher des totaux différents sur la même période : c'est attendu et expliqué par le bandeau de rapprochement.
- La qualité du résultat dépend de la discipline de saisie du centre de coût sur les dépenses. Le taux de couverture des coûts est affiché pour rendre cette dépendance visible.
- Aucune migration de données historique n'est nécessaire : les dépenses existantes sans centre de coût restent des coûts non affectés.
