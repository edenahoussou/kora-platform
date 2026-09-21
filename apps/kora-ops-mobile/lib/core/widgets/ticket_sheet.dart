import 'package:flutter/material.dart';
import '../../app/theme/app_radius.dart';
import '../../app/theme/app_spacing.dart';
import '../../app/theme/kora_colors.dart';
import '../../features/ops/domain/entities/ticket.dart';
import 'ticket_card.dart';

/// Ouvre le bottom sheet "Voir le billet" — partagé entre
/// `DetailPassagerPage` ("Voir le billet") et `DescenteAnticipeePage`
/// (tap sur un siège, refonte UI/UX du 21/09/2026) : même contenu, même
/// style, un seul point d'entrée.
Future<void> showTicketSheet(
  BuildContext context, {
  required Ticket ticket,
  required String passengerName,
  required String route,
  required String date,
  required String heure,
}) {
  return showModalBottomSheet<void>(
    context: context,
    backgroundColor: Colors.transparent,
    isScrollControlled: true,
    builder: (BuildContext context) => TicketSheet(
      ticket: ticket,
      passengerName: passengerName,
      route: route,
      date: date,
      heure: heure,
    ),
  );
}

class TicketSheet extends StatelessWidget {
  const TicketSheet({
    required this.ticket,
    required this.passengerName,
    required this.route,
    required this.date,
    required this.heure,
    super.key,
  });

  final Ticket ticket;
  final String passengerName;
  final String route;
  final String date;
  final String heure;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Padding(
        // Seul le clavier justifie un padding bas ici — plus de marge
        // fixe en plus, qui laissait un vide sous la carte.
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: Material(
          color: context.koraColors.surface,
          borderRadius: const BorderRadius.vertical(
            top: Radius.circular(AppRadius.lg),
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              const SizedBox(height: AppSpacing.sm),
              Container(
                width: 36,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(
                    AppSpacing.md, AppSpacing.sm, AppSpacing.xs, 0),
                child: Row(
                  children: <Widget>[
                    Expanded(
                      child: Text('Billet',
                          style: Theme.of(context).textTheme.titleMedium),
                    ),
                    IconButton(
                      icon: const Icon(Icons.close),
                      tooltip: 'Fermer',
                      onPressed: () => Navigator.of(context).pop(),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(
                    AppSpacing.md, 0, AppSpacing.md, AppSpacing.md),
                child: TicketCard(
                  ticket: ticket,
                  passengerName: passengerName,
                  route: route,
                  date: date,
                  heure: heure,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
