---
paths:
    - 'app/**'
    - 'config/**'
    - 'database/**'
    - 'routes/**'
    - 'tests/**'
    - 'resources/**'
---

# Commentaires et PHPDoc

Pas de commentaires superflus : le code doit rester clair et lisible par lui-même (noms explicites, fonctions courtes). Un commentaire inline n'est justifié que pour une logique vraiment non évidente.

Les PHPDoc sont bienvenus sur les classes et les méthodes publiques, mais doivent rester courts : une ou deux lignes d'intention, plus les annotations qui précisent ce que la signature ne dit pas (`@param` array shapes, `@return`, `@throws`).

## Créer les fichiers via les commandes Artisan

Toujours créer les fichiers PHP via `php artisan make:*` (make:controller, make:request, make:model, make:migration, make:factory, make:seeder, make:notification, make:listener, make:middleware, make:policy, make:test, make:class, etc.) plutôt que d'écrire les fichiers directement. Ne générer un fichier à la main que si aucune commande Artisan ne couvre le type visé (classes métiers → make:class).

## Synchroniser les migrations et les permissions
Après ajout de migrations ou modification des rôles/permissions, appliquer les migrations locales puis relancer KoraBusRolePermissionSeeder pour synchroniser Spatie et vérifier les droits effectifs. Pour initialiser entièrement une base locale, utiliser `php artisan migrate --seed --no-interaction`; les migrations seules ne créent ni n’attribuent les permissions.
