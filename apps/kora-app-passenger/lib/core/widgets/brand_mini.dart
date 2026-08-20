import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

/// `.brand-mini` : le logo KORA dans sa pastille blanche, en tête des
/// écrans racines (accueil, mes voyages).
///
/// Pastille **blanche dans les deux thèmes** : c'est le fond réservé du
/// logo, pas une surface d'écran. Le logo lui-même est bleu et doré, il
/// disparaîtrait sur un fond sombre.
class BrandMini extends StatelessWidget {
  const BrandMini({super.key, this.width = 102, this.height = 42});

  final double width;
  final double height;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppRadius.base),
      ),
      // `object-fit: cover` comme dans le CSS, et non `contain` : le PNG
      // du logo est presque carré avec une large marge blanche autour du
      // dessin. En `contain`, c'est cette marge qu'on met à l'échelle, et
      // le logo apparaît minuscule dans sa pastille. En `cover`, la marge
      // est rognée en haut et en bas et le logo occupe toute la largeur,
      // exactement comme sur la maquette.
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: Image.asset('assets/images/kora_logo.png', fit: BoxFit.cover),
      ),
    );
  }
}

/// `.notification-button` : rond blanc, cloche bleue et pastille dorée
/// « non lu ».
///
/// Volontairement pas un [KoraIconButton] : celui-ci prend la couleur de
/// surface du thème (bleu très sombre en mode sombre), qui disparaîtrait
/// sur les fonds navy — celui de l'en-tête d'accueil comme celui de
/// l'écran en thème sombre. Ce bouton reste blanc dans les deux thèmes,
/// comme le logo à côté duquel il se tient.
class NotificationButton extends StatelessWidget {
  const NotificationButton({super.key, this.onTap, this.hasUnread = true});

  final VoidCallback? onTap;

  /// Affiche la pastille dorée. À passer à `false` quand tout est lu.
  final bool hasUnread;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 42,
      height: 42,
      child: Material(
        color: AppColors.white,
        shape: const CircleBorder(),
        child: InkWell(
          onTap: onTap,
          customBorder: const CircleBorder(),
          child: Stack(
            children: [
              const Center(
                child: Icon(
                  Icons.notifications_none_rounded,
                  size: 21,
                  color: AppColors.kora,
                ),
              ),
              if (hasUnread)
                Positioned(
                  right: 9,
                  top: 9,
                  child: Container(
                    width: 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: AppColors.gold,
                      shape: BoxShape.circle,
                      border: Border.all(color: AppColors.white, width: 1.5),
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
