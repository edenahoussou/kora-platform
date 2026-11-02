# Espace partenaire KORA POINT et autorisations

## Statut

Accepté — novembre 2026

## Contexte

Les comptes superviseur et trésorier d'une organisation partenaire étaient redirigés vers l'espace OPS interne, qui ne fournit pas leur parcours de travail. Une route interdite après la connexion pouvait laisser leur session active sur une erreur 403. Le rôle superviseur était aussi limité à la lecture, alors que l'organisation doit répartir son plafond et administrer le cycle de vie de ses Points.

## Décisions

### Espace web dédié

Les rôles superviseur et trésorier reçoivent un espace partenaire cloisonné à l'organisation de leur compte. Sa navigation à plat expose Tableau de bord, Points de vente, Ventes, Commissions, Remboursements, Documents, Reversements et Mon organisation. Les vues de tableau de bord, ventes/billets, commissions et remboursements réutilisent les composants KORA POINT du Point, avec agrégation côté serveur et filtre/détail par Point. Le suivi des remboursements est en lecture seule dans cet espace. L'espace n'expose pas les menus OPS sans rapport avec leur activité.

Le superviseur peut créer des Points rattachés à son organisation, modifier leurs informations opérationnelles, suspendre, réactiver ou clôturer un Point, et répartir son allocation. La création demande un nom et un e-mail de responsable et crée automatiquement le compte agent avec l'accès web initial. Le superviseur ne gère pas les comptes organisationnels ou les comptes agents déjà créés, ne modifie pas les conditions commerciales ou le plafond global, et ne transfère pas un Point.

Le superviseur et le trésorier peuvent tous deux préparer et soumettre un reversement groupé couvrant les Points de leur organisation. La preuve est requise avant soumission et Finance KORA valide le reversement ; le déclarant ne peut pas valider sa propre opération. Le trésorier seul ne gère pas les Points ; le superviseur conserve la gestion des Points et des allocations.

### Documents

Le superviseur et le trésorier peuvent déposer, consulter et télécharger les documents de leur organisation. Seuls les rôles KORA OPS autorisés peuvent les supprimer. Les Points rattachés consultent les documents de l'organisation et n'ont pas de dossier documentaire séparé.

### Accès refusé et organisations inactives

Après connexion, si l'espace partenaire ou son rattachement est absent ou non reconnu, l'application ferme la session, invalide son identifiant et redirige vers la connexion avec un toast explicite. Les refus ordinaires d'une action après l'arrivée conservent la session.

Une organisation suspendue ou clôturée ne peut ouvrir l'espace. Si elle change d'état pendant une session, la prochaine requête ferme cette session avec un message adapté. Les comptes associés ne conservent donc pas d'accès en lecture seule après suspension.

### Plafond partagé

Les allocations des Points restent soumises aux contrôles serveur transactionnels existants : elles sont positives, leur somme ne dépasse pas le plafond global, et une allocation ne descend pas sous l'encours. Une suspension conserve l'allocation ; la clôture ne la libère qu'après solde de l'encours.

Le tableau de bord organisationnel compare l'encours au total des allocations encore détenues par les Points. Le plafond global et sa part non allouée apparaissent séparément. Les états et alertes sont calculés par Point ; le réseau n'est indiqué comme entièrement bloqué que si chaque Point actif est bloqué.

## Conséquences

- Les actions et données sont contrôlées côté serveur et dans un périmètre organisationnel, en complément du menu de rôle.
- La gestion de l'organisation, de ses membres, des conditions globales, des changements de compte agent et des transferts entre organisations reste réservée à KORA OPS.
- Le portail réutilise les modèles, les règles d'allocation, le suivi d'accès initial et le service de reversement existants ; aucune API publique n'est ajoutée.
