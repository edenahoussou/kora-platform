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
import '../../domain/entities/journey.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/seat_plan.dart';
import '../bloc/group_quote_cubit.dart';
import '../bloc/seat_hold_bloc.dart';
import '../bloc/seats_bloc.dart';
import '../widgets/booking_progress.dart';
import '../widgets/hold_countdown.dart';
import '../widgets/state_view.dart';
import 'passenger_form_page.dart';

/// Données passées à [SeatsPage] par `go_router` (`extra`).
///
/// Le voyage s'y transmet **entier**, tronçons déjà pourvus compris :
/// l'écran sert le suivant, et se repousse lui-même tant qu'il en reste.
/// Un aller-retour en demande deux, une correspondance autant que de
/// bus, et les sièges d'un bus ne valent pas pour l'autre.
class SeatsArgs {
  const SeatsArgs({required this.journey, this.passengers = 1});

  final Journey journey;
  final int passengers;
}

/// Choix du siège — écran `seats` de la maquette : progression de la
/// réservation, rappel de la protection de la sélection, plan du bus, puis
/// la barre de validation.
class SeatsPage extends StatelessWidget {
  const SeatsPage({super.key, required this.journey, this.passengers = 1});

  /// `null` si l'écran est ouvert sans données (lien profond).
  final Journey? journey;

  final int passengers;

  @override
  Widget build(BuildContext context) {
    final journey = this.journey;
    final leg = journey?.pendingLeg;

    if (journey == null || leg == null) {
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
            ..add(SeatsRequested(departure: leg, passengers: passengers)),
      child: _SeatsView(journey: journey),
    );
  }
}

class _SeatsView extends StatelessWidget {
  const _SeatsView({required this.journey});

  /// Le voyage, avec les tronçons déjà pourvus.
  final Journey journey;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: BlocBuilder<SeatsBloc, SeatsState>(
          builder: (context, state) {
            final departure = state.departure;

            // Plusieurs bus à pourvoir : les deux sens d'un aller-retour,
            // ou les tronçons d'une correspondance. Deux plans identiques
            // à la file se ressemblent trop pour qu'on laisse deviner
            // lequel on est en train de remplir.
            final hasSeveralLegs = journey.legs.length > 1;
            final isOutbound =
                journey.pendingLegOrder <= journey.outbound.legs.length;

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
                    title: journey.isRoundTrip
                        ? context.l10n.seatsTitleFor(
                            isOutbound: isOutbound,
                            passengers: state.passengers,
                          )
                        : context.l10n.seatsTitle,
                  ),
                  // Le badge redit le sens à hauteur du plan, avec le
                  // trajet concerné : « Retour » seul obligerait à se
                  // souvenir d'où l'on revient.
                  if (hasSeveralLegs) ...[
                    _LegBadge(journey: journey, departure: departure),
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
                    _Checkout(state: state, journey: journey),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

/// Quel bus on est en train de pourvoir.
///
/// Le badge navy porte le sens sur un aller-retour, le rang du tronçon
/// sur une correspondance ; la ligne à côté rappelle le trajet concerné,
/// parce que « Retour » seul oblige à se souvenir d'où l'on revient.
class _LegBadge extends StatelessWidget {
  const _LegBadge({required this.journey, required this.departure});

  final Journey journey;
  final Departure? departure;

  /// Ce que dit le badge : le sens quand il n'y a que deux trajets à
  /// distinguer, le rang dès qu'un bus en cache un autre.
  String _label(AppLocalization l10n) {
    final isOutbound = journey.pendingLegOrder <= journey.outbound.legs.length;

    if (journey.isRoundTrip &&
        journey.outbound.isDirect &&
        (journey.inbound?.isDirect ?? true)) {
      return isOutbound ? l10n.seatsLegOutbound : l10n.seatsLegInbound;
    }

    return l10n.departuresLegOrder(
      journey.pendingLegOrder,
      journey.legs.length,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final line = departure?.line;

    return Row(
      children: [
        KoraTag(label: _label(l10n), variant: KoraTagVariant.navy),
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
          // « Embarqué » et « Bloqué » regardent l'exploitation : le
          // voyageur ne choisit pas entre eux, et ils encombraient la
          // légende de deux états qu'il ne rencontre pas.
          legendStatuses: const {
            KoraSeatStatus.available,
            KoraSeatStatus.selected,
            KoraSeatStatus.held,
            KoraSeatStatus.paid,
          },
          seats: [
            for (final seat in state.seats)
              KoraSeat(
                label: seat.label,
                status: _statusOf(seat, isSelected: state.isSelected(seat)),
              ),
          ],
          // La vraie cabine quand le serveur la connaît ; sans elle, le
          // plan reste découpé en rangées de quatre.
          grid: _cabin(state),
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

  /// La cabine à dessiner, ou `null` quand le bus n'a pas de gabarit.
  ///
  /// Les deux moitiés du plan se recollent ici : la grille dit **où**
  /// est chaque case, la liste plate dit **dans quel état** se trouve
  /// chaque siège, et le code du siège est la jointure. Une case dont
  /// l'inventaire ne dit rien reste un trou plutôt qu'un siège inventé.
  KoraCabinGrid? _cabin(SeatsState state) {
    final grid = state.grid;
    if (!state.hasGrid || grid == null) return null;

    final byLabel = {for (final seat in state.seats) seat.label: seat};

    return KoraCabinGrid(
      rows: grid.rows,
      cols: grid.cols,
      cells: [
        for (final cell in grid.cells)
          KoraSeatCell(
            kind: _kindOf(cell.kind),
            x: cell.x,
            y: cell.y,
            seat: _seatOf(cell, byLabel[cell.code], state),
          ),
      ],
    );
  }

  KoraCellKind _kindOf(SeatCellKind kind) => switch (kind) {
    SeatCellKind.seat => KoraCellKind.seat,
    SeatCellKind.aisle => KoraCellKind.aisle,
    SeatCellKind.door => KoraCellKind.door,
    SeatCellKind.driver => KoraCellKind.driver,
    SeatCellKind.empty => KoraCellKind.empty,
  };

  /// Le siège d'une case, prêt à être dessiné.
  ///
  /// Un siège d'équipage ou hors service se montre **grisé et
  /// intouchable**, quoi qu'en dise sa disponibilité : la place existe à
  /// bord, elle ne se vend pas. Le serveur l'annonce déjà invendable
  /// dans la liste plate, mais la grille est la seule à dire *pourquoi*,
  /// et à le dire pour un siège que l'inventaire n'aurait pas renvoyé.
  KoraSeat? _seatOf(SeatCell cell, Seat? seat, SeatsState state) {
    if (cell.kind != SeatCellKind.seat) return null;

    final label = seat?.label ?? cell.code;
    if (label == null) return null;

    if (!cell.isBookableSeat) {
      return KoraSeat(label: label, status: KoraSeatStatus.blocked);
    }

    // Une case annoncée vendable mais absente de l'inventaire : on ne
    // l'offre pas, faute de connaître sa disponibilité.
    if (seat == null) {
      return KoraSeat(label: label, status: KoraSeatStatus.blocked);
    }

    return KoraSeat(
      label: label,
      status: _statusOf(seat, isSelected: state.isSelected(seat)),
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
  const _Checkout({required this.state, required this.journey});

  final SeatsState state;
  final Journey journey;

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
                  value: _payableTotal(state, quote, journey).formatted,
                  alignEnd: true,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          _ContinueButton(state: state, journey: journey),
        ],
      ),
    );
  }
}

/// Le bouton qui poursuit le parcours, et ce qui l'accompagne selon que
/// des sièges sont déjà retenus ou non.
///
/// Deux situations, deux comportements :
/// * **rien de retenu** — le cas normal : « Continuer » mène au plan du
///   retour ou au formulaire passagers, sans rien bloquer. Le blocage part
///   du récapitulatif, une fois le prix arrêté — code promo compris ;
/// * **sièges retenus** — le voyageur revient du paiement : « Continuer »
///   est grisé, et il faut d'abord annuler pour choisir autrement. Le
///   laisser actif aurait bloqué une seconde fois les mêmes places, ou
///   d'autres, sans jamais rendre les premières.
class _ContinueButton extends StatelessWidget {
  const _ContinueButton({required this.state, required this.journey});

  final Journey journey;
  final SeatsState state;

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<SeatHoldBloc, SeatHoldState>(
      listenWhen: (previous, current) => previous.status != current.status,
      // Le blocage, parti du récapitulatif, a trouvé un siège déjà pris :
      // le récapitulatif ramène ici, et le plan doit le montrer occupé.
      // Le laisser en « votre choix » inviterait à retenter une place
      // perdue.
      listener: (context, hold) {
        // Tous les plans du voyage écoutent le même bloc : seul le
        // dernier, celui qu'on retrouve en revenant, prévient le voyageur.
        if (journey.pendingLegOrder != journey.legs.length) return;

        final failure = hold.failure;
        if (hold.status != SeatHoldStatus.failure ||
            failure is! SeatUnavailableFailure) {
          return;
        }

        KoraToaster.show(context, failure.message);
        context.read<SeatsBloc>().add(
          SeatRefused(seatId: failure.seatId, seatLabel: failure.seatLabel),
        );
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
          // Désactivé tant qu'il manque un siège : la maquette laisse le
          // bouton actif et affiche un message d'erreur au clic, ce qui
          // fait faire un aller-retour pour rien.
          onPressed: state.canContinue
              ? () => _continue(context, state, journey)
              : null,
        );
      },
    );
  }
}

/// Le total que le voyageur réglera : celui du devis quand le serveur en a
/// rendu un, le tarif de la recherche multiplié sinon.
///
/// **Le voyage entier, jamais le bus qu'on regarde.** [SeatsState.total]
/// ne connaît que le tronçon affiché : sur la correspondance Cotonou →
/// Bohicon → Natitingou, le plan du premier bus annonçait « 2 600 FCFA »
/// au-dessus d'un voyage facturé 10 500. Même raison pour le groupe,
/// dont la remise est arrêtée par le serveur : le voyageur lisait
/// 316 000 là où on lui avait promis 284 400.
Price _payableTotal(SeatsState state, GroupQuoteState quote, Journey journey) {
  if (quote.matches(journey.outbound.id, state.passengers)) {
    final total = quote.quote?.total;
    if (total != null) return total;
  }

  return journey.total * state.passengers;
}

/// Poursuit le parcours : le plan du tronçon suivant s'il en reste un à
/// pourvoir, le formulaire passagers sinon.
///
/// Chaque bus demande son plan — les sièges de l'un ne valent pas pour
/// l'autre — mais le voyage n'a qu'un blocage, au récapitulatif : le
/// serveur réclame un siège par tronçon du devis, et c'est ce qui donne
/// au voyageur un paiement unique.
void _continue(BuildContext context, SeatsState state, Journey journey) {
  final next = journey.withSeats(state.selectedSeats);

  if (!next.isComplete) {
    context.push(
      AppRouter.seatsPath,
      extra: SeatsArgs(journey: next, passengers: state.passengers),
    );
    return;
  }

  context.push(
    AppRouter.passengerInfoPath,
    extra: PassengerFormArgs(journey: next),
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
