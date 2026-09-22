import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/widgets/brand_mini.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../domain/entities/ticket.dart';
import '../bloc/assistance_cubits.dart';
import '../bloc/cancel_ticket_cubit.dart';
import '../bloc/tickets_bloc.dart';
import '../widgets/assistance_card.dart';
import '../widgets/official_ticket_download.dart';
import '../widgets/state_view.dart';
import '../widgets/ticket_card.dart';

/// Écran `tickets` de la maquette — l'onglet « Voyages » de la bottom nav :
/// les billets du voyageur, répartis entre à venir, passés et annulés.
///
/// Les billets sont mockés (`BookingMockDataSource`) ; le Sprint 2 changera
/// la source, pas cet écran.
class TicketsPage extends StatelessWidget {
  const TicketsPage({
    super.key,
    this.onBookTrip,
    this.onOpenTicket,
    this.onOpenAssistance,
    this.onOpenNotifications,
  });

  /// « Réserver un voyage » — ramène au formulaire de recherche.
  final VoidCallback? onBookTrip;

  /// Ouvre le détail d'un billet. Reçoit le billet **et** la date du jour
  /// retenue par le bloc : l'écran de détail affiche lui aussi un délai
  /// avant départ, il doit compter à partir du même instant.
  final void Function(Ticket ticket, DateTime today)? onOpenTicket;

  /// Ouvre le suivi d'une réservation confiée à une agence.
  final void Function(String reservationId)? onOpenAssistance;

  /// Ouvre le centre de notifications, depuis la cloche de l'en-tête.
  final VoidCallback? onOpenNotifications;

  @override
  Widget build(BuildContext context) {
    return MultiBlocProvider(
      providers: [
        BlocProvider<TicketsBloc>(
          create: (_) => getIt<TicketsBloc>()..add(const TicketsStarted()),
        ),
        // Les demandes en agence n'ont pas de billet tant qu'elles ne sont
        // pas finalisées : sans cette section, elles n'apparaîtraient nulle
        // part.
        BlocProvider<AssistanceRequestsCubit>(
          create: (_) => getIt<AssistanceRequestsCubit>()..load(),
        ),
      ],
      child: _TicketsView(
        onBookTrip: onBookTrip,
        onOpenTicket: onOpenTicket,
        onOpenAssistance: onOpenAssistance,
        onOpenNotifications: onOpenNotifications,
      ),
    );
  }
}

/// Les quatre onglets de l'écran.
///
/// [pending] ne vient pas des billets : ce sont les demandes confiées à une
/// agence, qui n'ont pas encore de billet — elles attendent d'être validées
/// sur WhatsApp.
enum _VoyagesTab { pending, upcoming, past, cancelled }

class _TicketsView extends StatefulWidget {
  const _TicketsView({
    this.onBookTrip,
    this.onOpenTicket,
    this.onOpenAssistance,
    this.onOpenNotifications,
  });

  final VoidCallback? onBookTrip;
  final void Function(Ticket ticket, DateTime today)? onOpenTicket;
  final void Function(String reservationId)? onOpenAssistance;
  final VoidCallback? onOpenNotifications;

  @override
  State<_TicketsView> createState() => _TicketsViewState();
}

class _TicketsViewState extends State<_TicketsView>
    with WidgetsBindingObserver {
  var _tab = _VoyagesTab.upcoming;

  /// Une annulation faite depuis le détail d'un billet : la liste, restée
  /// en mémoire derrière, doit relire ses billets plutôt que d'afficher
  /// un statut périmé.
  StreamSubscription<void>? _changes;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    // Absent d'un test qui n'en a que faire : la liste marche sans, elle
    // ne se relit simplement pas toute seule.
    if (getIt.isRegistered<BookingRefresh>()) {
      _changes = getIt<BookingRefresh>().changes.listen((_) => _reload());
    }
  }

  /// Revenu d'ailleurs — d'un paiement Mobile Money, de WhatsApp —, le
  /// voyageur peut avoir des billets de plus : la liste se relit plutôt
  /// que d'afficher celle d'avant.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _reload();
  }

  void _reload() {
    if (mounted) context.read<TicketsBloc>().add(const TicketsStarted());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _changes?.cancel();
    super.dispose();
  }

  /// Les onglets de billets restent pilotés par le bloc, qui filtre et
  /// compte ; celui des demandes ne le concerne pas.
  void _select(_VoyagesTab tab) {
    setState(() => _tab = tab);

    final filter = switch (tab) {
      _VoyagesTab.upcoming => TicketFilter.upcoming,
      _VoyagesTab.past => TicketFilter.past,
      _VoyagesTab.cancelled => TicketFilter.cancelled,
      _VoyagesTab.pending => null,
    };
    if (filter != null) {
      context.read<TicketsBloc>().add(TicketsFilterChanged(filter));
    }
  }

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
                      onTap: widget.onOpenNotifications,
                    ),
                  ),
                  Expanded(
                    child: _Body(
                      state: state,
                      tab: _tab,
                      onSelectTab: _select,
                      onBookTrip: widget.onBookTrip,
                      onOpenTicket: widget.onOpenTicket,
                      onOpenAssistance: widget.onOpenAssistance,
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
  const _Body({
    required this.state,
    required this.tab,
    required this.onSelectTab,
    this.onBookTrip,
    this.onOpenTicket,
    this.onOpenAssistance,
  });

  final TicketsState state;
  final _VoyagesTab tab;
  final void Function(_VoyagesTab tab) onSelectTab;
  final VoidCallback? onBookTrip;
  final void Function(Ticket ticket, DateTime today)? onOpenTicket;
  final void Function(String reservationId)? onOpenAssistance;

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
        tab: tab,
        onSelectTab: onSelectTab,
        onBookTrip: onBookTrip,
        onOpenTicket: onOpenTicket,
        onOpenAssistance: onOpenAssistance,
      ),
    };
  }
}

class _TicketsList extends StatelessWidget {
  const _TicketsList({
    required this.state,
    required this.tab,
    required this.onSelectTab,
    this.onBookTrip,
    this.onOpenTicket,
    this.onOpenAssistance,
  });

  final TicketsState state;
  final _VoyagesTab tab;
  final void Function(_VoyagesTab tab) onSelectTab;
  final VoidCallback? onBookTrip;
  final void Function(Ticket ticket, DateTime today)? onOpenTicket;
  final void Function(String reservationId)? onOpenAssistance;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final requests = context.watch<AssistanceRequestsCubit>().state;
    // Les demandes encore en cours attendent l'agence ; celles qui n'ont
    // pas abouti rejoignent les annulations.
    final pending = requests.openRequests;
    final closed = requests.deadEndRequests;
    final tickets = tab == _VoyagesTab.pending
        ? const <Ticket>[]
        : state.visibleTickets;
    final showAssistance = tab == _VoyagesTab.pending
        ? pending
        : (tab == _VoyagesTab.cancelled
              ? closed
              : const <AssistanceRequestView>[]);

    return ListView(
      padding: const EdgeInsets.only(bottom: 100),
      children: [
        _FilterTabs(
          state: state,
          tab: tab,
          onSelect: onSelectTab,
          pendingCount: pending.length,
          closedCount: closed.length,
        ),
        const SizedBox(height: 14),
        for (final request in showAssistance) ...[
          AssistanceCard(
            reference: request.record.reference,
            status: request.status,
            origin: request.record.origin,
            destination: request.record.destination,
            departsAt: request.record.departsAt,
            seats: [for (final seat in request.record.seats) seat.label],
            footnote: request.record.agencyName,
            // Pas de billet à montrer : la demande attend encore l'agence.
            actionLabel: l10n.assistSeeRequest,
            onTap: onOpenAssistance == null
                ? null
                : () => onOpenAssistance!(request.record.reservationId),
          ),
          const SizedBox(height: 10),
        ],
        if (tab == _VoyagesTab.pending && pending.isEmpty)
          _EmptyPending()
        // Le vide ne s'annonce que si l'onglet est vraiment vide : une
        // demande sans suite compte comme une annulation.
        else if (tab != _VoyagesTab.pending &&
            tickets.isEmpty &&
            showAssistance.isEmpty)
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

/// `À venir · 1 / En attente · 1 / Passés · 3 / Annulés · 1` : les
/// compteurs viennent des listes complètes, pas de l'onglet affiché — un
/// onglet vide doit annoncer zéro, pas disparaître.
///
/// « En attente » compte les demandes confiées à une agence, « Annulés »
/// ajoute celles qui n'ont pas abouti aux billets annulés : chaque
/// réservation est rangée dans sa partie.
class _FilterTabs extends StatelessWidget {
  const _FilterTabs({
    required this.state,
    required this.tab,
    required this.onSelect,
    required this.pendingCount,
    required this.closedCount,
  });

  final TicketsState state;
  final _VoyagesTab tab;
  final void Function(_VoyagesTab tab) onSelect;
  final int pendingCount;
  final int closedCount;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    String labelFor(_VoyagesTab value) => switch (value) {
      _VoyagesTab.upcoming => l10n.ticketsTabUpcoming(
        state.countFor(TicketFilter.upcoming),
      ),
      _VoyagesTab.pending => l10n.ticketsTabPending(pendingCount),
      _VoyagesTab.past => l10n.ticketsTabPast(
        state.countFor(TicketFilter.past),
      ),
      _VoyagesTab.cancelled => l10n.ticketsTabCancelled(
        state.countFor(TicketFilter.cancelled) + closedCount,
      ),
    };

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final value in _VoyagesTab.values) ...[
            KoraFilterChip(
              label: labelFor(value),
              isSelected: value == tab,
              // Pas de chevron : ces puces sont des onglets, il n'y a rien
              // à dérouler.
              showIcon: false,
              onTap: () => onSelect(value),
            ),
            const SizedBox(width: AppSpacing.sm),
          ],
        ],
      ),
    );
  }
}

/// Onglet « En attente » vide : personne n'a encore confié de réservation
/// à une agence.
class _EmptyPending extends StatelessWidget {
  const _EmptyPending();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
      child: StateView(
        icon: Icons.chat_rounded,
        title: l10n.ticketsEmptyPendingTitle,
        message: l10n.ticketsEmptyPendingText,
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
