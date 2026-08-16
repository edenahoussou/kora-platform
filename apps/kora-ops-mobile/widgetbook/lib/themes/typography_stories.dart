import 'package:flutter/material.dart';
import 'package:kora_ops_mobile/app/theme/app_colors.dart';
import 'package:kora_ops_mobile/app/theme/app_text_styles.dart';
import 'package:widgetbook/widgetbook.dart';

final WidgetbookUseCase typographyUseCase = WidgetbookUseCase(
  name: 'Typography',
  builder: (BuildContext context) => ListView(
    padding: const EdgeInsets.all(16),
    children: <Widget>[
      Text('Heading 1', style: AppTextStyles.heading1(AppColors.kora)),
      const SizedBox(height: 12),
      Text('Heading 2', style: AppTextStyles.heading2(AppColors.kora)),
      const SizedBox(height: 12),
      Text('EYEBROW', style: AppTextStyles.eyebrow(AppColors.kora)),
      const SizedBox(height: 12),
      Text('Title Medium', style: AppTextStyles.titleMedium(AppColors.kora)),
      const SizedBox(height: 12),
      Text('Lead', style: AppTextStyles.lead(AppColors.kora)),
      const SizedBox(height: 12),
      Text('Body', style: AppTextStyles.body(AppColors.kora)),
      const SizedBox(height: 12),
      Text('Caption', style: AppTextStyles.caption(AppColors.kora)),
    ],
  ),
);
