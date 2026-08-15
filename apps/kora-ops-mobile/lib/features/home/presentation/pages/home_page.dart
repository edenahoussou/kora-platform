import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/theme_cubit.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/widgets/app_card.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  IconData _themeIcon(ThemeMode mode) => switch (mode) {
        ThemeMode.light => Icons.light_mode_outlined,
        ThemeMode.dark => Icons.dark_mode_outlined,
        ThemeMode.system => Icons.brightness_auto_outlined,
      };

  String _themeTooltip(ThemeMode mode) => switch (mode) {
        ThemeMode.light => 'Thème clair (appuyer pour sombre)',
        ThemeMode.dark => 'Thème sombre (appuyer pour automatique)',
        ThemeMode.system => 'Thème automatique (appuyer pour clair)',
      };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('KORA OPS'),
        actions: <Widget>[
          // Bascule manuelle du thème (système → clair → sombre → système) —
          // un vrai écran Paramètres accueillera ce réglage plus tard, voir
          // document.md § Dark mode.
          BlocBuilder<ThemeCubit, ThemeMode>(
            builder: (BuildContext context, ThemeMode mode) => IconButton(
              icon: Icon(_themeIcon(mode)),
              tooltip: _themeTooltip(mode),
              onPressed: () => context.read<ThemeCubit>().cycle(),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Déconnexion',
            onPressed: () => context.read<AuthCubit>().logout(),
          ),
        ],
      ),
      // Grille adaptative : 1 colonne sur téléphone, 2 sur tablette, 3 en
      // desktop/web — voir Responsive.gridColumns.
      body: GridView.count(
        padding: const EdgeInsets.all(AppSpacing.md),
        crossAxisCount: Responsive.gridColumns(context),
        mainAxisSpacing: AppSpacing.md,
        crossAxisSpacing: AppSpacing.md,
        childAspectRatio: 1.3,
        children: <Widget>[
          const _HomeTile(icon: Icons.list_alt, label: 'Manifestes'),
          const _HomeTile(icon: Icons.qr_code_scanner, label: 'Embarquement'),
          _HomeTile(
            icon: Icons.print_outlined,
            label: 'Impression',
            onTap: () => context.push(AppRoutes.printerTest),
          ),
          const _HomeTile(icon: Icons.sync, label: 'Synchronisation'),
        ],
      ),
    );
  }
}

class _HomeTile extends StatelessWidget {
  const _HomeTile({required this.icon, required this.label, this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: AppCard(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(icon, size: 32, color: Theme.of(context).colorScheme.primary),
            const SizedBox(height: AppSpacing.sm),
            Text(label, style: Theme.of(context).textTheme.titleMedium),
          ],
        ),
      ),
    );
  }
}
