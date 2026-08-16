import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../app/theme/app_spacing.dart';
import '../../app/theme/app_text_styles.dart';
import '../../app/theme/kora_colors.dart';
import 'kora_icon_button.dart';

/// En-tête commun à tous les écrans empilés au-dessus de la bottom nav
/// (OTP, détail passager, scan...) — un **vrai** `AppBar`
/// (`PreferredSizeWidget`, à passer en `Scaffold(appBar: ...)`), pas un
/// widget de body : bouton retour, surtitre, titre, action optionnelle à
/// droite.
class ScreenHeader extends StatelessWidget implements PreferredSizeWidget {
  const ScreenHeader(
      {required this.eyebrow,
      required this.title,
      this.action,
      this.onBack,
      super.key});

  /// Petite ligne au-dessus du titre ("Sécurité", "Manifeste"...).
  final String eyebrow;

  final String title;

  /// Bouton d'action aligné à droite.
  final Widget? action;

  final VoidCallback? onBack;

  @override
  Size get preferredSize => const Size.fromHeight(72);

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;

    return AppBar(
      backgroundColor: koraColors.background,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      toolbarHeight: preferredSize.height,
      leadingWidth: 64,
      leading: Padding(
        padding: const EdgeInsets.only(left: AppSpacing.md),
        child: KoraIconButton(
          icon: Icons.chevron_left_rounded,
          onTap: onBack ?? () => context.canPop() ? context.pop() : null,
        ),
      ),
      titleSpacing: 0,
      title: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          Text(eyebrow.toUpperCase(),
              style: AppTextStyles.eyebrow(koraColors.textMuted)),
          const SizedBox(height: 2),
          Text(title,
              style: AppTextStyles.heading2(koraColors.textPrimary),
              overflow: TextOverflow.ellipsis),
        ],
      ),
      actions: action != null
          ? <Widget>[
              Padding(
                  padding: const EdgeInsets.only(right: AppSpacing.md),
                  child: action!)
            ]
          : null,
    );
  }
}
