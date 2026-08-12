import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../domain/entities/app_theme_mode.dart';
import '../bloc/theme_bloc.dart';

/// Petit interrupteur clair/sombre prêt à l'emploi, à poser dans un écran
/// de réglages ou un profil.
///
/// Exemple d'utilisation :
/// ```dart
/// const ThemeSwitch()
/// ```
///
/// C'est un exemple simple de widget qui LIT le Bloc (via `BlocBuilder`)
/// pour savoir quoi afficher, et qui ENVOIE un Event (via `context.read`)
/// quand l'utilisateur interagit.
class ThemeSwitch extends StatelessWidget {
  const ThemeSwitch({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<ThemeBloc, ThemeState>(
      buildWhen: (previous, current) => previous.mode != current.mode,
      builder: (context, state) {
        final brightness = MediaQuery.platformBrightnessOf(context);
        final isDark =
            state.mode == AppThemeMode.dark ||
            (state.mode == AppThemeMode.system &&
                brightness == Brightness.dark);

        return Switch(
          value: isDark,
          activeThumbColor: AppColors.gold,
          onChanged: (_) {
            context.read<ThemeBloc>().add(const ThemeToggled());
          },
        );
      },
    );
  }
}
