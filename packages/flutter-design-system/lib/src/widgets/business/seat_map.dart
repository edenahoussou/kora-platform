import 'package:flutter/material.dart';

import '../../colors/app_colors.dart';
import '../../colors/kora_colors.dart';
import '../../dimens/app_dimens.dart';

/// Les 4 états possibles d'un siège, repris de `.seat` / `.seat.selected` /
/// `.seat.busy` / `.seat:disabled` (`kora-ops-web/css/style.css`, bloc
/// `.bus-cabin`).
enum KoraSeatStatus { available, selected, occupied, disabled }

/// Un siège du plan de bus.
class KoraSeat {
  const KoraSeat({required this.label, this.status = KoraSeatStatus.available});

  /// Ex: "12A".
  final String label;
  final KoraSeatStatus status;

  KoraSeat copyWith({KoraSeatStatus? status}) {
    return KoraSeat(label: label, status: status ?? this.status);
  }
}

/// Plan de sièges d'un bus — équivalent `.bus-cabin` + `.seat-grid` du
/// prototype : grille de sièges avec une allée centrale, sièges
/// sélectionnables tant qu'ils ne sont pas déjà occupés/bloqués.
///
/// Exemple d'utilisation :
/// ```dart
/// SeatMap(
///   seats: [
///     KoraSeat(label: '1A'),
///     KoraSeat(label: '1B', status: KoraSeatStatus.occupied),
///     KoraSeat(label: '1C', status: KoraSeatStatus.selected),
///     KoraSeat(label: '1D'),
///   ],
///   onSeatTap: (seat) => ...,
/// )
/// ```
class SeatMap extends StatelessWidget {
  const SeatMap({
    super.key,
    required this.seats,
    this.columns = 4,
    this.onSeatTap,
  });

  final List<KoraSeat> seats;

  /// Nombre de sièges par rangée. L'allée est insérée automatiquement au
  /// milieu (entre les colonnes `columns ~/ 2 - 1` et `columns ~/ 2`).
  final int columns;

  final ValueChanged<KoraSeat>? onSeatTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final aisleAfter = columns ~/ 2 - 1;

    return Container(
      padding: const EdgeInsets.fromLTRB(24, 30, 24, 20),
      decoration: BoxDecoration(
        color: koraColors.background,
        border: Border.all(color: koraColors.line, width: 2),
        borderRadius: const BorderRadius.only(
          topLeft: Radius.circular(48),
          topRight: Radius.circular(48),
          bottomLeft: Radius.circular(AppRadius.lg),
          bottomRight: Radius.circular(AppRadius.lg),
        ),
      ),
      child: Wrap(
        spacing: 10,
        runSpacing: 10,
        children: [
          for (var i = 0; i < seats.length; i++) ...[
            _SeatButton(seat: seats[i], onTap: onSeatTap),
            if (i % columns == aisleAfter) const SizedBox(width: 14),
          ],
        ],
      ),
    );
  }
}

class _SeatButton extends StatelessWidget {
  const _SeatButton({required this.seat, this.onTap});

  final KoraSeat seat;
  final ValueChanged<KoraSeat>? onTap;

  bool get _isTappable =>
      seat.status == KoraSeatStatus.available ||
      seat.status == KoraSeatStatus.selected;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    late final Color background;
    late final Color foreground;
    late final Color border;

    switch (seat.status) {
      case KoraSeatStatus.available:
        background = koraColors.surface;
        foreground = AppColors.kora;
        border = koraColors.line;
        break;
      case KoraSeatStatus.selected:
        background = AppColors.gold;
        foreground = AppColors.kora;
        border = AppColors.gold;
        break;
      case KoraSeatStatus.occupied:
        background = AppColors.digital;
        foreground = AppColors.white;
        border = AppColors.digital;
        break;
      case KoraSeatStatus.disabled:
        background = koraColors.background;
        foreground = koraColors.textMuted;
        border = koraColors.line;
        break;
    }

    return SizedBox(
      width: 45,
      height: 43,
      child: Material(
        color: background,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: InkWell(
          onTap: _isTappable ? () => onTap?.call(seat) : null,
          borderRadius: BorderRadius.circular(AppRadius.sm),
          child: DecoratedBox(
            decoration: BoxDecoration(
              border: Border.all(color: border),
              borderRadius: BorderRadius.circular(AppRadius.sm),
            ),
            child: Center(
              child: Text(
                seat.label,
                style: TextStyle(
                  fontSize: 9,
                  fontWeight: FontWeight.w800,
                  color: foreground,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
