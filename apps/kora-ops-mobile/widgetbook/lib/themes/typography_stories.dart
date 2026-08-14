import 'package:flutter/material.dart';
import 'package:kora_ops_mobile/app/theme/app_typography.dart';
import 'package:widgetbook/widgetbook.dart';

final WidgetbookUseCase typographyUseCase = WidgetbookUseCase(
  name: 'Typography',
  builder: (BuildContext context) => ListView(
    padding: const EdgeInsets.all(16),
    children: const <Widget>[
      Text('Display Large', style: AppTypography.displayLarge),
      SizedBox(height: 12),
      Text('Display Medium', style: AppTypography.displayMedium),
      SizedBox(height: 12),
      Text('Headline', style: AppTypography.headline),
      SizedBox(height: 12),
      Text('Title Medium', style: AppTypography.titleMedium),
      SizedBox(height: 12),
      Text('Body Large', style: AppTypography.bodyLarge),
      SizedBox(height: 12),
      Text('Body Medium', style: AppTypography.bodyMedium),
      SizedBox(height: 12),
      Text('Caption', style: AppTypography.caption),
    ],
  ),
);
