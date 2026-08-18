import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_radius.dart';
import '../../app/theme/app_spacing.dart';
import '../../app/theme/app_text_styles.dart';
import '../../app/theme/kora_colors.dart';
import '../../features/ops/domain/entities/seat.dart';

/// Plan du bus (voir échange du 17/08/2026) : avant du véhicule avec les
/// places chauffeur/convoyeur, puis les rangées de sièges passagers
/// séparées par une allée centrale — 1 siège à gauche de l'allée, 2 à
/// droite (disposition 1+2, courante sur les cars intercités), fidèle au
/// séquençage `MockOpsDataSource` (3 sièges par rangée : A à gauche de
/// l'allée, B/C à droite).
class SeatMap extends StatelessWidget {
  const SeatMap({required this.seats, super.key});

  final List<Seat> seats;

  /// Regroupe la liste plate en rangées de 3 (A, B, C).
  List<List<Seat>> get _rows {
    final List<List<Seat>> rows = <List<Seat>>[];
    for (int i = 0; i < seats.length; i += 3) {
      rows.add(seats.sublist(i, i + 3 > seats.length ? seats.length : i + 3));
    }
    return rows;
  }

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Container(
          decoration: BoxDecoration(
            border: Border.all(color: koraColors.line, width: 1.5),
            borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(40),
              topRight: Radius.circular(40),
              bottomLeft: Radius.circular(AppRadius.lg),
              bottomRight: Radius.circular(AppRadius.lg),
            ),
          ),
          padding: const EdgeInsets.symmetric(
              vertical: AppSpacing.md, horizontal: AppSpacing.sm),
          child: Column(
            children: <Widget>[
              // --- Avant du bus : chauffeur / convoyeur ---
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: AppSpacing.xs),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: <Widget>[
                    _CrewSeat(
                        icon: Icons.airline_seat_recline_normal,
                        label: 'Chauffeur'),
                    _CrewSeat(icon: Icons.badge_outlined, label: 'Convoyeur'),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.xs),
              Row(
                children: <Widget>[
                  const SizedBox(width: 4),
                  Expanded(child: Divider(color: koraColors.line)),
                  Padding(
                    padding:
                        const EdgeInsets.symmetric(horizontal: AppSpacing.sm),
                    child: Icon(Icons.door_front_door_outlined,
                        size: 14, color: koraColors.textMuted),
                  ),
                  Expanded(child: Divider(color: koraColors.line)),
                  const SizedBox(width: 4),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              // --- Rangées de sièges, séparées par l'allée centrale ---
              ..._rows.map((List<Seat> row) {
                final Seat? left = row.isNotEmpty ? row[0] : null;
                final Seat? rightA = row.length > 1 ? row[1] : null;
                final Seat? rightB = row.length > 2 ? row[2] : null;

                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 5),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: <Widget>[
                      if (left != null)
                        _SeatChip(seat: left)
                      else
                        const SizedBox(width: 44),
                      // Allée centrale.
                      SizedBox(
                        width: 36,
                        child: Center(
                          child: Container(height: 1, color: koraColors.line),
                        ),
                      ),
                      Row(
                        children: <Widget>[
                          if (rightA != null) _SeatChip(seat: rightA),
                          const SizedBox(width: AppSpacing.sm),
                          if (rightB != null) _SeatChip(seat: rightB),
                        ],
                      ),
                    ],
                  ),
                );
              }),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        const Wrap(
          spacing: AppSpacing.md,
          runSpacing: AppSpacing.xs,
          children: <Widget>[
            _LegendDot(color: AppColors.success, label: 'Libéré'),
            _LegendDot(color: AppColors.warning, label: 'Se libère bientôt'),
            _LegendDot(color: AppColors.digital, label: 'Occupé'),
          ],
        ),
      ],
    );
  }
}

class _CrewSeat extends StatelessWidget {
  const _CrewSeat({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        Container(
          width: 44,
          height: 44,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: context.koraColors.background,
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(color: context.koraColors.line),
          ),
          child: Icon(icon, size: 20, color: context.koraColors.textMuted),
        ),
        const SizedBox(height: 3),
        Text(label, style: AppTextStyles.eyebrow(context.koraColors.textMuted)),
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
      _ => AppColors.digital,
    };
    final bool isFullyOccupied =
        seat.statut == SeatStatus.occupe && seat.libereAPartirDe == null;
    final Color foreground =
        isFullyOccupied ? AppColors.white : AppColors.onGold;

    return Tooltip(
      message: seat.libereAPartirDe != null
          ? 'Libéré à partir de ${seat.libereAPartirDe}'
          : (seat.passengerName ?? 'Libre'),
      child: Container(
        width: 44,
        height: 44,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: color.withValues(alpha: isFullyOccupied ? 1 : 0.15),
          border: Border.all(color: color, width: 1.5),
          borderRadius: BorderRadius.circular(AppRadius.sm),
        ),
        child: Text(
          seat.numero,
          style: TextStyle(
            color: isFullyOccupied ? foreground : color,
            fontWeight: FontWeight.w700,
            fontSize: 11,
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
