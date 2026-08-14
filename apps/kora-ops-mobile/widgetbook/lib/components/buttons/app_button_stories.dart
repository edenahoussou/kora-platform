import 'package:flutter/material.dart';
import 'package:kora_ops_mobile/core/widgets/app_button.dart';
import 'package:widgetbook/widgetbook.dart';

final List<WidgetbookNode> appButtonUseCases = <WidgetbookNode>[
  WidgetbookUseCase(
    name: 'Primary (accent doré — CTA principal)',
    builder: (BuildContext context) => Padding(
      padding: const EdgeInsets.all(16),
      child: AppButton(label: 'Recevoir mon code', onPressed: () {}),
    ),
  ),
  WidgetbookUseCase(
    name: 'Disabled',
    builder: (BuildContext context) => const Padding(
      padding: EdgeInsets.all(16),
      child: AppButton(label: 'Recevoir mon code', onPressed: null),
    ),
  ),
  WidgetbookUseCase(
    name: 'Loading',
    builder: (BuildContext context) => Padding(
      padding: const EdgeInsets.all(16),
      child: AppButton(label: 'Vérifier le code', isLoading: true, onPressed: () {}),
    ),
  ),
  WidgetbookUseCase(
    name: 'Secondary',
    builder: (BuildContext context) => Padding(
      padding: const EdgeInsets.all(16),
      child: AppButton(label: 'Action secondaire', variant: AppButtonVariant.secondary, onPressed: () {}),
    ),
  ),
  WidgetbookUseCase(
    name: 'Outline',
    builder: (BuildContext context) => Padding(
      padding: const EdgeInsets.all(16),
      child: AppButton(label: 'Ce n\'est pas moi', variant: AppButtonVariant.outline, onPressed: () {}),
    ),
  ),
  WidgetbookUseCase(
    name: 'Success',
    builder: (BuildContext context) => Padding(
      padding: const EdgeInsets.all(16),
      child: AppButton(label: 'Confirmer', variant: AppButtonVariant.success, onPressed: () {}),
    ),
  ),
  WidgetbookUseCase(
    name: 'Warning',
    builder: (BuildContext context) => Padding(
      padding: const EdgeInsets.all(16),
      child: AppButton(label: 'Attention requise', variant: AppButtonVariant.warning, onPressed: () {}),
    ),
  ),
  WidgetbookUseCase(
    name: 'Danger',
    builder: (BuildContext context) => Padding(
      padding: const EdgeInsets.all(16),
      child: AppButton(label: 'Bloquer la tentative', variant: AppButtonVariant.danger, onPressed: () {}),
    ),
  ),
];
