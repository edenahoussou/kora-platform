# ADR 0017 — Filtre par entité sur l'écran Rentabilité

## Statut

Accepté — 24 février 2026

## Contexte

Les onglets d'axe de l'écran Rentabilité (Bus, Ligne, Départ, Agence, Période) ne faisaient que regrouper les cartes : impossible de restreindre tout le rapport (hero, KPIs, P&L, TCO) à une entité précise. Le détail n'existait que carte par carte dans le drawer. Un sélecteur supplémentaire a été envisagé puis écarté : l'écran possède déjà un mécanisme qui ressemble à un filtre — les cartes de contribution elles-mêmes.

## Décisions

### Le clic carte est le filtre

Cliquer une carte Bus, Ligne ou Agence restreint tout le rapport à cette entité ; les cartes Départ et Période gardent le comportement drawer. L'icône « détail » en haut à droite de chaque carte ouvre toujours le drawer. Le filtre actif apparaît sous le titre dans une pastille escamotable (« Bus · BJ 9254 RB × ») et se propage dans l'URL (`entity_type` + `entity`, uuid) — deep-linkable, retour arrière navigateur fonctionnel, export XLSX filtré et marqué.

### Filtre orthogonal à l'axe, entité unique

Le filtre survit au changement d'axe : filtré sur le bus X, l'axe Ligne liste les lignes parcourues par X. Un seul filtre à la fois : sélectionner une autre carte remplace le filtre ; cliquer la carte de l'entité déjà filtrée ouvre son détail.

### Agence = canal de vente

Le filtre agence repose sur `selling_agency_id` (qui vend) et `expenses.agency_id` (qui dépense), jamais sur un exploitant de départ : un départ n'a pas d'agence (ADR 0005, ADR 0016). Filtre agence + axe Bus = recette des ventes de l'agence par bus ; les coûts portés par l'agence non rattachables à un axe restent visibles dans les totaux mais pas sur les cartes.

### Exclusions assumées sous filtre

- **Rapprochement caisse/service masqué** : il compare les encaissements réseau, sans sens pour une entité isolée. Les autres contrôles sont recalculés sur le sous-ensemble.
- **TCO masqué sauf filtre bus** : le TCO est par construction un indicateur par bus ; sous filtre ligne ou agence il attribuerait tout le coût des bus à la vue.
- **Coûts réseau non affectés exclus** : ils n'appartiennent à aucune entité ; leur montant reste visible en note dans le hero (`networkUnassigned`).

### Fidélité de la ventilation

Sous filtre, les segments de réservation hors périmètre sont rechargés et les uuid des départs hors périmètre résolus, afin que le partage d'un paiement multi-jambes conserve sa proportionnalité réelle : la part attribuée aux départs hors vue est écartée, non reportée sur l'entité.

## Implémentation

`EntityScope` (`app/Modules/Profitability/Data`) résout `entity_type`+`entity` (uuid, `withTrashed`) ; `ProfitabilityReportRequest` valide type, format et existence ; le scope se propage à `RevenueAttribution`, `CostAttribution`, `ProfitabilityControls` et `ProfitabilityReport` (période courante et précédente). Chaque carte porte `axisType` pour déterminer sa filtrabilité côté React.
