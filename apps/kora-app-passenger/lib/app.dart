import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'core/di/injector.dart';
import 'core/env/app_env.dart';
import 'core/l10n/app_localization.dart';
import 'core/network/session_expiry.dart';
import 'core/push/push_listener.dart';
import 'core/router/app_router.dart';
import 'core/update/update_gate.dart';
import 'features/auth/presentation/bloc/auth_bloc.dart';
import 'features/auth/presentation/widgets/phone_country_picker.dart';
import 'features/language/domain/entities/app_language.dart';
import 'features/language/presentation/bloc/language_bloc.dart';
import 'features/support/presentation/widgets/support_chat_overlay.dart';
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
    return MultiBlocProvider(
      providers: [
        BlocProvider<ThemeBloc>(
          create: (_) => getIt<ThemeBloc>()..add(const ThemeStarted()),
        ),
        BlocProvider<LanguageBloc>(
          create: (_) => getIt<LanguageBloc>()..add(const LanguageStarted()),
        ),
        // Porte la session pour toute la durée de l'app. `AuthStarted`
        // relit le stockage sécurisé : tant qu'il n'a pas répondu, le
        // garde de navigation ne redirige personne.
        BlocProvider<AuthBloc>(
          create: (_) => getIt<AuthBloc>()
            ..add(const AuthStarted())
            // Un 401 sur une requête qui portait un token veut dire que
            // la session est finie. Le bloc le sait d'ici, et le garde de
            // navigation ramène à l'accueil public — sans quoi l'app
            // restait sur un écran qui répétait « Unauthenticated ».
            ..watchExpiry(getIt<SessionExpiry>()),
        ),
      ],
      child: BlocBuilder<ThemeBloc, ThemeState>(
        buildWhen: (previous, current) => previous.mode != current.mode,
        builder: (context, themeState) {
          return BlocBuilder<LanguageBloc, LanguageState>(
            buildWhen: (previous, current) =>
                previous.language != current.language,
            builder: (context, languageState) {
              return MaterialApp.router(
                title: AppEnv.current.appName,
                debugShowCheckedModeBanner: false,
                theme: AppTheme.light,
                darkTheme: AppTheme.dark,
                themeMode: themeState.materialThemeMode,
                // La langue suit le choix de l'utilisateur, pas celle du
                // téléphone : au Bénin, un appareil réglé en anglais
                // n'indique pas que son porteur préfère lire l'app en
                // anglais. Le français reste le défaut (voir AppLanguage).
                locale: languageState.locale,
                supportedLocales: AppLanguage.values.map(
                  (language) => Locale(language.code),
                ),
                localizationsDelegates: const [
                  AppLocalization.delegate,
                  // La liste des pays du sélecteur d'indicatif, traduite
                  // par le paquet lui-même.
                  SyncCountryLocalizationsDelegate(),
                  GlobalMaterialLocalizations.delegate,
                  GlobalWidgetsLocalizations.delegate,
                  GlobalCupertinoLocalizations.delegate,
                ],
                routerConfig: AppRouter.router,
                // Les notifications s'écoutent ici, sous `MaterialApp` :
                // il faut un `ScaffoldMessenger` au-dessus pour afficher
                // un message reçu alors que l'app est à l'écran.
                builder: (context, child) => PushListener(
                  child: UpdateGate(
                    child: SupportChatOverlay(
                      child: child ?? const SizedBox.shrink(),
                    ),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
