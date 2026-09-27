import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/app_text_styles.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/errors/failures.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/app_state_placeholder.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/kora_skeleton.dart';
import '../../../../core/widgets/main_app_bar.dart';
import '../../../../injection/injection.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/domain/repositories/departure_repository.dart';
import '../../../ops/presentation/cubit/departure_list_cubit.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/presentation/widgets/sync_status_banner.dart';

/// Onglet Tournée : départs réellement affectés au convoyeur connecté
/// (MEZ-92 — voir `DepartureListCubit`, `GET /ops/departures`). Sélectionner
/// une course charge son détail complet (arrêts, équipage — `GET /ops/
/// departures/{uuid}`), l'enregistre comme départ géré et ouvre son
/// Manifeste.
///
/// Vue par défaut : les départs du JOUR uniquement (voir
/// `DepartureListCubit.loadUpcomingToday`) — pour un autre jour, voir le
/// FAB. Seuls les départs du JOUR sont cliquables/actionnables (échange du
/// 26/09/2026) — le reste (consultation d'un autre jour via le filtre)
/// reste en lecture seule : on ne gère jamais un départ d'un autre jour
/// depuis cet écran.
class TourneePage extends StatefulWidget {
  const TourneePage({super.key});

  @override
  State<TourneePage> createState() => _TourneePageState();
}

class _TourneePageState extends State<TourneePage> {
  /// UUID du départ dont le détail est en cours de chargement (affiche un
  /// indicateur sur sa seule ligne) — `null` si aucune sélection en cours.
  String? _selectingId;

  @override
  void initState() {
    super.initState();
    context.read<DepartureListCubit>().loadUpcomingToday();
  }

  /// `Depart.date` est déjà formaté `dd/MM/yyyy` (voir `DepartureDtos`) —
  /// comparaison directe avec la date du jour formatée pareil, pas besoin
  /// de reparser.
  String _todayFormatted() {
    final DateTime now = DateTime.now();
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(now.day)}/${two(now.month)}/${now.year}';
  }

  Future<void> _select(Depart listRow) async {
    setState(() => _selectingId = listRow.id);

    final result = await getIt<DepartureRepository>().show(listRow.id);

    if (!mounted) return;
    setState(() => _selectingId = null);

    await result.when(
      onSuccess: (Depart detail) async {
        await context.read<SelectedDepartCubit>().select(detail);
        if (mounted) await context.push(AppRoutes.manifeste);
      },
      onFailure: (failure) async {
        // Hors ligne ou serveur indisponible : on ne bloque pas l'agent,
        // on continue avec les champs déjà connus depuis la liste (ligne,
        // date, heure, bus) — arrêts/équipage resteront vides jusqu'au
        // prochain chargement réussi.
        if (failure is NetworkFailure || failure is ServerFailure) {
          await context.read<SelectedDepartCubit>().select(listRow);
          if (mounted) await context.push(AppRoutes.manifeste);
          return;
        }
        await KoraDialogs.error(
          context,
          title: 'Départ indisponible',
          description: failure.message,
        );
      },
    );
  }

  Future<void> _openDateFilter() async {
    final DepartureListCubit cubit = context.read<DepartureListCubit>();
    final DateTime? current = cubit.state.selectedDate;

    final _DateFilterChoice? choice =
        await showModalBottomSheet<_DateFilterChoice>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (BuildContext sheetContext) =>
          _DateFilterSheet(isFiltered: current != null),
    );

    if (!mounted || choice == null) return;

    switch (choice) {
      case _DateFilterChoice.today:
        await cubit.clearDateFilter();
      case _DateFilterChoice.pick:
        final DateTime now = DateTime.now();
        final DateTime? picked = await showDatePicker(
          context: context,
          initialDate: current ?? now,
          firstDate: now.subtract(const Duration(days: 365)),
          lastDate: now.add(const Duration(days: 365)),
        );
        if (picked != null) await cubit.load(picked);
    }
  }

  @override
  Widget build(BuildContext context) {
    final Depart? current = context.watch<SelectedDepartCubit>().state;
    final String today = _todayFormatted();

    return Scaffold(
      appBar: const MainAppBar(eyebrow: 'Vos courses', title: 'Tournée'),
      floatingActionButton: Padding(
        // Le Scaffold parent (`_ScaffoldWithBottomNav`, voir app_router.dart)
        // utilise `extendBody: true` pour que `KoraBottomNav` flotte par-
        // dessus le contenu — un FAB positionné normalement (16px du bas de
        // CE Scaffold) se retrouve donc entièrement caché derrière elle.
        // Décalage manuel pour le faire apparaître au-dessus (échange du
        // 26/09/2026 : "je ne vois pas où le filtre se trouve") + libellé
        // explicite plutôt qu'une simple icône, pour qu'il soit repérable
        // du premier coup d'œil.
        padding: const EdgeInsets.only(bottom: 88),
        child: FloatingActionButton.extended(
          onPressed: _openDateFilter,
          icon: const Icon(Icons.filter_alt_outlined),
          label: const Text('Filtrer'),
          backgroundColor: AppColors.gold,
        ),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              const SizedBox(height: AppSpacing.sm),
              const Align(
                  alignment: Alignment.centerLeft, child: SyncStatusBanner()),
              const SizedBox(height: AppSpacing.sm),
              BlocBuilder<DepartureListCubit, DepartureListState>(
                buildWhen: (a, b) => a.selectedDate != b.selectedDate,
                builder: (context, state) {
                  final DateTime? selectedDate = state.selectedDate;
                  if (selectedDate == null) return const SizedBox.shrink();

                  String two(int n) => n.toString().padLeft(2, '0');
                  final String label =
                      '${two(selectedDate.day)}/${two(selectedDate.month)}/${selectedDate.year}';

                  return Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: Material(
                        color: AppColors.gold.withValues(alpha: 0.14),
                        borderRadius: BorderRadius.circular(AppRadius.pill),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(AppRadius.pill),
                          onTap: () => context
                              .read<DepartureListCubit>()
                              .clearDateFilter(),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(
                                horizontal: AppSpacing.sm, vertical: 6),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: <Widget>[
                                const Icon(Icons.event_outlined,
                                    size: 16, color: AppColors.kora),
                                const SizedBox(width: 6),
                                Text(
                                  'Filtré : $label',
                                  style: const TextStyle(
                                      color: AppColors.kora,
                                      fontWeight: FontWeight.w700,
                                      fontSize: 12),
                                ),
                                const SizedBox(width: 6),
                                const Icon(Icons.close_rounded,
                                    size: 16, color: AppColors.kora),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  );
                },
              ),
              Expanded(
                child: BlocBuilder<DepartureListCubit, DepartureListState>(
                  builder: (context, state) {
                    return switch (state.status) {
                      DepartureListStatus.loading =>
                        const SkeletonList(itemCount: 4),
                      DepartureListStatus.empty => AppStatePlaceholder.empty(
                          message: state.selectedDate == null
                              ? 'Aucun départ ne vous est affecté aujourd\'hui.'
                              : 'Aucun départ ce jour-là.',
                        ),
                      DepartureListStatus.error => AppStatePlaceholder.error(
                          message: state.errorMessage ??
                              'Impossible de charger vos départs.',
                          actionLabel: 'Réessayer',
                          onAction: () =>
                              context.read<DepartureListCubit>().retry(),
                        ),
                      DepartureListStatus.success => ListView.separated(
                          itemCount: state.departs.length,
                          separatorBuilder: (_, __) =>
                              const SizedBox(height: AppSpacing.sm),
                          itemBuilder: (BuildContext context, int index) {
                            final Depart depart = state.departs[index];
                            final bool isSelected = depart.id == current?.id;
                            final bool isSelecting = _selectingId == depart.id;
                            // Seul le départ du JOUR est actionnable — les
                            // autres (consultation d'un autre jour via le
                            // filtre) restent consultables mais pas
                            // cliquables.
                            final bool isToday = depart.date == today;

                            return Opacity(
                              opacity: isToday ? 1 : 0.55,
                              child: KoraCard(
                                variant: isSelected
                                    ? KoraCardVariant.gold
                                    : KoraCardVariant.surface,
                                onTap: isToday && _selectingId == null
                                    ? () => _select(depart)
                                    : null,
                                child: Row(
                                  children: <Widget>[
                                    Icon(
                                      Icons.directions_bus_filled,
                                      color: isSelected
                                          ? AppColors.onGold
                                          : context.koraColors.textPrimary,
                                    ),
                                    const SizedBox(width: AppSpacing.md),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: <Widget>[
                                          // "Ville de départ → Ville
                                          // d'arrivée" (échange du
                                          // 26/09/2026) — voir
                                          // `DepartureDtos._fromJson`, qui
                                          // dérive `route` des arrêts.
                                          Text(
                                            depart.route,
                                            style: Theme.of(context)
                                                .textTheme
                                                .titleMedium
                                                ?.copyWith(
                                                  color: isSelected
                                                      ? AppColors.onGold
                                                      : null,
                                                ),
                                          ),
                                          const SizedBox(height: 2),
                                          Text(
                                            '${depart.date} · ${depart.heure} · ${depart.vehiculeImmat}',
                                            style: TextStyle(
                                              color: isSelected
                                                  ? AppColors.onGold
                                                      .withValues(alpha: 0.7)
                                                  : context
                                                      .koraColors.textMuted,
                                              fontSize: 12,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    if (isSelecting)
                                      const SizedBox(
                                        width: 18,
                                        height: 18,
                                        child: CircularProgressIndicator(
                                            strokeWidth: 2),
                                      )
                                    else if (!isToday)
                                      Icon(Icons.lock_outline,
                                          size: 18,
                                          color: context.koraColors.textMuted)
                                    else ...<Widget>[
                                      Text(
                                        '${depart.totalPlaces} places',
                                        style: TextStyle(
                                          fontWeight: FontWeight.w700,
                                          color: isSelected
                                              ? AppColors.onGold
                                              : context.koraColors.textPrimary,
                                        ),
                                      ),
                                      Icon(
                                        Icons.chevron_right,
                                        color: isSelected
                                            ? AppColors.onGold
                                            : context.koraColors.textMuted,
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                    };
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

enum _DateFilterChoice { today, pick }

/// Bottom sheet "flottant" du filtre par date : coins arrondis sur les 4
/// côtés (pas seulement en haut) avec une marge autour, dans le style des
/// cartes de l'app (voir `_AccountTile` dans `ProfilPage`) plutôt qu'une
/// liste Material par défaut.
class _DateFilterSheet extends StatelessWidget {
  const _DateFilterSheet({required this.isFiltered});

  final bool isFiltered;

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.sm, 0, AppSpacing.sm, AppSpacing.sm),
        child: Container(
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            color: koraColors.surface,
            borderRadius: BorderRadius.circular(AppRadius.xl),
            boxShadow: <BoxShadow>[
              BoxShadow(
                  color: koraColors.shadow,
                  blurRadius: 24,
                  offset: const Offset(0, -4)),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: AppSpacing.md),
                  decoration: BoxDecoration(
                    color: koraColors.line,
                    borderRadius: BorderRadius.circular(AppRadius.pill),
                  ),
                ),
              ),
              Text('Filtrer la Tournée',
                  style: AppTextStyles.heading2(koraColors.textPrimary)),
              const SizedBox(height: 2),
              Text(
                'Choisissez les départs à afficher.',
                style: TextStyle(color: koraColors.textMuted, fontSize: 12),
              ),
              const SizedBox(height: AppSpacing.md),
              _SheetOption(
                icon: Icons.today_rounded,
                iconBackground: AppColors.gold.withValues(alpha: 0.15),
                iconColor: AppColors.gold,
                title: 'Aujourd\'hui',
                subtitle: 'Vos départs du jour — vue par défaut',
                selected: !isFiltered,
                onTap: () => Navigator.pop(context, _DateFilterChoice.today),
              ),
              const SizedBox(height: AppSpacing.sm),
              _SheetOption(
                icon: Icons.calendar_month_rounded,
                iconBackground: AppColors.digital.withValues(alpha: 0.12),
                iconColor: AppColors.digital,
                title: 'Choisir une date',
                subtitle: 'Consulter un autre jour, en lecture seule',
                selected: isFiltered,
                onTap: () => Navigator.pop(context, _DateFilterChoice.pick),
              ),
              SizedBox(
                  height: MediaQuery.of(context).padding.bottom == 0
                      ? AppSpacing.xs
                      : 0),
            ],
          ),
        ),
      ),
    );
  }
}

class _SheetOption extends StatelessWidget {
  const _SheetOption({
    required this.icon,
    required this.iconBackground,
    required this.iconColor,
    required this.title,
    required this.subtitle,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final Color iconBackground;
  final Color iconColor;
  final String title;
  final String subtitle;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;

    return Material(
      color: selected
          ? AppColors.gold.withValues(alpha: 0.08)
          : koraColors.background,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: Border.all(
              color: selected ? AppColors.gold : Colors.transparent,
              width: 1.5,
            ),
          ),
          child: Row(
            children: <Widget>[
              Container(
                width: 42,
                height: 42,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: iconBackground,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(icon, size: 20, color: iconColor),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(title, style: Theme.of(context).textTheme.titleMedium),
                    Text(subtitle,
                        style: TextStyle(
                            color: koraColors.textMuted, fontSize: 12)),
                  ],
                ),
              ),
              if (selected)
                const Icon(Icons.check_circle_rounded,
                    color: AppColors.gold, size: 22),
            ],
          ),
        ),
      ),
    );
  }
}
