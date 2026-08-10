# KORA OPS Web avec Laravel React Inertia

KORA OPS Web est construit avec le starter officiel Laravel React/Inertia dans le meme projet Laravel que l'API. Ce choix garde l'authentification, les permissions, les actions operationnelles et les ecrans ERP proches du serveur, tout en livrant une interface React/TypeScript moderne.

## Consequences

Les controleurs Inertia servent uniquement a recevoir la requete, appeler les services et retourner les pages. Les validations sont dans les Form Requests, les autorisations dans les policies ou gates, et les effets externes critiques passent par jobs, evenements ou outbox selon leur nature.

