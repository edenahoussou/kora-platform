import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/widgets/brand_mini.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../domain/entities/ticket.dart';
import '../bloc/tickets_bloc.dart';
import '../widgets/official_ticket_download.dart';
import '../widgets/state_view.dart';
import '../widgets/ticket_card.dart';

/// Écran `tickets` de la maquette — l'onglet « Voyages » de la bottom nav :
/// les billets du voyageur, répartis entre à venir, passés et annulés.
///
/// Les billets sont mockés (`BookingMockDataSource`) ; le Sprint 2 changera
/// la source, pas cet écran.
class TicketsPage extends StatelessWidget {
  const TicketsPage({super.key, this.onBookTrip, this.onOpenTicket});

  /// « Réserver un voyage » — ramène au formulaire de recherche.
  final VoidCallback? onBookTrip;

  /// Ouvre le détail d'un billet. Reçoit le billet **et** la date du jour
  /// retenue par le bloc : l'écran de détail affiche lui aussi un délai
  /// avant départ, il doit compter à partir du même instant.
  final void Function(Ticket ticket, DateTime today)? onOpenTicket;

  @override
  Widget build(BuildContext context) {
    return BlocProvider<TicketsBloc>(
      create: (_) => getIt<TicketsBloc>()..add(const TicketsStarted()),
      child: _TicketsView(onBookTrip: onBookTrip, onOpenTicket: onOpenTicket),
    );
  }
}

class _TicketsView extends StatelessWidget {
  const _TicketsView({this.onBookTrip, this.onOpenTicket});

  final VoidCallback? onBookTrip;
  final void Function(Ticket ticket, DateTime today)? onOpenTicket;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: BlocBuilder<TicketsBloc, TicketsState>(
            builder: (context, state) {
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ScreenHeader(
                    // `back: false` : écran racine d'un onglet. La
                    // maquette met le logo à la place du retour et la
                    // cloche à droite, comme sur l'accueil.
                    showBack: false,
                    leading: const BrandMini(),
                    eyebrow: l10n.ticketsEyebrow,
                    title: l10n.ticketsTitle,
                    action: NotificationButton(
                      onTap: () =>
                          KoraToaster.show(context, l10n.ticketsNotifications),
                    ),
                  ),
                  Expanded(
                    child: _Body(
                      state: state,
                      onBookTrip: onBookTrip,
                      onOpenTicket: onOpenTicket,
                    ),
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({required this.state, this.onBookTrip, this.onOpenTicket});

  final TicketsState state;
  final VoidCallback? onBookTrip;
  final void Function(Ticket ticket, DateTime today)? onOpenTicket;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return switch (state.status) {
      // Les onglets restent affichés pendant le chargement : ils ne
      // dépendent pas de la réponse, les masquer ferait sauter l'écran.
      TicketsStatus.loading => const _TicketsSkeleton(),
      TicketsStatus.failure => StateView(
        icon: Icons.error_outline_rounded,
        title: l10n.ticketsErrorTitle,
        message: state.errorMessage,
        primaryLabel: l10n.actionRetry,
        onPrimary: () =>
            context.read<TicketsBloc>().add(const TicketsStarted()),
      ),
      TicketsStatus.success => _TicketsList(
        state: state,
        onBookTrip: onBookTrip,
        onOpenTicket: onOpenTicket,
      ),
    };
  }
}

class _TicketsList extends StatelessWidget {
  const _TicketsList({required this.state, this.onBookTrip, this.onOpenTicket});

  final TicketsState state;
  final VoidCallback? onBookTrip;
  final void Function(Ticket ticket, DateTime today)? onOpenTicket;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final tickets = state.visibleTickets;

    return ListView(
      padding: const EdgeInsets.only(bottom: 100),
      children: [
        _FilterTabs(state: state),
        const SizedBox(height: 14),
        if (tickets.isEmpty)
          _EmptyTab(filter: state.filter)
        else
          for (final ticket in tickets) ...[
            TicketCard(
              ticket: ticket,
              today: state.today,
              // Un voyage d'un seul billet se télécharge depuis la carte ;
              // un groupe passe par le détail, voyageur par voyageur.
              action: ticket.ticketIds.length == 1
                  ? OfficialTicketDownloadIcon(
                      ticketId: ticket.ticketIds.single,
                    )
                  : null,
              onTap: onOpenTicket == null
                  ? null
                  : () => onOpenTicket!(ticket, state.today),
            ),
            const SizedBox(height: 10),
          ],
        const SizedBox(height: AppSpacing.md),
        SectionTitle(title: l10n.ticketsNewDestination),
        const SizedBox(height: AppSpacing.md),
        KoraButton(label: l10n.ticketsBookTrip, onPressed: onBookTrip),
      ],
    );
  }
}

/// `À venir · 1 / Passés · 3 / Annulés · 1` : les compteurs viennent de la
/// liste complète, pas de l'onglet affiché — un onglet vide doit annoncer
/// zéro, pas disparaître.
class _FilterTabs extends StatelessWidget {
  const _FilterTabs({required this.state});

  final TicketsState state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    String labelFor(TicketFilter filter) {
      final count = state.countFor(filter);

      return switch (filter) {
        TicketFilter.upcoming => l10n.ticketsTabUpcoming(count),
        TicketFilter.past => l10n.ticketsTabPast(count),
        TicketFilter.cancelled => l10n.ticketsTabCancelled(count),
      };
    }

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final filter in TicketFilter.values) ...[
            KoraFilterChip(
              label: labelFor(filter),
              isSelected: filter == state.filter,
              // Pas de chevron : ces puces sont des onglets, il n'y a rien
              // à dérouler.
              showIcon: false,
              onTap: () =>
                  context.read<TicketsBloc>().add(TicketsFilterChanged(filter)),
            ),
            const SizedBox(width: AppSpacing.sm),
          ],
        ],
      ),
    );
  }
}

/// Onglet sans billet. Chaque onglet a son propre message : « aucun voyage
/// à venir » et « aucune annulation » n'appellent pas la même suite.
class _EmptyTab extends StatelessWidget {
  const _EmptyTab({required this.filter});

  final TicketFilter filter;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    final (title, message) = switch (filter) {
      TicketFilter.upcoming => (
        l10n.ticketsEmptyUpcomingTitle,
        l10n.ticketsEmptyUpcomingText,
      ),
      TicketFilter.past => (
        l10n.ticketsEmptyPastTitle,
        l10n.ticketsEmptyPastText,
      ),
      TicketFilter.cancelled => (
        l10n.ticketsEmptyCancelledTitle,
        l10n.ticketsEmptyCancelledText,
      ),
    };

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
      child: StateView(
        icon: Icons.confirmation_number_outlined,
        title: title,
        message: message,
      ),
    );
  }
}

/// Chargement : les onglets puis deux cartes fantômes, à la place et à la
/// taille des vraies.
class _TicketsSkeleton extends StatelessWidget {
  const _TicketsSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.only(bottom: 100),
      children: const [
        Row(
          children: [
            KoraSkeleton(width: 96, height: 26, radius: AppRadius.pill),
            SizedBox(width: AppSpacing.sm),
            KoraSkeleton(width: 88, height: 26, radius: AppRadius.pill),
            SizedBox(width: AppSpacing.sm),
            KoraSkeleton(width: 92, height: 26, radius: AppRadius.pill),
          ],
        ),
        SizedBox(height: 14),
        KoraSkeleton(height: 148, radius: 19),
        SizedBox(height: 10),
        KoraSkeleton(height: 148, radius: 19),
      ],
    );
  }
}
