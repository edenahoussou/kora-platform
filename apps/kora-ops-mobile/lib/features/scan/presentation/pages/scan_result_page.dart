import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../cubit/scan_boarding_cubit.dart';

/// Détail du scan — feedback plein écran pour chacun des 7 états requis
/// par le ticket MEZ-91 (billet valide, mauvais départ, billet inconnu,
/// déjà embarqué, annulé, conflit, en attente de synchronisation). Route :
/// `/scan/resultat`.
class ScanResultPage extends StatelessWidget {
  const ScanResultPage({super.key});

  ({Color color, IconData icon, String title}) _presentationOf(
      ScanBoardingStatus status) {
    return switch (status) {
      ScanBoardingStatus.valide => (
          color: AppColors.success,
          icon: Icons.check_circle,
          title: 'Billet valide',
        ),
      ScanBoardingStatus.mauvaisDepart => (
          color: AppColors.danger,
          icon: Icons.wrong_location,
          title: 'Mauvais départ',
        ),
      ScanBoardingStatus.billetInconnu => (
          color: AppColors.danger,
          icon: Icons.help_outline,
          title: 'Billet inconnu',
        ),
      ScanBoardingStatus.dejaEmbarque => (
          color: AppColors.danger,
          icon: Icons.event_busy,
          title: 'Déjà embarqué',
        ),
      ScanBoardingStatus.annule => (
          color: AppColors.danger,
          icon: Icons.cancel,
          title: 'Billet annulé',
        ),
      ScanBoardingStatus.conflit => (
          color: AppColors.danger,
          icon: Icons.report_gmailerrorred,
          title: 'Conflit terminal',
        ),
      ScanBoardingStatus.enAttenteSync => (
          color: AppColors.gold,
          icon: Icons.cloud_off,
          title: 'En attente de synchronisation',
        ),
      _ => (
          color: AppColors.danger,
          icon: Icons.error_outline,
          title: 'Erreur',
        ),
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const ScreenHeader(
          eyebrow: 'Scan boarding', title: 'Résultat du scan'),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Expanded(
                child: BlocBuilder<ScanBoardingCubit, ScanBoardingState>(
                  builder: (BuildContext context, ScanBoardingState state) {
                    final presentation = _presentationOf(state.status);
                    final passenger = state.matchedPassenger;

                    return Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: <Widget>[
                        Container(
                          width: 110,
                          height: 110,
                          decoration: BoxDecoration(
                              color: presentation.color.withValues(alpha: 0.12),
                              shape: BoxShape.circle),
                          child: Icon(
                            presentation.icon,
                            color: presentation.color,
                            size: 64,
                          ),
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        Text(
                          presentation.title,
                          style: Theme.of(context)
                              .textTheme
                              .displayMedium
                              ?.copyWith(color: presentation.color),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        if (state.lastCode != null)
                          Text(
                            'Code : ${state.lastCode}',
                            style: TextStyle(
                                color: context.koraColors.textMuted,
                                fontSize: 12),
                          ),
                        const SizedBox(height: AppSpacing.lg),
                        if (passenger != null)
                          KoraCard(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(passenger.nomComplet,
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium),
                                const SizedBox(height: 4),
                                Text(
                                  'Siège ${passenger.siege} · ${passenger.segmentOrigine} → ${passenger.segmentDestination}',
                                  style: TextStyle(
                                      color: context.koraColors.textMuted,
                                      fontSize: 12),
                                ),
                              ],
                            ),
                          )
                        else if (state.message != null)
                          Padding(
                            padding: const EdgeInsets.symmetric(
                                horizontal: AppSpacing.lg),
                            child: Text(
                              state.message!,
                              textAlign: TextAlign.center,
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ),
                        // "Voir le billet" (aperçu imprimable) s'appuyait
                        // sur MockOpsDataSource.ticketFor — retiré tant que
                        // MEZ-91 ne dispose pas d'un vrai objet Ticket côté
                        // scan (BoardingEventResource ne renvoie qu'un
                        // ticketUuid, volontairement minimal). À reprendre
                        // avec le module Impression (MEZ-10) si besoin.
                      ],
                    );
                  },
                ),
              ),
              KoraButton(
                label: 'Scanner le billet suivant',
                onPressed: () {
                  context.read<ScanBoardingCubit>().reset();
                  context.pop();
                },
              ),
              const SizedBox(height: AppSpacing.md),
            ],
          ),
        ),
      ),
    );
  }
}
