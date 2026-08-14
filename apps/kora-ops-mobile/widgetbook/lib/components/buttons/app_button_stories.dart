import 'package:flutter/material.dart';
import 'package:widgetbook/widgetbook.dart';
import 'package:kora_ops_mobile/core/widgets/app_button.dart';

final List<WidgetbookNode> appButtonUseCases = <WidgetbookNode>[
  WidgetbookUseCase(
    name: 'Enabled',
    builder: (BuildContext context) => Center(
      child: AppButton(label: 'Valider', onPressed: () {}),
    ),
  ),
  WidgetbookUseCase(
    name: 'Disabled',
    builder: (BuildContext context) => const Center(
      child: AppButton(label: 'Valider', onPressed: null),
    ),
  ),
  WidgetbookUseCase(
    name: 'Loading',
    builder: (BuildContext context) => Center(
      child: AppButton(label: 'Valider', isLoading: true, onPressed: () {}),
    ),
  ),
  WidgetbookUseCase(
    name: 'Secondary',
    builder: (BuildContext context) => Center(
      child: AppButton(label: 'Annuler', variant: AppButtonVariant.secondary, onPressed: () {}),
    ),
  ),
  WidgetbookUseCase(
    name: 'Outline',
    builder: (BuildContext context) => Center(
      child: AppButton(label: 'Détails', variant: AppButtonVariant.outline, onPressed: () {}),
    ),
  ),
];
