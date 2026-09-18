# ADR 0010 — Finance : factures fiscales et dépenses

## Statut

Accepté — 18 septembre 2026

## Contexte

MEZ-70 et MEZ-77 ajoutent au même OPS Web une supervision des factures fiscales et un suivi des dépenses opérationnelles. Les deux flux ont des exigences d'audit et de périmètre différentes : une facture appartient au module Fiscalization et une sortie métier ne doit pas être confondue avec un mouvement de caisse historique.

## Décisions

### Factures fiscales

- `FiscalInvoice` reste l'agrégat propriétaire du statut et des transitions `pending`, `certified`, `failed` et `canceled`.
- `fiscal_sync_attempts` est append-only par opération et numéro de tentative. Chaque tentative conserve un résumé assaini et, pour la durée de vie de la facture, le payload fournisseur chiffré après suppression des secrets d'authentification.
- La relance est une transition atomique `failed → pending`, protégée par `FiscalInvoicePolicy`, verrouillage SQL et job unique par facture. Les états certifié et annulé sont terminaux.
- Les compteurs, filtres et exports passent par `FiscalInvoiceQuery`. Les réponses Inertia et les exports n'exposent que des UUID, références métier, UID MECeF et messages utiles ; le payload complet n'est visible qu'avec `fiscalization.payload.view`.

### Dépenses et caisse

- `Expense` est un module métier profond séparé de `CashMovement`. Il porte une référence `DEP-YYYYMMDD-XXXX`, une agence, une catégorie seedée, un montant entier en XOF, un moyen de paiement et un justificatif privé.
- Les états sont `pending → approved`, `pending → rejected` et `approved → reversed`. Une dépense approuvée est immuable ; toute correction publiée passe par une contrepassation auditée avec motif.
- La création peut référencer une session et un ledger. Lorsqu'un ledger est fourni, son verrouillage, la vérification du solde, la création de la sortie et la publication de la dépense sont atomiques. Une dépense sans ledger est explicitement « à rapprocher » et ne change aucun solde.
- Les sorties historiques de `cash_movements` sont conservées. Les requêtes Finance combinent les dépenses et ces sorties sans compter deux fois un mouvement lié à une dépense.

### Autorisation et exports

- Les permissions Finance sont explicites et indépendantes de `bookings.view`, `cash.view` et des permissions de vente. Les agents créent et voient leurs dossiers ; les managers valident leur agence ; la DAF valide et supervise le réseau ; la Direction générale consulte ; SystemAdmin dispose de tous les droits.
- Les exports CSV/XLSX réutilisent une interface commune, appliquent le même périmètre et les mêmes filtres que l'écran, neutralisent les formules et n'exportent jamais le payload e-MECeF complet. Les exports sensibles sont journalisés.

## Conséquences

Cette séparation permet de réutiliser les jobs e-MECeF et la comptabilité de caisse sans dupliquer leurs écritures, tout en gardant une piste d'audit lisible et des contrôles d'accès indépendants. PhpSpreadsheet devient une dépendance de production pour l'export XLSX. Les anciennes réponses fiscales sont réduites à un résumé assaini lors de la migration additive ; aucune donnée financière existante n'est supprimée ou reclassifiée automatiquement.
