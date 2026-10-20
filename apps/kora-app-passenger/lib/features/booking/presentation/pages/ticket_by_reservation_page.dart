import 'package:flutter/material.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/usecases/usecase.dart';
import '../../domain/entities/ticket.dart';
import '../../domain/usecases/get_tickets_usecase.dart';
import '../widgets/state_view.dart';

/// Le billet d'une réservation, désigné par son seul identifiant.
///
/// C'est la porte d'entrée des liens profonds. Une notification — touchée
/// dans le centre de notifications, ou reçue en push — ne transporte
/// qu'une chaîne de caractères : elle ne peut pas passer un billet déjà
/// chargé, comme le fait la liste des voyages. Cet écran comble l'écart :
/// il reçoit l'identifiant, va chercher le billet, puis cède la place au
/// détail habituel.
///
/// Les trois issues sont traitées, parce que les trois arrivent :
/// le billet est là, le réseau manque, ou la réservation n'existe plus —
/// annulée, remboursée, ou appartenant à un autre compte.
class TicketByReservationPage extends StatefulWidget {
  const TicketByReservationPage({
    required this.reservationUuid,
    required this.getTickets,
    super.key,
    this.onOpenTicket,
    this.onFallback,
    this.today,
  });

  /// L'identifiant que portait la notification.
  final String reservationUuid;

  final GetTicketsUseCase getTickets;

  /// Ouvre le détail une fois le billet trouvé.
  ///
  /// L'écran ne dessine jamais le détail lui-même : son travail s'arrête à
  /// retrouver le billet. C'est la route qui décide de la suite, comme
  /// pour n'importe quelle autre ouverture de billet.
  final void Function(Ticket ticket, DateTime today)? onOpenTicket;

  /// « Voir tous mes voyages » : la sortie quand ce billet-là est
  /// introuvable.
  final VoidCallback? onFallback;

  /// Horloge de référence, figée par les tests.
  final DateTime? today;

  @override
  State<TicketByReservationPage> createState() =>
      _TicketByReservationPageState();
}

class _TicketByReservationPageState extends State<TicketByReservationPage> {
  late final DateTime _today = widget.today ?? DateTime.now();

  Ticket? _ticket;
  bool _loading = true;

  /// Vrai quand le serveur a répondu mais ne connaît pas cette
  /// réservation — à distinguer d'une panne, qui se réessaie.
  bool _missing = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _missing = false;
    });

    final result = await widget.getTickets(const NoParams());
    if (!mounted) return;

    result.fold((_) => setState(() => _loading = false), (tickets) {
      // Le billet est cherché dans la liste du voyageur plutôt que par
      // un appel dédié : c'est la même source que l'onglet Voyages, donc
      // le détail reçoit exactement ce qu'il attend, et un billet qui ne
      // lui appartient pas ne peut pas s'y glisser.
      final found = tickets
          .where((ticket) => ticket.reservationId == widget.reservationUuid)
          .firstOrNull;

      setState(() {
        _ticket = found;
        _missing = found == null;
        _loading = false;
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final ticket = _ticket;

    final open = widget.onOpenTicket;
    if (ticket != null && open != null) {
      // Le détail remplace cet écran plutôt que de s'empiler dessus :
      // revenir en arrière doit ramener à la notification, pas à un sas
      // de chargement.
      WidgetsBinding.instance.addPostFrameCallback((_) => open(ticket, _today));
    }

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: _loading || ticket != null
              ? const Center(child: CircularProgressIndicator())
              : StateView(
                  icon: _missing
                      ? Icons.confirmation_number_outlined
                      : Icons.wifi_off_rounded,
                  title: _missing
                      ? l10n.ticketGoneTitle
                      : l10n.ticketUnavailableTitle,
                  message: _missing
                      ? l10n.ticketGoneText
                      : l10n.ticketUnavailableText,
                  primaryLabel: _missing
                      ? l10n.ticketMissingPrimary
                      : l10n.actionRetry,
                  onPrimary: _missing ? widget.onFallback : _load,
                  secondaryLabel: _missing ? null : l10n.ticketMissingPrimary,
                  onSecondary: _missing ? null : widget.onFallback,
                ),
        ),
      ),
    );
  }
}
