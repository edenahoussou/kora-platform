import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/group_ticket.dart';
import '../bloc/group_tickets_cubit.dart';
import '../widgets/kora_qr.dart';

/// Ce qu'attend [GroupTicketsPage] : la réservation dont on liste les
/// billets.
class GroupTicketsArgs {
  const GroupTicketsArgs({required this.reservationId, this.passengers = 0});

  final String reservationId;

  /// Le nombre attendu, pour l'annoncer avant même que le serveur réponde.
  final int passengers;
}

/// Écran `multi-tickets` de la maquette (BUS-17) : un billet et un QR par
/// voyageur du groupe.
///
/// Chaque code est unique, et la validation de l'un n'affecte pas les
/// autres — c'est ce que dit l'écran, et c'est ce qui distingue un achat
/// groupé d'un billet collectif : dix personnes peuvent embarquer à dix
/// moments différents.
class GroupTicketsPage extends StatefulWidget {
  const GroupTicketsPage({super.key, this.args});

  /// `null` si l'écran est ouvert sans données (lien profond).
  final GroupTicketsArgs? args;

  @override
  State<GroupTicketsPage> createState() => _GroupTicketsPageState();
}

class _GroupTicketsPageState extends State<GroupTicketsPage> {
  @override
  void initState() {
    super.initState();

    final args = widget.args;
    if (args == null) return;

    context.read<GroupTicketsCubit>().load(args.reservationId);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = widget.args;

    if (args == null) return const _MissingBooking();

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: BlocBuilder<GroupTicketsCubit, GroupTicketsState>(
            builder: (context, state) {
              final count = state.tickets.isNotEmpty
                  ? state.tickets.length
                  : args.passengers;

              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ScreenHeader(
                    eyebrow: l10n.groupTicketsEyebrow(count),
                    title: l10n.groupTicketsTitle,
                  ),
                  Expanded(child: _Content(state: state)),
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                    child: KoraButton(
                      label: l10n.groupTicketsDone,
                      onPressed: () => context.go('/voyages'),
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

/// Le corps de l'écran : la liste, l'attente, ou l'échec.
class _Content extends StatelessWidget {
  const _Content({required this.state});

  final GroupTicketsState state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    if (state.isLoading && state.tickets.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.hasFailed) {
      return _Notice(message: state.errorMessage!);
    }

    // Le serveur a répondu sans billet : l'émission suit le paiement et
    // prend un instant. Dire « aucun billet » ferait croire à une perte.
    if (state.isPending) {
      return _Notice(message: l10n.groupTicketsPending);
    }

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        for (var i = 0; i < state.tickets.length; i++) ...[
          _TicketRow(ticket: state.tickets[i], index: i + 1),
          const SizedBox(height: 9),
        ],
        const SizedBox(height: AppSpacing.sm),
        KoraCard(
          variant: KoraCardVariant.mist,
          child: Text(
            l10n.groupTicketsNotice,
            style: AppTextStyles.body(context.koraColors.textMuted),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
      ],
    );
  }
}

/// `.multi-ticket-list button` : la pastille numérotée, le voyageur et sa
/// référence, puis le chevron.
class _TicketRow extends StatelessWidget {
  const _TicketRow({required this.ticket, required this.index});

  final GroupTicket ticket;
  final int index;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return InkWell(
      onTap: () => _showQr(context),
      borderRadius: BorderRadius.circular(17),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: koraColors.surface,
          borderRadius: BorderRadius.circular(17),
          border: Border.all(color: koraColors.line),
        ),
        child: Row(
          children: [
            // `.mini-qr` : le rang du voyageur dans le groupe, pas le QR
            // lui-même — il ne serait pas lisible à cette taille, et c'est
            // l'écran du billet qui le montre en grand.
            Container(
              width: 38,
              height: 38,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: AppColors.kora,
                borderRadius: BorderRadius.circular(11),
              ),
              child: Text(
                '$index',
                style: const TextStyle(
                  color: AppColors.gold,
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    ticket.seatCode.isEmpty
                        ? ticket.label
                        : '${ticket.label} · ${ticket.seatCode}',
                    style: AppTextStyles.body(context.koraTitleColor),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${ticket.shortCode} · ${_status(l10n, ticket.status)}',
                    style: AppTextStyles.caption(koraColors.textMuted),
                  ),
                ],
              ),
            ),
            Icon(
              Icons.chevron_right_rounded,
              size: 18,
              color: koraColors.textMuted,
            ),
          ],
        ),
      ),
    );
  }

  /// Montre le QR de ce voyageur, en grand.
  ///
  /// Une feuille plutôt qu'un écran : le contrôleur scanne, puis on
  /// revient à la liste pour le voyageur suivant. Ouvrir et refermer dix
  /// écrans à la file, à l'embarquement, ferait perdre le fil.
  void _showQr(BuildContext context) {
    final l10n = context.l10n;

    showModalBottomSheet<void>(
      context: context,
      backgroundColor: context.koraColors.surface,
      showDragHandle: true,
      builder: (sheetContext) => Padding(
        padding: const EdgeInsets.fromLTRB(18, 0, 18, 28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              ticket.label,
              style: AppTextStyles.titleMedium(sheetContext.koraTitleColor),
            ),
            const SizedBox(height: 4),
            Text(
              ticket.seatCode.isEmpty
                  ? ticket.shortCode
                  : '${l10n.passengersSeat(ticket.seatCode)} · '
                        '${ticket.shortCode}',
              style: AppTextStyles.body(sheetContext.koraColors.textMuted),
            ),
            const SizedBox(height: AppSpacing.md),
            // La charge du serveur, jamais la référence : c'est elle que
            // la douchette du convoyeur attend.
            KoraQr(payload: ticket.qrCode, size: 190),
          ],
        ),
      ),
    );
  }

  /// Le statut en clair. Un statut inconnu de cette version garde son nom
  /// serveur plutôt que d'être rangé de force parmi ceux qu'on connaît.
  static String _status(AppLocalization l10n, GroupTicketStatus status) =>
      switch (status) {
        GroupTicketStatus.issued => l10n.groupTicketsStatusReady,
        GroupTicketStatus.boarded => l10n.groupTicketsStatusBoarded,
        GroupTicketStatus.cancelled => l10n.groupTicketsStatusCancelled,
        GroupTicketStatus.unknown => l10n.groupTicketsStatusUnknown,
      };
}

/// Un message seul, au centre : l'attente d'émission ou l'échec.
class _Notice extends StatelessWidget {
  const _Notice({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: KoraCard(
        variant: KoraCardVariant.mist,
        child: Text(
          message,
          textAlign: TextAlign.center,
          style: AppTextStyles.body(context.koraColors.textMuted),
        ),
      ),
    );
  }
}

/// L'écran ouvert sans réservation — un lien profond.
class _MissingBooking extends StatelessWidget {
  const _MissingBooking();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                l10n.summaryMissingTitle,
                style: AppTextStyles.titleMedium(context.koraTitleColor),
              ),
              const SizedBox(height: AppSpacing.md),
              KoraButton(
                label: l10n.summaryMissingPrimary,
                onPressed: () => context.go('/voyages'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
