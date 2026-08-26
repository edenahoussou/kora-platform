import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/widgets/brand_mini.dart';

/// En-tête bleu marine de l'accueil — équivalent `.home-hero` de la
/// maquette (`css/style.css`, L394-420 puis L1086-1112 pour la version
/// « premium » qui écrase la première).
///
/// **Les couleurs sont fixes ici** (pas de `context.koraColors`), comme
/// pour `DepartureCard` dans le design system : la maquette ne prévoit
/// aucune variante sombre pour cet en-tête, il reste navy quel que soit
/// le thème. Seule exception, en bas de ce fichier : le filet qui redonne
/// sa silhouette à la courbe basse en thème sombre, lui contextuel.
///
/// Il déborde volontairement en bas de [bottomPadding] : c'est sous cette
/// zone que la carte de recherche vient se glisser (voir `HomeSearchCard`).
class HomeHero extends StatelessWidget {
  const HomeHero({
    super.key,
    required this.greeting,
    required this.title,
    this.onNotificationsTap,
    this.bottomPadding = 82,
  });

  /// `Bonjour Aïcha,`
  final String greeting;

  /// `Votre prochain voyage commence ici.`
  final String title;

  final VoidCallback? onNotificationsTap;

  final double bottomPadding;

  @override
  Widget build(BuildContext context) {
    // L'en-tête passe sous la barre de statut système : on récupère sa
    // hauteur pour que le logo ne se retrouve pas sous l'heure du
    // téléphone. `+20` reprend l'espace de la maquette au-dessus du logo.
    final topInset = MediaQuery.paddingOf(context).top;

    // L'en-tête est navy dans les deux thèmes : la barre d'état du système
    // doit donc toujours s'afficher en clair au-dessus. Sans ça, Android
    // suit le thème de l'app et bascule ses icônes en noir en thème clair
    // — illisibles sur ce fond bleu marine.
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light, // Android
        statusBarBrightness: Brightness.dark, // iOS
      ),
      child: ClipRRect(
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(42)),
        child: DecoratedBox(
          decoration: const BoxDecoration(
            // `linear-gradient(155deg, #00143c, var(--kora))`
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF00143C), AppColors.kora],
            ),
          ),
          child: Stack(
            children: [
              // `radial-gradient(circle at 90% 12%, rgba(0,87,184,.48), transparent 30%)`
              const Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: RadialGradient(
                      center: Alignment(0.8, -0.76),
                      radius: 0.62,
                      colors: [Color(0x7A0057B8), Colors.transparent],
                    ),
                  ),
                ),
              ),
              // `.home-hero::before` — grand cercle filaire à moitié sorti
              // du cadre, à droite.
              Positioned(
                top: topInset + 22,
                right: -135,
                child: Container(
                  width: 240,
                  height: 240,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: AppColors.white.withValues(alpha: 0.09),
                    ),
                  ),
                ),
              ),
              // `.home-hero::after` — trait doré incliné, signature visuelle
              // de la marque (repris à l'identique sur la bannière Ride).
              Positioned(
                right: -32,
                bottom: 25,
                child: Transform.rotate(
                  angle: -23 * math.pi / 180,
                  child: Container(
                    width: 240,
                    height: 7,
                    decoration: BoxDecoration(
                      color: AppColors.gold,
                      borderRadius: BorderRadius.circular(AppRadius.pill),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.white.withValues(alpha: 0.12),
                          offset: const Offset(0, -13),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              Padding(
                padding: EdgeInsets.fromLTRB(
                  AppSpacing.lg + 2,
                  topInset + 20,
                  AppSpacing.lg + 2,
                  bottomPadding,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const BrandMini(),
                        NotificationButton(onTap: onNotificationsTap),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.xl),
                    Text(
                      greeting,
                      style: TextStyle(
                        color: AppColors.white.withValues(alpha: 0.6),
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    // Pas de contrainte de largeur ici (la maquette a un
                    // `max-width: 280px`) : [title] porte ses propres
                    // coupures de ligne, une largeur maximale pourrait en
                    // ajouter d'autres, non voulues, sur un écran étroit.
                    Text(
                      title,
                      style: const TextStyle(
                        color: AppColors.white,
                        fontSize: 29,
                        fontWeight: FontWeight.w800,
                        height: 1.05,
                        letterSpacing: -1.015, // -.035em × 29
                      ),
                    ),
                  ],
                ),
              ),
              // En thème sombre, le fond d'écran est ce même bleu KORA que
              // la fin du dégradé : la courbe du bas devient invisible faute
              // de contraste. Un filet, celui des cartes voisines, redonne
              // sa silhouette à l'en-tête.
              //
              // Le cadre est volontairement décalé de 2px vers le haut : son
              // arête supérieure sort ainsi de la zone rognée par le
              // `ClipRRect`, et seule la courbe du bas reste dessinée.
              if (Theme.of(context).brightness == Brightness.dark)
                Positioned(
                  top: -2,
                  left: 0,
                  right: 0,
                  bottom: 0,
                  // `IgnorePointer` : ce cadre est posé **par-dessus**
                  // tout l'en-tête, donc au-dessus de la cloche. Sans lui,
                  // il intercepte le toucher et le bouton de notifications
                  // ne répond plus — en thème sombre seulement, ce qui
                  // rend la panne d'autant plus difficile à voir.
                  child: IgnorePointer(
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        borderRadius: const BorderRadius.vertical(
                          bottom: Radius.circular(42),
                        ),
                        border: Border.all(color: context.koraColors.line),
                      ),
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
