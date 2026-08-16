import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/ticket_card.dart';
import '../../../ops/data/mock_ops_data_source.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../cubit/scan_boarding_cubit.dart';

/// Détail du scan — feedback plein écran valide/refusé, fidèle aux codes
/// couleur success/danger du design system. Route : `/scan/resultat`.
class ScanResultPage extends StatelessWidget {
  const ScanResultPage({super.key});

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
                    final bool isValide =
                        state.status == ScanBoardingStatus.valide;
                    final Color color =
                        isValide ? AppColors.success : AppColors.danger;
                    final passenger = state.matchedPassenger;

                    return Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: <Widget>[
                        Container(
                          width: 110,
                          height: 110,
                          decoration: BoxDecoration(
                              color: color.withValues(alpha: 0.12),
                              shape: BoxShape.circle),
                          child: Icon(
                            isValide ? Icons.check_circle : Icons.cancel,
                            color: color,
                            size: 64,
                          ),
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        Text(
                          isValide ? 'Billet valide' : 'Billet refusé',
                          style: Theme.of(context)
                              .textTheme
                              .displayMedium
                              ?.copyWith(color: color),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        if (state.lastScannedCode != null)
                          Text(
                            'Code : ${state.lastScannedCode}',
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
                        if (passenger != null) ...<Widget>[
                          const SizedBox(height: AppSpacing.sm),
                          TextButton.icon(
                            onPressed: () => _showTicket(context, passenger),
                            icon: const Icon(Icons.confirmation_number_outlined,
                                size: 16),
                            label: const Text('Voir le billet'),
                          ),
                        ],
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

  Future<void> _showTicket(BuildContext context, Passenger passenger) {
    final depart = context.read<SelectedDepartCubit>().state;
    if (depart == null) return Future<void>.value();
    final ticket = MockOpsDataSource.ticketFor(passenger, depart.id);

    return showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (BuildContext context) => Padding(
        padding: EdgeInsets.fromLTRB(
          AppSpacing.md,
          AppSpacing.lg,
          AppSpacing.md,
          MediaQuery.viewInsetsOf(context).bottom + AppSpacing.lg,
        ),
        child: TicketCard(
          ticket: ticket,
          passengerName: passenger.nomComplet,
          route: depart.route,
          date: depart.date,
          heure: depart.heure,
        ),
      ),
    );
  }
}
