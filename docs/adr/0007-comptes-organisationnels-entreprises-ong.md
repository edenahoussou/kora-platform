# ADR 0007 — Comptes organisationnels pour les achats Bus à crédit

## Statut

Accepté — 17 septembre 2026

## Contexte

Le parcours d’achats groupés Bus doit permettre à une entreprise, une ONG ou une ambassade de financer des billets au moyen d’un crédit mensuel. Le payeur organisationnel ne doit pas être confondu avec le voyageur, l’agent de guichet ou un compte Wallet personnel.

Le CDC prévoit également un référent unique capable de suivre les déplacements de son organisation. Le modèle MEZ-57 impose déjà un cycle actif/inactif, une limite mensuelle, des réservations de crédit et des transactions immuables.

## Décision

KORA conserve un compte organisationnel distinct des utilisateurs. Le compte est identifié par une référence métier générée automatiquement par KORA et possède les attributs légaux nécessaires à son administration.

Les utilisateurs sont rattachés au compte par une appartenance active avec un rôle `referent` ou `member`. Une seule appartenance `referent` peut être active ; le remplacement désactive l’ancien référent et conserve son historique.

La désactivation du compte est logique. Elle bloque les nouveaux achats à crédit, mais conserve la référence, le solde historique, les transactions, les réservations et les rattachements pour l’audit.

Le plafond mensuel ne peut pas être abaissé sous les débits confirmés du mois civil courant. Le crédit disponible est calculé côté serveur à partir du plafond, des débits, remboursements et réservations actives.

## Conséquences

- Les identifiants internes numériques restent confinés au backend ; les interfaces exposent les UUID et la référence métier.
- Les permissions `company-accounts.manage` et `company-credit.view` contrôlent respectivement la gestion et la visibilité des données financières.
- La génération de factures mensuelles et les exports de déplacements restent des capacités séparées, à spécifier dans un ticket ultérieur.
