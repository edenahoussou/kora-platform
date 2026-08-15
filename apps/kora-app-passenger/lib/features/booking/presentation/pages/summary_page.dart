import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/passenger.dart';
import '../widgets/booking_progress.dart';
import '../widgets/state_view.dart';

/// Données passées à [SummaryPage] par `go_router` (`extra`).
class SummaryArgs {
  const SummaryArgs({required this.departure, required this.passengers});

  final Departure departure;
  final List<Passenger> passengers;
}

/// Récapitulatif avant paiement — écran `summary` de la maquette : rappel
/// du voyage, liste des voyageurs avec leur siège, détail du prix, puis
/// l'accès au paiement.
///
/// Écran de vérification : il n'affiche que ce qui a été choisi aux étapes
/// précédentes, sans rien recharger. D'où l'absence de Bloc — il n'y a
/// aucun état à gérer, seulement des totaux à calculer.
class SummaryPage extends StatelessWidget {
  const SummaryPage({
    super.key,
    required this.departure,
    this.passengers = const [],
  });

  /// `null` si l'écran est ouvert sans données (lien profond).
  final Departure? departure;

  final List<Passenger> passengers;

  @override
  Widget build(BuildContext context) {
    final departure = this.departure;

    if (departure == null || passengers.isEmpty) {
      return const Scaffold(
        body: SafeArea(
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: 18),
            child: _MissingReservation(),
          ),
        ),
      );
    }

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: _SummaryContent(departure: departure, passengers: passengers),
        ),
      ),
    );
  }
}

class _SummaryContent extends StatelessWidget {
  const _SummaryContent({required this.departure, required this.passengers});

  final Departure departure;
  final List<Passenger> passengers;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const ScreenHeader(
          eyebrow: 'Récapitulatif',
          title: 'Vérifiez votre voyage',
        ),
        Expanded(
          child: ListView(
            padding: EdgeInsets.zero,
            children: [
              const BookingProgress(step: 5, label: 'Récapitulatif'),
              const SizedBox(height: 11),
              _TripCard(departure: departure, passengers: passengers),
              SectionTitle(
                title: 'Passagers',
                actionLabel: 'Modifier',
                // Retour à l'écran précédent, celui de la saisie : la
                // correction se fait là où les champs existent déjà.
                onActionTap: () => Navigator.of(context).pop(),
              ),
              _PassengersCard(passengers: passengers),
              const SectionTitle(title: 'Détail du prix'),
              _PriceCard(departure: departure, passengers: passengers),
              const SizedBox(height: AppSpacing.lg),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.lg),
          child: KoraButton(
            label: 'Continuer vers le paiement',
            onPressed: () => ScaffoldMessenger.of(context)
              ..hideCurrentSnackBar()
              ..showSnackBar(
                const SnackBar(
                  content: Text('Le paiement sera branché au Sprint 2.'),
                ),
              ),
          ),
        ),
      ],
    );
  }
}

/// `.card.navy` : le voyage tel qu'il a été choisi.
class _TripCard extends StatelessWidget {
  const _TripCard({required this.departure, required this.passengers});

  final Departure departure;
  final List<Passenger> passengers;

  @override
  Widget build(BuildContext context) {
    final line = departure.line;
    final seats = passengers
        .map((passenger) => passenger.seat.label)
        .join(', ');

    return KoraCard(
      variant: KoraCardVariant.navy,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              // La maquette affiche ici un code de ligne (`Ligne L2`) que
              // nos données n'ont pas ; la gamme du bus le remplace, comme
              // sur l'écran de détail.
              KoraTag(
                label: departure.comfort.label,
                variant: KoraTagVariant.gold,
              ),
              Flexible(
                child: Text(
                  passengers.length > 1 ? 'Sièges $seats' : 'Siège $seats',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.right,
                  style: TextStyle(
                    color: AppColors.white.withValues(alpha: 0.8),
                    fontSize: 11,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            '${line.origin.name} → ${line.destination.name}',
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 19,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            '${KoraDateFormats.capitalize(KoraDateFormats.weekdayDayMonth.format(departure.departsAt))} · '
            '${KoraDateFormats.time.format(departure.departsAt)}',
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.7),
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            _boardingPoint(line.origin.station, line.origin.name),
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.7),
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  /// `Gare KORA St Michel` quand la ville a une gare nommée, sinon son nom
  /// seul — toutes n'en ont qu'une, sans nom propre.
  String _boardingPoint(String? station, String cityName) {
    return station == null ? cityName : 'Gare KORA $station';
  }
}

/// `.passenger-summary-row` : une ligne par voyageur, avec son siège.
class _PassengersCard extends StatelessWidget {
  const _PassengersCard({required this.passengers});

  final List<Passenger> passengers;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return KoraCard(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
      child: Column(
        children: [
          for (final passenger in passengers)
            Container(
              constraints: const BoxConstraints(minHeight: 48),
              decoration: BoxDecoration(
                border: passenger == passengers.last
                    ? null
                    : Border(bottom: BorderSide(color: koraColors.line)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          passenger.fullName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: context.koraTitleColor,
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          passenger.phone,
                          style: TextStyle(
                            color: koraColors.textMuted,
                            fontSize: 10,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  KoraTag(
                    label: passenger.seat.label,
                    variant: KoraTagVariant.gold,
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// Le détail du prix, ligne à ligne, jusqu'au total à payer.
class _PriceCard extends StatelessWidget {
  const _PriceCard({required this.departure, required this.passengers});

  final Departure departure;
  final List<Passenger> passengers;

  @override
  Widget build(BuildContext context) {
    final count = passengers.length;
    final total = departure.price * count;

    return KoraCard(
      child: Column(
        children: [
          _PriceRow(
            label: count > 1
                ? '$count billets ${departure.comfort.label}'
                : '1 billet ${departure.comfort.label}',
            value: total.formatted,
          ),
          const Divider(height: 26),
          const _PriceRow(label: 'Frais de service', value: 'Inclus'),
          const Divider(height: 26),
          _PriceRow(
            label: 'Total à payer',
            value: total.formatted,
            isTotal: true,
          ),
        ],
      ),
    );
  }
}

class _PriceRow extends StatelessWidget {
  const _PriceRow({
    required this.label,
    required this.value,
    this.isTotal = false,
  });

  final String label;
  final String value;
  final bool isTotal;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Flexible(
          child: Text(
            label,
            style: isTotal
                ? TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  )
                : AppTextStyles.body(koraColors.textMuted),
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Text(
          value,
          // `.amount` : le total est l'information que l'on vient vérifier.
          style: isTotal
              ? TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 23,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.69,
                )
              : TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
        ),
      ],
    );
  }
}

class _MissingReservation extends StatelessWidget {
  const _MissingReservation();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        const ScreenHeader(eyebrow: 'KORA BUS', title: 'Réservation perdue'),
        StateView(
          icon: Icons.help_outline_rounded,
          title: 'Rien à récapituler',
          message: 'Reprenez la réservation depuis la recherche de départs.',
          primaryLabel: 'Retour',
          onPrimary: () => Navigator.of(context).pop(),
        ),
      ],
    );
  }
}
