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
