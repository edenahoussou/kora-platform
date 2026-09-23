import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../features/auth/presentation/cubit/auth_cubit.dart';
import '../features/manifeste/presentation/cubit/manifeste_cubit.dart';
import '../features/notifications/presentation/cubit/notification_cubit.dart';
import '../features/ops/presentation/cubit/departure_list_cubit.dart';
import '../features/ops/presentation/cubit/selected_depart_cubit.dart';
import '../features/scan/presentation/cubit/scan_boarding_cubit.dart';
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
        BlocProvider<TrackingCubit>.value(value: getIt<TrackingCubit>()),
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
              theme: AppTheme.light,
              darkTheme: AppTheme.dark,
              themeMode: themeMode,
              routerConfig: appRouter.router,
            );
          },
        );
      },
    );
  }
}
