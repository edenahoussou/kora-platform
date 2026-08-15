import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'core/di/injector.dart';
import 'core/env/app_env.dart';
import 'core/router/app_router.dart';
import 'features/theme/presentation/bloc/theme_bloc.dart';

/// Widget racine de l'application.
///
/// C'est ICI, et seulement ici, qu'on assemble tout :
/// - le [BlocProvider] qui rend `ThemeBloc` disponible partout dans l'app
///   (`context.read<ThemeBloc>()` / `BlocBuilder<ThemeBloc, ...>` marchent
///   ensuite dans n'importe quel widget descendant).
/// - `MaterialApp.router` avec `theme`, `darkTheme`, et la configuration
///   de navigation `AppRouter.router` (go_router).
///
/// Plus tard, quand on ajoutera d'autres Blocs globaux (ex: un AuthBloc
/// qui doit être visible depuis toute l'app), on les ajoutera ici avec
/// `MultiBlocProvider`.
class KoraApp extends StatelessWidget {
  const KoraApp({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider<ThemeBloc>(
      create: (_) => getIt<ThemeBloc>()..add(const ThemeStarted()),
      child: BlocBuilder<ThemeBloc, ThemeState>(
        buildWhen: (previous, current) => previous.mode != current.mode,
        builder: (context, themeState) {
          return MaterialApp.router(
            title: AppEnv.current.appName,
            debugShowCheckedModeBanner: false,
            theme: AppTheme.light,
            darkTheme: AppTheme.dark,
            themeMode: themeState.materialThemeMode,
            // KORA EXPRESS est une app francophone : on force le français
            // plutôt que de suivre la langue du téléphone, faute de
            // traductions anglaises de nos propres textes.
            locale: const Locale('fr', 'FR'),
            supportedLocales: const [Locale('fr', 'FR')],
            localizationsDelegates: const [
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            routerConfig: AppRouter.router,
          );
        },
      ),
    );
  }
}
