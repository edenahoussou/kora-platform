import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_radius.dart';
import '../../app/theme/app_spacing.dart';
import '../../app/theme/kora_colors.dart';
import '../../features/ops/domain/entities/seat.dart';

/// Plan des sièges du véhicule (3 par rangée), utilisé par la Descente
/// anticipée pour visualiser les places libérées sur les segments
/// suivants.
class SeatMap extends StatelessWidget {
  const SeatMap({required this.seats, super.key});

  final List<Seat> seats;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Wrap(
          spacing: AppSpacing.sm,
          runSpacing: AppSpacing.sm,
          children: seats.map((Seat seat) => _SeatChip(seat: seat)).toList(),
        ),
        const SizedBox(height: AppSpacing.md),
        const Wrap(
          spacing: AppSpacing.md,
          runSpacing: AppSpacing.xs,
          children: <Widget>[
            _LegendDot(color: AppColors.success, label: 'Libéré'),
            _LegendDot(color: AppColors.warning, label: 'Se libère bientôt'),
            _LegendDot(color: AppColors.kora, label: 'Occupé'),
          ],
        ),
      ],
    );
  }
}

class _SeatChip extends StatelessWidget {
  const _SeatChip({required this.seat});

  final Seat seat;

  @override
  Widget build(BuildContext context) {
    final Color color = switch (seat) {
      Seat(statut: SeatStatus.libere) => AppColors.success,
      Seat(libereAPartirDe: final String? stop) when stop != null =>
        AppColors.warning,
      _ => AppColors.kora,
    };
    final Color foreground =
        seat.statut == SeatStatus.occupe && seat.libereAPartirDe == null
            ? AppColors.white
            : AppColors.onGold;

    return Tooltip(
      message: seat.libereAPartirDe != null
          ? 'Libéré à partir de ${seat.libereAPartirDe}'
          : (seat.passengerName ?? 'Libre'),
      child: Container(
        width: 52,
        height: 52,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: color.withValues(
              alpha: seat.statut == SeatStatus.occupe &&
                      seat.libereAPartirDe == null
                  ? 1
                  : 0.15),
          border: Border.all(color: color, width: 1.5),
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        child: Text(
          seat.numero,
          style: TextStyle(
            color:
                seat.statut == SeatStatus.occupe && seat.libereAPartirDe == null
                    ? foreground
                    : color,
            fontWeight: FontWeight.w700,
            fontSize: 12,
          ),
        ),
      ),
    );
  }
}

class _LegendDot extends StatelessWidget {
  const _LegendDot({required this.color, required this.label});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Container(
            width: 10,
            height: 10,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
        const SizedBox(width: 6),
        Text(label,
            style:
                TextStyle(color: context.koraColors.textMuted, fontSize: 11)),
      ],
    );
  }
}
