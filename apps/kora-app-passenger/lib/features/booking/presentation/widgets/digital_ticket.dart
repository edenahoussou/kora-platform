import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../domain/entities/city.dart';
import '../../domain/entities/ticket.dart';

/// `.digital-ticket` : le billet lui-même, celui qu'on présente à
/// l'embarquement.
///
/// Deux moitiés séparées par une découpe crantée, comme un billet papier :
/// en haut le trajet, en bas le code et les informations d'embarquement.
/// La forme n'est pas gratuite — elle dit d'un coup d'œil, à distance, que
/// l'écran affiche un titre de transport et non une confirmation de plus.
class DigitalTicket extends StatelessWidget {
  const DigitalTicket({super.key, required this.ticket, this.passengerName});

  final Ticket ticket;

  /// `A. HOUNKPÈ` — le porteur, en bas du billet.
  final String? passengerName;

  bool get _cancelled => ticket.status == TicketStatus.cancelled;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final departure = ticket.departure;

    return Stack(
      children: [
        DecoratedBox(
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(22),
            boxShadow: const [
              BoxShadow(
                color: Color(0x1A001D4E),
                blurRadius: 24,
                offset: Offset(0, 10),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 18, 18, 0),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const _Wordmark(),
                        KoraTag(
                          label: _cancelled
                              ? l10n.ticketStateCancelled
                              : l10n.ticketStateConfirmed,
                          variant: _cancelled
                              ? KoraTagVariant.danger
                              : KoraTagVariant.gold,
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    _Route(
                      origin: departure.line.origin,
                      destination: departure.line.destination,
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                ),
              ),
              const _Perforation(),
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
                child: Column(
                  children: [
                    _QrBlock(payload: ticket.reference),
                    const SizedBox(height: AppSpacing.md),
                    Row(
                      children: [
                        Expanded(
                          child: _Field(
                            label: l10n.ticketFieldDate,
                            value: KoraDateFormats.dayMonthShort
                                .format(departure.departsAt)
                                .toUpperCase(),
                          ),
                        ),
                        Expanded(
                          child: _Field(
                            label: l10n.ticketFieldDeparture,
                            value: KoraDateFormats.time.format(
                              departure.departsAt,
                            ),
                          ),
                        ),
                        Expanded(
                          child: _Field(
                            label: l10n.ticketFieldSeat,
                            value: ticket.seat.label,
                          ),
                        ),
                      ],
                    ),
                    const Divider(height: 26),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Flexible(
                          child: Text(
                            ticket.reference,
                            overflow: TextOverflow.ellipsis,
                            style: AppTextStyles.caption(
                              AppColors.seatBlockedTextLight,
                            ),
                          ),
                        ),
                        if (passengerName != null)
                          Text(
                            passengerName!,
                            style: const TextStyle(
                              color: AppColors.kora,
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        // `.cancel-stamp` : le tampon qui barre le billet annulé. En
        // surimpression plutôt qu'en badge — un billet annulé doit se
        // reconnaître sans lire.
        if (_cancelled)
          Positioned.fill(
            child: IgnorePointer(
              child: Center(
                child: Transform.rotate(
                  angle: -0.28,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 18,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      border: Border.all(color: AppColors.danger, width: 3),
                      borderRadius: BorderRadius.circular(AppRadius.sm),
                    ),
                    child: Text(
                      l10n.ticketStampCancelled,
                      style: const TextStyle(
                        color: AppColors.danger,
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 2,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}

/// `CTN ⟶ MLV`, les codes en grand et les villes en dessous.
class _Route extends StatelessWidget {
  const _Route({required this.origin, required this.destination});

  final City origin;
  final City destination;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: _City(code: origin.code, name: origin.name),
        ),
        const Padding(
          padding: EdgeInsets.only(top: 6),
          child: Icon(
            Icons.arrow_forward_rounded,
            color: AppColors.gold,
            size: 22,
          ),
        ),
        Expanded(
          child: _City(
            code: destination.code,
            name: destination.name,
            alignEnd: true,
          ),
        ),
      ],
    );
  }
}

class _City extends StatelessWidget {
  const _City({required this.code, required this.name, this.alignEnd = false});

  final String code;
  final String name;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: alignEnd
          ? CrossAxisAlignment.end
          : CrossAxisAlignment.start,
      children: [
        Text(
          code,
          style: const TextStyle(
            color: AppColors.kora,
            fontSize: 27,
            fontWeight: FontWeight.w900,
            letterSpacing: -0.5,
          ),
        ),
        Text(
          name,
          style: AppTextStyles.caption(AppColors.seatBlockedTextLight),
        ),
      ],
    );
  }
}

/// `.ticket-cut` : la ligne de découpe, deux encoches et des pointillés.
class _Perforation extends StatelessWidget {
  const _Perforation();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 22,
      child: Stack(
        alignment: Alignment.center,
        children: [
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 18),
            child: _DashedLine(),
          ),
          Positioned(left: -11, child: _Notch()),
          Positioned(right: -11, child: _Notch()),
        ],
      ),
    );
  }
}

class _Notch extends StatelessWidget {
  const _Notch();

  @override
  Widget build(BuildContext context) {
    // De la couleur du fond de page, pas blanche : c'est un trou dans le
    // billet, pas une pastille posée dessus.
    return Container(
      width: 22,
      height: 22,
      decoration: BoxDecoration(
        color: context.koraColors.background,
        shape: BoxShape.circle,
      ),
    );
  }
}

class _DashedLine extends StatelessWidget {
  const _DashedLine();

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        const dash = 5.0;
        const gap = 4.0;
        final count = (constraints.maxWidth / (dash + gap)).floor();

        return Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: List.generate(
            count,
            (_) => Container(
              width: dash,
              height: 1.5,
              color: AppColors.seatBorderLight,
            ),
          ),
        );
      },
    );
  }
}

/// Le carré du code d'embarquement.
///
/// **Décoratif pour l'instant.** Le serveur porte bien un `qr_code` sur le
/// billet, mais aucun endpoint ne l'expose encore et l'app n'embarque pas
/// d'encodeur. Cette grille est donc dérivée de la référence : elle change
/// d'un billet à l'autre, mais **ne se scanne pas**. Le jour où le champ
/// arrive, c'est ce widget seul qui change.
class _QrBlock extends StatelessWidget {
  const _QrBlock({required this.payload});

  final String payload;

  /// 9 × 9, comme la maquette.
  static const int _side = 9;

  @override
  Widget build(BuildContext context) {
    final cells = _cellsOf(payload);

    return Semantics(
      label: context.l10n.ticketQrSemantics,
      child: Container(
        padding: const EdgeInsets.all(9),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(AppRadius.input),
          border: Border.all(color: AppColors.seatBorderLight),
        ),
        child: SizedBox(
          width: 132,
          height: 132,
          child: GridView.count(
            crossAxisCount: _side,
            physics: const NeverScrollableScrollPhysics(),
            children: [
              for (final filled in cells)
                ColoredBox(color: filled ? AppColors.kora : AppColors.white),
            ],
          ),
        ),
      ),
    );
  }

  /// Une grille stable pour une référence donnée : le même billet montre
  /// toujours le même dessin, deux billets différents non.
  static List<bool> _cellsOf(String payload) {
    var seed = 0x811c9dc5;
    for (final unit in payload.codeUnits) {
      seed = (seed ^ unit) * 0x01000193 & 0x7fffffff;
    }

    return [
      for (var i = 0; i < _side * _side; i++)
        ((seed >> (i % 31)) ^ (i * 2654435761)) & 1 == 1,
    ];
  }
}

class _Field extends StatelessWidget {
  const _Field({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: AppTextStyles.caption(AppColors.seatBlockedTextLight),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: const TextStyle(
            color: AppColors.kora,
            fontSize: 15,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    );
  }
}

/// Le nom de la marque, en toutes lettres faute d'image vectorielle.
class _Wordmark extends StatelessWidget {
  const _Wordmark();

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 26,
          height: 26,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.kora,
            borderRadius: BorderRadius.circular(8),
          ),
          child: const Text(
            'K',
            style: TextStyle(
              color: AppColors.gold,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        const SizedBox(width: 7),
        const Text(
          'KORA EXPRESS',
          style: TextStyle(
            color: AppColors.kora,
            fontSize: 12,
            fontWeight: FontWeight.w900,
            letterSpacing: 0.5,
          ),
        ),
      ],
    );
  }
}
