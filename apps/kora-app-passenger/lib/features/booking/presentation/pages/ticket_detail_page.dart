import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/ticket.dart';

/// Écran `ticket-detail` de la maquette : le billet en grand, puis ce qu'on
/// peut en faire — QR code, suivi du bus, partage, annulation.
///
/// Les quatre actions ouvrent des écrans qui n'existent pas encore
/// (`ticket`, `tracking`, `share-ticket`, `cancel-policy`) : elles
/// affichent un toast plutôt que de mener à une page blanche.
class TicketDetailPage extends StatelessWidget {
  const TicketDetailPage({
    super.key,
    required this.ticket,
    required this.today,
  });

  final Ticket ticket;

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
                    const SizedBox(height: 13),
                    KoraMenuList(
                      items: [
                        KoraMenuItem(
                          icon: Icons.qr_code_2_rounded,
                          title: l10n.ticketDetailQrTitle,
                          subtitle: l10n.ticketDetailQrSubtitle,
                          onTap: () => _soon(context),
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
                          onTap: () => _soon(context),
                        ),
                        // Seule action destructrice de la liste : elle
                        // passe en rouge pour ne pas se confondre avec les
                        // trois autres, qui ne retirent rien.
                        KoraMenuItem(
                          icon: Icons.close_rounded,
                          title: l10n.ticketDetailCancelTitle,
                          subtitle: l10n.ticketDetailCancelSubtitle,
                          isDestructive: true,
                          onTap: () => _soon(context),
                        ),
                      ],
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

  void _soon(BuildContext context) =>
      KoraToaster.show(context, context.l10n.ticketDetailSoon);
}

/// La carte navy du haut : délai, siège, date et heure, gare de départ.
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
