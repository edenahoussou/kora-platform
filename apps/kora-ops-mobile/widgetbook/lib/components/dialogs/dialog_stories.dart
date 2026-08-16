import 'package:flutter/material.dart';
import 'package:kora_ops_mobile/core/services/kora_dialogs.dart';
import 'package:widgetbook/widgetbook.dart';

final List<WidgetbookNode> dialogUseCases = <WidgetbookNode>[
  WidgetbookUseCase(
    name: 'Success',
    builder: (BuildContext context) => _DialogTrigger(
      label: 'Afficher succès',
      onPressed: () => KoraDialogs.success(context,
          title: 'Succès', description: 'Colis livré avec succès.'),
    ),
  ),
  WidgetbookUseCase(
    name: 'Error',
    builder: (BuildContext context) => _DialogTrigger(
      label: 'Afficher erreur',
      onPressed: () => KoraDialogs.error(context,
          title: 'Erreur', description: 'Échec de synchronisation.'),
    ),
  ),
  WidgetbookUseCase(
    name: 'Warning',
    builder: (BuildContext context) => _DialogTrigger(
      label: 'Afficher avertissement',
      onPressed: () => KoraDialogs.warning(
        context,
        title: 'Attention',
        description: 'Connexion instable — certaines données sont en attente.',
      ),
    ),
  ),
  WidgetbookUseCase(
    name: 'Info',
    builder: (BuildContext context) => _DialogTrigger(
      label: 'Afficher info',
      onPressed: () => KoraDialogs.info(context,
          title: 'Information',
          description: 'Nouvelle mise à jour disponible.'),
    ),
  ),
  WidgetbookUseCase(
    name: 'Confirmation',
    builder: (BuildContext context) => _DialogTrigger(
      label: 'Afficher confirmation',
      onPressed: () =>
          KoraDialogs.confirm(context, title: 'Clôturer la tournée ?'),
    ),
  ),
  WidgetbookUseCase(
    name: 'Loading',
    builder: (BuildContext context) => _DialogTrigger(
      label: 'Afficher chargement',
      onPressed: () {
        KoraDialogs.showLoading(context, message: 'Synchronisation…');
        Future<void>.delayed(const Duration(seconds: 2), () {
          if (context.mounted) KoraDialogs.hideLoading(context);
        });
      },
    ),
  ),
];

class _DialogTrigger extends StatelessWidget {
  const _DialogTrigger({required this.label, required this.onPressed});

  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Center(
        child: ElevatedButton(onPressed: onPressed, child: Text(label)));
  }
}
