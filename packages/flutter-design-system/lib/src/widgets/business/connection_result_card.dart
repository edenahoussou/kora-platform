import 'package:flutter/material.dart';

import '../../colors/app_colors.dart';
import '../../dimens/app_dimens.dart';
import '../kora_tag.dart';

/// Carte de résultat avec correspondance — équivalent
/// `.connection-result-card` du prototype (`docs/maquettes/
/// kora-app-passenger/css/style.css`, L2176 ; markup dans `js/app.js`,
/// écran `results`, L538). Distincte de [DepartureCard] (`.trip-card`) :
/// structure propre à un trajet multi-segments (liseré doré, ligne de
/// dégradé au lieu de la ligne pointillée navy→or, titre de trajet
/// multi-villes, pied avec nombre de segments).
///
/// **Fond toujours blanc, quel que soit le thème de l'app** — même
/// raisonnement que [DepartureCard]/[TicketView] : aucune des 6 règles
/// `.dark-mode` du prototype ne touche `.connection-result-card`
/// (`style.css`, L1714-1719).
///
/// Exemple d'utilisation :
/// ```dart
/// ConnectionResultCard(
///   departureTime: '06:45',
///   arrivalTime: '17:40',
///   routeLabel: 'Cotonou → Bohicon → Natitingou',
///   noteLabel: 'Changement de bus à Bohicon · attente 35 min',
///   segmentsLabel: '2 segments inclus',
///   price: '14 500 FCFA',
///   onTap: () {},
/// )
/// ```
class ConnectionResultCard extends StatelessWidget {
  const ConnectionResultCard({
    super.key,
    required this.departureTime,
    required this.arrivalTime,
    required this.routeLabel,
    required this.noteLabel,
    required this.segmentsLabel,
    required this.price,
    this.connectionsLabel = '1 correspondance',
    this.onTap,
  });

  final String departureTime;
  final String arrivalTime;

  /// Trajet complet multi-villes (ex: `'Cotonou → Bohicon → Natitingou'`).
  final String routeLabel;

  /// Détail du changement (ex: `'Changement de bus à Bohicon · attente 35 min'`).
  final String noteLabel;

  /// Équivalent `footer span` (ex: `'2 segments inclus'`).
  final String segmentsLabel;
  final String price;

  /// Équivalent `.tag.gold` en tête de carte (ex: `'1 correspondance'`,
  /// `'2 correspondances'`).
  final String connectionsLabel;

  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(AppRadius.base),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.base),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(13),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(AppRadius.base),
            border: Border.all(color: AppColors.connectionBorderLight),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              KoraTag(label: connectionsLabel, variant: KoraTagVariant.gold),
              const SizedBox(height: 10),
              Row(
                children: [
                  Text(
                    departureTime,
                    style: const TextStyle(color: AppColors.kora, fontSize: 13, fontWeight: FontWeight.w900),
                  ),
                  Expanded(
                    child: Container(
                      height: 2,
                      margin: const EdgeInsets.symmetric(horizontal: 8),
                      decoration: const BoxDecoration(
                        gradient: LinearGradient(colors: [AppColors.gold, AppColors.digital]),
                      ),
                    ),
                  ),
                  Text(
                    arrivalTime,
                    style: const TextStyle(color: AppColors.kora, fontSize: 13, fontWeight: FontWeight.w900),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                routeLabel,
                style: const TextStyle(color: AppColors.kora, fontSize: 11, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 4),
              Text(
                noteLabel,
                style: const TextStyle(color: AppColors.mutedLight, fontSize: 10),
              ),
              const SizedBox(height: 9),
              Container(
                padding: const EdgeInsets.only(top: 8),
                decoration: const BoxDecoration(
                  border: Border(top: BorderSide(color: AppColors.lineLight)),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      segmentsLabel,
                      style: const TextStyle(color: AppColors.mutedLight, fontSize: 10),
                    ),
                    Text(
                      '$price ›',
                      style: const TextStyle(color: AppColors.digital, fontSize: 11, fontWeight: FontWeight.w900),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
