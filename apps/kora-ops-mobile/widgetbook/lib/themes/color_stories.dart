import 'package:flutter/material.dart';
import 'package:kora_ops_mobile/app/theme/app_colors.dart';
import 'package:widgetbook/widgetbook.dart';

final WidgetbookUseCase colorsUseCase = WidgetbookUseCase(
  name: 'Colors',
  builder: (BuildContext context) {
    final Map<String, Color> swatches = <String, Color>{
      'kora (navy)': AppColors.kora,
      'gold (CTA principal)': AppColors.gold,
      'onGold (texte sur gold/warning)': AppColors.onGold,
      'digital (secondaire)': AppColors.digital,
      'mist (fond clair)': AppColors.mist,
      'success': AppColors.success,
      'warning (distinct de gold)': AppColors.warning,
      'danger': AppColors.danger,
      'info': AppColors.info,
    };

    return ListView(
      padding: const EdgeInsets.all(16),
      children: swatches.entries
          .map(
            (MapEntry<String, Color> entry) => ListTile(
              leading: Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: entry.value,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.black12),
                ),
              ),
              title: Text(entry.key),
              subtitle: Text(
                '#${entry.value.toARGB32().toRadixString(16).padLeft(8, '0').toUpperCase()}',
              ),
            ),
          )
          .toList(),
    );
  },
);
