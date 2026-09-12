import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../domain/entities/departure.dart';
import 'amenity_view.dart';

/// Le sens d'un trajet dans un aller-retour.
enum TripDirection { outbound, inbound }

/// Une carte de résultat de recherche.
///
/// Toutes les mises en forme (heures, durée, prix) se font ici :
/// `DepartureCard` du design system ne reçoit que du texte déjà prêt, ce
/// qui la garde utilisable par n'importe quelle app.
///
/// Elle ne connaît pas sa destination : la liste de l'aller ouvre le
/// choix du retour, celle du retour ouvre le détail. Le geste appartient
/// à l'écran, la mise en forme à la carte.
class DepartureTile extends StatelessWidget {
  const DepartureTile({
    super.key,
    required this.departure,
    required this.onTap,
    this.direction,
  });

  final Departure departure;
  final VoidCallback onTap;

  /// Le sens du trajet, sur un aller-retour seulement.
  ///
  /// `null` sur un aller simple : la carte n'affiche alors ni badge de
  /// sens ni date, puisque l'écran porte déjà le jour en surtitre et
  /// qu'il n'y a qu'un trajet à lire.
  final TripDirection? direction;

  /// Le pied de carte n'a qu'une ligne, et le serveur peut annoncer
  /// vingt-huit services : on n'en montre que les premiers. L'ordre est
  /// celui du serveur — c'est lui qui range son catalogue, pas l'app.
  static const int _maxServices = 3;

  /// Les services à bord, en une ligne. Vide quand aucun bus n'est encore
  /// affecté au départ : la carte ne montre alors rien plutôt que de
  /// promettre un confort inconnu.
  String _servicesLabel(BuildContext context) => departure.amenities
      .take(_maxServices)
      .map((amenity) => amenity.displayLabel(context))
      .join(' · ');

  @override
  Widget build(BuildContext context) {
    return DepartureCard(
      departureTime: KoraDateFormats.time.format(departure.departsAt),
      arrivalTime: KoraDateFormats.time.format(departure.arrivesAt),
      origin: departure.line.origin.name,
      destination: departure.line.destination.name,
      duration: KoraDateFormats.approximateDuration(departure.line.duration),
      price: departure.price.formatted,
      vehicleLabel: departure.vehicleLabel,
      directionLabel: switch (direction) {
        TripDirection.outbound => context.l10n.departuresDirectionOutbound,
        TripDirection.inbound => context.l10n.departuresDirectionInbound,
        null => null,
      },
      // Le jour n'accompagne le badge que sur un aller-retour : les deux
      // sens se lisent l'un sous l'autre, et l'heure seule ne suffit plus
      // à les distinguer.
      dateLabel: direction == null
          ? null
          : KoraDateFormats.weekdayDayMonthIn(
              departure.departsAt,
              context.l10n.languageCode,
            ),
      servicesLabel: _servicesLabel(context),
      seatsAvailable: departure.seatsAvailable,
      // Le total donne au badge sa couleur : 12 places sur 55, c'est
      // orange ; sur 20, c'est vert.
      seatsTotal: departure.seatsTotal,
      isSoldOut: departure.isSoldOut,
      onTap: onTap,
    );
  }
}
