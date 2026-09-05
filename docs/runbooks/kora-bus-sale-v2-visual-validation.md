# Validation visuelle — Guichet KORA BUS v2

Ce guide couvre le parcours isolé `/sale-v2` et ses quatre scénarios métier :

- aller simple direct ;
- groupe de 5 à 10 voyageurs ;
- aller-retour avec un devis global et un paiement unique ;
- itinéraire avec correspondance sûre d'au moins 45 minutes.

## Préparer les données locales

Depuis `apps/kora-ops-web` :

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=KoraBusSaleScenarioSeeder
```

Le seeder est rejouable. Il crée ou réutilise les lignes, points d'arrêt physiques, arrêts de ligne, bus, sièges, départs, tarifs, utilisateurs de démonstration et comptes entreprise.

## Ouvrir le guichet

```bash
composer dev
```

Se connecter avec un agent de démonstration, puis ouvrir la route nommée `sale-v2`. Choisir les points d'arrêt physiques, la date et le nombre de voyageurs.

## Parcours à vérifier

1. **Direct** : sélectionner un itinéraire aller, choisir un siège par voyageur, vérifier le devis puis créer le blocage.
2. **Groupe** : utiliser 5 à 10 voyageurs et vérifier que la remise est appliquée une seule fois au dossier global.
3. **Entreprise** : choisir un compte avec crédit suffisant, puis vérifier le snapshot du payeur et le débit du compte.
4. **Aller-retour** : sélectionner une option aller et une option retour ; chaque voyageur doit avoir un siège pour chaque tronçon, avec un total et une seule réservation.
5. **Correspondance** : sélectionner une option multi-tronçons ; une correspondance inférieure à 45 minutes ne doit pas apparaître dans les résultats.
6. **Expiration** : attendre ou simuler l'expiration du devis/blocage ; les sièges doivent redevenir disponibles et le parcours doit afficher une action de reprise.

## Stories Storybook

```bash
npm run storybook
```

La story `Vente guichet v2 / états métier` expose `OneWay`, `RoundTrip` et `GroupWithCompanyPayer` pour une revue rapide de la hiérarchie visuelle, des montants et des états de sélection.
