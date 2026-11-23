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
import '../widgets/colis_surface.dart';

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
    // L'emplacement à remplir ne se souligne plus : la maquette le dit
    // par la pastille du rail, pleine dès qu'un guichet est retenu, et
    // une bordure dorée en plus brouillait les deux signaux.
    if (origin) {
      cubit.chooseOrigin(hub.id);
      return;
    }
    cubit.chooseDestination(hub.id);
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
            onContinue: served
                ? () => colisAdvance(context, AppRouter.colisContentPath)
                : null,
            onCancel: () => context.go('/colis'),
          ),
          children: [
            _RouteCard(
              draft: draft,
              onPickOrigin: () => _pickCity(context, origin: true),
              onPickDestination: () => _pickCity(context, origin: false),
              onSwap: context.read<ColisDraftCubit>().swapHubs,
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
            ColisNote(
              // Le bouclier de la maquette, pas l'étincelle : ce qui
              // est promis ici est une garantie d'acheminement, pas un
              // tour de magie.
              icon: Icons.verified_user_outlined,
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
/// La carte d'itinéraire : les deux emplacements, le rail qui les
/// relie, et le bouton d'inversion posé par-dessus.
///
/// Une seule carte blanche, et non deux encadrés : les deux
/// emplacements décrivent un même trajet, et le rail vertical le dit
/// mieux qu'une bordure dorée sur celui qu'on va remplir. C'est la
/// construction de la maquette, reprise telle quelle.
class _RouteCard extends StatelessWidget {
  const _RouteCard({
    required this.draft,
    required this.onPickOrigin,
    required this.onPickDestination,
    required this.onSwap,
  });

  final ColisDraft draft;
  final VoidCallback onPickOrigin;
  final VoidCallback onPickDestination;
  final VoidCallback onSwap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      // `Stack` : le bouton d'inversion mord sur le bord droit des deux
      // blocs, à cheval entre eux. Dans la colonne il les écartait.
      child: Stack(
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _Rail(filled: draft.originHub != null, isOrigin: true),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _HubSlot(
                        label: l10n.colisOriginHub,
                        hub: draft.originHub,
                        onTap: onPickOrigin,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _Rail(filled: draft.destinationHub != null, isOrigin: false),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _HubSlot(
                      label: l10n.colisDestinationHub,
                      hub: draft.destinationHub,
                      onTap: onPickDestination,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              // Le même geste que toucher un emplacement, nommé : on ne
              // devine pas qu'un bloc gris s'ouvre.
              Center(
                child: TextButton.icon(
                  onPressed: onPickOrigin,
                  icon: const Icon(Icons.search_rounded, size: 18),
                  label: Text(l10n.colisHubPlaceholder),
                  style: TextButton.styleFrom(
                    foregroundColor: AppColors.digital,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                ),
              ),
            ],
          ),
          // Le retour est le second envoi le plus fréquent : un bouton
          // vaut mieux que deux choix refaits à la main.
          Positioned(
            right: 0,
            top: 0,
            bottom: 0,
            child: Center(
              child: Material(
                color: koraColors.surface,
                shape: const CircleBorder(),
                elevation: 3,
                shadowColor: AppColors.kora.withValues(alpha: 0.18),
                child: InkWell(
                  onTap: onSwap,
                  customBorder: const CircleBorder(),
                  child: Container(
                    width: 40,
                    height: 40,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(color: koraColors.line),
                    ),
                    child: Icon(
                      Icons.swap_vert_rounded,
                      size: 20,
                      color: context.koraTitleColor,
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Le rail de gauche : une pastille cerclée, et le trait qui descend
/// vers la suivante.
///
/// Marine au départ, dorée à l'arrivée — c'est la pastille qui dit
/// lequel des deux emplacements on regarde, pas une bordure.
class _Rail extends StatelessWidget {
  const _Rail({required this.filled, required this.isOrigin});

  /// L'emplacement porte déjà un guichet. Il ne change plus que
  /// l'intensité du halo : la couleur, elle, dit le sens du trajet.
  final bool filled;
  final bool isOrigin;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final color = isOrigin ? AppColors.kora : AppColors.gold;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const SizedBox(height: 8),
        Container(
          width: 16,
          height: 16,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            // Marine au départ, dorée à l'arrivée, remplie ou non : la
            // pastille dit quel bout du trajet on regarde, pas si le
            // guichet est choisi. Grisée tant que le champ est vide,
            // elle ne distinguait plus les deux emplacements.
            color: color,
            // Le halo de la maquette : quatre pixels de la teinte, très
            // pâles, qui détachent la pastille du fond.
            boxShadow: [
              BoxShadow(
                color: color.withValues(alpha: filled ? 0.22 : 0.12),
                spreadRadius: 4,
              ),
            ],
          ),
          child: Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: isOrigin ? AppColors.white : AppColors.kora,
            ),
          ),
        ),
        if (isOrigin) ...[
          const SizedBox(height: 4),
          Expanded(child: Container(width: 2, color: koraColors.line)),
          const SizedBox(height: 4),
        ],
      ],
    );
  }
}

/// Un emplacement : son intitulé, le guichet retenu, et le chevron qui
/// dit qu'il s'ouvre.
///
/// Un bloc gris encastré dans la carte, sans bordure : c'est un champ
/// de formulaire, pas une carte à lui seul.
class _HubSlot extends StatelessWidget {
  const _HubSlot({required this.label, required this.hub, required this.onTap});

  final String label;
  final ColisHub? hub;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;

    return Material(
      color: koraColors.background,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      label.toUpperCase(),
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.6,
                      ),
                    ),
                  ),
                  // La place du chevron est tenue même quand le bouton
                  // d'inversion passe par-dessus : sans elle, les deux
                  // blocs n'auraient pas la même largeur de texte.
                  const SizedBox(width: 44),
                  Icon(
                    Icons.expand_more_rounded,
                    size: 20,
                    color: koraColors.textMuted,
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                hub?.label ?? l10n.colisHubPlaceholder,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: hub == null
                      ? koraColors.textMuted
                      : context.koraTitleColor,
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
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
                    // Le quartier et le rang du hub : c'est ce qui
                    // distingue deux gares d'une même ville quand on
                    // ne connaît que leur nom.
                    const SizedBox(height: 2),
                    Text(
                      hub.zone,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                    const SizedBox(height: 2),
                    // Le réseau dépasse le Bénin : une gare de Lomé ne
                    // se choisit pas par inadvertance.
                    Text(
                      hub.country,
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
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
