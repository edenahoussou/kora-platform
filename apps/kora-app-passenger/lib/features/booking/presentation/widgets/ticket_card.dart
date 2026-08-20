import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../domain/entities/ticket.dart';

/// La carte d'un billet dans la liste « Mes voyages » — `.card.navy` de la
/// maquette : statut et référence en haut, trajet, date et siège, puis un
/// pied séparé par un filet.
///
/// Fond navy dans les deux thèmes, comme toutes les cartes `navy` du
/// prototype : c'est la carte qui porte le billet, elle doit se détacher de
/// la liste.
class TicketCard extends StatelessWidget {
  const TicketCard({
    super.key,
    required this.ticket,
    required this.today,
    this.onTap,
  });

  final Ticket ticket;

  /// Date du jour, fournie par le bloc — l'écran ne lit pas l'horloge
  /// lui-même, sinon la carte et les données pourraient se référer à deux
  /// instants différents.
  final DateTime today;

  /// Ouvre le détail du billet. `null` tant que l'écran `ticket-detail`
  /// n'existe pas — le lien « Voir le billet » reste affiché, comme sur la
  /// maquette, mais la carte ne réagit pas encore au doigt.
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final departure = ticket.departure;

    return KoraCard(
      variant: KoraCardVariant.navy,
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              // `.navy .tag { color: var(--kora); background: var(--gold) }` :
              // sur une carte navy, une pastille est dorée à texte bleu,
              // quel que soit le statut. Les variantes contour et danger
              // y seraient illisibles — c'est le texte qui distingue
              // « Terminé » de « Confirmé », pas la couleur.
              KoraTag(label: _statusLabel(l10n), variant: KoraTagVariant.gold),
              Flexible(
                child: Text(
                  ticket.shortReference,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: AppColors.white.withValues(alpha: 0.65),
                    fontSize: 11,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            '${departure.line.origin.name} → ${departure.line.destination.name}',
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 19,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.475,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            '${KoraDateFormats.dayMonthIn(departure.departsAt, l10n.languageCode)}'
            ' · '
            '${KoraDateFormats.timeIn(departure.departsAt, l10n.languageCode)}'
            ' · ${l10n.ticketsSeat(ticket.seat.label)}',
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.65),
              fontSize: 12,
            ),
          ),
          // `.divider` : le filet qui sépare le billet de son pied.
          Container(
            height: 1,
            margin: const EdgeInsets.symmetric(vertical: AppSpacing.lg),
            color: AppColors.white.withValues(alpha: 0.12),
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(
                child: Text(
                  _footnote(l10n),
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: AppColors.white.withValues(alpha: 0.65),
                    fontSize: 11,
                  ),
                ),
              ),
              Text(
                l10n.ticketsSeeTicket,
                style: const TextStyle(
                  color: AppColors.gold,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
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

  /// En bas à gauche : le compte à rebours pour un départ à venir, la date
  /// du voyage sinon — « Départ dans -12 jours » n'aurait aucun sens.
  String _footnote(AppLocalization l10n) {
    if (ticket.status != TicketStatus.confirmed) {
      return KoraDateFormats.dayMonthYearIn(
        ticket.departure.departsAt,
        l10n.languageCode,
      );
    }

    return l10n.ticketsDepartureIn(
      l10n.relativeDays(ticket.daysUntilDeparture(today)),
    );
  }
}
