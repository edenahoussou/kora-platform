import 'package:flutter/material.dart';
import 'package:kora_ops_mobile/core/widgets/app_text_field.dart';
import 'package:widgetbook/widgetbook.dart';

final List<WidgetbookNode> appTextFieldUseCases = <WidgetbookNode>[
  WidgetbookUseCase(
    name: 'Enabled',
    builder: (BuildContext context) => const Padding(
      padding: EdgeInsets.all(16),
      child: AppTextField(label: 'Identifiant'),
    ),
  ),
  WidgetbookUseCase(
    name: 'Error',
    builder: (BuildContext context) => const Padding(
      padding: EdgeInsets.all(16),
      child: AppTextField(label: 'Identifiant', errorText: 'Champ requis'),
    ),
  ),
  WidgetbookUseCase(
    name: 'Obscured (mot de passe)',
    builder: (BuildContext context) => const Padding(
      padding: EdgeInsets.all(16),
      child: AppTextField(label: 'Mot de passe', obscureText: true),
    ),
  ),
];
