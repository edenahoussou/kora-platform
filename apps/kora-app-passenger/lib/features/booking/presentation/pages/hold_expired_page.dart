import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../bloc/seat_hold_bloc.dart';
import '../widgets/state_view.dart';
import 'seats_page.dart';

/// Écran `expired` de la maquette : le délai est écoulé et les sièges sont
/// repartis à la vente.
///
/// Un écran plein plutôt qu'un message temporaire : c'est un cul-de-sac,
/// pas un avertissement. La sélection n'existe plus, le prix n'est plus
/// garanti, et continuer le formulaire en cours n'aurait aucun sens — il
/// faut repartir du plan.
class HoldExpiredPage extends StatelessWidget {
  const HoldExpiredPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final holdState = context.read<SeatHoldBloc>().state;
    final departure = holdState.departure;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
          child: ListView(
            children: [
              const SizedBox(height: AppSpacing.lg),
              StateView(
                icon: Icons.hourglass_empty_rounded,
                title: l10n.holdExpiredTitle,
                // La raison du serveur quand il y en a une — un siège pris
                // pendant qu'on rebloquait pour un code promo, par exemple.
                // Une simple expiration n'en a pas, et garde le texte
                // habituel.
                message: holdState.failure?.message ?? l10n.holdExpiredText,
                primaryLabel: l10n.holdExpiredPrimary,
                // On repart d'un plan neuf plutôt que de revenir en
                // arrière : l'écran quitté — le formulaire passagers, le
                // récapitulatif — porte des sièges qui n'existent plus.
                // `pushReplacement` pour que cet écran-ci ne reste pas
                // derrière, il n'y a rien à y revoir.
                onPrimary: departure == null
                    ? null
                    : () => context.pushReplacement(
                        AppRouter.seatsPath,
                        extra: SeatsArgs(
                          departure: departure,
                          passengers: holdState.passengers,
                        ),
                      ),
                secondaryLabel: l10n.holdExpiredSecondary,
                onSecondary: () => context.go('/home'),
              ),
              const SizedBox(height: AppSpacing.xl),
            ],
          ),
        ),
      ),
    );
  }
}
