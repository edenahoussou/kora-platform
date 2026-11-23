import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../data/datasources/colis_tracking_mock.dart';

/// L'état d'un envoi, en pastille.
///
/// Les quatre états ne se valent pas : « prêt à retirer » appelle un
/// déplacement, les autres non. Le doré lui est réservé — en faire une
/// pastille verte de plus le noierait dans le reste.
class ColisStatusChip extends StatelessWidget {
  const ColisStatusChip({super.key, required this.status});

  final ColisTrackingStatus status;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    final (label, variant) = switch (status) {
      ColisTrackingStatus.sealed => (
        l10n.colisStatusSealed,
        KoraTagVariant.outline,
      ),
      ColisTrackingStatus.inTransit => (
        l10n.colisStatusTransit,
        KoraTagVariant.success,
      ),
      ColisTrackingStatus.readyForPickup => (
        l10n.colisStatusReady,
        KoraTagVariant.gold,
      ),
      ColisTrackingStatus.collected => (
        l10n.colisStatusCollected,
        KoraTagVariant.neutral,
      ),
    };

    return KoraTag(label: label, variant: variant);
  }
}
