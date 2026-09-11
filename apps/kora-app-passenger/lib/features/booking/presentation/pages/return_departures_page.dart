import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/departure.dart';
import '../widgets/departure_tile.dart';
import '../widgets/state_view.dart';
import 'departure_detail_page.dart';

/// Ce que le choix de l'aller transmet à celui du retour.
class ReturnDeparturesArgs {
  const ReturnDeparturesArgs({
    required this.outbound,
    required this.departures,
    this.passengers = 1,
    this.returnDate,
  });

  /// L'aller déjà choisi. Rappelé en tête d'écran, et repris ensuite
  /// jusqu'au devis : les deux trajets se paient ensemble.
  final Departure outbound;

  /// Les retours proposés, tels que la recherche les a rendus.
  final List<Departure> departures;

  final int passengers;

  /// Le jour demandé pour le retour, pour l'annoncer en surtitre.
  final DateTime? returnDate;
}

/// Le choix du retour, deuxième temps d'un aller-retour.
///
/// La recherche rend les deux sens d'un coup, mais ils ne se choisissent
/// pas ensemble : on prend son aller, puis son retour. Cet écran n'a donc
/// rien à demander au serveur — il montre la moitié de la réponse que
/// l'écran précédent n'a pas listée.
///
/// Sans écran dédié, le voyageur aurait payé un aller après avoir demandé
/// un aller-retour.
class ReturnDeparturesPage extends StatelessWidget {
  const ReturnDeparturesPage({super.key, this.args});

  final ReturnDeparturesArgs? args;

  @override
  Widget build(BuildContext context) {
    final args = this.args;
    if (args == null) return const _MissingArgs();

    final l10n = context.l10n;
    final departures = args.departures;
    final returnDate = args.returnDate;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                // Sans date demandée, le surtitre n'en annonce pas : le
                // gabarit « Retour · {date} » laissait un séparateur
                // orphelin quand le serveur propose les jours qui suivent.
                eyebrow: returnDate == null
                    ? l10n.returnResultsEyebrowUndated
                    : l10n.returnResultsEyebrow(
                        KoraDateFormats.weekdayDayMonthIn(
                          returnDate,
                          l10n.languageCode,
                        ),
                      ),
                title: departures.isEmpty
                    ? l10n.returnResultsEmptyHeading
                    : l10n.returnResultsTitle(departures.length),
              ),
              Expanded(
                child: departures.isEmpty
                    ? _Empty(outbound: args.outbound)
                    : _List(args: args),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _List extends StatelessWidget {
  const _List({required this.args});

  final ReturnDeparturesArgs args;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.only(bottom: AppSpacing.xl),
      children: [
        _OutboundReminder(departure: args.outbound),
        const SizedBox(height: AppSpacing.md),
        for (final departure in args.departures) ...[
          DepartureTile(
            departure: departure,
            direction: TripDirection.inbound,
            // Le détail reçoit les deux trajets : c'est à partir de là
            // que le devis couvre l'aller-retour entier.
            onTap: () => context.push(
              AppRouter.departureDetailPath,
              extra: DepartureDetailArgs(
                departure: args.outbound,
                returnDeparture: departure,
                passengers: args.passengers,
              ),
            ),
          ),
          const SizedBox(height: 10),
        ],
      ],
    );
  }
}

/// `.outbound-reminder` de la maquette : l'aller reste sous les yeux
/// pendant qu'on choisit le retour.
class _OutboundReminder extends StatelessWidget {
  const _OutboundReminder({required this.departure});

  final Departure departure;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    // Le voile doré de la maquette tombe sur une page claire ; en thème
    // sombre il ne tranche plus, et le texte navy qu’il porte devient
    // pâle. La teinte du texte suit donc le thème, comme partout ailleurs.
    final textColor = context.koraTitleColor;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            l10n.returnResultsOutbound.toUpperCase(),
            style: TextStyle(
              color: textColor,
              fontSize: 8,
              fontWeight: FontWeight.w900,
              letterSpacing: 0.8,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            '${departure.line.origin.name} → '
            '${departure.line.destination.name} · '
            '${KoraDateFormats.weekdayDayMonthIn(departure.departsAt, l10n.languageCode)} · '
            '${KoraDateFormats.time.format(departure.departsAt)}',
            style: TextStyle(
              color: textColor,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

/// Aucun retour ce jour-là.
///
/// Distinct d'un aller simple : l'étape existe, et c'est elle qui n'a rien
/// à proposer. On renvoie au formulaire plutôt que de laisser le voyageur
/// devant une liste muette — la date du retour est ce qu'il faut changer.
class _Empty extends StatelessWidget {
  const _Empty({required this.outbound});

  final Departure outbound;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return ListView(
      padding: const EdgeInsets.only(bottom: AppSpacing.xl),
      children: [
        _OutboundReminder(departure: outbound),
        const SizedBox(height: AppSpacing.lg),
        StateView(
          icon: Icons.event_busy_rounded,
          title: l10n.returnResultsEmptyTitle,
          message: l10n.returnResultsEmptyText,
          primaryLabel: l10n.returnResultsEmptyCta,
          // Deux crans en arrière : la liste de l'aller, puis le
          // formulaire, où se règle la date du retour.
          onPrimary: () => context.go(AppRouter.busSearchPath),
        ),
      ],
    );
  }
}

/// Lien profond ou redémarrage à chaud : l'écran n'a rien à lister.
class _MissingArgs extends StatelessWidget {
  const _MissingArgs();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: StateView(
            icon: Icons.event_busy_rounded,
            title: l10n.returnResultsEmptyTitle,
            message: l10n.returnResultsEmptyText,
            primaryLabel: l10n.returnResultsEmptyCta,
            onPrimary: () => context.go(AppRouter.busSearchPath),
          ),
        ),
      ),
    );
  }
}
