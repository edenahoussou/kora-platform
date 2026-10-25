import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../splash/presentation/widgets/kora_wordmark.dart';
import '../../domain/entities/app_update_info.dart';
import '../cubit/app_update_cubit.dart';

/// Écran de mise à jour de KORA OPS Mobile (MEZ-150).
///
/// - [mandatory] `true` : écran plein écran **bloquant** affiché par
///   `AppUpdateGate` à la place de toute l'application — ni retour, ni
///   fermeture : l'app reste inutilisable tant que la version obligatoire
///   n'est pas installée.
/// - [mandatory] `false` : même écran, ouvert depuis le prompt « Nouvelle
///   version disponible », avec « Plus tard ».
///
/// Construit pour vivre au-dessus du `Navigator` (mode bloquant) : aucun
/// `Tooltip`, dialogue ni `Overlay` ici.
class AppUpdatePage extends StatelessWidget {
  const AppUpdatePage({required this.mandatory, this.onLater, super.key});

  final bool mandatory;

  /// « Plus tard » (mode non obligatoire uniquement).
  final VoidCallback? onLater;

  @override
  Widget build(BuildContext context) {
    final AppUpdateState state = context.watch<AppUpdateCubit>().state;
    final AppUpdateInfo? info = state.info;

    return Scaffold(
      backgroundColor: AppColors.kora,
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: kKoraSceneGradient),
        child: SafeArea(
          child: LayoutBuilder(
            builder: (BuildContext context, BoxConstraints constraints) {
              return SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.lg,
                  AppSpacing.lg,
                  AppSpacing.lg,
                  AppSpacing.xl,
                ),
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: constraints.maxHeight - AppSpacing.lg * 2,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      Row(
                        children: <Widget>[
                          const KoraWordmark(),
                          const Spacer(),
                          if (!mandatory && !state.isBusy)
                            IconButton(
                              key: const Key('app-update-close'),
                              onPressed: onLater,
                              icon: const Icon(Icons.close),
                              color: AppColors.white,
                            ),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.xl),
                      _Header(mandatory: mandatory, info: info),
                      const SizedBox(height: AppSpacing.lg),
                      if (info != null)
                        _VersionCard(
                          installed: state.installed?.toString(),
                          info: info,
                        ),
                      if (info?.releaseNotes != null) ...<Widget>[
                        const SizedBox(height: AppSpacing.md),
                        _ReleaseNotes(notes: info!.releaseNotes!),
                      ],
                      const SizedBox(height: AppSpacing.lg),
                      _Progress(state: state),
                      const SizedBox(height: AppSpacing.md),
                      _Actions(
                        state: state,
                        mandatory: mandatory,
                        onLater: onLater,
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.mandatory, required this.info});

  final bool mandatory;
  final AppUpdateInfo? info;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Container(
          width: 56,
          height: 56,
          decoration: BoxDecoration(
            color: (mandatory ? AppColors.danger : AppColors.gold)
                .withValues(alpha: 0.18),
            borderRadius: BorderRadius.circular(AppRadius.lg),
          ),
          child: Icon(
            mandatory ? Icons.system_update_alt : Icons.new_releases_outlined,
            color: mandatory ? const Color(0xFFFF8A94) : AppColors.gold,
            size: 30,
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        Text(
          mandatory ? 'Mise à jour obligatoire' : 'Nouvelle version disponible',
          style: const TextStyle(
            color: AppColors.white,
            fontSize: 26,
            fontWeight: FontWeight.w800,
            height: 1.15,
          ),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          mandatory
              ? 'Cette version de KORA OPS n’est plus autorisée. Installez la '
                  'mise à jour pour continuer à utiliser l’application.'
              : 'Une nouvelle version de KORA OPS est prête. Installez-la '
                  'dès maintenant ou plus tard.',
          style: TextStyle(
            color: AppColors.white.withValues(alpha: 0.78),
            fontSize: 15,
            height: 1.45,
          ),
        ),
      ],
    );
  }
}

class _VersionCard extends StatelessWidget {
  const _VersionCard({required this.installed, required this.info});

  final String? installed;
  final AppUpdateInfo info;

  @override
  Widget build(BuildContext context) {
    final String? size = info.sizeBytes == null
        ? null
        : '${(info.sizeBytes! / 1048576).toStringAsFixed(1).replaceAll('.', ',')} Mo';

    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.white.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: AppColors.white.withValues(alpha: 0.12)),
      ),
      child: Row(
        children: <Widget>[
          Expanded(
            child: _VersionLabel(
              caption: 'Installée',
              value: installed ?? '—',
              muted: true,
            ),
          ),
          Icon(
            Icons.arrow_forward,
            color: AppColors.white.withValues(alpha: 0.5),
            size: 18,
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: _VersionLabel(
              caption: size == null ? 'Nouvelle' : 'Nouvelle · $size',
              value: '${info.latestVersion}+${info.latestBuild}',
            ),
          ),
        ],
      ),
    );
  }
}

class _VersionLabel extends StatelessWidget {
  const _VersionLabel({
    required this.caption,
    required this.value,
    this.muted = false,
  });

  final String caption;
  final String value;
  final bool muted;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          caption.toUpperCase(),
          style: TextStyle(
            color: AppColors.white.withValues(alpha: 0.55),
            fontSize: 10,
            fontWeight: FontWeight.w700,
            letterSpacing: 0.8,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: TextStyle(
            color: muted
                ? AppColors.white.withValues(alpha: 0.75)
                : AppColors.gold,
            fontSize: 18,
            fontWeight: FontWeight.w800,
            fontFamily: 'monospace',
          ),
        ),
      ],
    );
  }
}

class _ReleaseNotes extends StatelessWidget {
  const _ReleaseNotes({required this.notes});

  final String notes;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxHeight: 220),
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Text(
              'Nouveautés',
              style: TextStyle(
                color: AppColors.kora,
                fontSize: 14,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              notes,
              style: const TextStyle(
                color: Color(0xFF273142),
                fontSize: 14,
                height: 1.5,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Progress extends StatelessWidget {
  const _Progress({required this.state});

  final AppUpdateState state;

  @override
  Widget build(BuildContext context) {
    final String? message = switch (state.phase) {
      AppUpdatePhase.downloading when state.isVerifying =>
        'Vérification de l’intégrité du fichier…',
      AppUpdatePhase.downloading => state.progress == null
          ? 'Téléchargement en cours…'
          : 'Téléchargement… ${(state.progress! * 100).round()} %',
      AppUpdatePhase.awaitingPermission =>
        'Autorisez KORA OPS à installer des applications, puis revenez '
            'ici : l’installation reprendra automatiquement.',
      AppUpdatePhase.installerOpened =>
        'Confirmez l’installation dans la fenêtre Android. L’application '
            'redémarrera dans sa nouvelle version.',
      AppUpdatePhase.failed => state.errorMessage,
      AppUpdatePhase.idle => null,
    };

    if (message == null) return const SizedBox.shrink();

    final bool failed = state.phase == AppUpdatePhase.failed;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (state.isBusy) ...<Widget>[
          ClipRRect(
            borderRadius: BorderRadius.circular(AppRadius.pill),
            child: LinearProgressIndicator(
              key: const Key('app-update-progress'),
              value: state.isVerifying ? null : state.progress,
              minHeight: 8,
              backgroundColor: AppColors.white.withValues(alpha: 0.15),
              color: AppColors.gold,
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
        ],
        Text(
          message,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: failed
                ? const Color(0xFFFFB3BA)
                : AppColors.white.withValues(alpha: 0.85),
            fontSize: 13,
            height: 1.4,
            fontWeight: failed ? FontWeight.w600 : FontWeight.w400,
          ),
        ),
      ],
    );
  }
}

class _Actions extends StatelessWidget {
  const _Actions({
    required this.state,
    required this.mandatory,
    required this.onLater,
  });

  final AppUpdateState state;
  final bool mandatory;
  final VoidCallback? onLater;

  @override
  Widget build(BuildContext context) {
    final AppUpdateCubit cubit = context.read<AppUpdateCubit>();

    final Widget primary = switch (state.phase) {
      AppUpdatePhase.awaitingPermission => KoraButton(
          key: const Key('app-update-permission'),
          label: 'Autoriser l’installation',
          icon: Icons.security_update_good,
          onPressed: cubit.openPermissionSettings,
        ),
      AppUpdatePhase.failed => KoraButton(
          key: const Key('app-update-primary'),
          label: 'Réessayer',
          icon: Icons.refresh,
          onPressed: cubit.startUpdate,
        ),
      _ => KoraButton(
          key: const Key('app-update-primary'),
          label: state.apkPath != null
              ? 'Installer la mise à jour'
              : 'Télécharger et installer',
          icon: Icons.download_rounded,
          isLoading: state.isBusy,
          onPressed: state.info == null ? null : cubit.startUpdate,
        ),
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        primary,
        if (state.phase == AppUpdatePhase.awaitingPermission) ...<Widget>[
          const SizedBox(height: AppSpacing.sm),
          TextButton(
            onPressed: cubit.startUpdate,
            style: TextButton.styleFrom(foregroundColor: AppColors.white),
            child: const Text('J’ai autorisé, installer'),
          ),
        ],
        if (!mandatory && !state.isBusy) ...<Widget>[
          const SizedBox(height: AppSpacing.sm),
          TextButton(
            key: const Key('app-update-later'),
            onPressed: onLater,
            style: TextButton.styleFrom(
              foregroundColor: AppColors.white.withValues(alpha: 0.8),
            ),
            child: const Text('Plus tard'),
          ),
        ],
        if (mandatory) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          Text(
            'Besoin d’aide ? Contactez votre agence ou l’équipe KORA OPS.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.55),
              fontSize: 12,
            ),
          ),
        ],
      ],
    );
  }
}
