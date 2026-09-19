import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
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
    context.read<DepartureListCubit>().load();
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

  @override
  Widget build(BuildContext context) {
    final Depart? current = context.watch<SelectedDepartCubit>().state;

    return Scaffold(
      appBar: const MainAppBar(eyebrow: 'Vos courses', title: 'Tournée'),
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
              Expanded(
                child: BlocBuilder<DepartureListCubit, DepartureListState>(
                  builder: (context, state) {
                    return switch (state.status) {
                      DepartureListStatus.loading =>
                        const SkeletonList(itemCount: 4),
                      DepartureListStatus.empty =>
                        const AppStatePlaceholder.empty(
                          message:
                              'Aucun départ ne vous est affecté pour le moment.',
                        ),
                      DepartureListStatus.error => AppStatePlaceholder.error(
                          message: state.errorMessage ??
                              'Impossible de charger vos départs.',
                          actionLabel: 'Réessayer',
                          onAction: () =>
                              context.read<DepartureListCubit>().load(),
                        ),
                      DepartureListStatus.success => ListView.separated(
                          itemCount: state.departs.length,
                          separatorBuilder: (_, __) =>
                              const SizedBox(height: AppSpacing.sm),
                          itemBuilder: (BuildContext context, int index) {
                            final Depart depart = state.departs[index];
                            final bool isSelected = depart.id == current?.id;
                            final bool isSelecting = _selectingId == depart.id;

                            return KoraCard(
                              variant: isSelected
                                  ? KoraCardVariant.gold
                                  : KoraCardVariant.surface,
                              onTap: _selectingId == null
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
                                                : context.koraColors.textMuted,
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
