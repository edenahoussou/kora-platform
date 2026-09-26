import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router/app_routes.dart';
import '../../../injection/injection.dart';
import '../../auth/presentation/cubit/auth_cubit.dart';
import '../../ops/domain/entities/depart.dart';
import '../../ops/domain/repositories/departure_repository.dart';
import '../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../domain/entities/ops_notification.dart';

/// Exécute l'action associée à une notification — voir
/// `OpsNotificationAction` (MEZ-97, critère "rejoindre directement le
/// manifeste, la file de synchronisation ou l'écran d'authentification
/// concerné"). Extrait de `NotificationsPage._NotificationTile` (échange
/// du 24/09/2026) pour être partagé avec `NotificationDetailPage` : une
/// notification tapée dans la liste ouvre désormais SES détails plutôt
/// que de déclencher l'action directement (le convoyeur doit pouvoir lire
/// le message complet avant d'être redirigé) — l'action reste accessible
/// depuis un bouton sur cette page de détails, via la même fonction.
Future<void> runNotificationAction(
  BuildContext context,
  OpsNotification notification,
) async {
  switch (notification.action) {
    case OpsNotificationAction.openDeparture:
    case OpsNotificationAction.refreshManifest:
    case OpsNotificationAction.reviewScanConflict:
      await _openDeparture(context, notification);
    case OpsNotificationAction.reauthenticate:
      await context.read<AuthCubit>().sessionExpired();
    case OpsNotificationAction.reviewRejectedOperations:
    case OpsNotificationAction.retrySync:
      await context.push(AppRoutes.synchro);
    case OpsNotificationAction.checkPrinter:
      await context.push(AppRoutes.printerTest);
    case OpsNotificationAction.none:
      break;
  }
}

/// Libellé du bouton d'action sur `NotificationDetailPage` — `null` pour
/// [OpsNotificationAction.none] (aucun bouton à afficher dans ce cas).
String? notificationActionLabel(OpsNotificationAction action) =>
    switch (action) {
      OpsNotificationAction.openDeparture => 'Voir le départ',
      OpsNotificationAction.refreshManifest => 'Voir le manifeste',
      OpsNotificationAction.reviewScanConflict => 'Voir le conflit',
      OpsNotificationAction.reauthenticate => 'Se reconnecter',
      OpsNotificationAction.reviewRejectedOperations => 'Voir la synchro',
      OpsNotificationAction.retrySync => 'Relancer la synchro',
      OpsNotificationAction.checkPrinter => 'Tester le scanner',
      OpsNotificationAction.none => null,
    };

/// Charge le départ visé par la notification et l'ouvre — même logique
/// que `TourneePage._select` (repli sur les infos déjà connues si le
/// serveur est injoignable). `refresh_manifest`/`review_scan_conflict`
/// utilisent le même chemin : sélectionner le départ recharge le
/// manifeste de lui-même (`ManifesteCubit` écoute `SelectedDepartCubit`),
/// pas besoin d'un rafraîchissement séparé. Sans départ associé, ou si
/// le chargement échoue autrement, on renvoie vers Tournée pour une
/// sélection manuelle plutôt que de bloquer le convoyeur.
Future<void> _openDeparture(
  BuildContext context,
  OpsNotification notification,
) async {
  final String? uuid = notification.departure?.uuid;
  if (uuid == null) {
    context.go(AppRoutes.tournee);
    return;
  }

  final result = await getIt<DepartureRepository>().show(uuid);
  if (!context.mounted) return;

  await result.when(
    onSuccess: (Depart detail) async {
      await context.read<SelectedDepartCubit>().select(detail);
      if (context.mounted) await context.push(AppRoutes.manifeste);
    },
    onFailure: (_) async {
      context.go(AppRoutes.tournee);
    },
  );
}
