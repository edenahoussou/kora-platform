import 'package:flutter/animation.dart';

/// Courbes d'animation du design system.
///
/// [ease] est l'unique courbe formalisée comme variable CSS partagée côté
/// web (`--ease: cubic-bezier(.2,.8,.2,1)`, `kora-ops-web/css/style.css`,
/// section `/* Fondations KORA partagées */`, L41-43). Flutter n'a pas de
/// notion native de `cubic-bezier` : [Cubic] est la classe [Curve] qui
/// reproduit exactement le même calcul avec les 4 mêmes points de contrôle.
class AppMotion {
  const AppMotion._();

  static const Curve ease = Cubic(0.2, 0.8, 0.2, 1);
}
