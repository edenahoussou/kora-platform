import 'package:flutter/material.dart';
import 'package:widgetbook/widgetbook.dart';
import 'package:kora_ops_mobile/core/services/dialog_service.dart';

final DialogService _dialogService = DialogService();

final List<WidgetbookNode> dialogUseCases = <WidgetbookNode>[
  WidgetbookUseCase(
    name: 'Success',
    builder: (BuildContext context) => _DialogTrigger(
      label: 'Afficher succès',
      onPressed: () => _dialogService.showSuccess(context, message: 'Colis livré avec succès.'),
    ),
  ),
  WidgetbookUseCase(
    name: 'Error',
    builder: (BuildContext context) => _DialogTrigger(
      label: 'Afficher erreur',
      onPressed: () => _dialogService.showError(context, message: 'Échec de synchronisation.'),
    ),
  ),
  WidgetbookUseCase(
    name: 'Info',
    builder: (BuildContext context) => _DialogTrigger(
      label: 'Afficher info',
      onPressed: () => _dialogService.showInfo(context, message: 'Nouvelle mise à jour disponible.'),
    ),
  ),
  WidgetbookUseCase(
    name: 'Confirmation',
    builder: (BuildContext context) => _DialogTrigger(
      label: 'Afficher confirmation',
      onPressed: () => _dialogService.showConfirmation(context, message: 'Clôturer la tournée ?'),
    ),
  ),
  WidgetbookUseCase(
    name: 'Loading',
    builder: (BuildContext context) => _DialogTrigger(
      label: 'Afficher chargement',
      onPressed: () => _dialogService.showLoading(context, message: 'Synchronisation…'),
    ),
  ),
];

class _DialogTrigger extends StatelessWidget {
  const _DialogTrigger({required this.label, required this.onPressed});

  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Center(child: ElevatedButton(onPressed: onPressed, child: Text(label)));
  }
}
