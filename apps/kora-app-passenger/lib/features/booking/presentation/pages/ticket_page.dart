import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/ticket.dart';
import '../widgets/digital_ticket.dart';
import '../widgets/state_view.dart';

/// Données passées à [TicketPage] par `go_router` (`extra`).
class TicketArgs {
  const TicketArgs({required this.ticket, this.passengerName});

  final Ticket ticket;
  final String? passengerName;
}

/// Écran `ticket` de la maquette : le billet numérique, prêt à présenter.
///
/// Distinct du détail du billet, qui liste ce qu'on peut en faire. Ici il
/// n'y a qu'une chose à faire : montrer l'écran au convoyeur. Tout le
/// reste — suivi, partage — passe après, et en petit.
class TicketPage extends StatelessWidget {
  const TicketPage({super.key, this.args});

  final TicketArgs? args;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = this.args;

    if (args == null) {
      return Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: ListView(
              children: [
                ScreenHeader(
                  eyebrow: l10n.ticketEyebrow,
                  title: l10n.ticketTitle,
                ),
                StateView(
                  icon: Icons.confirmation_number_outlined,
                  title: l10n.ticketMissingTitle,
                  message: l10n.ticketMissingText,
                  primaryLabel: l10n.ticketMissingPrimary,
                  onPrimary: () => context.go('/voyages'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    final cancelled = args.ticket.status == TicketStatus.cancelled;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: ListView(
            children: [
              ScreenHeader(
                eyebrow: l10n.ticketEyebrow,
                title: l10n.ticketTitle,
                action: KoraIconButton(
                  icon: Icons.ios_share_rounded,
                  onTap: () => KoraToaster.show(context, l10n.ticketShareSoon),
                ),
              ),
              if (!cancelled) ...[
                const _StatusStrip(),
                const SizedBox(height: AppSpacing.md),
              ],
              DigitalTicket(
                ticket: args.ticket,
                passengerName: args.passengerName,
              ),
              const SizedBox(height: AppSpacing.lg),
              KoraMenuList(
                items: [
                  KoraMenuItem(
                    icon: Icons.my_location_rounded,
                    title: l10n.ticketTrackTitle,
                    subtitle: l10n.ticketTrackSubtitle,
                    onTap: () =>
                        KoraToaster.show(context, l10n.ticketShareSoon),
                  ),
                  KoraMenuItem(
                    icon: Icons.ios_share_rounded,
                    title: l10n.ticketShareTitle,
                    subtitle: l10n.ticketShareSubtitle,
                    onTap: () =>
                        KoraToaster.show(context, l10n.ticketShareSoon),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              const _OfflineNote(),
              const SizedBox(height: AppSpacing.xl),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.ticket-status-strip` : le bandeau vert au-dessus du billet.
class _StatusStrip extends StatelessWidget {
  const _StatusStrip();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.success.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(AppRadius.input),
        border: Border.all(color: AppColors.success.withValues(alpha: 0.35)),
      ),
      child: Row(
        children: [
          Container(
            width: 30,
            height: 30,
            alignment: Alignment.center,
            decoration: const BoxDecoration(
              color: AppColors.success,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.check_rounded,
              size: 17,
              color: AppColors.white,
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  l10n.ticketStripTitle,
                  style: AppTextStyles.titleMedium(context.koraTitleColor),
                ),
                const SizedBox(height: 1),
                Text(
                  l10n.ticketStripLead,
                  style: AppTextStyles.caption(context.koraColors.textMuted),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// `.offline-ticket-note` : le billet reste lisible sans réseau.
///
/// L'information compte plus qu'elle n'en a l'air : les gares KORA sont
/// souvent en limite de couverture, et c'est précisément là qu'on présente
/// son billet.
class _OfflineNote extends StatelessWidget {
  const _OfflineNote();

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      children: [
        Icon(Icons.cloud_done_rounded, size: 16, color: koraColors.textMuted),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: Text(
            context.l10n.ticketOfflineNote,
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
        ),
      ],
    );
  }
}
