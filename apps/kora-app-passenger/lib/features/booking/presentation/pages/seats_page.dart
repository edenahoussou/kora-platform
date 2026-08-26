import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/seat.dart';
import '../bloc/seat_hold_bloc.dart';
import '../bloc/seats_bloc.dart';
import '../widgets/booking_progress.dart';
import '../widgets/hold_countdown.dart';
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
                    // Le véhicule réel (`KORA Coach 55`), et non la gamme
                    // commerciale : l'API n'expose pas de gamme, et
                    // `comfort` n'est qu'un repli pour les écrans qui
                    // n'en ont pas d'autre.
                    eyebrow: departure == null
                        ? 'KORA BUS'
                        : '${departure.vehicleLabel} · '
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
        const HoldCountdown(margin: EdgeInsets.only(bottom: 11)),
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
          _ContinueButton(state: state),
        ],
      ),
    );
  }
}

/// Le bouton qui engage la réservation, et ce qui l'accompagne selon que
/// des sièges sont déjà retenus ou non.
///
/// Trois situations, trois comportements :
/// * **rien de retenu** — « Continuer » bloque les sièges puis mène au
///   formulaire passagers ;
/// * **blocage en cours** — le bouton attend la réponse du serveur ;
/// * **sièges retenus** — on revient sur ses pas : « Continuer » est
///   grisé, et il faut d'abord annuler pour choisir autrement. Le laisser
///   actif aurait bloqué une seconde fois les mêmes places, ou d'autres,
///   sans jamais rendre les premières.
class _ContinueButton extends StatelessWidget {
  const _ContinueButton({required this.state});

  final SeatsState state;

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<SeatHoldBloc, SeatHoldState>(
      listenWhen: (previous, current) => previous.status != current.status,
      listener: (context, hold) {
        if (hold.status == SeatHoldStatus.held) {
          context.push(
            AppRouter.passengerInfoPath,
            extra: PassengerFormArgs(
              departure: state.departure!,
              seats: state.selectedSeats,
            ),
          );
          return;
        }

        final failure = hold.failure;
        if (hold.status != SeatHoldStatus.failure || failure == null) return;

        KoraToaster.show(context, failure.message);

        // Un siège que le serveur vient d'attribuer à quelqu'un d'autre
        // doit le montrer sur le plan : le laisser en « votre choix »
        // inviterait à retaper « Continuer » sur une place perdue.
        if (failure is SeatUnavailableFailure) {
          context.read<SeatsBloc>().add(
            SeatRefused(seatId: failure.seatId, seatLabel: failure.seatLabel),
          );
        }
      },
      builder: (context, hold) {
        final l10n = context.l10n;

        if (hold.isHeld) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              KoraButton(
                label: l10n.seatsHoldRelease,
                variant: KoraButtonVariant.secondary,
                onPressed: () =>
                    context.read<SeatHoldBloc>().add(const SeatHoldReleased()),
              ),
              const SizedBox(height: 6),
              Text(
                l10n.seatsHoldLocked,
                textAlign: TextAlign.center,
                style: AppTextStyles.caption(context.koraColors.textMuted),
              ),
            ],
          );
        }

        return KoraButton(
          label: 'Continuer',
          // `.seat-checkout button { justify-content: space-between }` :
          // le libellé est calé à gauche, la flèche à droite.
          icon: Icons.arrow_forward_rounded,
          iconPosition: KoraButtonIconPosition.edges,
          isLoading: hold.status == SeatHoldStatus.holding,
          // Désactivé tant qu'il manque un siège : la maquette laisse le
          // bouton actif et affiche un message d'erreur au clic, ce qui
          // fait faire un aller-retour pour rien.
          onPressed: state.canContinue
              ? () => context.read<SeatHoldBloc>().add(
                  SeatHoldRequested(
                    departure: state.departure!,
                    seats: state.selectedSeats,
                  ),
                )
              : null,
        );
      },
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
