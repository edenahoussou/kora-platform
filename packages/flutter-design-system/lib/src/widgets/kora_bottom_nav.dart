import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';
import '../icons/kora_nav_icon.dart';

/// Barre de navigation flottante en bas de l'écran, avec coins très
/// arrondis en bas (24/24/34/34) et effet de flou, comme `.bottom-nav`
/// dans le prototype. Les 5 onglets et leurs icônes (via [KoraNavIcon])
/// sont fidèles à `KORA/design/js/app.js` (fonction `nav`) et
/// `KORA/design/css/style.css` (`.nav-home`, `.nav-ticket`, `.nav-parcel`,
/// `.nav-wallet`, `.nav-profile`).
///
/// Exemple d'utilisation :
/// ```dart
/// KoraBottomNav(
///   currentIndex: 0,
///   onTap: (index) => setState(() => _index = index),
/// )
/// ```
class KoraBottomNav extends StatelessWidget {
  const KoraBottomNav({
    super.key,
    required this.currentIndex,
    required this.onTap,
  });

  final int currentIndex;
  final ValueChanged<int> onTap;

  static const _tabs = [
    (icon: KoraNavIconType.home, label: 'Accueil'),
    (icon: KoraNavIconType.tickets, label: 'Voyages'),
    (icon: KoraNavIconType.parcel, label: 'Colis'),
    (icon: KoraNavIconType.wallet, label: 'Wallet'),
    (icon: KoraNavIconType.profile, label: 'Profil'),
  ];

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      height: 76,
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 8),
      decoration: BoxDecoration(
        color: koraColors.surface.withValues(alpha: 0.96),
        borderRadius: const BorderRadius.only(
          topLeft: Radius.circular(24),
          topRight: Radius.circular(24),
          bottomLeft: Radius.circular(34),
          bottomRight: Radius.circular(34),
        ),
        boxShadow: koraColors.cardShadow,
      ),
      child: Row(
        children: List.generate(_tabs.length, (index) {
          final tab = _tabs[index];
          final isActive = index == currentIndex;
          final color = isActive ? AppColors.gold : koraColors.textMuted;

          return Expanded(
            child: InkWell(
              onTap: () => onTap(index),
              borderRadius: BorderRadius.circular(AppRadius.md),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (isActive)
                    Container(
                      width: 25,
                      height: 3,
                      margin: const EdgeInsets.only(bottom: 6),
                      decoration: BoxDecoration(
                        color: AppColors.gold,
                        borderRadius: BorderRadius.circular(4),
                      ),
                    ),
                  KoraNavIcon(type: tab.icon, color: color, size: 20),
                  const SizedBox(height: 4),
                  Text(
                    tab.label,
                    style: TextStyle(
                      fontSize: 9,
                      fontWeight: FontWeight.w700,
                      color: isActive ? AppColors.kora : color,
                    ),
                  ),
                ],
              ),
            ),
          );
        }),
      ),
    );
  }
}
