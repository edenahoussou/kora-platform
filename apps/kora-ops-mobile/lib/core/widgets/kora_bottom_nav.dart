import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_radius.dart';
import '../../app/theme/kora_colors.dart';

class KoraBottomNavItem {
  const KoraBottomNavItem(
      {required this.icon, required this.activeIcon, required this.label});

  final IconData icon;
  final IconData activeIcon;
  final String label;
}

/// Barre de navigation flottante à 5 onglets — Accueil / Manifeste / Scan
/// boarding / Descente anticipée / Synchro. Fond clair flottant, icône +
/// libellé actifs en sombre/gras avec un fond doré discret, inactifs en
/// gris — fidèle trait pour trait à la bottom nav de `kora-passager`.
class KoraBottomNav extends StatelessWidget {
  const KoraBottomNav(
      {required this.currentIndex, required this.onTap, super.key});

  final int currentIndex;
  final ValueChanged<int> onTap;

  static const List<KoraBottomNavItem> items = <KoraBottomNavItem>[
    KoraBottomNavItem(
        icon: Icons.home_outlined,
        activeIcon: Icons.home_rounded,
        label: 'Accueil'),
    KoraBottomNavItem(
        icon: Icons.list_alt_outlined,
        activeIcon: Icons.list_alt,
        label: 'Manifeste'),
    KoraBottomNavItem(
      icon: Icons.qr_code_scanner_outlined,
      activeIcon: Icons.qr_code_scanner,
      label: 'Scan',
    ),
    KoraBottomNavItem(
        icon: Icons.move_down_outlined,
        activeIcon: Icons.move_down,
        label: 'Descente'),
    KoraBottomNavItem(
        icon: Icons.sync_outlined, activeIcon: Icons.sync, label: 'Synchro'),
  ];

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;

    return DecoratedBox(
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.xl),
        boxShadow: <BoxShadow>[
          BoxShadow(
              color: koraColors.shadow,
              blurRadius: 24,
              offset: const Offset(0, 10)),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: List<Widget>.generate(items.length, (int index) {
              final bool isActive = index == currentIndex;
              final KoraBottomNavItem item = items[index];
              final Color activeColor = koraColors.textPrimary;
              final Color inactiveColor = koraColors.textMuted;

              return Expanded(
                child: InkWell(
                  borderRadius: BorderRadius.circular(AppRadius.lg),
                  onTap: () => onTap(index),
                  child: Padding(
                    padding:
                        const EdgeInsets.symmetric(vertical: 6, horizontal: 2),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          padding: const EdgeInsets.all(6),
                          decoration: BoxDecoration(
                            color: isActive
                                ? AppColors.gold.withValues(alpha: 0.15)
                                : Colors.transparent,
                            borderRadius: BorderRadius.circular(AppRadius.md),
                          ),
                          child: Icon(
                            isActive ? item.activeIcon : item.icon,
                            color: isActive ? AppColors.gold : inactiveColor,
                            size: 22,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          item.label,
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight:
                                isActive ? FontWeight.w700 : FontWeight.w500,
                            color: isActive ? activeColor : inactiveColor,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }),
          ),
        ),
      ),
    );
  }
}
