# Note de livraison — MEZ-70 + MEZ-77

## Contenu

- Écran OPS Web des factures fiscales (`/fiscal-invoices`) avec filtres, compteurs, détail des tentatives, relance contrôlée et exports CSV/XLSX.
- Module `Expenses` (`/expenses`) avec catégories seedées, justificatifs privés image/PDF, validation, rejet motivé, contrepassation et rapprochement optionnel aux ledgers de caisse.
- Permissions Finance dédiées et navigation Finance séparée de la vente guichet et de la caisse.
- Enregistrement append-only des tentatives e-MECeF avec payload complet chiffré et résumé assaini.

## Migration et dépendance

Les migrations sont additives : métadonnées chiffrées des tentatives fiscales, référentiel `expense_categories`, table `expenses` et lien facultatif `cash_movements.expense_id`. Les sorties historiques sont conservées. PhpSpreadsheet (`phpoffice/phpspreadsheet`) est ajouté pour l'export XLSX.

## Permissions

| Profil | Factures fiscales | Dépenses |
| --- | --- | --- |
| Agent d'agence | Aucun accès | Création et consultation de ses dossiers |
| Manager d'agence | Selon permission explicite | Consultation et validation de son agence |
| DAF | Consultation, payload fournisseur, relance | Consultation, validation et export réseau |
| Direction générale | Consultation sans relance ni payload | Consultation et export réseau |
| SystemAdmin | Tous les droits | Tous les droits |

## Vérifications

La livraison est couverte par les tests Pest ciblés des files Finance et des permissions, PHPStan, Pint, ESLint, TypeScript et le build Vite. Les gates complets restent à exécuter avant ouverture de la PR.
