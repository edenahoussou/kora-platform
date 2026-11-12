import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/app_text_styles.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/widgets/app_state_placeholder.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/kora_tag.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../injection/injection.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../domain/entities/trip_log.dart';
import '../cubit/trip_log_cubit.dart';
import '../trip_log_actions.dart';
import '../trip_log_formatters.dart';
import 'trip_expense_form_page.dart';

/// Carnet de bord du départ géré — chauffeur ET convoyeur : kilométrage
/// au départ et à l'arrivée, pleins de carburant (litres, prix, station,
/// photo du ticket), péages et taxes frontières. Accessible depuis
/// l'Accueil (tuile « Carnet »), le Manifeste (speed dial) ; le kilométrage
/// est aussi proposé automatiquement à « Bus parti » / « Arrivé ».
///
/// Fonctionne hors ligne : les saisies partent en file et sont envoyées au
/// retour du réseau (voir `TripLogRepository`, `SyncCubit`). Les dépenses
/// sont validées au bureau (KORA OPS Web › Finance › Dépenses).
class TripLogPage extends StatelessWidget {
  const TripLogPage({super.key});

  @override
  Widget build(BuildContext context) {
    final Depart? depart = context.watch<SelectedDepartCubit>().state;

    if (depart == null) {
      return const Scaffold(
        appBar:
            ScreenHeader(eyebrow: 'Carnet de bord', title: 'Carnet de bord'),
        body: AppStatePlaceholder.empty(
          message: 'Sélectionnez une course depuis l\'onglet Tournée.',
        ),
      );
    }

    return BlocProvider<TripLogCubit>(
      key: ValueKey<String>(depart.id),
      create: (_) => getIt<TripLogCubit>()..load(depart.id),
      child: _TripLogView(depart: depart),
    );
  }
}

class _TripLogView extends StatelessWidget {
  const _TripLogView({required this.depart});

  final Depart depart;

  Future<void> _editOdometer(BuildContext context, OdometerKind kind) async {
    final TripLog? updated = await promptOdometerReading(
      context,
      departureUuid: depart.id,
      kind: kind,
    );
    if (updated != null && context.mounted) {
      await context.read<TripLogCubit>().refresh();
    }
  }

  Future<void> _addExpense(BuildContext context, TripExpenseKind kind) async {
    final TripLog? log = context.read<TripLogCubit>().state.log;
    final bool? saved = await context.push<bool>(
      AppRoutes.tripExpenseForm,
      extra: TripExpenseFormArgs(
        departureUuid: depart.id,
        kind: kind,
        suggestedOdometerKm: log?.latestKm,
        busRegistration: log?.busRegistration ?? depart.vehiculeImmat,
      ),
    );
    if (saved == true && context.mounted) {
      await context.read<TripLogCubit>().refresh();
    }
  }

  @override
  Widget build(BuildContext context) {
    final KoraColors colors = context.koraColors;

    return Scaffold(
      backgroundColor: colors.background,
      appBar: ScreenHeader(eyebrow: depart.route, title: 'Carnet de bord'),
      body: SafeArea(
        child: BlocBuilder<TripLogCubit, TripLogState>(
          builder: (BuildContext context, TripLogState state) {
            final TripLog? log = state.log;

            if (log == null) {
              return const Center(child: CircularProgressIndicator());
            }

            return RefreshIndicator(
              onRefresh: () => context.read<TripLogCubit>().refresh(),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(AppSpacing.md),
                children: <Widget>[
                  if (state.isLoading)
                    const Padding(
                      padding: EdgeInsets.only(bottom: AppSpacing.sm),
                      child: LinearProgressIndicator(minHeight: 2),
                    ),
                  if (state.message != null)
                    _Banner(
                      icon: Icons.cloud_off_rounded,
                      color: AppColors.warning,
                      text: state.message!,
                    ),
                  if (log.pendingSyncCount > 0)
                    _Banner(
                      icon: Icons.sync_rounded,
                      color: AppColors.digital,
                      text: '${log.pendingSyncCount} saisie(s) hors ligne — '
                          'envoi automatique au retour du réseau.',
                    ),
                  _OdometerCard(
                    log: log,
                    busRegistration:
                        log.busRegistration ?? depart.vehiculeImmat,
                    onEdit: (OdometerKind kind) => _editOdometer(context, kind),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  Text('Dépenses de voyage',
                      style: AppTextStyles.heading2(colors.textPrimary)),
                  const SizedBox(height: 4),
                  Text(
                    'Envoyées au bureau pour validation.',
                    style: AppTextStyles.caption(colors.textMuted),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: _ActionTile(
                          icon: Icons.local_gas_station_rounded,
                          label: 'Plein carburant',
                          highlighted: true,
                          onTap: () =>
                              _addExpense(context, TripExpenseKind.fuel),
                        ),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: _ActionTile(
                          icon: Icons.toll_rounded,
                          label: 'Péage / taxe frontière',
                          onTap: () =>
                              _addExpense(context, TripExpenseKind.toll),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.md),
                  if (log.expenses.isEmpty)
                    Padding(
                      padding:
                          const EdgeInsets.symmetric(vertical: AppSpacing.lg),
                      child: Text(
                        'Aucune dépense déclarée pour ce voyage.',
                        textAlign: TextAlign.center,
                        style: AppTextStyles.body(colors.textMuted),
                      ),
                    )
                  else
                    ...log.expenses.map(
                      (TripExpense expense) => Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                        child: _ExpenseTile(expense: expense),
                      ),
                    ),
                  const SizedBox(height: AppSpacing.xl),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({required this.icon, required this.color, required this.text});

  final IconData icon;
  final Color color;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.sm),
      padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md, vertical: AppSpacing.sm),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Row(
        children: <Widget>[
          Icon(icon, size: 18, color: color),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(text,
                style: TextStyle(
                    color: color, fontSize: 12, fontWeight: FontWeight.w600)),
          ),
        ],
      ),
    );
  }
}

class _OdometerCard extends StatelessWidget {
  const _OdometerCard({
    required this.log,
    required this.onEdit,
    this.busRegistration,
  });

  final TripLog log;
  final String? busRegistration;
  final void Function(OdometerKind kind) onEdit;

  @override
  Widget build(BuildContext context) {
    final KoraColors colors = context.koraColors;
    final int? distance = log.distanceKm;

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const Icon(Icons.speed_rounded, color: AppColors.gold),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text('Kilométrage',
                    style: AppTextStyles.titleMedium(colors.textPrimary)),
              ),
              if (busRegistration != null && busRegistration!.isNotEmpty)
                KoraTag(
                  label: busRegistration!,
                  color: AppColors.digital,
                  icon: Icons.directions_bus_rounded,
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          _OdometerRow(
            kind: OdometerKind.start,
            reading: log.start,
            onEdit: () => onEdit(OdometerKind.start),
          ),
          Divider(color: colors.line, height: AppSpacing.lg),
          _OdometerRow(
            kind: OdometerKind.end,
            reading: log.end,
            onEdit: () => onEdit(OdometerKind.end),
          ),
          if (distance != null) ...<Widget>[
            Divider(color: colors.line, height: AppSpacing.lg),
            Row(
              children: <Widget>[
                Text('Distance parcourue',
                    style: AppTextStyles.body(colors.textMuted)),
                const Spacer(),
                Text(
                  formatKm(distance),
                  style: AppTextStyles.titleMedium(colors.textPrimary),
                ),
              ],
            ),
          ],
          if (log.start == null && log.lastKnownOdometerKm != null) ...<Widget>[
            const SizedBox(height: AppSpacing.sm),
            Text(
              'Dernier relevé connu du bus : '
              '${formatKm(log.lastKnownOdometerKm!)}',
              style: AppTextStyles.caption(colors.textMuted),
            ),
          ],
        ],
      ),
    );
  }
}

class _OdometerRow extends StatelessWidget {
  const _OdometerRow({
    required this.kind,
    required this.reading,
    required this.onEdit,
  });

  final OdometerKind kind;
  final OdometerReading? reading;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final KoraColors colors = context.koraColors;
    final OdometerReading? r = reading;

    return InkWell(
      onTap: onEdit,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: <Widget>[
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(kind.label,
                      style: AppTextStyles.caption(colors.textMuted)),
                  const SizedBox(height: 2),
                  Text(
                    r == null ? 'À saisir' : formatKm(r.valueKm),
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: r == null ? AppColors.warning : colors.textPrimary,
                    ),
                  ),
                  if (r != null)
                    Text(
                      <String>[
                        formatShortDateTime(r.recordedAt),
                        if (r.recordedBy != null) r.recordedBy!,
                        if (r.pendingSync) 'hors ligne',
                      ].join(' · '),
                      style: AppTextStyles.caption(colors.textMuted),
                    ),
                ],
              ),
            ),
            TextButton.icon(
              onPressed: onEdit,
              icon: Icon(r == null ? Icons.add_rounded : Icons.edit_rounded,
                  size: 18),
              label: Text(r == null ? 'Saisir' : 'Corriger'),
            ),
          ],
        ),
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.highlighted = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool highlighted;

  @override
  Widget build(BuildContext context) {
    final KoraColors colors = context.koraColors;

    return Material(
      color: colors.surface,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(
              vertical: AppSpacing.md, horizontal: AppSpacing.sm),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: Border.all(
              color: highlighted ? AppColors.gold : colors.line,
              width: highlighted ? 1.5 : 1,
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(icon,
                  size: 26,
                  color: highlighted ? AppColors.gold : colors.textPrimary),
              const SizedBox(height: 6),
              Text(
                label,
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: colors.textPrimary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ExpenseTile extends StatelessWidget {
  const _ExpenseTile({required this.expense});

  final TripExpense expense;

  Color get _statusColor {
    if (expense.pendingSync) return AppColors.digital;
    return switch (expense.status) {
      'approved' => AppColors.success,
      'rejected' || 'reversed' => AppColors.danger,
      _ => AppColors.warning,
    };
  }

  IconData get _icon => switch (expense.kind) {
        TripExpenseKind.fuel => Icons.local_gas_station_rounded,
        TripExpenseKind.toll => Icons.toll_rounded,
        TripExpenseKind.borderTax => Icons.flag_rounded,
      };

  @override
  Widget build(BuildContext context) {
    final KoraColors colors = context.koraColors;
    final List<String> details = <String>[
      formatShortDateTime(expense.occurredAt),
      if (expense.kind == TripExpenseKind.fuel && expense.liters != null)
        formatLiters(expense.liters!),
      if (expense.kind == TripExpenseKind.fuel && expense.unitPrice != null)
        '${formatInteger(expense.unitPrice!)} FCFA/L',
      if (expense.kind != TripExpenseKind.fuel && expense.countryCode != null)
        expense.countryCode!,
      if (expense.odometerKm != null) formatKm(expense.odometerKm!),
    ];

    return KoraCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: colors.background,
              shape: BoxShape.circle,
            ),
            child: Icon(_icon, size: 20, color: colors.textPrimary),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  '${expense.kind.label} · ${expense.placeLabel}',
                  style: AppTextStyles.titleMedium(colors.textPrimary),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(details.join(' · '),
                    style: AppTextStyles.caption(colors.textMuted)),
                if (expense.submittedBy != null)
                  Text('Saisi par ${expense.submittedBy}',
                      style: AppTextStyles.caption(colors.textMuted)),
                if (expense.rejectionReason != null &&
                    expense.status == 'rejected')
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      'Motif : ${expense.rejectionReason}',
                      style: const TextStyle(
                          color: AppColors.danger,
                          fontSize: 12,
                          fontWeight: FontWeight.w600),
                    ),
                  ),
                const SizedBox(height: 6),
                Row(
                  children: <Widget>[
                    KoraTag(label: expense.statusLabel, color: _statusColor),
                    if (expense.hasPhoto) ...<Widget>[
                      const SizedBox(width: 6),
                      Icon(Icons.photo_camera_rounded,
                          size: 14, color: colors.textMuted),
                    ],
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Text(
            formatFcfa(expense.amount),
            style: AppTextStyles.titleMedium(colors.textPrimary),
          ),
        ],
      ),
    );
  }
}
