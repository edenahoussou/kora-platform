import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/depart.dart';
import 'package:kora_ops_mobile/features/ops/domain/repositories/departure_repository.dart';

import '../../../core/services/kora_dialogs.dart';
import '../../../injection/injection.dart';
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
        label: 'Marquer le bus arrivé',
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
    },
    onFailure: (failure) => KoraDialogs.error(
      context,
      title: 'Action impossible',
      description: failure.message,
    ),
  );
}

/// Demande retard + motif puis l'envoie (`PATCH .../delay`) — départ
/// programmé ou en embarquement uniquement (le serveur tranche, 422
/// `invalid_delay` sinon).
Future<void> recordDepartureDelay(BuildContext context, Depart depart) async {
  final result = await KoraDialogs.promptDelay(
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
