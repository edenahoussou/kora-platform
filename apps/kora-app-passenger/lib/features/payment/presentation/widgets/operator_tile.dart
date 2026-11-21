import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/title_color.dart';
import '../pages/mobile_money_page.dart';
import 'operator_badge.dart';

/// La vignette d'un opérateur Mobile Money : son logo, son nom, et le
/// liseré doré quand c'est lui qu'on débitera.
///
/// Partagée par le paiement d'un billet et celui d'une expédition : le
/// geste est le même, et deux copies auraient fini par diverger à la
/// première correction.
class OperatorTile extends StatelessWidget {
  const OperatorTile({
    super.key,
    required this.network,
    required this.selected,
    required this.onTap,
  });

  final String network;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    // Deux vignettes par ligne : la place manque pour le logo, le nom
    // **et** la pastille de sélection côte à côte. Le nom descend donc
    // sous le logo, et la sélection se lit au liseré doré et au fond
    // teinté plutôt qu'à une pastille qui rognerait le nom.
    return Semantics(
      button: true,
      selected: selected,
      label: mobileMoneyLabel(network),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Container(
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.sm,
            vertical: 10,
          ),
          decoration: BoxDecoration(
            color: selected
                ? AppColors.gold.withValues(alpha: 0.12)
                : koraColors.surface,
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(
              color: selected ? AppColors.gold : koraColors.line,
              width: selected ? 2 : 1,
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              OperatorBadge(network: network),
              const SizedBox(height: 6),
              Text(
                mobileMoneyLabel(network),
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: AppTextStyles.titleMedium(context.koraTitleColor),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
