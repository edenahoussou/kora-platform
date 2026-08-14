import 'package:flutter/material.dart';
import 'package:widgetbook/widgetbook.dart';
import 'package:kora_ops_mobile/app/theme/app_typography.dart';

final WidgetbookUseCase typographyUseCase = WidgetbookUseCase(
  name: 'Typography',
  builder: (BuildContext context) => ListView(
    padding: const EdgeInsets.all(16),
    children: <Widget>[
      Text('Display Large', style: AppTypography.displayLarge),
      const SizedBox(height: 12),
      Text('Display Medium', style: AppTypography.displayMedium),
      const SizedBox(height: 12),
      Text('Headline', style: AppTypography.headline),
      const SizedBox(height: 12),
      Text('Title Medium', style: AppTypography.titleMedium),
      const SizedBox(height: 12),
      Text('Body Large', style: AppTypography.bodyLarge),
      const SizedBox(height: 12),
      Text('Body Medium', style: AppTypography.bodyMedium),
      const SizedBox(height: 12),
      Text('Caption', style: AppTypography.caption),
    ],
  ),
);
