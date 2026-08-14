import 'package:flutter/material.dart';
import 'package:widgetbook/widgetbook.dart';
import 'package:kora_ops_mobile/app/theme/app_colors.dart';

final WidgetbookUseCase colorsUseCase = WidgetbookUseCase(
  name: 'Colors',
  builder: (BuildContext context) {
    final Map<String, Color> swatches = <String, Color>{
      'primary (--kora)': AppColors.primary,
      'accent (--gold)': AppColors.accent,
      'secondary (--digital)': AppColors.secondary,
      'background (--mist)': AppColors.background,
      'textPrimary (--graphite)': AppColors.textPrimary,
      'success': AppColors.success,
      'warning': AppColors.warning,
      'error': AppColors.error,
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
              subtitle: Text('#${entry.value.value.toRadixString(16).padLeft(8, '0').toUpperCase()}'),
            ),
          )
          .toList(),
    );
  },
);
