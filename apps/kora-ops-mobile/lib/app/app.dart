import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:phone_form_field/phone_form_field.dart';
import '../features/auth/presentation/cubit/auth_cubit.dart';
import '../features/manifeste/presentation/cubit/manifeste_cubit.dart';
import '../features/notifications/presentation/cubit/notification_cubit.dart';
import '../features/notifications/presentation/cubit/sent_notification_cubit.dart';
import '../features/ops/presentation/cubit/departure_list_cubit.dart';
import '../features/ops/presentation/cubit/selected_depart_cubit.dart';
import '../features/scan/presentation/cubit/scan_boarding_cubit.dart';
import '../features/support_chat/data/support_chat_remote_datasource.dart';
import '../features/support_chat/presentation/cubit/support_chat_cubit.dart';
import '../features/support_chat/presentation/widgets/support_chat_overlay.dart';
import '../features/synchro/presentation/cubit/sync_cubit.dart';
import '../features/terminal/presentation/cubit/terminal_cubit.dart';
import '../features/tracking/presentation/cubit/tracking_cubit.dart';
import '../injection/injection.dart';
import 'config/app_config.dart';
import 'router/app_router.dart';
import 'router/auth_guard.dart';
import 'theme/app_theme.dart';
import 'theme/theme_cubit.dart';

class App extends StatelessWidget {
  const App({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiBlocProvider(
      providers: [
        BlocProvider<AuthCubit>.value(value: getIt<AuthCubit>()),
        BlocProvider<TerminalCubit>.value(value: getIt<TerminalCubit>()),
        BlocProvider<ThemeCubit>.value(value: getIt<ThemeCubit>()),
        // Cubits partagés par les 5 onglets ET les écrans empilés
        // au-dessus (détail passager, résultat de scan…) — voir
        // document.md § Navigation.
        BlocProvider<SelectedDepartCubit>.value(
            value: getIt<SelectedDepartCubit>()),
        BlocProvider<DepartureListCubit>.value(
            value: getIt<DepartureListCubit>()),
        BlocProvider<SyncCubit>.value(value: getIt<SyncCubit>()),
        BlocProvider<ManifesteCubit>.value(value: getIt<ManifesteCubit>()),
        BlocProvider<ScanBoardingCubit>.value(
            value: getIt<ScanBoardingCubit>()),
        BlocProvider<NotificationCubit>.value(
            value: getIt<NotificationCubit>()),
        BlocProvider<SentNotificationCubit>.value(
            value: getIt<SentNotificationCubit>()),
        BlocProvider<TrackingCubit>.value(value: getIt<TrackingCubit>()),
        // Chat support tawk.to : un seul cubit pour le bouton flottant
        // affiché sur tous les écrans connectés (voir SupportChatOverlay).
        BlocProvider<SupportChatCubit>(
          create: (_) => SupportChatCubit(
            getIt<SupportChatRemoteDataSource>(),
            authCubit: getIt<AuthCubit>(),
          ),
        ),
      ],
      child: const _AppLifecycleListener(child: _AppRouterHost()),
    );
  }
}

/// Relit l'état de session au retour au premier plan (verrouillage écran,
/// changement d'app, etc.) — pas seulement au démarrage à froid. Un token
/// expiré pendant que l'app était en arrière-plan doit être détecté dès le
/// retour, pas seulement à la prochaine action de l'utilisateur.
class _AppLifecycleListener extends StatefulWidget {
  const _AppLifecycleListener({required this.child});

  final Widget child;

  @override
  State<_AppLifecycleListener> createState() => _AppLifecycleListenerState();
}

class _AppLifecycleListenerState extends State<_AppLifecycleListener>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      getIt<AuthCubit>().checkAuthStatus();
      // Redemande la permission de localisation si elle n'est toujours pas
      // accordée (point 5, échange du 23/09/2026) — le suivi de position
      // en dépend, voir TrackingCubit.onAppResumed.
      getIt<TrackingCubit>().onAppResumed();
    }
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

class _AppRouterHost extends StatelessWidget {
  const _AppRouterHost();

  @override
  Widget build(BuildContext context) {
    final AuthGuard authGuard =
        AuthGuard(context.read<AuthCubit>(), context.read<TerminalCubit>());
    final AppRouter appRouter = AppRouter(authGuard);

    // ScreenUtilInit calibre .w / .h / .sp / .r sur un design de référence
    // 375x812 (iPhone standard), proche des terminaux P501 utilisés sur
    // le terrain — voir core/helpers/responsive.dart pour les breakpoints
    // tablette/desktop complémentaires.
    return ScreenUtilInit(
      designSize: const Size(375, 812),
      minTextAdapt: true,
      splitScreenMode: true,
      builder: (BuildContext context, Widget? child) {
        return BlocBuilder<ThemeCubit, ThemeMode>(
          builder: (BuildContext context, ThemeMode themeMode) {
            return MaterialApp.router(
              title: AppConfig.instance.appName,
              debugShowCheckedModeBanner: AppConfig.instance.enableDebugBanner,
              // Requis par phone_form_field (login par téléphone, voir
              // IdentifierInput) pour son sélecteur de pays et ses
              // messages de validation.
              localizationsDelegates: const <LocalizationsDelegate<dynamic>>[
                GlobalMaterialLocalizations.delegate,
                GlobalWidgetsLocalizations.delegate,
                GlobalCupertinoLocalizations.delegate,
                ...PhoneFieldLocalization.delegates,
              ],
              supportedLocales: const <Locale>[
                Locale('fr'),
                Locale('en'),
              ],
              locale: const Locale('fr'),
              theme: AppTheme.light,
              darkTheme: AppTheme.dark,
              themeMode: themeMode,
              routerConfig: appRouter.router,
              // Bouton flottant « Chat support » au-dessus de toutes les
              // pages connectées (plus seulement le Profil).
              builder: (BuildContext context, Widget? child) =>
                  SupportChatOverlay(router: appRouter.router, child: child),
            );
          },
        );
      },
    );
  }
}
