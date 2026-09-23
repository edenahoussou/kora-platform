import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:uuid/uuid.dart';
import '../../../../app/config/app_config.dart';
import '../../../../app/config/terminal_mode.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/action_speed_dial.dart';
import '../../../../core/widgets/app_state_placeholder.dart';
import '../../../../core/widgets/kora_skeleton.dart';
import '../../../../core/widgets/passenger_list_tile.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../injection/injection.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../border_manifest/domain/services/border_manifest_builder.dart';
import '../../../border_manifest/presentation/pages/border_manifest_pdf_page.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../ops/presentation/depart_progress.dart';
import '../../../seat_release/domain/entities/seat_release_outcome.dart';
import '../../../seat_release/domain/entities/seat_release_outcome_status.dart';
import '../../../seat_release/domain/repositories/seat_release_repository.dart';
import '../../../synchro/presentation/widgets/sync_status_banner.dart';
import '../cubit/manifeste_cubit.dart';

/// Manifeste de la course sélectionnée depuis l'onglet Tournée (voir
/// échange du 17/08/2026 : le Manifeste n'est plus un onglet, on y accède
/// après avoir choisi une course). Liste des passagers (nom, siège, statut
/// d'embarquement, segment O/D). Route : `/tournee/manifeste`.
class ManifestePage extends StatelessWidget {
  const ManifestePage({super.key});

  bool _canCloseNoShows(Depart depart, ManifesteState state) {
    if (state.status != ManifesteStatus.success) return false;
    final bool hasUncontrolled = state.passengers
        .any((Passenger p) => p.statut == BoardingStatus.enAttente);
    if (!hasUncontrolled) return false;

    // Confort d'UX seulement : si le statut du départ n'est pas encore
    // connu localement, on laisse le bouton visible et le serveur
    // tranche (`departure_not_closable`, voir `CloseNoShowsForDeparture
    // Action`) plutôt que de priver l'agent de l'action.
    if (depart.status == null) return true;
    return depart.status == 'departed' || depart.status == 'arrived';
  }

  /// Le manifeste frontière n'a de sens que pour un départ international ;
  /// tant que ce n'est pas connu (`isInternational == null`, cache antérieur
  /// à MEZ-94) le bouton reste visible et l'écran d'impression demande un
  /// rafraîchissement plutôt que de faire disparaître silencieusement
  /// l'action.
  bool _canPrintBorderManifest(ManifesteState state) =>
      state.status == ManifesteStatus.success &&
      state.passengers.isNotEmpty &&
      state.isInternational != false;

  /// P501 : écran d'impression thermique (3 exemplaires). Mode téléphone :
  /// pas d'imprimante — PDF A4 généré par le serveur, après la même
  /// vérification de complétude côté mobile.
  Future<void> _openBorderManifest(
      BuildContext context, Depart depart, ManifesteState state) async {
    if (AppConfig.instance.terminalMode != TerminalMode.phone) {
      await context.push(AppRoutes.borderManifestPrint);
      return;
    }

    final BorderManifestBuild build = BorderManifestBuilder.build(
      BorderManifestInput(
        depart: depart,
        passengers: state.passengers,
        isInternational: state.isInternational,
        manifestVersion: state.manifestVersion,
        fetchedAt: state.fetchedAt,
      ),
    );
    if (!build.isReady) {
      await KoraDialogs.warning(
        context,
        title: 'Manifeste frontière impossible',
        description: _borderManifestIssuesSummary(build.issues),
      );
      return;
    }
    if (!context.mounted) return;

    await context.push(
      AppRoutes.borderManifestPdf,
      extra: BorderManifestPdfArgs(
        departureUuid: depart.id,
        label: depart.route,
      ),
    );
  }

  Future<void> _closeNoShows(
      BuildContext context, Depart depart, ManifesteState state) async {
    final ManifesteCubit manifesteCubit = context.read<ManifesteCubit>();
    final List<Passenger> uncontrolled = state.passengers
        .where((Passenger p) => p.statut == BoardingStatus.enAttente)
        .toList();

    final bool confirmed = await KoraDialogs.confirm(
      context,
      title: 'Clôturer les no-show ?',
      description: '${uncontrolled.length} passager(s) non contrôlé(s) '
          'seront marqués no-show :\n'
          '${uncontrolled.map((p) => '• ${p.nomComplet} (${p.siege})').join('\n')}',
      confirmLabel: 'Clôturer',
    );
    if (!confirmed || !context.mounted) return;

    KoraDialogs.showLoading(context, message: 'Clôture en cours…');
    final result = await getIt<SeatReleaseRepository>().closeNoShows(
      departureUuid: depart.id,
      uncontrolledTicketUuids: uncontrolled.map((p) => p.id).toList(),
      operationUuid: const Uuid().v4(),
      localRecordedAt: DateTime.now(),
    );
    if (!context.mounted) return;
    KoraDialogs.hideLoading(context);

    await result.when(
      onSuccess: (outcomes) async {
        int accepted = 0, pending = 0, rejected = 0;
        for (final SeatReleaseOutcome outcome in outcomes) {
          final String? ticketUuid = outcome.ticketUuid;
          if (ticketUuid == null) continue;
          switch (outcome.status) {
            case SeatReleaseOutcomeStatus.accepted:
              accepted++;
              await manifesteCubit.markSeatReleaseAccepted(
                  ticketUuid, BoardingStatus.refuse);
            case SeatReleaseOutcomeStatus.pendingSync:
              pending++;
              manifesteCubit.markSeatReleasePending(ticketUuid);
            default:
              rejected++;
          }
        }
        if (context.mounted) {
          await KoraDialogs.success(
            context,
            title: 'Clôture no-show',
            description: pending > 0
                ? '$pending en attente de synchronisation, $accepted '
                    'accepté(s), $rejected rejeté(s).'
                : '$accepted accepté(s), $rejected rejeté(s).',
          );
        }
      },
      onFailure: (failure) => KoraDialogs.error(context,
          title: 'Clôture no-show', description: failure.message),
    );
  }

  @override
  Widget build(BuildContext context) {
    final Depart? depart = context.watch<SelectedDepartCubit>().state;
    final AuthState authState = context.watch<AuthCubit>().state;
    final bool canProgressDeparture = authState is AuthAuthenticated &&
        authState.user.can('departures.progress');
    final bool canReportIncident = authState is AuthAuthenticated &&
        authState.user.can('incidents.report');
    final ManifesteState state = context.watch<ManifesteCubit>().state;

    // Actions du Speed Dial (refonte UI/UX du 21/09/2026) : Scan et
    // Descente anticipée — sortis de l'AppBar, ils n'ont plus de tuile
    // sur l'Accueil une fois un départ sélectionné (voir AccueilPage) —
    // rejoignent ici les actions précédemment empilées sous la liste,
    // toutes réservées à un départ sélectionné.
    final List<SpeedDialAction> speedDialActions = <SpeedDialAction>[];
    if (depart != null) {
      speedDialActions.addAll(<SpeedDialAction>[
        SpeedDialAction(
          icon: Icons.qr_code_scanner_rounded,
          label: 'Scan',
          onPressed: () => context.go(AppRoutes.scanBoarding),
        ),
        SpeedDialAction(
          icon: Icons.move_down_rounded,
          label: 'Descente anticipée',
          onPressed: () => context.go(AppRoutes.descenteAnticipee),
        ),
      ]);

      final bool canPrintBorder = _canPrintBorderManifest(state);
      final bool canCloseNoShows = _canCloseNoShows(depart, state);
      final DepartProgressStep? progressStep =
          canProgressDeparture ? nextDepartProgressStep(depart) : null;
      // Retard : seulement tant que le départ n'est pas parti (même
      // règle que RecordDepartureDelayAction).
      final bool canRecordDelay = canProgressDeparture &&
          (depart.status == null ||
              depart.status == 'scheduled' ||
              depart.status == 'boarding');

      if (progressStep != null) {
        speedDialActions.add(SpeedDialAction(
          icon: progressStep.icon,
          label: progressStep.label,
          onPressed: () => progressDeparture(context, depart, progressStep),
        ));
      }
      if (canReportIncident) {
        speedDialActions.add(SpeedDialAction(
          icon: Icons.report_problem,
          label: 'Signaler un incident',
          danger: true,
          onPressed: () => context.push(AppRoutes.incidentReport),
        ));
      }
      if (canRecordDelay) {
        speedDialActions.add(SpeedDialAction(
          icon: Icons.schedule,
          label: 'Signaler un retard',
          onPressed: () => recordDepartureDelay(context, depart),
        ));
      }
      if (canPrintBorder) {
        speedDialActions.add(SpeedDialAction(
          icon: Icons.print,
          label: 'Imprimer le manifeste frontière',
          onPressed: () => _openBorderManifest(context, depart, state),
        ));
      }
      if (canCloseNoShows) {
        speedDialActions.add(SpeedDialAction(
          icon: Icons.event_busy,
          label: 'Clôturer les no-show',
          danger: true,
          onPressed: () => _closeNoShows(context, depart, state),
        ));
      }
    }

    return Scaffold(
      appBar: ScreenHeader(
        eyebrow: depart?.route ?? 'Manifeste',
        title: 'Manifeste',
        action: const SyncStatusBanner(compact: true),
      ),
      // Speed Dial en Stack (plutôt que `floatingActionButton`, limité à
      // l'espace du bouton) : il a besoin des dimensions de l'écran
      // entier pour flouter tout ce qu'il recouvre à l'ouverture.
      body: Stack(
        children: <Widget>[
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
              child: depart == null
                  ? const AppStatePlaceholder.empty(
                      message:
                          'Sélectionnez une course depuis l\'onglet Tournée.',
                    )
                  : Builder(
                      builder: (BuildContext innerContext) {
                        final Widget content = switch (state.status) {
                          ManifesteStatus.noDepart =>
                            const AppStatePlaceholder.empty(
                              message:
                                  'Sélectionnez une course depuis l\'onglet Tournée.',
                            ),
                          ManifesteStatus.loading => const SkeletonList(),
                          ManifesteStatus.error => AppStatePlaceholder.error(
                              message:
                                  state.errorMessage ?? 'Erreur inattendue.',
                              actionLabel: 'Réessayer',
                              onAction: () =>
                                  innerContext.read<ManifesteCubit>().load(),
                            ),
                          ManifesteStatus.success
                              when state.passengers.isEmpty =>
                            const AppStatePlaceholder.empty(
                                message: 'Aucun passager sur ce départ.'),
                          ManifesteStatus.success => RefreshIndicator(
                              onRefresh: () =>
                                  innerContext.read<ManifesteCubit>().load(),
                              child: ListView.separated(
                                itemCount: state.passengers.length,
                                separatorBuilder: (_, __) => Divider(
                                    height: 1,
                                    color: innerContext.koraColors.line),
                                itemBuilder: (BuildContext context, int index) {
                                  final passenger = state.passengers[index];
                                  return PassengerListTile(
                                    passenger: passenger,
                                    onTap: () => context.push(
                                        AppRoutes.detailPassager,
                                        extra: passenger.id),
                                  );
                                },
                              ),
                            ),
                        };

                        return (state.status != ManifesteStatus.success ||
                                !state.isStale)
                            ? content
                            : Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: <Widget>[
                                  _StaleManifestBanner(
                                      fetchedAt: state.fetchedAt),
                                  const SizedBox(height: AppSpacing.sm),
                                  Expanded(child: content),
                                ],
                              );
                      },
                    ),
            ),
          ),
          // Toujours monté (voir docstring ActionSpeedDial) — évite le
          // clignotement du FAB (échange du 23/09/2026).
          Positioned.fill(
            child: ActionSpeedDial(
                actions: speedDialActions, heroTag: 'speed-dial-manifeste'),
          ),
        ],
      ),
    );
  }
}

/// Résume `BorderManifestBuild.issues` en une seule phrase (au lieu de
/// lister chaque problème) — affichée via `KoraDialogs.warning`, qui
/// gère déjà correctement les longs messages (pas de RenderFlex
/// overflow comme sur l'ancien dialogue dédié à liste scrollable).
String _borderManifestIssuesSummary(List<String> issues) {
  if (issues.length == 1) return issues.first;
  return '${issues.length} informations obligatoires sont manquantes '
      '(nationalité, n° de document, siège ou destination) pour imprimer '
      'le manifeste frontière. Complétez-les puis réessayez.';
}

class _StaleManifestBanner extends StatelessWidget {
  const _StaleManifestBanner({this.fetchedAt});

  final DateTime? fetchedAt;

  @override
  Widget build(BuildContext context) {
    final String suffix = fetchedAt == null
        ? ''
        : ' (${fetchedAt!.hour.toString().padLeft(2, '0')}:'
            '${fetchedAt!.minute.toString().padLeft(2, '0')})';

    return Container(
      padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md, vertical: AppSpacing.sm),
      decoration: BoxDecoration(
        color: Colors.orange.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: <Widget>[
          const Icon(Icons.cloud_off, size: 18, color: Colors.orange),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              'Hors ligne — dernière version connue$suffix, non vérifiée '
              'auprès du serveur.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
        ],
      ),
    );
  }
}
