import 'package:flutter/material.dart';
import 'package:kora_ops_mobile/app/theme/app_colors.dart';
import 'package:widgetbook/widgetbook.dart';

final WidgetbookUseCase colorsUseCase = WidgetbookUseCase(
  name: 'Colors',
  builder: (BuildContext context) {
    final Map<String, Color> swatches = <String, Color>{
      'primary (--kora, navy)': AppColors.primary,
      'accent (--gold, CTA principal)': AppColors.accent,
      'onAccent (texte sur accent/warning)': AppColors.onAccent,
      'secondary (--digital)': AppColors.secondary,
      'background (--mist)': AppColors.background,
      'backgroundDark': AppColors.backgroundDark,
      'surfaceDark': AppColors.surfaceDark,
      'textPrimary (--graphite)': AppColors.textPrimary,
      'success': AppColors.success,
      'warning (distinct de accent)': AppColors.warning,
      'error / danger': AppColors.error,
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
