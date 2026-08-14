import 'package:flutter/material.dart';
import 'package:widgetbook/widgetbook.dart';
import 'package:kora_ops_mobile/core/widgets/app_card.dart';

final WidgetbookUseCase appCardUseCase = WidgetbookUseCase(
  name: 'Default',
  builder: (BuildContext context) => const Padding(
    padding: EdgeInsets.all(16),
    child: AppCard(child: Text('Manifeste #A123 — 42 passagers')),
  ),
);
