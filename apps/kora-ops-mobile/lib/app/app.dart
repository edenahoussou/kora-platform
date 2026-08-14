import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../features/auth/presentation/cubit/auth_cubit.dart';
import '../injection/injection.dart';
import 'config/app_config.dart';
import 'router/app_router.dart';
import 'router/auth_guard.dart';
import 'theme/app_theme.dart';

class App extends StatelessWidget {
  const App({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider<AuthCubit>(
      create: (_) => getIt<AuthCubit>(),
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
              return MaterialApp.router(
                title: AppConfig.instance.appName,
                debugShowCheckedModeBanner: AppConfig.instance.enableDebugBanner,
                theme: AppTheme.light,
                routerConfig: appRouter.router,
              );
            },
          );
        },
      ),
    );
  }
}
