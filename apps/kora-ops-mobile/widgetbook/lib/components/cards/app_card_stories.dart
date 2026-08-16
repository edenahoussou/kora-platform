import 'package:flutter/material.dart';
import 'package:kora_ops_mobile/core/widgets/kora_card.dart';
import 'package:widgetbook/widgetbook.dart';

final List<WidgetbookNode> koraCardUseCases = <WidgetbookNode>[
  WidgetbookUseCase(
    name: 'Surface',
    builder: (BuildContext context) => const Padding(
      padding: EdgeInsets.all(16),
      child: KoraCard(child: Text('Manifeste #A123 — 42 passagers')),
    ),
  ),
  WidgetbookUseCase(
    name: 'Navy',
    builder: (BuildContext context) => const Padding(
      padding: EdgeInsets.all(16),
      child: KoraCard(
        variant: KoraCardVariant.navy,
        child: Text('Connexion à confirmer',
            style: TextStyle(color: Colors.white)),
      ),
    ),
  ),
  WidgetbookUseCase(
    name: 'Gold',
    builder: (BuildContext context) => const Padding(
      padding: EdgeInsets.all(16),
      child: KoraCard(
          variant: KoraCardVariant.gold, child: Text('Départ sélectionné')),
    ),
  ),
];
