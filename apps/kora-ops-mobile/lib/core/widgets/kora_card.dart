import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_radius.dart';
import '../../app/theme/app_spacing.dart';
import '../../app/theme/kora_colors.dart';

enum KoraCardVariant { surface, navy, gold, mist }

/// Carte unique de l'application, 4 variantes (fidèle à `kora-passager`) :
/// `surface` (fond thème, usage général), `navy` (mise en avant marine),
/// `gold` (accent), `mist` (fond neutre légèrement teinté).
class KoraCard extends StatelessWidget {
  const KoraCard({
    required this.child,
    this.variant = KoraCardVariant.surface,
    this.padding,
    this.onTap,
    super.key,
  });

  final Widget child;
  final KoraCardVariant variant;
  final EdgeInsetsGeometry? padding;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;

    final Color background = switch (variant) {
      KoraCardVariant.surface => koraColors.surface,
      KoraCardVariant.navy => AppColors.kora,
      KoraCardVariant.gold => AppColors.gold,
      KoraCardVariant.mist => AppColors.mist,
    };

    final Widget content = Container(
      width: double.infinity,
      padding: padding ?? const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: variant == KoraCardVariant.surface
            ? Border.all(color: koraColors.line)
            : null,
        boxShadow: <BoxShadow>[
          BoxShadow(
              color: koraColors.shadow,
              blurRadius: 20,
              offset: const Offset(0, 8)),
        ],
      ),
      child: child,
    );

    if (onTap == null) return content;

    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        onTap: onTap,
        child: content,
      ),
    );
  }
}
