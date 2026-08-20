import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../utils/title_color.dart';

/// `.app-header` de la maquette : bouton retour, surtitre, titre d'écran et
/// action optionnelle à droite. Commun à tous les écrans empilés au-dessus
/// de la bottom nav (étapes du formulaire de recherche, liste des
/// départs...).
class ScreenHeader extends StatelessWidget {
  const ScreenHeader({
    super.key,
    required this.eyebrow,
    required this.title,
    this.action,
    this.showBack = true,
    this.leading,
  });

  /// Petite ligne au-dessus du titre (`Votre trajet`, `Août 2026`...).
  final String eyebrow;

  final String title;

  /// Bouton d'action aligné à droite du titre (`options.action` dans la
  /// maquette), par exemple « Modifier la recherche ».
  final Widget? action;

  /// Affiche le bouton retour (`options.back` de la maquette).
  ///
  /// À couper sur les écrans racines d'un onglet de la bottom nav : il n'y
  /// a rien derrière eux, un retour n'aurait nulle part où aller.
  final bool showBack;

  /// Ce qui prend la place du bouton retour quand [showBack] est faux.
  ///
  /// `back: false` dans la maquette ne retire pas le bouton, il le
  /// remplace par le logo (`<img class="brand-mini">`) — un écran racine
  /// n'a rien derrière lui, mais son en-tête ne commence pas dans le vide
  /// pour autant.
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    // Ces écrans ont le fond clair du thème, pas le navy de l'accueil : ils
    // doivent donc réclamer des icônes SOMBRES dans la barre d'état. Sans
    // cette déclaration, le style reste celui posé par le dernier écran —
    // l'accueil et son en-tête navy, qui demande des icônes blanches — et
    // l'heure et les pictogrammes système deviennent illisibles sur fond
    // clair.
    //
    // `sized: false` : la région ne couvre pas physiquement la barre
    // d'état (elle vit sous la `SafeArea`), mais elle doit quand même
    // répondre à la question « quel style au sommet de l'écran ? ». Sans
    // ça, la recherche se ferait sur la position et ne trouverait rien.
    return AnnotatedRegion<SystemUiOverlayStyle>(
      sized: false,
      value: SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: isDark ? Brightness.light : Brightness.dark,
        statusBarBrightness: isDark ? Brightness.dark : Brightness.light,
      ),
      child: Padding(
        padding: const EdgeInsets.only(top: AppSpacing.md, bottom: 15),
        child: Row(
          children: [
            if (showBack) ...[
              KoraIconButton(
                icon: Icons.chevron_left_rounded,
                onTap: () => Navigator.of(context).pop(),
              ),
              const SizedBox(width: AppSpacing.md),
            ] else if (leading != null) ...[
              leading!,
              const SizedBox(width: AppSpacing.md),
            ],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    // `.eyebrow { text-transform: uppercase }` — Flutter
                    // n'a pas d'équivalent à `text-transform`, la mise en
                    // capitales se fait donc sur la chaîne. Ici plutôt que
                    // chez chaque appelant, pour que tous les écrans
                    // s'accordent.
                    eyebrow.toUpperCase(),
                    style: AppTextStyles.eyebrow(koraColors.textMuted),
                  ),
                  const SizedBox(height: 3),
                  // `.app-header h2 { color: var(--kora) }`
                  Text(
                    title,
                    style: AppTextStyles.heading1(context.koraTitleColor),
                  ),
                ],
              ),
            ),
            if (action != null) ...[
              const SizedBox(width: AppSpacing.sm),
              action!,
            ],
          ],
        ),
      ),
    );
  }
}
