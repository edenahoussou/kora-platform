import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_radius.dart';
import '../../app/theme/app_spacing.dart';
import '../../app/theme/app_text_styles.dart';
import '../../app/theme/kora_colors.dart';
import 'notification_button.dart';

/// En-tête unifié des écrans principaux (onglets de la bottom nav) — logo
/// KORA à gauche, surtitre + titre empilés, icône de notification à
/// droite. Fidèle trait pour trait à la référence fournie (voir échange du
/// 16/08/2026) : « VOS BILLETS » / « Mes voyages » côté passager,
/// équivalent OPS par écran (voir document.md § Navigation).
///
/// Distinct de [ScreenHeader] (bouton retour, pour les écrans empilés) :
/// celui-ci n'a pas de navigation retour, il est la racine visuelle de
/// chaque onglet.
class MainAppBar extends StatelessWidget implements PreferredSizeWidget {
  const MainAppBar(
      {required this.eyebrow,
      required this.title,
      this.extraActions,
      super.key});

  final String eyebrow;
  final String title;

  /// Actions spécifiques à l'écran (ex. torche sur Scan boarding),
  /// insérées avant [NotificationButton].
  final List<Widget>? extraActions;

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
      automaticallyImplyLeading: false,
      toolbarHeight: preferredSize.height,
      titleSpacing: AppSpacing.md,
      title: Row(
        children: <Widget>[
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            decoration: BoxDecoration(
                color: AppColors.white,
                borderRadius: BorderRadius.circular(AppRadius.md)),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(AppRadius.sm),
              child: Image.asset('assets/images/kora_logo.png',
                  height: 22, fit: BoxFit.contain),
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Text(eyebrow.toUpperCase(),
                    style: AppTextStyles.eyebrow(koraColors.textMuted)),
                Text(
                  title,
                  style: AppTextStyles.heading2(koraColors.textPrimary),
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
      actions: <Widget>[
        ...?extraActions,
        const Padding(
            padding: EdgeInsets.only(right: AppSpacing.md),
            child: NotificationButton()),
      ],
    );
  }
}
