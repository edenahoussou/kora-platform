import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/widgets/app_card.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('KORA OPS'),
        actions: <Widget>[
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
        children: const <Widget>[
          _HomeTile(icon: Icons.list_alt, label: 'Manifestes'),
          _HomeTile(icon: Icons.qr_code_scanner, label: 'Embarquement'),
          _HomeTile(icon: Icons.print_outlined, label: 'Impression'),
          _HomeTile(icon: Icons.sync, label: 'Synchronisation'),
        ],
      ),
    );
  }
}

class _HomeTile extends StatelessWidget {
  const _HomeTile({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return AppCard(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          Icon(icon, size: 32, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: AppSpacing.sm),
          Text(label, style: Theme.of(context).textTheme.titleMedium),
        ],
      ),
    );
  }
}
