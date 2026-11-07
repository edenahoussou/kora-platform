# Visites terrain et PLV sur KORA POINT

## Statut

Accepté — novembre 2026

## Contexte

La maquette KORA POINT prévoit le suivi des visites et des supports de publicité sur le lieu de vente (PLV). Les visites doivent être saisies par la Direction KORA au Point visité. Les organisations partenaires et les agents concernés ont besoin de vues adaptées à leurs responsabilités sans étendre leurs droits de gestion.

## Décisions

### Saisie et consultation des visites

- La visite est créée dans la fiche du Point par un utilisateur interne disposant de `kora-points.manage`.
- Les trois types sont fixes : Visite qualité, Suivi commercial et Contrôle PLV. Tous exigent une note entière entre 0 et 10 et un compte rendu. Le contrôle PLV n'ajoute pas de checklist.
- Le compte rendu et les informations de visite sont immuables après leur enregistrement. Les photos facultatives sont privées, limitées à cinq fichiers JPEG, PNG ou WebP de 10 Mo maximum chacun.
- La fiche d'une organisation KORA OPS agrège les visites de ses Points en lecture seule. L'espace partenaire dispose d'une vue consolidée et d'un filtre Point ; chaque fiche partenaire d'un Point affiche uniquement ses visites. Les photos et comptes rendus sont servis par des routes qui vérifient le périmètre de la visite.

### Actions de suivi

- Le créateur peut joindre zéro ou plusieurs actions à la visite. Chaque action contient un libellé, un responsable actif — utilisateur interne KORA ou agent de l'agence du Point — et une échéance.
- Le responsable marque l'action comme réalisée avec une note de réalisation. Un utilisateur interne KORA valide l'action ou la renvoie avec un motif obligatoire.
- Un agent de Point reçoit uniquement la permission `kora-points.visits.actions.update`, et uniquement pour modifier l'état de réalisation de ses propres actions. Il ne peut ni créer ni valider une visite ou une commande PLV.
- Une visite sans action est clôturée lors de son enregistrement. Une visite avec actions reste en cours jusqu'à validation KORA de toutes les actions.

### Commandes PLV

- Une commande est créée au niveau d'un Point par KORA. Elle porte un support parmi Affiches A3, Autocollants, Bâche et Panneau, ainsi qu'une quantité.
- Son cycle est Préparée, Livrée, Installée. La date de livraison correspond à la transition vers Livrée. KORA peut annuler une commande préparée ou livrée avec un motif conservé.
- L'espace partenaire agrège les commandes de l'organisation et chaque fiche Point les filtre sur le Point courant, en lecture seule. La V1 ne gère ni prix, ni stock, ni API REST.

## Conséquences

- Les mutations terrain et PLV restent réservées aux utilisateurs internes autorisés, à l'exception du signalement d'une action affectée à l'agent concerné.
- Les pages internes, les pages partenaires et les pages agent exposent des UUID uniquement. Les photos ne sont jamais servies depuis un espace public.
- Les droits de lecture partenaire restent cloisonnés à l'organisation et aux Points qui lui sont rattachés.
