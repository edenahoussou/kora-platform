import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/seat.dart';
import '../bloc/seats_bloc.dart';
import '../widgets/booking_progress.dart';
import '../widgets/state_view.dart';
import 'passenger_form_page.dart';

/// Données passées à [SeatsPage] par `go_router` (`extra`) — même principe
/// que le détail : le départ vient d'être affiché, on le transmet.
class SeatsArgs {
  const SeatsArgs({required this.departure, this.passengers = 1});

  final Departure departure;
  final int passengers;
}

/// Choix du siège — écran `seats` de la maquette : progression de la
/// réservation, rappel de la protection de la sélection, plan du bus, puis
/// la barre de validation.
class SeatsPage extends StatelessWidget {
  const SeatsPage({super.key, required this.departure, this.passengers = 1});

  /// `null` si l'écran est ouvert sans données (lien profond).
  final Departure? departure;

  final int passengers;

  @override
  Widget build(BuildContext context) {
    final departure = this.departure;

    if (departure == null) {
      return const Scaffold(
        body: SafeArea(
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: 18),
            child: _MissingDeparture(),
          ),
        ),
      );
    }

    return BlocProvider<SeatsBloc>(
      create: (_) =>
          getIt<SeatsBloc>()
            ..add(SeatsRequested(departure: departure, passengers: passengers)),
      child: const _SeatsView(),
    );
  }
}

class _SeatsView extends StatelessWidget {
  const _SeatsView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: BlocBuilder<SeatsBloc, SeatsState>(
          builder: (context, state) {
            final departure = state.departure;

            return Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ScreenHeader(
                    eyebrow: departure == null
                        ? 'KORA BUS'
                        : '${departure.comfort.label} · Bus '
                              '${departure.seatsTotal} places',
                    title: 'Choisissez votre siège',
                  ),
                  Expanded(
                    child: switch (state.status) {
                      SeatsStatus.loading => const Center(
                        child: KoraLoadingIndicator(),
                      ),
                      SeatsStatus.failure => _SeatsError(state: state),
                      SeatsStatus.success => _SeatsContent(state: state),
                    },
                  ),
                  if (state.status == SeatsStatus.success)
                    _Checkout(state: state),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class _SeatsContent extends StatelessWidget {
  const _SeatsContent({required this.state});

  final SeatsState state;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        const BookingProgress(step: 3, label: 'Votre siège'),
        const SizedBox(height: 11),
        const _ProtectionNotice(),
        const SizedBox(height: 11),
        SeatMap(
          seats: [
            for (final seat in state.seats)
              KoraSeat(
                label: seat.label,
                status: _statusOf(seat, isSelected: state.isSelected(seat)),
              ),
          ],
          onSeatTap: (koraSeat) {
            // `SeatMap` rend des sièges d'affichage ; on retrouve le siège
            // métier correspondant par son libellé, unique dans un bus.
            final seat = state.seats.firstWhere(
              (candidate) => candidate.label == koraSeat.label,
            );
            context.read<SeatsBloc>().add(SeatToggled(seat));
          },
        ),
        const SizedBox(height: AppSpacing.lg),
      ],
    );
  }

  /// Traduit l'état métier d'un siège en état d'affichage.
  ///
  /// « Choisi » n'existe que côté écran : c'est la sélection en cours, pas
  /// une information venue du réseau.
  KoraSeatStatus _statusOf(Seat seat, {required bool isSelected}) {
    if (isSelected) return KoraSeatStatus.selected;

    return switch (seat.status) {
      SeatStatus.free => KoraSeatStatus.available,
      SeatStatus.occupied => KoraSeatStatus.paid,
      SeatStatus.embarked => KoraSeatStatus.embarked,
      SeatStatus.held => KoraSeatStatus.held,
      SeatStatus.blocked => KoraSeatStatus.blocked,
    };
  }
}

/// `.seat-reservation-clock` : le rappel que la sélection est protégée.
///
/// La maquette y fait défiler un compte à rebours de 7 minutes, avec un
/// écran dédié à son expiration. Ce parcours n'existe pas encore : plutôt
/// qu'un minuteur décoratif qui ne se passerait rien à zéro, on annonce la
/// règle sans l'égrener.
class _ProtectionNotice extends StatelessWidget {
  const _ProtectionNotice();

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(AppRadius.input),
      ),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: const BoxDecoration(
              color: AppColors.gold,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.schedule_rounded,
              size: 18,
              color: AppColors.kora,
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Votre sélection est protégée',
                  style: TextStyle(color: koraColors.textMuted, fontSize: 9),
                ),
                const SizedBox(height: 2),
                Text(
                  '7 minutes pour finaliser la réservation',
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// `.seat-checkout` : la barre de validation, toujours visible en bas.
class _Checkout extends StatelessWidget {
  const _Checkout({required this.state});

  final SeatsState state;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    final labels = state.selectedSeats.map((seat) => seat.label).join(', ');

    return Container(
      margin: const EdgeInsets.only(top: 11, bottom: AppSpacing.md),
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(19),
        border: Border.all(color: koraColors.line),
      ),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _CheckoutSlot(
                  label: state.passengers > 1
                      ? 'Sièges sélectionnés'
                      : 'Siège sélectionné',
                  value: labels.isEmpty
                      ? 'Aucun siège · 0/${state.passengers}'
                      : '$labels · ${state.selectedSeats.length}/'
                            '${state.passengers}',
                ),
              ),
              const SizedBox(width: 9),
              _CheckoutSlot(
                label: 'Total',
                value: state.total.formatted,
                alignEnd: true,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          KoraButton(
            label: 'Continuer',
            // `.seat-checkout button { justify-content: space-between }` :
            // le libellé est calé à gauche, la flèche à droite.
            icon: Icons.arrow_forward_rounded,
            iconPosition: KoraButtonIconPosition.edges,
            // Désactivé tant qu'il manque un siège : la maquette laisse le
            // bouton actif et affiche un message d'erreur au clic, ce qui
            // fait faire un aller-retour pour rien.
            onPressed: state.canContinue
                ? () => context.push(
                    AppRouter.passengerInfoPath,
                    extra: PassengerFormArgs(
                      departure: state.departure!,
                      seats: state.selectedSeats,
                    ),
                  )
                : null,
          ),
        ],
      ),
    );
  }
}

class _CheckoutSlot extends StatelessWidget {
  const _CheckoutSlot({
    required this.label,
    required this.value,
    this.alignEnd = false,
  });

  final String label;
  final String value;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: alignEnd
          ? CrossAxisAlignment.end
          : CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          label.toUpperCase(),
          style: TextStyle(
            color: koraColors.textMuted,
            fontSize: 8,
            fontWeight: FontWeight.w800,
            letterSpacing: 0.56,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: 12,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _SeatsError extends StatelessWidget {
  const _SeatsError({required this.state});

  final SeatsState state;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        StateView(
          icon: Icons.cloud_off_rounded,
          title: 'Plan des sièges indisponible',
          message: state.errorMessage ?? 'Une erreur inattendue est survenue.',
          primaryLabel: 'Retour au départ',
          onPrimary: () => Navigator.of(context).pop(),
        ),
      ],
    );
  }
}

class _MissingDeparture extends StatelessWidget {
  const _MissingDeparture();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        const ScreenHeader(eyebrow: 'KORA BUS', title: 'Départ introuvable'),
        StateView(
          icon: Icons.help_outline_rounded,
          title: 'Ce départ n’est plus disponible',
          message: 'Reprenez la recherche pour voir les départs du jour.',
          primaryLabel: 'Retour aux départs',
          onPrimary: () => Navigator.of(context).pop(),
        ),
      ],
    );
  }
}
