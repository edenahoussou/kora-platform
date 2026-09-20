import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/app_text_styles.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../manifeste/presentation/cubit/manifeste_cubit.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../domain/services/border_manifest_builder.dart';
import '../cubit/border_manifest_print_cubit.dart';

/// Impression du manifeste frontière sur la P501 (MEZ-94) — 3 exemplaires
/// sur commande explicite, état de chaque exemplaire, reprise sans
/// doublon. Route : `/tournee/manifeste/frontiere`. Fournir un
/// [BorderManifestPrintCubit] au-dessus (voir `AppRouter`).
///
/// En mode téléphone (pas d'imprimante thermique), c'est
/// `BorderManifestPdfPage` qui est ouverte à la place.
class BorderManifestPrintPage extends StatefulWidget {
  const BorderManifestPrintPage({super.key});

  @override
  State<BorderManifestPrintPage> createState() =>
      _BorderManifestPrintPageState();
}

class _BorderManifestPrintPageState extends State<BorderManifestPrintPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _load();
    });
  }

  /// Relit le départ et le manifeste courants (`SelectedDepartCubit` /
  /// `ManifesteCubit`, partagés par toute l'app) et (re)charge l'écran.
  Future<void> _load() async {
    final Depart? depart = context.read<SelectedDepartCubit>().state;
    if (depart == null) return;
    final ManifesteState manifest = context.read<ManifesteCubit>().state;

    await context.read<BorderManifestPrintCubit>().load(
          BorderManifestInput(
            depart: depart,
            passengers: manifest.passengers,
            isInternational: manifest.isInternational,
            manifestVersion: manifest.manifestVersion,
            fetchedAt: manifest.fetchedAt,
          ),
        );
  }

  Future<void> _refreshManifest() async {
    await context.read<ManifesteCubit>().load();
    if (mounted) await _load();
  }

  Future<void> _reprint(BorderManifestPrintCubit cubit) async {
    final bool confirmed = await KoraDialogs.confirm(
      context,
      title: 'Réimprimer le manifeste ?',
      description: 'Les ${cubit.state.copiesTotal} exemplaires ont déjà été '
          'imprimés. Une nouvelle impression produira '
          '${cubit.state.copiesTotal} exemplaires supplémentaires.',
      confirmLabel: 'Réimprimer',
    );
    if (!confirmed) return;
    await cubit.printCopies(confirmReprint: true);
  }

  @override
  Widget build(BuildContext context) {
    final Depart? depart = context.watch<SelectedDepartCubit>().state;

    return Scaffold(
      appBar: ScreenHeader(
        eyebrow: depart?.route ?? 'Manifeste',
        title: 'Manifeste frontière',
      ),
      body: SafeArea(
        child: depart == null
            ? const Center(
                child:
                    Text('Sélectionnez une course depuis l\'onglet Tournée.'),
              )
            : BlocBuilder<BorderManifestPrintCubit, BorderManifestPrintState>(
                builder:
                    (BuildContext context, BorderManifestPrintState state) {
                  return ListView(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    children: <Widget>[
                      _SummaryCard(depart: depart, state: state),
                      const SizedBox(height: AppSpacing.md),
                      _CopiesCard(state: state),
                      if (state.message != null) ...<Widget>[
                        const SizedBox(height: AppSpacing.md),
                        _Notice(
                          message: state.message!,
                          color: state.phase ==
                                  BorderManifestPrintPhase.awaitingConfirmation
                              ? AppColors.warning
                              : AppColors.danger,
                        ),
                      ],
                      if (state.phase ==
                          BorderManifestPrintPhase.refused) ...<Widget>[
                        const SizedBox(height: AppSpacing.md),
                        _RefusalCard(issues: state.issues),
                      ],
                      const SizedBox(height: AppSpacing.lg),
                      _Actions(
                        state: state,
                        onPrint: () => context
                            .read<BorderManifestPrintCubit>()
                            .printCopies(),
                        onResume: () =>
                            context.read<BorderManifestPrintCubit>().resume(),
                        onReprint: () =>
                            _reprint(context.read<BorderManifestPrintCubit>()),
                        onConfirm: (bool printed) => context
                            .read<BorderManifestPrintCubit>()
                            .confirmInFlightCopy(printed: printed),
                        onRefresh: _refreshManifest,
                      ),
                    ],
                  );
                },
              ),
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.depart, required this.state});

  final Depart depart;
  final BorderManifestPrintState state;

  @override
  Widget build(BuildContext context) {
    final KoraColors colors = context.koraColors;
    final int? passengers = state.payload?.passengerCount;

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(depart.lineCode ?? depart.lineName ?? depart.route,
              style: AppTextStyles.heading2(colors.textPrimary)),
          const SizedBox(height: AppSpacing.xs),
          Text('${depart.date} · ${depart.heure}',
              style: TextStyle(color: colors.textMuted)),
          const SizedBox(height: AppSpacing.xs),
          Text(
            passengers == null
                ? 'Passagers : à vérifier'
                : 'Passagers : $passengers',
            style: TextStyle(color: colors.textPrimary),
          ),
        ],
      ),
    );
  }
}

class _CopiesCard extends StatelessWidget {
  const _CopiesCard({required this.state});

  final BorderManifestPrintState state;

  @override
  Widget build(BuildContext context) {
    final KoraColors colors = context.koraColors;
    final List<BorderCopyStatus> statuses = state.copyStatuses;

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            'Exemplaires : ${state.copiesConfirmed}/${state.copiesTotal} '
            'imprimés',
            style: AppTextStyles.heading2(colors.textPrimary),
          ),
          const SizedBox(height: AppSpacing.sm),
          for (int i = 0; i < statuses.length; i++)
            _CopyRow(copy: i + 1, total: statuses.length, status: statuses[i]),
        ],
      ),
    );
  }
}

class _CopyRow extends StatelessWidget {
  const _CopyRow({
    required this.copy,
    required this.total,
    required this.status,
  });

  final int copy;
  final int total;
  final BorderCopyStatus status;

  @override
  Widget build(BuildContext context) {
    final KoraColors colors = context.koraColors;

    final (IconData icon, Color color, String label) = switch (status) {
      BorderCopyStatus.pending => (
          Icons.radio_button_unchecked,
          colors.textMuted,
          'En attente',
        ),
      BorderCopyStatus.printing => (
          Icons.print,
          AppColors.digital,
          'Impression en cours…',
        ),
      BorderCopyStatus.printed => (
          Icons.check_circle,
          AppColors.success,
          'Imprimé',
        ),
      BorderCopyStatus.failed => (
          Icons.error,
          AppColors.danger,
          'Échec — sera réimprimé à la reprise',
        ),
      BorderCopyStatus.unconfirmed => (
          Icons.help,
          AppColors.warning,
          'À confirmer',
        ),
    };

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Row(
        children: <Widget>[
          if (status == BorderCopyStatus.printing)
            SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(strokeWidth: 2, color: color),
            )
          else
            Icon(icon, size: 20, color: color),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text('Exemplaire $copy/$total',
                style: TextStyle(color: colors.textPrimary)),
          ),
          Flexible(
            child: Text(
              label,
              textAlign: TextAlign.end,
              style: TextStyle(color: color, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({required this.message, required this.color});

  final String message;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(Icons.info_outline, size: 20, color: color),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(message, style: Theme.of(context).textTheme.bodyMedium),
          ),
        ],
      ),
    );
  }
}

class _RefusalCard extends StatelessWidget {
  const _RefusalCard({required this.issues});

  final List<String> issues;

  @override
  Widget build(BuildContext context) {
    final KoraColors colors = context.koraColors;

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const Icon(Icons.block, size: 20, color: AppColors.danger),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  'Impression impossible',
                  style: AppTextStyles.heading2(colors.textPrimary),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          for (final String issue in issues)
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.xs),
              child:
                  Text('• $issue', style: TextStyle(color: colors.textPrimary)),
            ),
        ],
      ),
    );
  }
}

class _Actions extends StatelessWidget {
  const _Actions({
    required this.state,
    required this.onPrint,
    required this.onResume,
    required this.onReprint,
    required this.onConfirm,
    required this.onRefresh,
  });

  final BorderManifestPrintState state;
  final VoidCallback onPrint;
  final VoidCallback onResume;
  final VoidCallback onReprint;
  final void Function(bool printed) onConfirm;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    final int total = state.copiesTotal;

    return switch (state.phase) {
      BorderManifestPrintPhase.loading => const Center(
          child: Padding(
            padding: EdgeInsets.all(AppSpacing.md),
            child: CircularProgressIndicator(),
          ),
        ),
      BorderManifestPrintPhase.ready => KoraButton(
          label: 'Imprimer $total exemplaires',
          icon: Icons.print,
          onPressed: onPrint,
        ),
      BorderManifestPrintPhase.printing => const KoraButton(
          label: 'Impression en cours…',
          icon: Icons.print,
          isLoading: true,
          onPressed: null,
        ),
      BorderManifestPrintPhase.interrupted => KoraButton(
          label: 'Reprendre (exemplaire ${state.copiesConfirmed + 1}/$total)',
          icon: Icons.replay,
          onPressed: onResume,
        ),
      BorderManifestPrintPhase.awaitingConfirmation => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            KoraButton(
              label: 'Oui, l\'exemplaire est sorti',
              icon: Icons.check,
              onPressed: () => onConfirm(true),
            ),
            const SizedBox(height: AppSpacing.sm),
            KoraButton(
              label: 'Non, il n\'est pas sorti',
              icon: Icons.close,
              variant: KoraButtonVariant.ghost,
              onPressed: () => onConfirm(false),
            ),
          ],
        ),
      BorderManifestPrintPhase.completed => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            const _Notice(
              message: 'Tous les exemplaires ont été imprimés.',
              color: AppColors.success,
            ),
            const SizedBox(height: AppSpacing.md),
            KoraButton(
              label: 'Réimprimer $total exemplaires',
              icon: Icons.print,
              variant: KoraButtonVariant.ghost,
              onPressed: onReprint,
            ),
          ],
        ),
      BorderManifestPrintPhase.refused => KoraButton(
          label: 'Rafraîchir le manifeste',
          icon: Icons.refresh,
          variant: KoraButtonVariant.ghost,
          onPressed: onRefresh,
        ),
    };
  }
}
