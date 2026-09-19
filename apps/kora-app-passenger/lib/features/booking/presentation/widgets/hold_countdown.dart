import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../bloc/seat_hold_bloc.dart';

/// `.seat-reservation-clock` : le temps qu'il reste avant que les sièges
/// repartent à la vente.
///
/// **Ne s'affiche que si des sièges sont réellement retenus.** Sur le plan,
/// le formulaire et le récapitulatif, rien n'est bloqué : le voyageur
/// choisit librement, et annoncer un délai avant qu'il n'en existe un
/// presserait pour rien. La carte apparaît une fois le récapitulatif
/// validé, puis suit le voyageur jusqu'au paiement — et sur les écrans
/// qu'il retraverse en revenant en arrière.
///
/// Rend un espace nul le reste du temps, d'où le `SizedBox.shrink` : les
/// écrans du tunnel peuvent la poser sans se demander où l'on en est.
class HoldCountdown extends StatelessWidget {
  const HoldCountdown({super.key, this.margin});

  final EdgeInsetsGeometry? margin;

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<SeatHoldBloc, SeatHoldState>(
      builder: (context, state) {
        if (!state.isHeld && state.status != SeatHoldStatus.holding) {
          return const SizedBox.shrink();
        }

        final l10n = context.l10n;
        final koraColors = context.koraColors;

        return Container(
          margin: margin,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            color: AppColors.gold.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(AppRadius.input),
          ),
          child: Row(
            children: [
              Container(
                width: 34,
                height: 34,
                alignment: Alignment.center,
                decoration: const BoxDecoration(
                  color: AppColors.gold,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.schedule_rounded,
                  size: 18,
                  color: AppColors.kora,
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.seatsHoldEyebrow,
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 9,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      state.isHeld
                          ? l10n.seatsHoldCountdown(state.formatted)
                          : l10n.seatsHoldHolding,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
