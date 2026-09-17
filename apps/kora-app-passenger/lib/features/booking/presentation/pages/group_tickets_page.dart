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
import '../widgets/official_ticket_download.dart';

/// Ce qu'attend [GroupTicketsPage] : la réservation dont on liste les
/// billets.
class GroupTicketsArgs {
  const GroupTicketsArgs({
    required this.reservationId,
    this.passengers = 0,
    this.exitToHome = false,
  });

  final String reservationId;

  /// `true` au sortir d'un paiement : le retour mène alors à l'accueil,
  /// plutôt que de rouvrir le tunnel d'achat. Ouvert depuis l'onglet
  /// Voyages, l'écran rend simplement la main au billet consulté.
  final bool exitToHome;

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

    // Au sortir d'un achat, le parcours s'arrête ici. Derrière cet écran il
    // y a le paiement, l'attente, le plan des sièges — des étapes qu'on ne
    // repasse pas une fois le billet émis, et que le geste « retour »
    // rouvrait pourtant une à une. On sort donc vers l'accueil, que le
    // voyageur revienne par la flèche ou par le geste du système.
    //
    // Depuis l'onglet Voyages, en revanche, derrière il y a le billet
    // qu'on consultait : c'est là que le retour doit ramener.
    return PopScope(
      canPop: !args.exitToHome,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) context.go('/home');
      },
      child: Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: BlocBuilder<GroupTicketsCubit, GroupTicketsState>(
              builder: (context, state) {
                final count = state.tickets.isNotEmpty
                    ? state.tickets.length
                    : args.passengers;

                // Le même écran sert le voyageur seul, qui vient d'ici depuis
                // que « Afficher mon billet » y mène : lui parler de « groupe »
                // et de « chaque QR » devant son unique billet sonnerait faux.
                final isAlone = count <= 1;

                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    ScreenHeader(
                      eyebrow: isAlone
                          ? l10n.ticketsOneEyebrow
                          : l10n.groupTicketsEyebrow(count),
                      title: isAlone
                          ? l10n.ticketsOneTitle
                          : l10n.groupTicketsTitle,
                      onBack: args.exitToHome
                          ? () => context.go('/home')
                          : null,
                    ),
                    Expanded(
                      child: _Content(state: state, isAlone: isAlone),
                    ),
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
      ),
    );
  }
}

/// Le corps de l'écran : la liste, l'attente, ou l'échec.
class _Content extends StatelessWidget {
  const _Content({required this.state, required this.isAlone});

  final GroupTicketsState state;

  /// Un seul billet : les textes qui parlent du groupe n'ont plus lieu
  /// d'être.
  final bool isAlone;

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
      return _Notice(
        message: isAlone ? l10n.ticketsOnePending : l10n.groupTicketsPending,
      );
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
            isAlone ? l10n.ticketsOneNotice : l10n.groupTicketsNotice,
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
  /// Une boîte plutôt qu'un écran : le contrôleur scanne, puis on revient
  /// à la liste pour le voyageur suivant. Ouvrir et refermer dix écrans à
  /// la file, à l'embarquement, ferait perdre le fil.
  ///
  /// Centrée, et non posée en bas comme la feuille d'avant : c'est un
  /// téléphone qu'on tend à bout de bras devant une douchette, et un code
  /// collé au bord bas oblige à incliner l'appareil pour le présenter.
  ///
  /// Défilante : le PDF officiel ajoute ses actions sous le code, et un
  /// petit écran ne doit pas les couper.
  void _showQr(BuildContext context) {
    final l10n = context.l10n;

    showDialog<void>(
      context: context,
      builder: (dialogContext) => Dialog(
        backgroundColor: dialogContext.koraColors.surface,
        insetPadding: const EdgeInsets.symmetric(horizontal: 28, vertical: 24),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(18, 22, 18, 22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                ticket.label,
                textAlign: TextAlign.center,
                style: AppTextStyles.titleMedium(dialogContext.koraTitleColor),
              ),
              const SizedBox(height: 4),
              Text(
                ticket.seatCode.isEmpty
                    ? ticket.shortCode
                    : '${l10n.passengersSeat(ticket.seatCode)} · '
                          '${ticket.shortCode}',
                textAlign: TextAlign.center,
                style: AppTextStyles.body(dialogContext.koraColors.textMuted),
              ),
              const SizedBox(height: AppSpacing.md),
              // La charge du serveur, jamais la référence : c'est elle que
              // la douchette du convoyeur attend.
              KoraQr(payload: ticket.qrCode, size: 190),
              const SizedBox(height: AppSpacing.md),
              // Le PDF officiel, tel que KORA le génère : plus aucun billet
              // n'est recomposé sur le téléphone à partir du JSON.
              OfficialTicketDownload(ticketId: ticket.id),
            ],
          ),
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
