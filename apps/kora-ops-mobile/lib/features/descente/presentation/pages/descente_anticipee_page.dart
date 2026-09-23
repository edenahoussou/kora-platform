import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/widgets/app_state_placeholder.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/kora_skeleton.dart';
import '../../../../core/widgets/main_app_bar.dart';
import '../../../../core/widgets/seat_map.dart';
import '../../../../core/widgets/ticket_sheet.dart';
import '../../../manifeste/presentation/cubit/manifeste_cubit.dart';
import '../../../ops/data/mock_ops_data_source.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../../ops/domain/entities/seat.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/presentation/widgets/sync_status_banner.dart';

/// Onglet Descente anticipée : plan de bus du départ courant, sièges
/// libérés (descente anticipée acceptée ou billet refusé/no-show) en
/// vert — source de données réelle depuis MEZ-87 (`ManifesteCubit`,
/// déjà chargé pour ce départ, aucun rechargement séparé nécessaire).
///
/// Simplification assumée : contrairement à l'ancienne version mockée,
/// on ne distingue plus "se libère bientôt à tel arrêt" (orange) — cette
/// donnée (arrêt réel d'une descente anticipée) n'est pas conservée sur
/// `Passenger` aujourd'hui, seul le statut final l'est. Un siège est donc
/// soit occupé, soit déjà libéré.
class DescenteAnticipeePage extends StatelessWidget {
  const DescenteAnticipeePage({super.key});

  List<Seat> _seatsFromManifest(List<Passenger> passengers) {
    final List<Passenger> sorted = List<Passenger>.of(passengers)
      ..sort((Passenger a, Passenger b) => a.siege.compareTo(b.siege));

    return sorted.map((Passenger p) {
      final bool freed = p.statut == BoardingStatus.refuse ||
          p.statut == BoardingStatus.descenduAnticipe;
      return Seat(
        numero: p.siege,
        statut: freed ? SeatStatus.libere : SeatStatus.occupe,
        passengerName: freed ? null : p.nomComplet,
      );
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final Depart? depart = context.watch<SelectedDepartCubit>().state;
    final ManifesteState manifesteState = context.watch<ManifesteCubit>().state;

    return Scaffold(
      appBar: const MainAppBar(eyebrow: 'Gestion', title: 'Descente anticipée'),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              const Align(
                  alignment: Alignment.centerLeft, child: SyncStatusBanner()),
              const SizedBox(height: AppSpacing.sm),
              Expanded(
                child: depart == null
                    ? const AppStatePlaceholder.empty(
                        message:
                            'Sélectionnez un départ à gérer pour voir les places libérées.',
                      )
                    : switch (manifesteState.status) {
                        ManifesteStatus.loading =>
                          const SkeletonCard(height: 260),
                        ManifesteStatus.error => AppStatePlaceholder.error(
                            message: manifesteState.errorMessage ??
                                'Erreur inattendue.',
                            actionLabel: 'Réessayer',
                            onAction: () =>
                                context.read<ManifesteCubit>().load(),
                          ),
                        _ => _SeatMapView(
                            depart: depart,
                            passengers: manifesteState.passengers,
                            seats:
                                _seatsFromManifest(manifesteState.passengers),
                          ),
                      },
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SeatMapView extends StatelessWidget {
  const _SeatMapView({
    required this.depart,
    required this.passengers,
    required this.seats,
  });

  final Depart depart;
  final List<Passenger> passengers;
  final List<Seat> seats;

  /// Tap sur un siège (refonte UI/UX du 21/09/2026) : retrouve le
  /// passager qui s'y trouve — un siège déjà libéré (descente anticipée
  /// ou no-show) reste associé au passager qui l'occupait, le tap montre
  /// alors le billet de ce même passager, comme "Voir le billet" sur
  /// `DetailPassagerPage`. Un siège sans passager résolu (donnée
  /// incohérente) n'ouvre rien plutôt que planter.
  void _onSeatTap(BuildContext context, Seat seat) {
    final Passenger? passenger = passengers
        .cast<Passenger?>()
        .firstWhere((Passenger? p) => p?.siege == seat.numero,
            orElse: () => null);
    if (passenger == null) return;

    final ticket = MockOpsDataSource.ticketFor(passenger, depart.id);
    showTicketSheet(
      context,
      ticket: ticket,
      passengerName: passenger.nomComplet,
      route: depart.route,
      date: depart.date,
      heure: depart.heure,
    );
  }

  @override
  Widget build(BuildContext context) {
    final int liberes =
        seats.where((Seat s) => s.statut == SeatStatus.libere).length;

    return RefreshIndicator(
      onRefresh: () => context.read<ManifesteCubit>().load(),
      child: ListView(
        children: <Widget>[
          Text(depart.route, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: AppSpacing.md),
          KoraCard(
            child: SeatMap(
              seats: seats,
              onSeatTap: (Seat seat) => _onSeatTap(context, seat),
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          Text(
            '$liberes place(s) libérée(s) (descente anticipée ou no-show)',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
        ],
      ),
    );
  }
}
