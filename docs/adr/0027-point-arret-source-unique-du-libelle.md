# Le point d'arrêt est la source unique du libellé

Le studio de lignes permettait de renommer un arrêt « pour cette ligne uniquement », et le rattachement automatique d'un point placé sur la carte (rayon de 300 m) ignorait les noms. Les arrêts de ligne (`stops`) et les points d'arrêt (`physical_stops`) affichaient donc des noms différents pour un même lieu, et l'application passager montrait des doublons.

Décision : le point d'arrêt (`PhysicalStop`) porte seul le nom, la ville, le pays et la position GPS. Un arrêt de ligne (`Stop`) n'a pas de libellé propre ; ses colonnes `name`, `city`, `country`, `latitude` et `longitude` sont une copie recopiée depuis le point d'arrêt à chaque écriture, et à chaque modification du point d'arrêt. Pas d'alias par ligne.

Alternative écartée : un alias facultatif par ligne affiché en complément du nom officiel. Il gardait l'ambiguïté pour le voyageur et pour la recherche.

## Conséquences

- Un point placé à moins de 300 m d'un point existant de nom différent est refusé : l'opérateur doit sélectionner le point existant ou éloigner le marqueur.
- Une nouvelle ville reprend l'orthographe existante lorsqu'elle ne diffère que par la casse, les accents ou les tirets.
- Un point d'arrêt qui n'est plus référencé par aucun arrêt de ligne est archivé et n'apparaît plus dans les sélecteurs.
- `php artisan bus:realign-stop-labels` liste les écarts existants (mode simulation par défaut) et les corrige avec `--apply`.
- Étape suivante : lire le libellé via le point d'arrêt partout, puis supprimer les colonnes copiées de `stops`.
