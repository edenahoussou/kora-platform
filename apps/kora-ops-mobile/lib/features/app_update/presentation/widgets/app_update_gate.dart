import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/router/app_routes.dart';
import '../../../../app/router/root_navigator_key.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../cubit/app_update_cubit.dart';
import '../pages/app_update_page.dart';

/// Applique la politique de version de KORA OPS Mobile à toute l'app
/// (MEZ-150) — branché dans `MaterialApp.router(builder:)`, au-dessus du
/// `Navigator` :
///
///  - **mise à jour obligatoire** : l'application entière est remplacée par
///    [AppUpdatePage] en mode bloquant. Aucune route n'est atteignable,
///    le bouton retour ne mène nulle part ; seule l'installation de la
///    nouvelle version lève le blocage ;
///  - **mise à jour non obligatoire** : un prompt « Nouvelle version
///    disponible » est proposé une fois (hors splash/onboarding), avec
///    « Plus tard » (report 24 h) ou ouverture de l'écran de mise à jour.
class AppUpdateGate extends StatefulWidget {
  const AppUpdateGate({required this.router, required this.child, super.key});

  final GoRouter router;
  final Widget? child;

  /// Écrans pendant lesquels le prompt attend (la navigation initiale n'est
  /// pas encore décidée, ou l'écran de mise à jour est déjà affiché).
  static const Set<String> deferredRoutes = <String>{
    AppRoutes.splash,
    AppRoutes.onboarding,
    AppRoutes.appUpdate,
  };

  @override
  State<AppUpdateGate> createState() => _AppUpdateGateState();
}

class _AppUpdateGateState extends State<AppUpdateGate> {
  bool _promptOpen = false;
  bool _waitingForRoute = false;

  @override
  void dispose() {
    widget.router.routerDelegate.removeListener(_onRouteChanged);
    super.dispose();
  }

  String get _currentPath =>
      widget.router.routerDelegate.currentConfiguration.uri.path;

  void _onRouteChanged() {
    if (AppUpdateGate.deferredRoutes.contains(_currentPath)) return;

    widget.router.routerDelegate.removeListener(_onRouteChanged);
    _waitingForRoute = false;
    _maybePrompt();
  }

  Future<void> _maybePrompt() async {
    final AppUpdateCubit cubit = context.read<AppUpdateCubit>();
    if (_promptOpen || !cubit.state.shouldPromptOptional) return;

    if (AppUpdateGate.deferredRoutes.contains(_currentPath)) {
      if (!_waitingForRoute) {
        _waitingForRoute = true;
        widget.router.routerDelegate.addListener(_onRouteChanged);
      }
      return;
    }

    final BuildContext? dialogContext = rootNavigatorKey.currentContext;
    final String? label = cubit.state.info?.latestLabel;
    if (dialogContext == null || label == null) return;

    _promptOpen = true;
    final bool update = await KoraDialogs.confirm(
      dialogContext,
      title: 'Nouvelle version disponible',
      description: 'KORA OPS $label est disponible. Installez-la pour '
          'profiter des dernières améliorations.',
      confirmLabel: 'Mettre à jour',
      cancelLabel: 'Plus tard',
    );
    _promptOpen = false;

    if (!mounted) return;

    if (update) {
      cubit.dismissPrompt();
      await widget.router.push(AppRoutes.appUpdate);
    } else {
      await cubit.postpone();
    }
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<AppUpdateCubit, AppUpdateState>(
      listenWhen: (AppUpdateState previous, AppUpdateState current) =>
          current.shouldPromptOptional &&
          (!previous.shouldPromptOptional ||
              previous.info?.releaseUuid != current.info?.releaseUuid),
      listener: (BuildContext context, AppUpdateState state) =>
          WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _maybePrompt();
      }),
      buildWhen: (AppUpdateState previous, AppUpdateState current) =>
          previous.isBlocking != current.isBlocking,
      builder: (BuildContext context, AppUpdateState state) {
        if (state.isBlocking) {
          return const AppUpdatePage(
            key: Key('app-update-blocking'),
            mandatory: true,
          );
        }

        return widget.child ?? const SizedBox.shrink();
      },
    );
  }
}
