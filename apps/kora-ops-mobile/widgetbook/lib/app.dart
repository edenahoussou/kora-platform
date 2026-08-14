import 'package:flutter/material.dart';
import 'package:kora_ops_mobile/app/theme/app_theme.dart';
import 'package:widgetbook/widgetbook.dart';
import 'components/buttons/app_button_stories.dart';
import 'components/cards/app_card_stories.dart';
import 'components/dialogs/dialog_stories.dart';
import 'components/inputs/app_text_field_stories.dart';
import 'components/loading/state_stories.dart';
import 'themes/color_stories.dart';
import 'themes/typography_stories.dart';

/// Point d'entrée Widgetbook, indépendant de l'application principale.
/// Lancement : `cd widgetbook && flutter run -t lib/app.dart`
class WidgetbookApp extends StatelessWidget {
  const WidgetbookApp({super.key});

  @override
  Widget build(BuildContext context) {
    return Widgetbook.material(
      directories: <WidgetbookNode>[
        WidgetbookFolder(
          name: 'Design System',
          children: <WidgetbookNode>[colorsUseCase, typographyUseCase],
        ),
        WidgetbookFolder(
          name: 'Buttons',
          children: appButtonUseCases,
        ),
        WidgetbookFolder(
          name: 'Inputs',
          children: appTextFieldUseCases,
        ),
        WidgetbookFolder(
          name: 'Cards',
          children: <WidgetbookNode>[appCardUseCase],
        ),
        WidgetbookFolder(
          name: 'Loading & States',
          children: stateUseCases,
        ),
        WidgetbookFolder(
          name: 'Dialogs (ProDialog)',
          children: dialogUseCases,
        ),
      ],
      addons: <WidgetbookAddon<Object?>>[
        ThemeAddon<ThemeData>(
          themes: <WidgetbookTheme<ThemeData>>[
            WidgetbookTheme(name: 'KORA Clair', data: AppTheme.light),
            WidgetbookTheme(name: 'KORA Sombre', data: AppTheme.dark),
          ],
          themeBuilder: (BuildContext context, ThemeData theme, Widget child) {
            return Theme(data: theme, child: child);
          },
        ),
        // ignore: deprecated_member_use
        DeviceFrameAddon(
          devices: <DeviceInfo>[Devices.ios.iPhone13, Devices.android.samsungGalaxyA50],
        ),
      ],
    );
  }
}
