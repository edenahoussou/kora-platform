import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/title_color.dart';

/// `.booking-progress` : où l'on en est dans la réservation.
///
/// Les 5 étapes de la maquette : recherche, départ, siège, voyageurs,
/// paiement.
class BookingProgress extends StatelessWidget {
  const BookingProgress({
    super.key,
    required this.step,
    required this.label,
    this.totalSteps = 5,
  });

  /// Étape en cours, à partir de 1.
  final int step;

  /// Nom de l'étape (`Votre siège`).
  final String label;

  final int totalSteps;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Text(
              'RÉSERVATION',
              style: TextStyle(
                color: koraColors.textMuted,
                fontSize: 9,
                letterSpacing: 0.72,
              ),
            ),
            const SizedBox(width: 7),
            Expanded(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            Text(
              '$step/$totalSteps',
              style: const TextStyle(
                color: AppColors.digital,
                fontSize: 10,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            for (var i = 1; i <= totalSteps; i++) ...[
              Expanded(
                child: Container(
                  height: 3,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(9),
                    // `linear-gradient(90deg, var(--gold), #ffd064)` pour
                    // les étapes franchies.
                    gradient: i <= step
                        ? const LinearGradient(
                            colors: [AppColors.gold, Color(0xFFFFD064)],
                          )
                        : null,
                    color: i <= step
                        ? null
                        : koraColors.textPrimary.withValues(alpha: 0.1),
                  ),
                ),
              ),
              if (i < totalSteps) const SizedBox(width: 5),
            ],
          ],
        ),
      ],
    );
  }
}
