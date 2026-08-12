import 'package:flutter/material.dart';

import '../../colors/app_colors.dart';
import '../../dimens/app_dimens.dart';
import '../../icons/kora_vehicle_icon.dart';
import '../kora_tag.dart';

/// Carte de départ (résultats de recherche Bus) — équivalent `.trip-card`
/// du prototype (`docs/maquettes/kora-app-passenger/css/style.css`,
/// L547-558 ; markup `tripCard(...)` dans `js/app.js`, L273-282) : badge
/// véhicule + places à gauche/droite en haut, heures de départ/arrivée
/// avec ligne de trajet au centre, services + prix garanti en bas.
///
/// **Fond toujours blanc, quel que soit le thème de l'app** — comme
/// [TicketView]. Ce n'est pas un oubli : le prototype ne définit AUCUNE
/// règle `.dark-mode` pour `.trip-card` (les 6 seules règles `.dark-mode`
/// du CSS ne touchent que le fond de l'écran et les titres d'en-tête,
/// L1714-1719) — une carte de résultat de recherche reste une carte
/// blanche sur fond navy en mode sombre, elle ne devient pas elle-même
/// sombre. D'où l'usage de couleurs fixes (`AppColors.*`) plutôt que du
/// thème contextuel (`koraColors.*`) dans tout ce fichier.
///
/// Exemple d'utilisation :
/// ```dart
/// DepartureCard(
///   departureTime: '07:30',
///   arrivalTime: '17:15',
///   origin: 'Cotonou',
///   destination: 'Malanville',
///   duration: '≈ 9 h 45',
///   price: '12 500 FCFA',
///   seatsAvailable: 12,
///   onTap: () {},
/// )
/// ```
class DepartureCard extends StatelessWidget {
  const DepartureCard({
    super.key,
    required this.departureTime,
    required this.arrivalTime,
    required this.origin,
    required this.destination,
    required this.duration,
    required this.price,
    this.vehicleLabel = 'KORA Confort',
    this.servicesLabel = 'Wi-Fi · Climatisation · USB',
    this.seatsAvailable,
    this.isSoldOut = false,
    this.isSelected = false,
    this.onTap,
  });

  final String departureTime;
  final String arrivalTime;
  final String origin;
  final String destination;

  /// Durée affichée au-dessus de la ligne de trajet (ex: `'≈ 9 h 45'`).
  /// Texte libre (pas de calcul de durée ici), comme [departureTime].
  final String duration;
  final String price;

  /// Type de véhicule affiché dans le badge (ex: `'KORA Confort'`).
  final String vehicleLabel;

  /// Équivalent `.trip-foot .tiny.muted` (`'Wi-Fi · Climatisation · USB'`
  /// par défaut dans le prototype).
  final String servicesLabel;

  /// `null` si non applicable (ex: Ride à la demande, pas de quota de places).
  final int? seatsAvailable;

  /// Segment complet — équivalent `.trip-card.sold-out` (opacité réduite,
  /// interaction désactivée, badge "Complet"). CSS de référence :
  /// `js/app.js`, écran `results`, L539-543.
  final bool isSoldOut;
  final bool isSelected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final card = Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: isSelected
            ? Border.all(color: AppColors.gold, width: 1.5)
            : null,
        boxShadow: isSelected
            ? [
                BoxShadow(
                  color: AppColors.gold.withValues(alpha: 0.12),
                  blurRadius: 0,
                  spreadRadius: 2,
                ),
              ]
            : AppShadows.card,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          _Head(
            vehicleLabel: vehicleLabel,
            seatsAvailable: seatsAvailable,
            isSoldOut: isSoldOut,
          ),
          const SizedBox(height: AppSpacing.lg),
          _Route(
            departureTime: departureTime,
            arrivalTime: arrivalTime,
            origin: origin,
            destination: destination,
            duration: duration,
          ),
          const SizedBox(height: AppSpacing.lg),
          _Foot(
            servicesLabel: servicesLabel,
            price: price,
            isSoldOut: isSoldOut,
          ),
        ],
      ),
    );

    // `.sold-out { opacity: .68; cursor: not-allowed }` — carte grisée,
    // non cliquable, quel que soit le onTap fourni par l'appelant.
    if (isSoldOut) {
      return Opacity(opacity: 0.68, child: card);
    }

    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        child: card,
      ),
    );
  }
}

/// `.trip-head` : badge véhicule (icône bus + libellé) à gauche, places
/// restantes ou badge "Complet" à droite.
class _Head extends StatelessWidget {
  const _Head({
    required this.vehicleLabel,
    required this.seatsAvailable,
    required this.isSoldOut,
  });

  final String vehicleLabel;
  final int? seatsAvailable;
  final bool isSoldOut;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        // Sold-out : le badge perd son icône et devient "outline" — reprise
        // littérale du markup sold-out du prototype (`js/app.js`, L540),
        // qui n'inclut pas `trip-mode-icon` dans ce cas précis.
        KoraTag(
          label: vehicleLabel,
          variant: isSoldOut ? KoraTagVariant.outline : KoraTagVariant.gold,
          leading: isSoldOut
              ? null
              : KoraVehicleIcon(
                  type: KoraVehicleType.bus,
                  size: 19,
                  // Le SVG a des traits blancs en dur (fond navy/digital
                  // habituel) ; ici le badge est doré donc le texte est
                  // navy (`--kora`) — on reteinte l'icône pour matcher
                  // `.trip-mode-icon svg { stroke: currentColor }`.
                  colorFilter: const ColorFilter.mode(AppColors.kora, BlendMode.srcIn),
                ),
        ),
        if (isSoldOut)
          const KoraTag(label: 'Complet', variant: KoraTagVariant.danger)
        else if (seatsAvailable != null)
          Text(
            '$seatsAvailable places',
            style: const TextStyle(color: AppColors.mutedLight, fontSize: 10),
          ),
      ],
    );
  }
}

/// `.trip-route` : heure + ville de départ, ligne de trajet + durée,
/// heure + ville d'arrivée.
class _Route extends StatelessWidget {
  const _Route({
    required this.departureTime,
    required this.arrivalTime,
    required this.origin,
    required this.destination,
    required this.duration,
  });

  final String departureTime;
  final String arrivalTime;
  final String origin;
  final String destination;
  final String duration;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _TimePoint(time: departureTime, city: origin, alignEnd: false),
        const SizedBox(width: AppSpacing.md),
        Expanded(child: _RouteLine(duration: duration)),
        const SizedBox(width: AppSpacing.md),
        _TimePoint(time: arrivalTime, city: destination, alignEnd: true),
      ],
    );
  }
}

class _TimePoint extends StatelessWidget {
  const _TimePoint({required this.time, required this.city, required this.alignEnd});

  final String time;
  final String city;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    final crossAxis = alignEnd ? CrossAxisAlignment.end : CrossAxisAlignment.start;
    final textAlign = alignEnd ? TextAlign.right : TextAlign.left;

    return Column(
      crossAxisAlignment: crossAxis,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          time,
          textAlign: textAlign,
          style: const TextStyle(color: AppColors.kora, fontSize: 18, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 2),
        Text(
          city,
          textAlign: textAlign,
          style: const TextStyle(color: AppColors.mutedLight, fontSize: 9),
        ),
      ],
    );
  }
}

/// Ligne pointant de l'origine (point navy) vers la destination (point
/// doré), avec la durée centrée au-dessus — `.route-line`.
class _RouteLine extends StatelessWidget {
  const _RouteLine({required this.duration});

  final String duration;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          duration,
          textAlign: TextAlign.center,
          style: const TextStyle(color: AppColors.mutedLight, fontSize: 9),
        ),
        const SizedBox(height: 6),
        Row(
          children: [
            const _RouteDot(color: AppColors.kora),
            Expanded(
              child: Container(height: 2, color: AppColors.routeLineLight),
            ),
            const _RouteDot(color: AppColors.gold),
          ],
        ),
      ],
    );
  }
}

/// Point plein de 10px (`.route-line::before/::after`, `width/height:
/// 10px`) — pas un anneau : les deux extrémités sont des disques pleins.
class _RouteDot extends StatelessWidget {
  const _RouteDot({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 10,
      height: 10,
      decoration: BoxDecoration(shape: BoxShape.circle, color: color),
    );
  }
}

/// `.trip-foot` : services inclus + prix garanti, ou message
/// d'indisponibilité pour un segment complet.
class _Foot extends StatelessWidget {
  const _Foot({
    required this.servicesLabel,
    required this.price,
    required this.isSoldOut,
  });

  final String servicesLabel;
  final String price;
  final bool isSoldOut;

  @override
  Widget build(BuildContext context) {
    if (isSoldOut) {
      return Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const Expanded(
            child: Text(
              'Aucune place sur ce segment',
              style: TextStyle(color: AppColors.mutedLight, fontSize: 10),
            ),
          ),
          const Text(
            'Indisponible',
            style: TextStyle(color: AppColors.kora, fontSize: 11, fontWeight: FontWeight.w800),
          ),
        ],
      );
    }

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Text(
            servicesLabel,
            style: const TextStyle(color: AppColors.mutedLight, fontSize: 10),
          ),
        ),
        Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            const Text(
              'Prix final garanti',
              style: TextStyle(color: AppColors.mutedLight, fontSize: 9),
            ),
            Text(
              price,
              style: const TextStyle(color: AppColors.kora, fontSize: 15, fontWeight: FontWeight.w900),
            ),
          ],
        ),
      ],
    );
  }
}
