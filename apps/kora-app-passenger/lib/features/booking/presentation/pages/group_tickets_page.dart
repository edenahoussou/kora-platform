import 'dart:typed_data';

import 'package:file_saver/file_saver.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';
import 'package:printing/printing.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/group_ticket.dart';
import '../bloc/group_tickets_cubit.dart';
import '../pdf/ticket_pdf.dart';
import '../widgets/kora_qr.dart';

/// Ce qu'attend [GroupTicketsPage] : la réservation dont on liste les
/// billets.
class GroupTicketsArgs {
  const GroupTicketsArgs({
    required this.reservationId,
    this.passengers = 0,
    this.departure,
    this.exitToHome = false,
  });

  final String reservationId;

  /// `true` au sortir d'un paiement : le retour mène alors à l'accueil,
  /// plutôt que de rouvrir le tunnel d'achat. Ouvert depuis l'onglet
  /// Voyages, l'écran rend simplement la main au billet consulté.
  final bool exitToHome;

  /// Le nombre attendu, pour l'annoncer avant même que le serveur réponde.
  final int passengers;

  /// Le départ payé, pour l'imprimer sur le billet exporté.
  ///
  /// `bookings/{uuid}/tickets` ne rend que des identifiants de gares et de
  /// départs — de quoi valider, pas de quoi écrire « Cotonou -> Abidjan »
  /// sur un PDF. L'écran de confirmation, lui, a le départ sous la main.
  final Departure? departure;
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
                      child: _Content(
                        state: state,
                        isAlone: isAlone,
                        departure: args.departure,
                      ),
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
  const _Content({required this.state, required this.isAlone, this.departure});

  final GroupTicketsState state;

  /// Voir [GroupTicketsArgs.departure].
  final Departure? departure;

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
          _TicketRow(
            ticket: state.tickets[i],
            index: i + 1,
            departure: departure,
          ),
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
  const _TicketRow({required this.ticket, required this.index, this.departure});

  final GroupTicket ticket;
  final int index;

  /// Voir [GroupTicketsArgs.departure].
  final Departure? departure;

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
        child: Padding(
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
              // Deux gestes, et non un : enregistrer le billet chez soi
              // n'est pas l'envoyer à quelqu'un. Le partage seul obligeait
              // à passer par une application tierce pour garder son propre
              // billet.
              KoraButton(
                label: l10n.ticketsDownload,
                variant: KoraButtonVariant.ghost,
                icon: Icons.file_download_outlined,
                onPressed: () => _save(dialogContext, l10n),
              ),
              const SizedBox(height: 8),
              KoraButton(
                label: l10n.ticketsShare,
                variant: KoraButtonVariant.ghost,
                icon: Icons.ios_share_rounded,
                onPressed: () => _share(dialogContext, l10n),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Enregistre le billet dans les téléchargements du téléphone.
  ///
  /// Le fichier part chez le voyageur, pas chez un tiers : c'est tout
  /// l'écart avec [_share], que le bouton unique d'avant confondait — il
  /// ouvrait le sélecteur de partage, et garder son propre billet
  /// obligeait à passer par une application tierce.
  ///
  /// `saveAs` et non `saveFile` : sur Android, le second écrit dans le
  /// dossier **privé** de l'application — le fichier existe, mais reste
  /// introuvable depuis Fichiers ou Téléchargements, ce qui revient à ne
  /// pas l'avoir enregistré. `saveAs` ouvre le sélecteur du système, où
  /// le voyageur choisit l'emplacement, et le fichier lui appartient
  /// vraiment.
  Future<void> _save(BuildContext context, AppLocalization l10n) async {
    await _export(context, l10n, (bytes) async {
      final path = await FileSaver.instance.saveAs(
        name: 'KORA-${ticket.shortCode}',
        bytes: bytes,
        fileExtension: 'pdf',
        mimeType: MimeType.pdf,
      );

      if (!context.mounted) return;

      // `null` quand le voyageur referme le sélecteur : rien n'a été
      // enregistré, et annoncer le contraire serait un mensonge.
      if (path != null) KoraToaster.show(context, l10n.ticketsDownloadDone);
    });
  }

  /// Envoie le billet ailleurs : WhatsApp, Drive, courriel — Android
  /// présente son sélecteur et le voyageur choisit.
  Future<void> _share(BuildContext context, AppLocalization l10n) async {
    await _export(context, l10n, (bytes) async {
      await Printing.sharePdf(
        bytes: bytes,
        filename: 'KORA-${ticket.shortCode}.pdf',
      );
    });
  }

  /// Fabrique le PDF, puis confie les octets à [deliver].
  ///
  /// Le document se fabrique ici plutôt que de se demander au serveur :
  /// l'API ne publie pas de route PDF — celle de `kora-ops-web` vit derrière
  /// la session des agents — et le QR signé, lui, est déjà là. C'est lui qui
  /// vaut billet ; le reste n'est que mise en page.
  Future<void> _export(
    BuildContext context,
    AppLocalization l10n,
    Future<void> Function(Uint8List bytes) deliver,
  ) async {
    try {
      final bytes = await buildTicketPdf(
        ticket: ticket,
        l10n: l10n,
        departure: departure,
      );

      await deliver(bytes);
    } on Object {
      if (!context.mounted) return;
      KoraToaster.show(context, l10n.ticketsDownloadFailed);
    }
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
