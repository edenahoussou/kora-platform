import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/depart.dart';
import 'package:kora_ops_mobile/features/ops/domain/repositories/departure_repository.dart';
import 'package:permission_handler/permission_handler.dart' as ph;

import '../../../core/services/kora_dialogs.dart';
import '../../../core/widgets/delay_sheet.dart';
import '../../../injection/injection.dart';
import '../../tracking/domain/entities/tracking_availability.dart';
import '../../tracking/presentation/cubit/tracking_cubit.dart';
import 'cubit/selected_depart_cubit.dart';

/// Étape suivante proposée au convoyeur pour un départ — voir
/// [nextDepartProgressStep]. Type unique partagé plutôt que dupliqué dans
/// chaque écran qui l'affiche (Accueil, Manifeste — voir échange du
/// 20/09/2026).
typedef DepartProgressStep = ({
  String label,
  IconData icon,
  String targetStatus
});

/// Prochaine étape de progression du départ (embarquement → parti →
/// arrivé, MEZ-98) — `null` une fois `arrived` ou si `cancelled` (plus
/// rien à proposer). Même statut que côté OPS Web (`departures.status`).
DepartProgressStep? nextDepartProgressStep(Depart depart) {
  return switch (depart.status) {
    null || 'scheduled' => (
        label: 'Commencer l\'embarquement',
        icon: Icons.how_to_reg,
        targetStatus: 'boarding',
      ),
    'boarding' => (
        label: 'Marquer le bus parti',
        icon: Icons.directions_bus,
        targetStatus: 'departed',
      ),
    'departed' => (
        label: 'Marquer l\'arrivé',
        icon: Icons.flag_circle,
        targetStatus: 'arrived',
      ),
    _ => null, // 'arrived' ou 'cancelled'.
  };
}

/// Envoie la transition de statut, répercute le statut confirmé sur le
/// [Depart] déjà géré (`SelectedDepartCubit`, sans rechargement complet —
/// voir docstring `DepartureRepository.updateStatus`) puis confirme au
/// convoyeur (voir échange du 20/09/2026 : une confirmation visible à
/// chaque étape, comme pour "Clôturer les no-show").
Future<void> progressDeparture(
  BuildContext context,
  Depart depart,
  DepartProgressStep step,
) async {
  final bool confirmed = await KoraDialogs.confirm(
    context,
    title: step.label,
    description: 'Confirmer : ${step.label.toLowerCase()} ?',
    confirmLabel: 'Confirmer',
  );
  if (!confirmed || !context.mounted) return;

  KoraDialogs.showLoading(context, message: 'Mise à jour…');
  final result = await getIt<DepartureRepository>()
      .updateStatus(depart.id, step.targetStatus);
  if (!context.mounted) return;
  KoraDialogs.hideLoading(context);

  await result.when(
    onSuccess: (newStatus) async {
      await context
          .read<SelectedDepartCubit>()
          .select(depart.copyWith(status: newStatus));
      if (context.mounted) {
        await KoraDialogs.success(
          context,
          title: step.label,
          description: 'Statut du départ mis à jour.',
        );
      }
      // Le suivi de position (MEZ-101) démarre automatiquement sur cette
      // transition (voir TrackingCubit._onDepartureChanged) — s'il ne
      // peut pas faute de permission de localisation, c'est ICI, sur une
      // action explicite du convoyeur, qu'on lui propose d'aller
      // l'autoriser (jamais automatiquement au retour au premier plan,
      // voir TrackingCubit.onAppResumed — échange du 24/09/2026, pour ne
      // pas réintroduire la boucle de boîtes de dialogue).
      if (newStatus == 'departed' && context.mounted) {
        await _offerLocationSettingsIfBlocked(context);
      }
    },
    onFailure: (failure) => KoraDialogs.error(
      context,
      title: 'Action impossible',
      description: failure.message,
    ),
  );
}

/// Le convoyeur a dû interagir avec le dialogue de confirmation ci-dessus
/// avant d'arriver ici : `TrackingCubit._start` (déclenché par `select()`
/// plus haut) a largement eu le temps de s'exécuter et d'émettre son
/// résultat — lire l'état courant suffit, pas besoin d'attendre une
/// prochaine émission. Si le suivi reste bloqué faute de permission,
/// propose d'ouvrir les réglages système — avec confirmation explicite du
/// convoyeur, jamais une redirection automatique.
Future<void> _offerLocationSettingsIfBlocked(BuildContext context) async {
  final TrackingState trackingState = context.read<TrackingCubit>().state;

  if (trackingState.isActive) return;
  if (trackingState.availability != TrackingAvailability.permissionDenied) {
    return;
  }

  final bool openSettings = await KoraDialogs.confirm(
    context,
    title: 'Localisation requise',
    description: 'Le suivi de position du bus a besoin de la localisation, '
        'et le convoyeur ne l\'a pas encore autorisée. Ouvrir les réglages '
        'pour l\'activer maintenant ?',
    confirmLabel: 'Ouvrir les réglages',
    cancelLabel: 'Plus tard',
  );

  if (openSettings) await ph.openAppSettings();
}

/// Demande retard + motif puis l'envoie (`PATCH .../delay`) — départ
/// programmé ou en embarquement uniquement (le serveur tranche, 422
/// `invalid_delay` sinon).
Future<void> recordDepartureDelay(BuildContext context, Depart depart) async {
  // Bottom sheet (et non plus un dialogue modal) : s'intègre au design
  // de l'app et gère proprement le clavier.
  final result = await showDelaySheet(
    context,
    title: 'Signaler un retard',
    description: 'Retard en minutes et motif — 0 minute annule un retard '
        'déjà enregistré.',
  );
  if (result == null || !context.mounted) return;

  KoraDialogs.showLoading(context, message: 'Enregistrement…');
  final delayResult = await getIt<DepartureRepository>()
      .recordDelay(depart.id, result.minutes, result.reason);
  if (!context.mounted) return;
  KoraDialogs.hideLoading(context);

  await delayResult.when(
    onSuccess: (_) => KoraDialogs.success(
      context,
      title: 'Retard enregistré',
      description: result.minutes == 0
          ? 'Le retard a été annulé.'
          : '${result.minutes} min — ${result.reason}',
    ),
    onFailure: (failure) => KoraDialogs.error(
      context,
      title: 'Action impossible',
      description: failure.message,
    ),
  );
}
