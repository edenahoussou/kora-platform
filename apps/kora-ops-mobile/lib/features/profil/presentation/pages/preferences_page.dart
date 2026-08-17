import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../app/theme/theme_cubit.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../synchro/presentation/cubit/sync_cubit.dart';

/// "Préférences" — apparence (thème clair/sombre, déplacé ici depuis
/// l'Accueil, voir échange du 16/08/2026), accès à la Synchronisation
/// (l'onglet dédié a été retiré, voir échange du 17/08/2026) et aux
/// adaptateurs P501. Route : `/profil/preferences`.
class PreferencesPage extends StatelessWidget {
  const PreferencesPage({super.key});

  @override
  Widget build(BuildContext context) {
    final int pending = context.watch<SyncCubit>().state.pending.length;

    return Scaffold(
      appBar: const ScreenHeader(eyebrow: 'Mon compte', title: 'Préférences'),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: <Widget>[
            BlocBuilder<ThemeCubit, ThemeMode>(
              builder: (BuildContext context, ThemeMode mode) => KoraCard(
                child: Row(
                  children: <Widget>[
                    const _IconBadge(icon: Icons.brightness_6_outlined),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text('Apparence',
                              style: Theme.of(context).textTheme.titleMedium),
                          Text(
                            switch (mode) {
                              ThemeMode.light => 'Clair',
                              ThemeMode.dark => 'Sombre',
                              ThemeMode.system => 'Automatique (système)',
                            },
                            style: TextStyle(
                                color: context.koraColors.textMuted,
                                fontSize: 12),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      icon: Icon(switch (mode) {
                        ThemeMode.light => Icons.light_mode_outlined,
                        ThemeMode.dark => Icons.dark_mode_outlined,
                        ThemeMode.system => Icons.brightness_auto_outlined,
                      }),
                      onPressed: () => context.read<ThemeCubit>().cycle(),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            KoraCard(
              onTap: () => context.push(AppRoutes.synchro),
              child: Row(
                children: <Widget>[
                  const _IconBadge(icon: Icons.sync),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text('Synchronisation',
                            style: Theme.of(context).textTheme.titleMedium),
                        Text(
                          pending > 0
                              ? '$pending opération(s) en attente'
                              : 'À jour',
                          style: TextStyle(
                              color: context.koraColors.textMuted,
                              fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: AppColors.digital),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            KoraCard(
              onTap: () => context.push(AppRoutes.printerTest),
              child: Row(
                children: <Widget>[
                  const _IconBadge(icon: Icons.print_outlined),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text('Adaptateurs P501',
                            style: Theme.of(context).textTheme.titleMedium),
                        Text(
                          'Tester le scanner et l\'imprimante',
                          style: TextStyle(
                              color: context.koraColors.textMuted,
                              fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: AppColors.digital),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _IconBadge extends StatelessWidget {
  const _IconBadge({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 36,
      height: 36,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: context.koraColors.background,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Icon(icon, size: 18, color: context.koraColors.textPrimary),
    );
  }
}
