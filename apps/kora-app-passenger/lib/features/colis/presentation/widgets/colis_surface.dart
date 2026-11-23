import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/title_color.dart';

/// L'ombre des cartes Colis, reprise de la maquette.
///
/// La maquette ne borde pas ses cartes : elle les pose sur le fond mist
/// avec une ombre très douce, et garde les traits pour les séparateurs
/// internes. Une bordure **et** un fond clair donnaient deux signaux
/// pour la même chose, et les deux conventions voisinaient à l'écran —
/// c'est ce mélange que cette constante supprime.
///
/// Équivalent de `shadow-sm` dans la maquette, teinté marine plutôt que
/// noir : une ombre grise sur un fond bleuté vire au sale.
const List<BoxShadow> kColisCardShadow = [
  BoxShadow(color: Color(0x0F001D4E), blurRadius: 10, offset: Offset(0, 2)),
];

/// Le fond d'un bloc encastré dans une carte Colis.
///
/// La maquette emboîte : carte blanche, et à l'intérieur des blocs gris
/// sans bordure — un champ, un rappel, une ligne de total. C'est ce qui
/// donne la profondeur, là où l'app empilait des encadrés de même
/// couleur séparés par des traits.
///
/// Exemple :
///
/// ```dart
/// Container(
///   padding: const EdgeInsets.all(14),
///   decoration: colisInsetDecoration(context),
///   child: ...,
/// )
/// ```
BoxDecoration colisInsetDecoration(BuildContext context) => BoxDecoration(
  color: context.koraColors.background,
  // Plus serré que la carte qui le porte : la maquette emboîte un
  // `rounded-xl` dans un `rounded-2xl`, et c'est ce décalage qui donne
  // la profondeur. Deux arrondis égaux faisaient un bloc plat.
  borderRadius: BorderRadius.circular(AppRadius.base),
);

/// Une note informative, telle que la maquette la construit.
///
/// Carte blanche, et à l'intérieur un encart gris portant l'icône et le
/// texte. Les blocs colorés — sable, rouge — restent réservés à ce qui
/// avertit vraiment : un tarif provisoire, un délai qui court, un
/// paiement à ne pas relancer.
///
/// L'app employait `KoraAlert` partout, pastille dorée comprise : tout
/// prenait l'allure d'un avertissement, et plus rien ne se distinguait
/// de ce qui en était un.
class ColisNote extends StatelessWidget {
  const ColisNote({
    super.key,
    required this.icon,
    required this.title,
    this.message,
  });

  final IconData icon;

  /// Ce qu'il faut retenir. Rendu en gras, en tête de la note.
  final String title;

  /// Le détail, facultatif : une note qui se suffit à son titre n'a pas
  /// à s'allonger.
  final String? message;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
      ),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: colisInsetDecoration(context),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.only(top: 1),
              child: Icon(icon, size: 18, color: AppColors.kora),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 13,
                      height: 1.35,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  if (message case final message?) ...[
                    const SizedBox(height: 3),
                    Text(
                      message,
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 12,
                        height: 1.45,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Une action sur fond blanc : picto, libellé, ombre douce.
///
/// La maquette ne met qu'une action en doré par écran. Celles qui
/// viennent après se tiennent sur le blanc, à plat — un second pavé
/// marine pleine largeur leur donnait le même poids qu'à la première.
class ColisFlatAction extends StatelessWidget {
  const ColisFlatAction({
    super.key,
    required this.label,
    required this.icon,
    required this.onTap,
    this.tint,
    this.iconTint,
    this.background,
    this.height = 48,
  });

  final String label;
  final IconData icon;
  final VoidCallback onTap;

  /// La couleur du libellé. Le titre par défaut ; le texte atténué pour
  /// une action qui fait reculer.
  final Color? tint;

  /// La couleur du pictogramme, quand elle diffère du libellé — le bleu
  /// digital d'un suivi, par exemple.
  final Color? iconTint;

  /// Le fond. Blanc par défaut, avec l'ombre des cartes ; gris quand
  /// l'action est posée dans une carte, où une seconde ombre ferait
  /// deux reliefs l'un sur l'autre.
  final Color? background;

  final double height;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final tint = this.tint ?? context.koraTitleColor;
    final onSurface = background == null || background == koraColors.surface;

    return Material(
      color: background ?? koraColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Container(
          height: height,
          alignment: Alignment.center,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.md),
            boxShadow: onSurface ? kColisCardShadow : null,
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 18, color: iconTint ?? tint),
              const SizedBox(width: 7),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: tint,
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
