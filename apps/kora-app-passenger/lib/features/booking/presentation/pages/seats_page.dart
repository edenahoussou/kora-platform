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
import '../../domain/entities/group_booking_limits.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/seat.dart';
import '../bloc/group_payer_cubit.dart';
import '../bloc/group_quote_cubit.dart';
import '../bloc/seat_hold_bloc.dart';
import '../bloc/seats_bloc.dart';
import '../widgets/booking_progress.dart';
import '../widgets/hold_countdown.dart';
import '../widgets/state_view.dart';
import 'passenger_form_page.dart';

/// Données passées à [SeatsPage] par `go_router` (`extra`) — même principe
/// que le détail : le départ vient d'être affiché, on le transmet.
class SeatsArgs {
  const SeatsArgs({
    required this.departure,
    this.passengers = 1,
    this.returnDeparture,
    this.outbound,
  });

  final Departure departure;
  final int passengers;

  /// Le retour à pourvoir après celui-ci, sur un aller-retour dont on
  /// choisit l'aller.
  final Departure? returnDeparture;

  /// L'aller déjà pourvu, quand cet écran sert le **retour**.
  final OutboundLeg? outbound;
}

/// L'aller d'un aller-retour, une fois ses sièges choisis.
///
/// Les deux trajets partent ensemble au serveur : il faut donc garder
/// l'aller sous la main pendant qu'on choisit le retour.
class OutboundLeg {
  const OutboundLeg({required this.departure, required this.seats});

  final Departure departure;
  final List<Seat> seats;
}

/// Choix du siège — écran `seats` de la maquette : progression de la
/// réservation, rappel de la protection de la sélection, plan du bus, puis
/// la barre de validation.
class SeatsPage extends StatelessWidget {
  const SeatsPage({
    super.key,
    required this.departure,
    this.passengers = 1,
    this.returnDeparture,
    this.outbound,
  });

  /// `null` si l'écran est ouvert sans données (lien profond).
  final Departure? departure;

  final int passengers;

  /// Voir [SeatsArgs.returnDeparture].
  final Departure? returnDeparture;

  /// Voir [SeatsArgs.outbound].
  final OutboundLeg? outbound;

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
      child: _SeatsView(returnDeparture: returnDeparture, outbound: outbound),
    );
  }
}

class _SeatsView extends StatelessWidget {
  const _SeatsView({this.returnDeparture, this.outbound});

  final Departure? returnDeparture;
  final OutboundLeg? outbound;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: BlocBuilder<SeatsBloc, SeatsState>(
          builder: (context, state) {
            final departure = state.departure;

            // Un retour reste à choisir, ou un aller l'a déjà été : dans
            // les deux cas le voyageur pourvoit un aller-retour.
            final isRoundTrip = returnDeparture != null || outbound != null;

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
                    // Sur un aller-retour, le titre nomme le trajet : deux
                    // plans de bus identiques à la file, le second passage
                    // ressemblait sinon à un retour en arrière du premier.
                    title: isRoundTrip
                        ? context.l10n.seatsTitleFor(
                            isOutbound: returnDeparture != null,
                            passengers: state.passengers,
                          )
                        : context.l10n.seatsTitle,
                  ),
                  // Le badge redit le sens à hauteur du plan, avec le
                  // trajet concerné : « Retour » seul obligerait à se
                  // souvenir d'où l'on revient.
                  if (isRoundTrip) ...[
                    _LegBadge(
                      isOutbound: returnDeparture != null,
                      departure: departure,
                    ),
                    const SizedBox(height: 11),
                  ],
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
                    _Checkout(
                      state: state,
                      returnDeparture: returnDeparture,
                      outbound: outbound,
                    ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

/// Quel trajet on est en train de pourvoir, sur un aller-retour.
///
/// Le badge navy porte le sens ; la ligne à côté rappelle le trajet
/// concerné, parce que « Retour » seul oblige à se souvenir d'où l'on
/// revient.
class _LegBadge extends StatelessWidget {
  const _LegBadge({required this.isOutbound, required this.departure});

  /// Vrai quand un retour reste à choisir : c'est donc l'aller qui est à
  /// l'écran.
  final bool isOutbound;

  final Departure? departure;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final line = departure?.line;

    return Row(
      children: [
        KoraTag(
          label: isOutbound ? l10n.seatsLegOutbound : l10n.seatsLegInbound,
          variant: KoraTagVariant.navy,
        ),
        if (line != null) ...[
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              '${line.origin.name} → ${line.destination.name}',
              overflow: TextOverflow.ellipsis,
              style: AppTextStyles.caption(context.koraColors.textMuted),
            ),
          ),
        ],
      ],
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
        BookingProgress(step: 3, label: context.l10n.seatsStep),
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
  const _Checkout({required this.state, this.returnDeparture, this.outbound});

  final SeatsState state;
  final Departure? returnDeparture;
  final OutboundLeg? outbound;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;

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
                      ? l10n.seatsSelectedMany
                      : l10n.seatsSelectedOne,
                  value: labels.isEmpty
                      ? l10n.seatsNone(state.passengers)
                      : l10n.seatsChosen(
                          labels,
                          state.selectedSeats.length,
                          state.passengers,
                        ),
                ),
              ),
              const SizedBox(width: 9),
              BlocBuilder<GroupQuoteCubit, GroupQuoteState>(
                builder: (context, quote) => _CheckoutSlot(
                  label: l10n.seatsTotal,
                  value: _payableTotal(state, quote).formatted,
                  alignEnd: true,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          _ContinueButton(
            state: state,
            returnDeparture: returnDeparture,
            outbound: outbound,
          ),
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
  const _ContinueButton({
    required this.state,
    this.returnDeparture,
    this.outbound,
  });

  final Departure? returnDeparture;
  final OutboundLeg? outbound;

  final SeatsState state;

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<SeatHoldBloc, SeatHoldState>(
      listenWhen: (previous, current) => previous.status != current.status,
      listener: (context, hold) {
        // Les deux plans d'un aller-retour écoutent le même bloc : sans ce
        // garde-fou, celui de l'aller — resté dans la pile — naviguerait
        // lui aussi au blocage déclenché depuis le retour.
        if (returnDeparture != null) return;

        if (hold.status == SeatHoldStatus.held) {
          // Sur un aller-retour, cet écran-ci montrait le retour : le
          // voyage se raconte au départ de l'aller.
          final leg = outbound;

          context.push(
            AppRouter.passengerInfoPath,
            extra: PassengerFormArgs(
              departure: leg?.departure ?? state.departure!,
              seats: leg?.seats ?? state.selectedSeats,
              returnDeparture: leg == null ? null : state.departure,
              returnSeats: leg == null ? null : state.selectedSeats,
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
          label: context.l10n.seatsContinue,
          // `.seat-checkout button { justify-content: space-between }` :
          // le libellé est calé à gauche, la flèche à droite.
          icon: Icons.arrow_forward_rounded,
          iconPosition: KoraButtonIconPosition.edges,
          isLoading: hold.status == SeatHoldStatus.holding,
          // Désactivé tant qu'il manque un siège : la maquette laisse le
          // bouton actif et affiche un message d'erreur au clic, ce qui
          // fait faire un aller-retour pour rien.
          onPressed: state.canContinue
              ? () => _continue(context, state, returnDeparture, outbound)
              : null,
        );
      },
    );
  }
}

/// Retient les sièges choisis, par le chemin qui convient.
///
/// Un groupe passe par le blocage **atomique** : ses N sièges partent
/// ensemble ou pas du tout, et il s'appuie sur le devis d'itinéraire déjà
/// obtenu — celui qui portait la remise montrée au voyageur. Sans ce devis
/// en mémoire, on retombe sur le chemin ordinaire plutôt que de bloquer le
/// parcours : le prix sera juste, à la remise près.
/// Le total que le voyageur réglera : celui du devis quand le serveur en a
/// rendu un, le tarif de la recherche multiplié sinon.
///
/// [SeatsState.total] ne connaît que le prix unitaire du départ : sur un
/// groupe il affichait le tarif plein, après deux écrans qui venaient
/// d'annoncer la remise. Le voyageur lisait 316 000 là où on lui avait
/// promis 284 400.
Price _payableTotal(SeatsState state, GroupQuoteState quote) {
  final departure = state.departure;
  if (departure == null) return state.total;

  if (quote.matches(departure.id, state.passengers)) {
    final total = quote.quote?.total;
    if (total != null) return total;
  }

  return state.total;
}

/// Poursuit le parcours : le plan du retour s'il en reste un à pourvoir,
/// le blocage sinon.
///
/// Un aller-retour demande deux plans — les sièges d'un bus ne valent
/// pas pour l'autre — mais un seul blocage : le serveur réclame un siège
/// par tronçon du devis, et c'est ce qui donne au voyageur un paiement
/// unique.
void _continue(
  BuildContext context,
  SeatsState state,
  Departure? returnDeparture,
  OutboundLeg? outbound,
) {
  if (returnDeparture != null) {
    context.push(
      AppRouter.seatsPath,
      extra: SeatsArgs(
        departure: returnDeparture,
        passengers: state.passengers,
        outbound: OutboundLeg(
          departure: state.departure!,
          seats: state.selectedSeats,
        ),
      ),
    );
    return;
  }

  _hold(context, state, outbound);
}

void _hold(BuildContext context, SeatsState state, OutboundLeg? outbound) {
  final departure = state.departure!;
  final quoteState = context.read<GroupQuoteCubit>().state;
  final quote = quoteState.matches(departure.id, state.passengers)
      ? quoteState.quote
      : null;

  final bloc = context.read<SeatHoldBloc>();

  if (state.passengers >= kGroupMinimumPassengers && quote != null) {
    bloc.add(
      SeatHoldGroupRequested(
        departure: departure,
        quote: quote,
        seats: state.selectedSeats,
        // Choisi deux écrans plus tôt, sur la réservation de groupe.
        payer: context.read<GroupPayerCubit>().state.payer,
      ),
    );
    return;
  }

  // Sur un aller-retour, le départ qui compte est l'aller : c'est lui que
  // porteront le devis et les écrans suivants. Cet écran-ci montrait le
  // retour.
  bloc.add(
    outbound == null
        ? SeatHoldRequested(departure: departure, seats: state.selectedSeats)
        : SeatHoldRequested(
            departure: outbound.departure,
            seats: outbound.seats,
            returnDeparture: departure,
            returnSeats: state.selectedSeats,
          ),
  );
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
    final l10n = context.l10n;

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        StateView(
          icon: Icons.cloud_off_rounded,
          title: l10n.seatsErrorTitle,
          message: state.errorMessage ?? l10n.departuresErrorText,
          primaryLabel: l10n.seatsErrorPrimary,
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
    final l10n = context.l10n;

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        ScreenHeader(
          eyebrow: l10n.departuresEyebrow,
          title: l10n.seatsMissingEyebrow,
        ),
        StateView(
          icon: Icons.help_outline_rounded,
          title: l10n.seatsMissingTitle,
          message: l10n.seatsMissingText,
          primaryLabel: l10n.seatsMissingPrimary,
          onPrimary: () => Navigator.of(context).pop(),
        ),
      ],
    );
  }
}
