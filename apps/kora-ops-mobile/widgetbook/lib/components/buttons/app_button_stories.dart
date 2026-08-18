import 'package:flutter/material.dart';
import 'package:kora_ops_mobile/core/widgets/kora_button.dart';
import 'package:widgetbook/widgetbook.dart';

final List<WidgetbookNode> koraButtonUseCases = <WidgetbookNode>[
  WidgetbookUseCase(
    name: 'Primary (jaune orangé KORA)',
    builder: (BuildContext context) => Padding(
      padding: const EdgeInsets.all(16),
      child: KoraButton(label: 'Vérifier le code', onPressed: () {}),
    ),
  ),
  WidgetbookUseCase(
    name: 'Secondary (bleu nuit KORA)',
    builder: (BuildContext context) => Padding(
      padding: const EdgeInsets.all(16),
      child: KoraButton(
        label: 'Changer de tournée',
        variant: KoraButtonVariant.secondary,
        onPressed: () {},
      ),
    ),
  ),
  WidgetbookUseCase(
    name: 'Disabled',
    builder: (BuildContext context) => const Padding(
      padding: EdgeInsets.all(16),
      child: KoraButton(label: 'Vérifier le code', onPressed: null),
    ),
  ),
  WidgetbookUseCase(
    name: 'Loading',
    builder: (BuildContext context) => Padding(
      padding: const EdgeInsets.all(16),
      child: KoraButton(
          label: 'Vérifier le code', isLoading: true, onPressed: () {}),
    ),
  ),
  WidgetbookUseCase(
    name: 'Ghost',
    builder: (BuildContext context) => Padding(
      padding: const EdgeInsets.all(16),
      child: KoraButton(
        label: 'Ce n\'est pas moi',
        variant: KoraButtonVariant.ghost,
        onPressed: () {},
      ),
    ),
  ),
  WidgetbookUseCase(
    name: 'Danger',
    builder: (BuildContext context) => Padding(
      padding: const EdgeInsets.all(16),
      child: KoraButton(
        label: 'Refuser',
        variant: KoraButtonVariant.danger,
        onPressed: () {},
      ),
    ),
  ),
];
