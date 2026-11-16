import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../domain/entities/ticket.dart';
import '../bloc/cancel_ticket_cubit.dart';
import '../bloc/ticket_document_cubit.dart';
import '../widgets/official_ticket_download.dart';
import 'group_tickets_page.dart';

/// Écran `ticket-detail` de la maquette : le billet en grand, puis ce qu'on
/// peut en faire — QR code, suivi du bus, partage, annulation.
///
/// Seule la première mène quelque part : le billet numérique. Les trois
/// autres (`tracking`, `share-ticket`, `cancel-policy`) attendent leurs
/// écrans, et affichent un toast plutôt que de mener à une page blanche.
class TicketDetailPage extends StatelessWidget {
  const TicketDetailPage({
    super.key,
    required this.ticket,
    required this.today,
    this.documents,
    this.cancel,
  });

  final Ticket ticket;

  /// Le cubit des PDF officiels. Par défaut celui de l'app ; les tests en
  /// passent un à eux.
  final TicketDocumentCubit? documents;

  /// Le cubit d'annulation. Même raison : les tests en fournissent un,
  /// branché sur un dépôt qui ne parle à personne.
  final CancelTicketCubit? cancel;

  /// Date du jour, transmise par l'écran appelant — même raison que sur
  /// [TicketCard] : l'écran ne lit pas l'horloge lui-même.
  final DateTime today;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final departure = ticket.departure;
    final koraColors = context.koraColors;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: _eyebrow(l10n),
                title:
                    '${departure.line.origin.name} → '
                    '${departure.line.destination.name}',
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.only(bottom: 100),
                  children: [
                    _Summary(ticket: ticket, today: today),
                    // Un billet de correspondance détaille ses bus : le
                    // titre annonce Cotonou → Natitingou, et c'est ici
                    // qu'on apprend qu'on descend à Bohicon, à quelle
                    // heure et sur quelle place.
                    if (ticket.isConnecting) ...[
                      SectionTitle(title: l10n.ticketDetailLegs),
                      _Legs(ticket: ticket),
                    ],
                    const SizedBox(height: 13),
                    _Actions(
                      ticket: ticket,
                      documents: documents,
                      cancel: cancel,
                    ),
                    const SizedBox(height: 13),
                    KoraCard(
                      variant: KoraCardVariant.mist,
                      child: Text(
                        l10n.ticketDetailReminder,
                        style: TextStyle(
                          color: koraColors.textMuted,
                          fontSize: 12,
                          height: 1.42,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Le surtitre dit dans quel état est le billet — c'est la première
  /// chose à savoir en ouvrant l'écran.
  String _eyebrow(AppLocalization l10n) => switch (ticket.status) {
    TicketStatus.confirmed => l10n.ticketDetailEyebrowConfirmed,
    TicketStatus.completed => l10n.ticketDetailEyebrowCompleted,
    TicketStatus.cancelled => l10n.ticketDetailEyebrowCancelled,
  };
}

/// Ce qu'on peut faire du billet, en une seule liste.
///
/// **Le PDF officiel y entre quand la réservation n'a qu'un billet** :
/// l'ouvrir, le partager, en garder une copie. Ces actions vivaient dans
/// un bloc à part, sous la liste, qui empilait quatre boutons pleine
/// largeur — quatre fois le même poids visuel pour des gestes secondaires.
///
/// Un groupe garde la liste courte : chaque voyageur a son propre billet,
/// et c'est l'écran des billets du groupe qui les sert un par un.
class _Actions extends StatefulWidget {
  const _Actions({required this.ticket, this.documents, this.cancel});

  final Ticket ticket;
  final TicketDocumentCubit? documents;
  final CancelTicketCubit? cancel;

  @override
  State<_Actions> createState() => _ActionsState();
}

class _ActionsState extends State<_Actions> {
  late final TicketDocumentCubit _cubit =
      widget.documents ?? getIt<TicketDocumentCubit>();

  late final CancelTicketCubit _cancel =
      widget.cancel ?? getIt<CancelTicketCubit>();

  @override
  void dispose() {
    // Celui de l'appelant lui appartient : on ne ferme que le nôtre.
    if (widget.cancel == null) _cancel.close();
    super.dispose();
  }

  /// L'unique billet de la réservation, ou `null` s'il y en a plusieurs.
  String? get _single => widget.ticket.ticketIds.length == 1
      ? widget.ticket.ticketIds.single
      : null;

  @override
  void initState() {
    super.initState();
    final single = _single;
    if (single != null) _cubit.restore(single);
  }

  @override
  Widget build(BuildContext context) {
    final single = _single;

    return BlocConsumer<CancelTicketCubit, CancelTicketState>(
      bloc: _cancel,
      listenWhen: (previous, current) =>
          previous.isCancelled != current.isCancelled ||
          (previous.failure != current.failure && current.failure != null),
      listener: (context, state) {
        if (state.isCancelled) {
          KoraToaster.show(context, context.l10n.ticketCancelDone);
          // La liste des voyages se relit d'elle-même : le billet s'y
          // range désormais sous « Annulés ».
          Navigator.of(context).pop();
          return;
        }
        final failure = state.failure;
        if (failure != null) KoraToaster.show(context, failure.message);
      },
      builder: (context, cancelling) {
        if (single == null) {
          return KoraMenuList(items: _items(context));
        }

        return BlocBuilder<TicketDocumentCubit, TicketDocumentState>(
          bloc: _cubit,
          buildWhen: (previous, current) =>
              previous.of(single) != current.of(single),
          builder: (context, state) =>
              KoraMenuList(items: _items(context, state.of(single), single)),
        );
      },
    );
  }

  /// Demande confirmation, puis annule. Un billet annulé ne se récupère
  /// pas : l'écran le dit avant, et nomme le nombre de billets en jeu.
  Future<void> _confirmCancel(BuildContext context) async {
    final l10n = context.l10n;
    final reservationId = widget.ticket.reservationId;
    if (reservationId == null) {
      KoraToaster.show(context, l10n.ticketDetailSoon);
      return;
    }

    final count = widget.ticket.ticketIds.length;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: dialogContext.koraColors.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        title: Text(
          l10n.ticketCancelTitle,
          style: AppTextStyles.titleMedium(dialogContext.koraTitleColor),
        ),
        // Les deux réponses sur une ligne, du même poids : garder le
        // billet, ou l'annuler. Les boutons de la maquette, pas ceux de
        // Material — c'est la même décision qu'ailleurs dans l'app.
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              count > 1
                  ? l10n.ticketCancelTextGroup('$count')
                  : l10n.ticketCancelText,
              style: AppTextStyles.body(dialogContext.koraColors.textMuted),
            ),
            const SizedBox(height: AppSpacing.lg),
            Row(
              children: [
                Expanded(
                  child: KoraButton(
                    label: l10n.ticketCancelKeep,
                    variant: KoraButtonVariant.ghost,
                    onPressed: () => Navigator.of(dialogContext).pop(false),
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: KoraButton(
                    label: l10n.ticketCancelConfirm,
                    variant: KoraButtonVariant.danger,
                    onPressed: () => Navigator.of(dialogContext).pop(true),
                  ),
                ),
              ],
            ),
          ],
        ),
        // Le contenu porte les boutons : la rangée d'actions de Material
        // les empilerait une seconde fois.
        actionsPadding: EdgeInsets.zero,
      ),
    );

    if (confirmed != true || !context.mounted) return;
    await _cancel.cancel(reservationId);
  }

  List<KoraMenuItem> _items(
    BuildContext context, [
    TicketDocumentEntry? entry,
    String? ticketId,
  ]) {
    final l10n = context.l10n;
    final document = entry?.document;

    return [
      KoraMenuItem(
        icon: Icons.qr_code_2_rounded,
        title: l10n.ticketDetailQrTitle,
        subtitle: l10n.ticketDetailQrSubtitle,
        // Vers les billets **émis**, et non vers la maquette du billet
        // numérique : celle-ci replie sur la référence à défaut de charge
        // signée, et affichait donc un QR bâti sur un UUID de réservation
        // — d'allure officielle, refusé à l'embarquement.
        onTap: () => _openGroupTickets(context),
      ),
      if (entry != null && ticketId != null)
        ...officialTicketMenuItems(
          context,
          ticketId: ticketId,
          entry: entry,
          cubit: _cubit,
        ),
      KoraMenuItem(
        icon: Icons.my_location_rounded,
        title: l10n.ticketDetailTrackTitle,
        subtitle: l10n.ticketDetailTrackSubtitle,
        onTap: () => _soon(context),
      ),
      KoraMenuItem(
        icon: Icons.ios_share_rounded,
        title: l10n.ticketDetailShareTitle,
        subtitle: l10n.ticketDetailShareSubtitle,
        // Un billet déjà enregistré se partage tel quel ; sinon c'est
        // l'écran des billets qui le prépare, voyageur par voyageur.
        onTap: document == null
            ? () => _openGroupTickets(context)
            : () => shareTicketDocument(context, document),
      ),
      // Seule action destructrice de la liste : elle passe en rouge pour
      // ne pas se confondre avec les autres, qui ne retirent rien. Elle
      // ne s'affiche que sur un billet encore valable — un voyage fait ou
      // déjà annulé ne s'annule pas.
      if (widget.ticket.status == TicketStatus.confirmed)
        KoraMenuItem(
          icon: Icons.close_rounded,
          title: _cancel.state.isCancelling
              ? l10n.ticketCancelProgress
              : l10n.ticketDetailCancelTitle,
          subtitle: l10n.ticketDetailCancelSubtitle,
          isDestructive: true,
          onTap: _cancel.state.isCancelling
              ? null
              : () => _confirmCancel(context),
        ),
    ];
  }

  void _openGroupTickets(BuildContext context) => context.push(
    AppRouter.groupTicketsPath,
    extra: GroupTicketsArgs(
      reservationId: widget.ticket.reservationId ?? widget.ticket.reference,
    ),
  );

  void _soon(BuildContext context) =>
      KoraToaster.show(context, context.l10n.ticketDetailSoon);
}

/// La carte navy du haut : délai, siège, date et heure, gare de départ.
/// Les bus du voyage, l'un sous l'autre.
class _Legs extends StatelessWidget {
  const _Legs({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    // Un aller-retour met ses deux sens dans la même liste : sans un mot
    // pour les séparer, le billet annonçait un troisième bus qui
    // repartait d'où l'on venait, comme s'il fallait le prendre dans la
    // foulée.
    final hasReturn = ticket.legs.any((leg) => leg.isReturn);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final (index, leg) in ticket.legs.indexed) ...[
          if (hasReturn &&
              (index == 0 || ticket.legs[index - 1].isReturn != leg.isReturn))
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Text(
                (leg.isReturn
                        ? l10n.departuresDirectionInbound
                        : l10n.departuresDirectionOutbound)
                    .toUpperCase(),
                style: const TextStyle(
                  color: AppColors.gold,
                  fontSize: 9,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 0.8,
                ),
              ),
            ),
          KoraCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${leg.origin} → ${leg.destination}',
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    if (leg.seatCode.isNotEmpty) ...[
                      const SizedBox(width: AppSpacing.sm),
                      KoraTag(
                        label: l10n.ticketDetailLegSeat(leg.seatCode),
                        variant: KoraTagVariant.gold,
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  _hours(l10n, leg),
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
        ],
      ],
    );
  }

  /// `06:00 → 07:31`, ou l'heure connue seule. Le serveur laisse ces
  /// heures nulles quand l'arrêt n'a pas d'horaire : mieux vaut n'en
  /// annoncer aucune que d'en inventer une.
  String _hours(AppLocalization l10n, TicketLeg leg) {
    final departs = leg.departsAt;
    final arrives = leg.arrivesAt;

    final parts = [
      if (departs != null) KoraDateFormats.timeIn(departs, l10n.languageCode),
      if (arrives != null) KoraDateFormats.timeIn(arrives, l10n.languageCode),
    ];

    return parts.join(' → ');
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.ticket, required this.today});

  final Ticket ticket;
  final DateTime today;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final departure = ticket.departure;

    return KoraCard(
      variant: KoraCardVariant.navy,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              KoraTag(
                label: ticket.status == TicketStatus.confirmed
                    ? l10n.relativeDays(ticket.daysUntilDeparture(today))
                    : _statusLabel(l10n),
                variant: KoraTagVariant.gold,
              ),
              Text(
                ticket.seat.label,
                style: const TextStyle(
                  color: AppColors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const SizedBox(height: 17),
          Text(
            '${KoraDateFormats.weekdayDayMonthIn(departure.departsAt, l10n.languageCode)}'
            ' · '
            '${KoraDateFormats.timeIn(departure.departsAt, l10n.languageCode)}',
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 21,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.525,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            l10n.ticketDetailStation(departure.line.origin.name),
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.65),
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  String _statusLabel(AppLocalization l10n) => switch (ticket.status) {
    TicketStatus.confirmed => l10n.ticketsStatusConfirmed,
    TicketStatus.completed => l10n.ticketsStatusCompleted,
    TicketStatus.cancelled => l10n.ticketsStatusCancelled,
  };
}

/// Ce que l'onglet Voyages transmet à l'écran de détail : le billet
/// choisi et la date du jour retenue par son bloc.
class TicketDetailArgs {
  const TicketDetailArgs({required this.ticket, required this.today});

  final Ticket ticket;
  final DateTime today;
}
