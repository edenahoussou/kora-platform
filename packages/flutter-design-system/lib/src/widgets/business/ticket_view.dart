import 'package:flutter/material.dart';

import '../../colors/app_colors.dart';
import '../../dimens/app_dimens.dart';

/// Ticket de trajet — équivalent `.ticket-card` du prototype
/// (`kora-ops-web/css/style.css`) : liseré doré, grand affichage du
/// trajet, informations passager/siège/date, QR code et code-barres
/// stylisés.
///
/// Exemple d'utilisation :
/// ```dart
/// TicketView(
///   passengerName: 'Awa Kouassi',
///   origin: 'Cotonou',
///   destination: 'Porto-Novo',
///   departureTime: '14:30',
///   date: '12 Août 2026',
///   seatLabel: '12A',
///   ticketCode: 'KRA-284910',
/// )
/// ```
class TicketView extends StatelessWidget {
  const TicketView({
    super.key,
    required this.passengerName,
    required this.origin,
    required this.destination,
    required this.departureTime,
    required this.date,
    required this.seatLabel,
    required this.ticketCode,
  });

  final String passengerName;
  final String origin;
  final String destination;
  final String departureTime;
  final String date;
  final String seatLabel;
  final String ticketCode;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Container(
          padding: const EdgeInsets.fromLTRB(26, 26, 26, 22),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(AppRadius.xl),
            boxShadow: [
              BoxShadow(
                color: AppColors.kora.withValues(alpha: 0.13),
                offset: const Offset(0, 25),
                blurRadius: 65,
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'KORA EXPRESS',
                    style: TextStyle(color: AppColors.kora, fontSize: 13, fontWeight: FontWeight.w900),
                  ),
                  Text(
                    date,
                    style: TextStyle(color: AppColors.graphite.withValues(alpha: 0.6), fontSize: 10),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              Row(
                children: [
                  Expanded(child: _RoutePoint(label: origin, alignEnd: false)),
                  const _RouteBadge(),
                  Expanded(child: _RoutePoint(label: destination, alignEnd: true)),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              const _Dashes(),
              const SizedBox(height: AppSpacing.lg),
              Row(
                children: [
                  _TicketField(label: 'PASSAGER', value: passengerName),
                  const SizedBox(width: AppSpacing.lg),
                  _TicketField(label: 'DÉPART', value: departureTime),
                  const SizedBox(width: AppSpacing.lg),
                  _TicketField(label: 'SIÈGE', value: seatLabel),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  const _QrPlaceholder(),
                  const SizedBox(width: AppSpacing.lg),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const _Barcode(),
                        const SizedBox(height: 6),
                        Text(
                          ticketCode,
                          style: const TextStyle(
                            color: AppColors.kora,
                            fontSize: 9,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _RoutePoint extends StatelessWidget {
  const _RoutePoint({required this.label, required this.alignEnd});

  final String label;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    return Text(
      label,
      textAlign: alignEnd ? TextAlign.right : TextAlign.left,
      style: const TextStyle(color: AppColors.kora, fontSize: 22, fontWeight: FontWeight.w900),
    );
  }
}

class _RouteBadge extends StatelessWidget {
  const _RouteBadge();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 30,
      height: 30,
      margin: const EdgeInsets.symmetric(horizontal: 10),
      alignment: Alignment.center,
      decoration: BoxDecoration(color: AppColors.gold, borderRadius: BorderRadius.circular(AppRadius.sm)),
      child: const Icon(Icons.arrow_forward, size: 15, color: AppColors.kora),
    );
  }
}

class _Dashes extends StatelessWidget {
  const _Dashes();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 1,
      child: LayoutBuilder(
        builder: (context, constraints) {
          const dashWidth = 6.0;
          const dashSpace = 5.0;
          final count = (constraints.maxWidth / (dashWidth + dashSpace)).floor();
          return Row(
            children: List.generate(
              count,
              (_) => Padding(
                padding: const EdgeInsets.only(right: dashSpace),
                child: Container(width: dashWidth, height: 1, color: AppColors.kora.withValues(alpha: 0.14)),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _TicketField extends StatelessWidget {
  const _TicketField({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            label,
            style: TextStyle(color: AppColors.graphite.withValues(alpha: 0.6), fontSize: 8, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(color: AppColors.kora, fontSize: 11, fontWeight: FontWeight.w800),
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

/// QR code stylisé (grille de carrés pleins/vides) — pas un vrai QR
/// scannable, juste une reproduction visuelle de `.ticket-qr`.
class _QrPlaceholder extends StatelessWidget {
  const _QrPlaceholder();

  @override
  Widget build(BuildContext context) {
    // Motif fixe (pas aléatoire) pour que le rendu soit stable d'un
    // build à l'autre — utile pour les stories/golden tests.
    const pattern = [
      1, 0, 1, 1, 0, 1, 0,
      0, 1, 0, 0, 1, 1, 1,
      1, 1, 1, 0, 1, 0, 1,
      0, 0, 1, 1, 0, 1, 0,
      1, 0, 0, 1, 1, 1, 0,
      0, 1, 1, 0, 0, 1, 1,
      1, 0, 1, 1, 0, 0, 1,
    ];

    return Container(
      width: 88,
      height: 88,
      padding: const EdgeInsets.all(6),
      decoration: BoxDecoration(
        border: Border.all(color: AppColors.kora.withValues(alpha: 0.1)),
        borderRadius: BorderRadius.circular(AppRadius.sm),
      ),
      child: GridView.count(
        crossAxisCount: 7,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 1,
        crossAxisSpacing: 1,
        children: [
          for (final bit in pattern) ColoredBox(color: bit == 1 ? AppColors.kora : AppColors.white),
        ],
      ),
    );
  }
}

/// Code-barres stylisé (rayures verticales de largeurs variables) —
/// reproduction visuelle de `.ticket-code span`.
class _Barcode extends StatelessWidget {
  const _Barcode();

  @override
  Widget build(BuildContext context) {
    const widths = [2.0, 1.0, 3.0, 1.0, 2.0, 1.0, 1.0, 3.0, 2.0, 1.0, 2.0, 1.0, 3.0, 1.0, 2.0];

    return SizedBox(
      height: 26,
      child: Row(
        children: [
          for (final w in widths)
            Padding(
              padding: const EdgeInsets.only(right: 2),
              child: Container(width: w, color: AppColors.kora),
            ),
        ],
      ),
    );
  }
}
