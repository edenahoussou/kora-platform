import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../features/auth/presentation/cubit/auth_cubit.dart';
import '../features/manifeste/presentation/cubit/manifeste_cubit.dart';
import '../features/ops/presentation/cubit/selected_depart_cubit.dart';
import '../features/scan/presentation/cubit/scan_boarding_cubit.dart';
import '../features/synchro/presentation/cubit/sync_cubit.dart';
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
        BlocProvider<ThemeCubit>.value(value: getIt<ThemeCubit>()),
        // Cubits partagés par les 5 onglets ET les écrans empilés
        // au-dessus (détail passager, résultat de scan…) — voir
        // document.md § Navigation.
        BlocProvider<SelectedDepartCubit>.value(
            value: getIt<SelectedDepartCubit>()),
        BlocProvider<SyncCubit>.value(value: getIt<SyncCubit>()),
        BlocProvider<ManifesteCubit>.value(value: getIt<ManifesteCubit>()),
        BlocProvider<ScanBoardingCubit>.value(
            value: getIt<ScanBoardingCubit>()),
      ],
      child: Builder(
        builder: (BuildContext context) {
          final AuthGuard authGuard = AuthGuard(context.read<AuthCubit>());
          final AppRouter appRouter = AppRouter(authGuard);

          // ScreenUtilInit calibre .w / .h / .sp / .r sur un design de
          // référence 375x812 (iPhone standard), proche des terminaux P501
          // utilisés sur le terrain — voir core/helpers/responsive.dart
          // pour les breakpoints tablette/desktop complémentaires.
          return ScreenUtilInit(
            designSize: const Size(375, 812),
            minTextAdapt: true,
            splitScreenMode: true,
            builder: (BuildContext context, Widget? child) {
              return BlocBuilder<ThemeCubit, ThemeMode>(
                builder: (BuildContext context, ThemeMode themeMode) {
                  return MaterialApp.router(
                    title: AppConfig.instance.appName,
                    debugShowCheckedModeBanner:
                        AppConfig.instance.enableDebugBanner,
                    theme: AppTheme.light,
                    darkTheme: AppTheme.dark,
                    themeMode: themeMode,
                    routerConfig: appRouter.router,
                  );
                },
              );
            },
          );
        },
      ),
    );
  }
}
