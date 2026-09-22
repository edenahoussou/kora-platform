import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../domain/entities/assisted_booking.dart';
import '../../domain/entities/price.dart';

/// La carte d'une demande confiée à une agence, taillée comme celle d'un
/// billet (`.card.navy`) : statut et référence en haut, trajet, date et
/// siège, puis un pied séparé par un filet.
///
/// La même carte sert la liste des voyages et l'écran de suivi : le
/// voyageur retrouve au même endroit la référence `KBR-…` qu'il donnera à
/// l'agence. L'écran de suivi, lui, complète le pied — agence, prix — là
/// où la liste reste courte.
class AssistanceCard extends StatelessWidget {
  const AssistanceCard({
    super.key,
    required this.reference,
    required this.status,
    required this.origin,
    required this.destination,
    this.departsAt,
    this.seats = const [],
    this.agencyName,
    this.amount,
    this.footnote,
    this.actionLabel,
    this.onTap,
  });

  final String reference;

  /// `null` tant que le serveur n'a pas répondu : la carte affiche alors
  /// la référence sans pastille.
  final AssistanceStatus? status;

  final String origin;
  final String destination;
  final DateTime? departsAt;
  final List<String> seats;

  /// Complété sur l'écran de suivi : c'est l'agence qui finalise.
  final String? agencyName;

  /// Indicatif : l'agence recalcule le prix en prenant le dossier.
  final Price? amount;

  /// Le pied à gauche — la validité de la demande, par exemple.
  final String? footnote;

  /// Le lien à droite du pied (« Voir la demande »).
  final String? actionLabel;

  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final departsAt = this.departsAt;
    final agencyName = this.agencyName;
    final amount = this.amount;
    final footnote = this.footnote;
    final actionLabel = this.actionLabel;
    final status = this.status;

    final faded = AppColors.white.withValues(alpha: 0.65);

    return KoraCard(
      variant: KoraCardVariant.navy,
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              // Sur une carte navy, la pastille est dorée quel que soit le
              // statut : c'est le texte qui distingue « En attente » de
              // « Refusée ».
              if (status != null)
                KoraTag(
                  label: assistanceChipLabel(l10n, status),
                  variant: KoraTagVariant.gold,
                ),
              Flexible(
                child: Text(
                  reference,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.right,
                  style: TextStyle(
                    color: AppColors.white.withValues(alpha: 0.85),
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.6,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            '$origin → $destination',
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 19,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.475,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            [
              if (departsAt != null)
                '${KoraDateFormats.dayMonthIn(departsAt, l10n.languageCode)}'
                    ' · '
                    '${KoraDateFormats.timeIn(departsAt, l10n.languageCode)}',
              if (seats.isNotEmpty) l10n.ticketsSeat(seats.join(', ')),
            ].join(' · '),
            style: TextStyle(color: faded, fontSize: 12),
          ),
          if (agencyName != null || amount != null) ...[
            const SizedBox(height: 5),
            Text(
              [?agencyName, ?amount?.formatted].join(' · '),
              style: TextStyle(color: faded, fontSize: 12),
            ),
          ],
          if (footnote != null || actionLabel != null) ...[
            // `.divider` : le filet qui sépare la demande de son pied.
            Container(
              height: 1,
              margin: const EdgeInsets.symmetric(vertical: AppSpacing.lg),
              color: AppColors.white.withValues(alpha: 0.12),
            ),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Flexible(
                  child: Text(
                    footnote ?? '',
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: faded, fontSize: 11),
                  ),
                ),
                if (actionLabel != null)
                  Text(
                    actionLabel,
                    style: const TextStyle(
                      color: AppColors.gold,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// Le statut en un mot ou deux, pour une pastille.
String assistanceChipLabel(AppLocalization l10n, AssistanceStatus status) =>
    switch (status) {
      AssistanceStatus.pending => l10n.assistChipPending,
      AssistanceStatus.inProgress => l10n.assistChipInProgress,
      AssistanceStatus.finalized => l10n.assistChipFinalized,
      AssistanceStatus.refused => l10n.assistChipRefused,
      AssistanceStatus.expired => l10n.assistChipExpired,
      AssistanceStatus.unavailable => l10n.assistChipUnavailable,
      AssistanceStatus.cancelled => l10n.assistChipCancelled,
      AssistanceStatus.resumedOnline => l10n.assistChipResumed,
      AssistanceStatus.unknown => l10n.assistChipUnknown,
    };
