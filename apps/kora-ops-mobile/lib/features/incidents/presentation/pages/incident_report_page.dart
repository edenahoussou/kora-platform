import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:uuid/uuid.dart';

import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/app_text_styles.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/app_state_placeholder.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../injection/injection.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../tracking/domain/services/location_fix.dart';
import '../../../tracking/domain/services/location_permission_status.dart';
import '../../../tracking/domain/services/location_provider.dart';
import '../../domain/entities/incident.dart';
import '../../domain/repositories/incident_repository.dart';

/// Signalement d'un incident (panne, retard, accident, sécurité, autre)
/// pour le départ géré — voir MEZ-98. Toujours accepté, même hors ligne
/// (`IncidentRepository.report`, file locale — voir `SyncCubit`).
///
/// Position GPS best-effort (échange du 26/09/2026) : une seule mesure au
/// moment de l'envoi, via `LocationProvider` (déjà utilisé par le suivi de
/// bus, voir module Tracking) — jamais bloquant. Ni permission refusée,
/// ni GPS coupé, ni délai dépassé n'empêchent l'envoi : l'incident part
/// simplement sans position dans ce cas (comme avant ce changement).
class IncidentReportPage extends StatefulWidget {
  const IncidentReportPage({super.key});

  @override
  State<IncidentReportPage> createState() => _IncidentReportPageState();
}

class _IncidentReportPageState extends State<IncidentReportPage> {
  IncidentType _type = IncidentType.breakdown;
  final TextEditingController _descriptionController = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _submit(Depart depart) async {
    final String description = _descriptionController.text.trim();
    if (description.length < 5) {
      await KoraDialogs.warning(
        context,
        title: 'Description trop courte',
        description: 'Décrivez l\'incident en au moins 5 caractères.',
      );
      return;
    }

    setState(() => _submitting = true);
    final LocationFix? fix = await _currentPositionOrNull();
    final result = await getIt<IncidentRepository>().report(
      operationUuid: const Uuid().v4(),
      departureUuid: depart.id,
      type: _type,
      description: description,
      localRecordedAt: DateTime.now(),
      latitude: fix?.latitude,
      longitude: fix?.longitude,
    );
    if (!mounted) return;
    setState(() => _submitting = false);

    await result.when(
      onSuccess: (Incident incident) async {
        await KoraDialogs.success(
          context,
          title: 'Incident signalé',
          description: incident.pendingSync
              ? 'Enregistré hors ligne — sera transmis au retour du réseau.'
              : 'Transmis avec succès.',
        );
        // ignore: use_build_context_synchronously
        if (context.mounted) context.pop();
      },
      onFailure: (failure) => KoraDialogs.error(
        context,
        title: 'Signalement impossible',
        description: failure.message,
      ),
    );
  }

  /// Une seule mesure de position, best-effort — jamais de demande de
  /// permission ici (`checkPermission`, pas `requestPermission` : ce n'est
  /// pas le moment d'interrompre un signalement urgent avec une boîte de
  /// dialogue système ; la permission se demande ailleurs, voir
  /// `TrackingCubit`). `null` dans tous les cas où la position n'est pas
  /// immédiatement disponible (permission refusée, GPS coupé, délai
  /// dépassé) — l'incident part quand même.
  Future<LocationFix?> _currentPositionOrNull() async {
    try {
      final LocationProvider location = getIt<LocationProvider>();

      final LocationPermissionStatus permission =
          await location.checkPermission();
      if (permission == LocationPermissionStatus.denied ||
          permission == LocationPermissionStatus.deniedForever) {
        return null;
      }

      if (!await location.isLocationServiceEnabled()) return null;

      return await location
          .watchPosition(intervalSeconds: 1, minDistanceMeters: 0)
          .first
          .timeout(const Duration(seconds: 6));
    } on Object {
      return null;
    }
  }

  @override
  Widget build(BuildContext context) {
    final Depart? depart = context.watch<SelectedDepartCubit>().state;
    final KoraColors koraColors = context.koraColors;

    return Scaffold(
      appBar: ScreenHeader(
        eyebrow: depart?.route ?? 'Incident',
        title: 'Signaler un incident',
      ),
      body: SafeArea(
        child: depart == null
            ? const AppStatePlaceholder.empty(
                message: 'Sélectionnez une course depuis l\'onglet Tournée.',
              )
            : Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Expanded(
                      child: SingleChildScrollView(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: <Widget>[
                            Text('Type d\'incident',
                                style: AppTextStyles.titleMedium(
                                    koraColors.textPrimary)),
                            const SizedBox(height: AppSpacing.sm),
                            Wrap(
                              spacing: AppSpacing.sm,
                              runSpacing: AppSpacing.sm,
                              children: IncidentType.values
                                  .map((IncidentType t) => ChoiceChip(
                                        label: Text(t.label),
                                        selected: _type == t,
                                        onSelected: (_) =>
                                            setState(() => _type = t),
                                        selectedColor: AppColors.gold
                                            .withValues(alpha: 0.25),
                                        labelStyle: TextStyle(
                                          color: _type == t
                                              ? AppColors.kora
                                              : koraColors.textPrimary,
                                          fontWeight: _type == t
                                              ? FontWeight.w700
                                              : FontWeight.w500,
                                        ),
                                      ))
                                  .toList(),
                            ),
                            const SizedBox(height: AppSpacing.lg),
                            Text('Description',
                                style: AppTextStyles.titleMedium(
                                    koraColors.textPrimary)),
                            const SizedBox(height: AppSpacing.sm),
                            TextField(
                              controller: _descriptionController,
                              minLines: 4,
                              maxLines: 8,
                              maxLength: 1000,
                              decoration: const InputDecoration(
                                hintText: 'Décrivez ce qui se passe…',
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    KoraButton(
                      label: 'Envoyer le signalement',
                      variant: KoraButtonVariant.danger,
                      icon: Icons.report_problem,
                      isLoading: _submitting,
                      onPressed: () => _submit(depart),
                    ),
                  ],
                ),
              ),
      ),
    );
  }
}
