import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router/app_routes.dart';
import '../../../core/errors/failures.dart';
import '../../../core/result/result.dart';
import '../../../core/services/kora_dialogs.dart';
import '../../../injection/injection.dart';
import '../../auth/presentation/cubit/auth_cubit.dart';
import '../../ops/domain/entities/depart.dart';
import '../../synchro/presentation/cubit/sync_cubit.dart';
import '../domain/entities/trip_log.dart';
import '../domain/repositories/trip_log_repository.dart';
import 'trip_log_formatters.dart';
import 'widgets/odometer_sheet.dart';

/// Points d'entrée du carnet de bord partagés par l'Accueil (tuile
/// « Carnet »), le Manifeste (speed dial) et la progression du départ
/// (« Bus parti » / « Arrivé » → kilométrage proposé aussitôt).

bool canRecordTripLog(BuildContext context) {
  final AuthState auth = context.read<AuthCubit>().state;
  return auth is AuthAuthenticated && auth.user.canRecordTripLog;
}

/// Ouvre le carnet de bord du départ géré — ou, sans départ sélectionné,
/// explique pourquoi et renvoie vers Tournée.
Future<void> openTripLog(BuildContext context, Depart? depart) async {
  if (depart == null) {
    await KoraDialogs.info(
      context,
      title: 'Aucun départ sélectionné',
      description: 'Choisissez d\'abord votre course dans Tournée pour '
          'saisir le kilométrage, le carburant ou les péages.',
    );
    if (context.mounted) context.go(AppRoutes.tournee);
    return;
  }
  await context.push(AppRoutes.tripLog);
}

/// Demande puis enregistre le kilométrage [kind] du départ. [fromProgress]
/// : appelé juste après « Bus parti » / « Arrivé » — le bouton d'abandon
/// devient « Plus tard » (la saisie reste possible depuis le carnet).
///
/// Retourne le carnet mis à jour, ou `null` si abandonné / refusé.
Future<TripLog?> promptOdometerReading(
  BuildContext context, {
  required String departureUuid,
  required OdometerKind kind,
  bool fromProgress = false,
}) async {
  final TripLogRepository repository = getIt<TripLogRepository>();
  final TripLog current = await repository.cached(departureUuid);
  if (!context.mounted) return null;

  // Proposé automatiquement à « Bus parti » / « Arrivé » : inutile de
  // redemander une valeur déjà saisie (par le chauffeur ou le convoyeur).
  if (fromProgress && current.reading(kind) != null) return current;

  final int? value = await showOdometerSheet(
    context,
    kind: kind,
    initialKm: current.reading(kind)?.valueKm,
    minKm: kind == OdometerKind.end ? current.start?.valueKm : null,
    hintKm: current.latestKm,
    busRegistration: current.busRegistration,
    cancelLabel: fromProgress ? 'Plus tard' : 'Annuler',
  );
  if (value == null || !context.mounted) return null;

  KoraDialogs.showLoading(context, message: 'Enregistrement…');
  final Result<TripLog> result = await repository.recordOdometer(
    departureUuid: departureUuid,
    kind: kind,
    valueKm: value,
  );
  if (!context.mounted) return null;
  KoraDialogs.hideLoading(context);

  return result.when<Future<TripLog?>>(
    onSuccess: (TripLog log) async {
      final bool offline = log.reading(kind)?.pendingSync ?? false;
      final int? distance = log.distanceKm;
      if (offline) unawaited(getIt<SyncCubit>().refreshPendingCount());
      await KoraDialogs.success(
        context,
        title: 'Kilométrage ${kind.label.toLowerCase()} enregistré',
        description: <String>[
          formatKm(value),
          if (kind == OdometerKind.end && distance != null)
            'Distance du voyage : ${formatKm(distance)}',
          if (offline) 'Hors ligne — sera transmis au retour du réseau.',
        ].join('\n'),
      );
      return log;
    },
    onFailure: (Failure failure) async {
      await KoraDialogs.error(
        context,
        title: 'Kilométrage non enregistré',
        description: failure.message,
      );
      return null;
    },
  );
}
