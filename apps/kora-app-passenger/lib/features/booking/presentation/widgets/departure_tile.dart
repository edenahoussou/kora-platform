import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../domain/entities/itinerary.dart';
import 'amenity_view.dart';

/// Le sens d'un trajet dans un aller-retour.
enum TripDirection { outbound, inbound }

/// Une carte de résultat de recherche.
///
/// Elle porte un **voyage entier** : les heures sont celles du départ du
/// premier bus et de l'arrivée du dernier, la durée comprend l'attente au
/// changement, et le prix est celui du voyage complet.
///
/// Deux cartes, selon le voyage. Un trajet direct prend `DepartureCard`
/// (`.trip-card` de la maquette). Une correspondance prend
/// `ConnectionResultCard` (`.connection-result-card`, écran `results`) :
/// liseré doré, trajet multi-villes, changement annoncé et nombre de
/// segments. La maquette les distingue, et pour cause — on ne lit pas de
/// la même façon un voyage où l'on descend en route.
///
/// Toutes les mises en forme (heures, durée, prix) se font ici : les
/// cartes du design system ne reçoivent que du texte déjà prêt, ce qui
/// les garde utilisables par n'importe quelle app.
///
/// Elle ne connaît pas sa destination : la liste de l'aller ouvre le
/// choix du retour, celle du retour ouvre le détail. Le geste appartient
/// à l'écran, la mise en forme à la carte.
class DepartureTile extends StatelessWidget {
  const DepartureTile({
    super.key,
    required this.itinerary,
    required this.onTap,
    this.direction,
  });

  final Itinerary itinerary;
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

  /// Les services à bord, en une ligne. Ceux du **premier** bus : c'est
  /// celui qu'on prend, et le détail du voyage montre les autres. Vide
  /// quand aucun bus n'est encore affecté : la carte ne montre alors
  /// rien plutôt que de promettre un confort inconnu.
  String _servicesLabel(BuildContext context) => itinerary.first.amenities
      .take(_maxServices)
      .map((amenity) => amenity.displayLabel(context))
      .join(' · ');

  @override
  Widget build(BuildContext context) {
    return itinerary.isDirect ? _direct(context) : _connection(context);
  }

  Widget _direct(BuildContext context) {
    return DepartureCard(
      departureTime: KoraDateFormats.time.format(itinerary.departsAt),
      arrivalTime: KoraDateFormats.time.format(itinerary.arrivesAt),
      origin: itinerary.origin.name,
      destination: itinerary.destination.name,
      duration: KoraDateFormats.approximateDuration(itinerary.duration),
      price: itinerary.total.formatted,
      vehicleLabel: itinerary.first.vehicleLabel,
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
              itinerary.departsAt,
              context.l10n.languageCode,
            ),
      servicesLabel: _servicesLabel(context),
      seatsAvailable: itinerary.seatsAvailable,
      // Le total donne au badge sa couleur : 12 places sur 55, c'est
      // orange ; sur 20, c'est vert.
      seatsTotal: itinerary.first.seatsTotal,
      isSoldOut: itinerary.isSoldOut,
      onTap: onTap,
    );
  }

  Widget _connection(BuildContext context) {
    final l10n = context.l10n;

    return ConnectionResultCard(
      departureTime: KoraDateFormats.time.format(itinerary.departsAt),
      arrivalTime: KoraDateFormats.time.format(itinerary.arrivesAt),
      // Le trajet au complet, villes de changement comprises : c'est ce
      // qui distingue ce voyage d'un direct portant les mêmes extrémités.
      routeLabel: [
        itinerary.origin.name,
        for (final city in itinerary.connectionCities) city.name,
        itinerary.destination.name,
      ].join(' → '),
      noteLabel: _noteLabel(l10n),
      segmentsLabel: l10n.departuresSegments(itinerary.legs.length),
      connectionsLabel: l10n.departuresConnectionsTag(
        itinerary.connectionsCount,
      ),
      price: itinerary.total.formatted,
      onTap: onTap,
    );
  }

  /// `Changement de bus à Cotonou · attente 1 h`.
  ///
  /// L'attente ne s'annonce que s'il n'y a qu'un changement : au-delà,
  /// deux durées dans une ligne de dix pixels ne se lisent plus, et le
  /// détail du voyage les donne l'une après l'autre.
  String _noteLabel(AppLocalization l10n) {
    final cities = itinerary.connectionCities
        .map((city) => city.name)
        .join(' · ');

    if (itinerary.connectionsCount > 1) {
      return l10n.departuresConnectionNotePlural(cities);
    }

    return l10n.departuresConnectionNote(
      cities,
      KoraDateFormats.approximateDuration(
        itinerary.connectionWaits.firstOrNull ?? Duration.zero,
      ),
    );
  }
}
