import 'package:flutter/material.dart';
import 'package:kora_ops_mobile/core/widgets/app_loading.dart';
import 'package:kora_ops_mobile/core/widgets/app_state_placeholder.dart';
import 'package:widgetbook/widgetbook.dart';

final List<WidgetbookNode> stateUseCases = <WidgetbookNode>[
  WidgetbookUseCase(
    name: 'Loading',
    builder: (BuildContext context) => const AppLoading(message: 'Chargement du manifeste…'),
  ),
  WidgetbookUseCase(
    name: 'Empty state',
    builder: (BuildContext context) =>
        const AppStatePlaceholder.empty(message: 'Aucun manifeste pour cette tournée.'),
  ),
  WidgetbookUseCase(
    name: 'Error state',
    builder: (BuildContext context) => AppStatePlaceholder.error(
      message: 'Impossible de charger le manifeste.',
      actionLabel: 'Réessayer',
      onAction: () {},
    ),
  ),
  WidgetbookUseCase(
    name: 'Success (via SnackBar)',
    builder: (BuildContext context) => Center(
      child: ElevatedButton(
        onPressed: () => ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Embarquement confirmé ✓')),
        ),
        child: const Text('Déclencher succès'),
      ),
    ),
  ),
];
