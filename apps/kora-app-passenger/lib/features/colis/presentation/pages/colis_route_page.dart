import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../booking/domain/entities/city.dart';
import '../../../home/presentation/pages/city_picker_page.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../bloc/colis_draft_cubit.dart';
import '../widgets/colis_step_scaffold.dart';

/// Écran 02 — « D'où part votre colis ? ».
///
/// L'itinéraire se choisit **avant** le contenu : c'est un critère
/// d'acceptation du ticket, et c'est aussi la règle du domaine. Un colis
/// voyage en soute d'un départ Bus entre deux arrêts d'une même ligne
/// (ADR 0028) : sans corridor, il n'y a ni tarif, ni délai, ni hub de
/// retrait à annoncer.
class ColisRoutePage extends StatefulWidget {
  const ColisRoutePage({super.key});

  @override
  State<ColisRoutePage> createState() => _ColisRoutePageState();
}

class _ColisRoutePageState extends State<ColisRoutePage> {
  /// L'emplacement que le prochain choix remplira : départ ou arrivée.
  ///
  /// Il ne commande plus aucune liste — il ne sert qu'à dire au voyageur
  /// où il en est, en soulignant l'emplacement à remplir.
  var _pickingOrigin = true;

  /// Ouvre le sélecteur de villes de l'accueil, celui du parcours Bus.
  ///
  /// Le même écran pour le même geste : chercher une ville desservie.
  /// Le colis demande ensuite un **guichet**, que la ville ne donne pas
  /// toujours — Cotonou en a deux. Il se choisit alors dans la foulée,
  /// en feuille, plutôt que dans une liste posée en bas d'écran : celle
  /// -ci montrait les guichets de tout le pays quand on n'en cherchait
  /// qu'un, et ne disait pas lequel des deux emplacements elle allait
  /// remplir.
  Future<void> _pickCity(BuildContext context, {required bool origin}) async {
    final draft = context.read<ColisDraftCubit>().state;
    final current = origin ? draft.originHub : draft.destinationHub;

    final city = await context.push<City>(
      origin ? AppRouter.originPath : AppRouter.destinationPath,
      extra: CityPickerArgs(
        cities: [for (final name in kColisCities) City.named(name)],
        selected: current == null ? null : City.named(current.city),
        purpose: CityPickerPurpose.parcel,
      ),
    );
    if (city == null || !context.mounted) return;

    final hubs = [
      for (final hub in kColisHubs)
        if (hub.city == city.name) hub,
    ];
    if (hubs.isEmpty) return;

    // Une seule gare dans la ville : inutile de la faire choisir.
    final hub = hubs.length == 1
        ? hubs.single
        : await _pickHub(
            context,
            city: city.name,
            hubs: hubs,
            current: current,
          );
    if (hub == null || !context.mounted) return;

    _choose(hub, origin: origin);
  }

  /// La feuille qui tranche entre les guichets d'une même ville.
  Future<ColisHub?> _pickHub(
    BuildContext context, {
    required String city,
    required List<ColisHub> hubs,
    required ColisHub? current,
  }) {
    return showModalBottomSheet<ColisHub>(
      context: context,
      useSafeArea: true,
      backgroundColor: context.koraColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) => _HubSheet(
        city: city,
        hubs: hubs,
        selected: current,
        onPick: (hub) => Navigator.of(sheetContext).pop(hub),
      ),
    );
  }

  void _choose(ColisHub hub, {required bool origin}) {
    final cubit = context.read<ColisDraftCubit>();
    if (origin) {
      cubit.chooseOrigin(hub.id);
      // Le voyageur vient de dire d'où il part : la suite logique est
      // l'arrivée, et la lui désigner évite un aller-retour.
      setState(() => _pickingOrigin = false);
      return;
    }
    cubit.chooseDestination(hub.id);
    setState(() => _pickingOrigin = true);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return BlocBuilder<ColisDraftCubit, ColisDraft>(
      builder: (context, draft) {
        final served = draft.isRouteServed;

        return ColisStepScaffold(
          step: 1,
          stepLabel: l10n.colisStepRoute,
          title: l10n.colisRouteTitle,
          subtitle: l10n.colisRouteSubtitle,
          footer: ColisStepFooter(
            label: l10n.colisRouteCta,
            hint: l10n.colisRouteHint,
            onContinue: served
                ? () => colisAdvance(context, AppRouter.colisContentPath)
                : null,
            onCancel: () => context.go('/colis'),
          ),
          children: [
            _HubSlot(
              label: l10n.colisOriginHub,
              hub: draft.originHub,
              selected: _pickingOrigin,
              onTap: () => _pickCity(context, origin: true),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: _HubSlot(
                    label: l10n.colisDestinationHub,
                    hub: draft.destinationHub,
                    selected: !_pickingOrigin,
                    onTap: () => _pickCity(context, origin: false),
                  ),
                ),
                const SizedBox(width: 8),
                // Le retour est le second envoi le plus fréquent : un
                // bouton vaut mieux que deux choix refaits à la main.
                KoraIconButton(
                  icon: Icons.swap_vert_rounded,
                  onTap: context.read<ColisDraftCubit>().swapHubs,
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            if (draft.hasRoute && !served)
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                // Aucun transfert implicite, aucun hub de substitution :
                // l'ADR 0028 refuse de rattraper une liaison absente, et
                // l'écran doit le dire plutôt que de laisser continuer.
                child: KoraAlert(
                  variant: KoraAlertVariant.warning,
                  icon: Icons.block_rounded,
                  title: l10n.colisRouteUnservedTitle,
                  message: l10n.colisRouteUnservedText,
                ),
              ),
            KoraAlert(
              icon: Icons.auto_awesome_rounded,
              title: l10n.colisAutoAssignTitle,
              message: l10n.colisAutoAssignText,
            ),
          ],
        );
      },
    );
  }
}

/// Un emplacement d'itinéraire : départ ou arrivée, rempli ou en attente.
class _HubSlot extends StatelessWidget {
  const _HubSlot({
    required this.label,
    required this.hub,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final ColisHub? hub;

  /// L'emplacement que la liste du dessous va remplir.
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;

    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(
              color: selected ? AppColors.gold : koraColors.line,
              width: selected ? 2 : 1,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 9,
                height: 9,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: hub == null
                      ? koraColors.line
                      : (selected ? AppColors.gold : AppColors.kora),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      label.toUpperCase(),
                      style: AppTextStyles.eyebrow(koraColors.textMuted),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      hub?.label ?? l10n.colisHubPlaceholder,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: hub == null
                            ? koraColors.textMuted
                            : context.koraTitleColor,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (hub != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        hub!.counter,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                    ],
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

/// Un hub de la liste : sa ville, son guichet, et la coche s'il est pris.
class _HubTile extends StatelessWidget {
  const _HubTile({
    required this.hub,
    required this.selected,
    required this.onTap,
  });

  final ColisHub hub;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.base),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.base),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.base),
            border: Border.all(color: koraColors.line),
          ),
          child: Row(
            children: [
              Icon(
                Icons.warehouse_outlined,
                size: 17,
                color: koraColors.textMuted,
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      hub.label,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      hub.counter,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ],
                ),
              ),
              if (selected)
                const Icon(
                  Icons.check_circle_rounded,
                  size: 18,
                  color: AppColors.success,
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// La feuille des guichets d'une ville, quand elle en compte plusieurs.
class _HubSheet extends StatelessWidget {
  const _HubSheet({
    required this.city,
    required this.hubs,
    required this.selected,
    required this.onPick,
  });

  final String city;
  final List<ColisHub> hubs;
  final ColisHub? selected;
  final ValueChanged<ColisHub> onPick;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 14, 18, AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Center(
              child: Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: koraColors.line,
                  borderRadius: BorderRadius.circular(AppRadius.pill),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              l10n.colisHubSheetTitle,
              style: TextStyle(
                color: context.koraTitleColor,
                fontSize: 17,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              l10n.colisHubSheetSubtitle(city),
              style: AppTextStyles.body(koraColors.textMuted),
            ),
            const SizedBox(height: AppSpacing.md),
            for (final hub in hubs) ...[
              _HubTile(
                hub: hub,
                selected: hub.id == selected?.id,
                onTap: () => onPick(hub),
              ),
              const SizedBox(height: 8),
            ],
          ],
        ),
      ),
    );
  }
}
