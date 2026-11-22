import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../data/datasources/colis_tracking_mock.dart';
import '../widgets/colis_status_chip.dart';
import '../widgets/colis_surface.dart';

/// Ce que la liste montre : ce qui roule, ce qui est clos, ce qui attend
/// encore d'être déposé.
///
/// Trois états, trois gestes différents. Un brouillon ne se suit pas, il
/// se finalise ; un colis retiré ne se suit plus, on en garde le reçu.
/// Les mêler ferait chercher un code de retrait sur un dossier qui n'a
/// même pas de référence.
enum _Shelf { active, history, drafts }

/// Écran 10 — « Mes envois & réceptions ».
///
/// Deux axes, et ils ne se recouvrent pas : le **sens** (ce qu'on envoie,
/// ce qu'on attend) et l'**état** (en cours, historique, brouillons). On
/// ne retire pas ce qu'on envoie, et on ne suit pas ce qu'on n'a pas
/// déposé.
class ColisShipmentsPage extends StatefulWidget {
  const ColisShipmentsPage({super.key});

  @override
  State<ColisShipmentsPage> createState() => _ColisShipmentsPageState();
}

class _ColisShipmentsPageState extends State<ColisShipmentsPage> {
  final _search = TextEditingController();

  var _outgoing = true;
  var _shelf = _Shelf.active;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  /// La recherche porte sur la référence et sur les deux villes : c'est
  /// par l'une des trois qu'on se souvient d'un envoi.
  bool _matches(ColisTrackedParcel parcel) {
    final query = _search.text.trim().toLowerCase();
    if (query.isEmpty) return true;

    return parcel.reference.toLowerCase().contains(query) ||
        parcel.originCity.toLowerCase().contains(query) ||
        parcel.destinationCity.toLowerCase().contains(query) ||
        parcel.recipientName.toLowerCase().contains(query);
  }

  /// Un colis est clos une fois remis : il quitte « en cours » pour
  /// l'historique, où l'on ne cherche plus qu'un reçu.
  bool _isClosed(ColisTrackedParcel parcel) =>
      parcel.status == ColisTrackingStatus.collected;

  List<ColisTrackedParcel> get _visible => [
    for (final parcel in kColisTrackedParcels)
      if (parcel.outgoing == _outgoing &&
          _isClosed(parcel) == (_shelf == _Shelf.history) &&
          _matches(parcel))
        parcel,
  ];

  Future<void> _trackExternal() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: context.koraColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) => const _TrackExternalSheet(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    final incoming = kColisTrackedParcels
        .where((parcel) => !parcel.outgoing)
        .length;
    final outgoing = kColisTrackedParcels.length - incoming;
    final parcels = _visible;
    final drafts = _shelf == _Shelf.drafts
        ? kColisDraftParcels
        : const <ColisDraftParcel>[];

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeaderless(
                eyebrow: l10n.colisShipmentsEyebrow,
                title: l10n.colisShipmentsTitle,
              ),
              KoraInput(
                hint: l10n.colisShipmentsSearch,
                controller: _search,
                prefixIcon: Icons.search_rounded,
                onChanged: (_) => setState(() {}),
              ),
              const SizedBox(height: AppSpacing.md),
              // Les deux gestes qu'on vient faire ici quand la liste ne
              // contient pas déjà ce qu'on cherche.
              Row(
                children: [
                  // Doré puis discret : déposer est l'action de
                  // l'écran, suivre n'en est que l'alternative. Deux
                  // pavés marine côte à côte, au-dessus d'un onglet
                  // marine lui aussi, ne se départageaient plus.
                  Expanded(
                    child: KoraButton(
                      label: l10n.parcelsCreateTitle,
                      icon: Icons.add_box_outlined,
                      onPressed: () => context.push(AppRouter.colisRoutePath),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: ColisFlatAction(
                      label: l10n.colisTrackParcel,
                      icon: Icons.travel_explore_rounded,
                      iconTint: AppColors.digital,
                      onTap: _trackExternal,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              // Le rail segmenté de la maquette : un seul fond gris
              // porte les deux sens, et celui qu'on regarde se détache
              // en blanc. Deux pavés marine côte à côte se lisaient
              // comme deux boutons, pas comme un choix.
              Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: context.koraColors.background,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: _Tab(
                        label: l10n.colisSent(outgoing),
                        icon: Icons.outbox_outlined,
                        selected: _outgoing,
                        onTap: () => setState(() => _outgoing = true),
                      ),
                    ),
                    Expanded(
                      child: _Tab(
                        label: l10n.colisToReceive(incoming),
                        icon: Icons.move_to_inbox_outlined,
                        selected: !_outgoing,
                        onTap: () => setState(() => _outgoing = false),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 10),
              // Les trois états, en pastilles : c'est un filtre, pas un
              // changement d'écran. Elles défilent plutôt que de se
              // partager la largeur — « Pré-enregistrements » ne tient
              // pas dans un tiers d'écran.
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.only(bottom: 2),
                child: Row(
                  children: [
                    for (final shelf in _Shelf.values) ...[
                      _ShelfChip(
                        label: _shelfLabel(l10n, shelf),
                        count: _shelfCount(shelf),
                        icon: shelf == _Shelf.drafts
                            ? Icons.edit_note_rounded
                            : null,
                        selected: _shelf == shelf,
                        onTap: () => setState(() => _shelf = shelf),
                      ),
                      if (shelf != _Shelf.values.last) const SizedBox(width: 6),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              Expanded(
                child: parcels.isEmpty && drafts.isEmpty
                    ? _EmptyShelf(
                        onCreate: () => context.push(AppRouter.colisRoutePath),
                        onTrack: _trackExternal,
                      )
                    : ListView(
                        padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                        children: [
                          for (final draft in drafts) ...[
                            _DraftTile(draft: draft),
                            const SizedBox(height: 8),
                          ],
                          for (final parcel in parcels) ...[
                            _ParcelTile(
                              parcel: parcel,
                              onTap: () => context.push(
                                AppRouter.colisTrackingPath,
                                extra: parcel.reference,
                              ),
                            ),
                            const SizedBox(height: 8),
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

  String _shelfLabel(AppLocalization l10n, _Shelf shelf) => switch (shelf) {
    _Shelf.active => l10n.colisTabActive,
    _Shelf.history => l10n.colisTabHistory,
    _Shelf.drafts => l10n.colisTabDrafts,
  };

  int _shelfCount(_Shelf shelf) => switch (shelf) {
    _Shelf.drafts => kColisDraftParcels.length,
    _Shelf.history =>
      kColisTrackedParcels
          .where((parcel) => parcel.outgoing == _outgoing && _isClosed(parcel))
          .length,
    _Shelf.active =>
      kColisTrackedParcels
          .where((parcel) => parcel.outgoing == _outgoing && !_isClosed(parcel))
          .length,
  };
}

/// Un en-tête sobre : un retour, un surtitre et un titre, sans la marque.
///
/// Les écrans de suivi s'ouvrent par-dessus l'onglet Colis, qui porte
/// déjà le logo : le répéter deux fois de suite n'apporte rien.
class ScreenHeaderless extends StatelessWidget {
  const ScreenHeaderless({super.key, required this.title, this.eyebrow});

  final String title;
  final String? eyebrow;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          KoraIconButton(
            icon: Icons.arrow_back_rounded,
            onTap: () => context.pop(),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                if (eyebrow case final eyebrow?) ...[
                  Text(
                    eyebrow.toUpperCase(),
                    style: AppTextStyles.eyebrow(context.koraColors.textMuted),
                  ),
                  const SizedBox(height: 2),
                ],
                Text(
                  title,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Un onglet de la liste : envoyés, ou à recevoir.
///
/// Posé dans le rail gris : le retenu passe en blanc avec son ombre,
/// l'autre reste à plat sur le fond. Aucun contour — c'est le rail qui
/// borne les deux.
class _Tab extends StatelessWidget {
  const _Tab({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final tint = selected ? context.koraTitleColor : koraColors.textMuted;

    return Material(
      color: selected ? koraColors.surface : Colors.transparent,
      borderRadius: BorderRadius.circular(AppRadius.sm),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: Container(
          height: 42,
          alignment: Alignment.center,
          padding: const EdgeInsets.symmetric(horizontal: 6),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.sm),
            boxShadow: selected ? kColisCardShadow : null,
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 17, color: tint),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: tint,
                    fontSize: 13,
                    fontWeight: selected ? FontWeight.w900 : FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Le filtre d'état, avec son compte.
///
/// Retenue, elle est marine pleine, avec sa puce dorée et son compte
/// dans un jeton clair ; au repos, elle est blanche à texte atténué. La
/// maquette ne lui donne pas de contour, dans un cas comme dans
/// l'autre : c'est la couleur qui dit lequel est actif.
class _ShelfChip extends StatelessWidget {
  const _ShelfChip({
    required this.label,
    required this.count,
    required this.selected,
    required this.onTap,
    this.icon,
  });

  final String label;
  final int count;
  final bool selected;
  final VoidCallback onTap;

  /// Le pictogramme des brouillons : un dossier non déposé ne se lit pas
  /// comme un état d'acheminement, et la maquette le marque.
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final tint = selected ? AppColors.white : koraColors.textMuted;

    return Material(
      color: selected ? AppColors.kora : koraColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.pill),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.pill),
        child: Container(
          height: 36,
          padding: const EdgeInsets.symmetric(horizontal: 14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.pill),
            boxShadow: selected ? null : kColisCardShadow,
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icon case final icon?) ...[
                Icon(icon, size: 16, color: tint),
                const SizedBox(width: 6),
              ] else if (selected) ...[
                // La puce dorée : elle ne paraît que sur la pastille
                // retenue, comme un voyant allumé.
                Container(
                  width: 7,
                  height: 7,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.gold,
                  ),
                ),
                const SizedBox(width: 7),
              ],
              Text(
                label,
                style: TextStyle(
                  color: tint,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(width: 7),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                decoration: BoxDecoration(
                  color: selected
                      ? AppColors.white.withValues(alpha: 0.2)
                      : koraColors.background,
                  borderRadius: BorderRadius.circular(AppRadius.pill),
                ),
                child: Text(
                  '$count',
                  style: TextStyle(
                    color: tint,
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Un envoi de la liste : son état, sa référence, son trajet, et ce
/// qu'il reste à faire.
///
/// La maquette construit cette carte en quatre bandes : l'état et la
/// référence, le trajet en trois colonnes, l'avancement dans un encart
/// gris, puis le destinataire. Le geste se prend en bas à droite, sur
/// un bouton nommé — toute la carte cliquable ne disait pas où l'on
/// allait.
class _ParcelTile extends StatelessWidget {
  const _ParcelTile({required this.parcel, required this.onTap});

  final ColisTrackedParcel parcel;
  final VoidCallback onTap;

  Future<void> _copyReference(BuildContext context) async {
    final confirmation = context.l10n.parcelsReferenceCopied;
    await Clipboard.setData(ClipboardData(text: parcel.reference));
    if (context.mounted) KoraToaster.show(context, confirmation);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final collected = parcel.status == ColisTrackingStatus.collected;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              if (collected)
                KoraTag(
                  label: l10n.colisDelivered,
                  variant: KoraTagVariant.success,
                )
              else
                ColisStatusChip(status: parcel.status),
              const Spacer(),
              // La référence et sa copie font un seul bouton : on ne lit
              // ce numéro que pour le redonner ailleurs.
              InkWell(
                onTap: () => _copyReference(context),
                borderRadius: BorderRadius.circular(AppRadius.base),
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 4,
                    vertical: 3,
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        parcel.reference,
                        style: TextStyle(
                          color: koraColors.textMuted,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          fontFeatures: const [FontFeature.tabularFigures()],
                        ),
                      ),
                      const SizedBox(width: 5),
                      Icon(
                        Icons.content_copy_rounded,
                        size: 14,
                        color: koraColors.textMuted,
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _RouteStrip(parcel: parcel, collected: collected),
          const SizedBox(height: 12),
          if (collected)
            // Un colis remis n'a plus d'avancement : il ne reste que la
            // date du retrait, et le reçu qu'on garde.
            Container(
              padding: const EdgeInsets.only(top: 11),
              decoration: BoxDecoration(
                border: Border(top: BorderSide(color: koraColors.line)),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.event_available_rounded,
                    size: 16,
                    color: AppColors.success,
                  ),
                  const SizedBox(width: 7),
                  Expanded(
                    child: Text(
                      l10n.colisCollectedOn(parcel.collectedOn ?? ''),
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ),
                  InkWell(
                    onTap: () => KoraToaster.show(context, l10n.colisReceipt),
                    borderRadius: BorderRadius.circular(AppRadius.base),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 4,
                        vertical: 2,
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            l10n.colisReceipt,
                            style: TextStyle(
                              color: context.koraTitleColor,
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(width: 5),
                          Icon(
                            Icons.download_rounded,
                            size: 15,
                            color: context.koraTitleColor,
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            )
          else ...[
            _ProgressStrip(parcel: parcel),
            const SizedBox(height: 12),
            Row(
              children: [
                // Les initiales sur fond doré clair : c'est au
                // destinataire qu'on pense en cherchant un envoi.
                Container(
                  width: 32,
                  height: 32,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: AppColors.gold.withValues(alpha: 0.3),
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    _initials(parcel.recipientName),
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        parcel.recipientName,
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 1),
                      Text(
                        l10n.colisPickupCodeSent,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                    ],
                  ),
                ),
                // Le cadenas dit que le code ne passe pas par ici.
                Icon(
                  Icons.lock_person_outlined,
                  size: 18,
                  color: koraColors.textMuted,
                ),
              ],
            ),
            const SizedBox(height: 12),
            // Le geste, à droite et sur fond gris : la maquette ne lui
            // donne pas la pleine largeur, elle la réserve au doré.
            Align(
              alignment: Alignment.centerRight,
              child: Material(
                color: koraColors.background,
                borderRadius: BorderRadius.circular(AppRadius.base),
                child: InkWell(
                  onTap: onTap,
                  borderRadius: BorderRadius.circular(AppRadius.base),
                  child: Container(
                    height: 40,
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    alignment: Alignment.center,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          l10n.colisDetailsLive,
                          style: TextStyle(
                            color: context.koraTitleColor,
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Icon(
                          Icons.arrow_forward_rounded,
                          size: 17,
                          color: context.koraTitleColor,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  /// « Amina BIO » → « AB ». Deux lettres suffisent à distinguer les
  /// destinataires d'une liste courte.
  static String _initials(String name) {
    final words = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((word) => word.isNotEmpty)
        .toList();
    if (words.isEmpty) return '?';
    if (words.length == 1) return words.first.characters.first.toUpperCase();
    return (words.first.characters.first + words[1].characters.first)
        .toUpperCase();
  }
}

/// Le trajet en trois colonnes : la ville de départ, le convoi, la ville
/// d'arrivée.
///
/// Sur une seule ligne, les deux villes se perdaient dans le nom des
/// gares. La maquette les met aux deux bords, en gros, et pose le convoi
/// au milieu du filet qui les relie.
class _RouteStrip extends StatelessWidget {
  const _RouteStrip({required this.parcel, required this.collected});

  final ColisTrackedParcel parcel;
  final bool collected;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final tint = collected ? AppColors.success : AppColors.digital;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: _RouteEnd(
            city: parcel.originCity,
            hub: parcel.originHub,
            alignEnd: false,
          ),
        ),
        const SizedBox(width: 6),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                SizedBox(
                  height: 24,
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      Container(height: 2, color: koraColors.line),
                      Container(
                        width: 24,
                        height: 24,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: tint.withValues(alpha: 0.16),
                        ),
                        child: Icon(
                          collected
                              ? Icons.done_all_rounded
                              : Icons.directions_bus_rounded,
                          size: 14,
                          color: tint,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  l10n.colisLineTag(parcel.line),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: collected ? koraColors.textMuted : tint,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(width: 6),
        Expanded(
          child: _RouteEnd(
            city: parcel.destinationCity,
            hub: parcel.destinationHub,
            alignEnd: true,
          ),
        ),
      ],
    );
  }
}

/// Un bout du trajet : la ville, puis sa gare.
class _RouteEnd extends StatelessWidget {
  const _RouteEnd({
    required this.city,
    required this.hub,
    required this.alignEnd,
  });

  final String city;
  final String hub;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final align = alignEnd ? TextAlign.end : TextAlign.start;

    return Column(
      crossAxisAlignment: alignEnd
          ? CrossAxisAlignment.end
          : CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          city,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: align,
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: 17,
            height: 1.15,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          hub,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: align,
          style: AppTextStyles.caption(koraColors.textMuted),
        ),
      ],
    );
  }
}

/// Un pré-enregistrement en attente de dépôt.
///
/// Il n'a pas de référence officielle, et c'est tout le propos : le
/// guichet le retrouve par son numéro de brouillon sans refaire la
/// saisie.
class _DraftTile extends StatelessWidget {
  const _DraftTile({required this.draft});

  final ColisDraftParcel draft;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Icon(
                Icons.pending_actions_rounded,
                size: 17,
                color: koraColors.textMuted,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisDraftPending,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              Text(
                l10n.colisDraftNumber(draft.draftNumber),
                style: AppTextStyles.caption(koraColors.textMuted),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            '${draft.originCity} (${draft.originHub})'
            '  →  '
            '${draft.destinationCity} (${draft.destinationHub})',
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 13,
              height: 1.25,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          // Le devis et son explication dans un encart ambré : c'est
          // ce qui attend au guichet, pas une mention de bas de carte.
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.gold.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(AppRadius.md),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(
                  Icons.info_outline_rounded,
                  size: 17,
                  color: AppColors.gold,
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        l10n.colisIndicativeQuote(
                          l10n.colisAmount(draft.indicativeFee),
                        ),
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        l10n.colisDraftHint,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              // Annuler se dit en rouge et sans pavé : c'est une sortie,
              // pas l'action attendue de la carte. Un bouton rouge plein
              // de même taille que le marine en faisait un choix entre
              // deux gestes équivalents.
              InkWell(
                onTap: () => KoraToaster.show(context, l10n.colisDraftCancel),
                borderRadius: BorderRadius.circular(AppRadius.base),
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 9,
                  ),
                  child: Text(
                    l10n.colisDraftCancel,
                    style: const TextStyle(
                      color: AppColors.danger,
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
              const Spacer(),
              // Le geste attendu : marine plein, et le QR en doré —
              // c'est lui qu'on présente au guichet.
              Material(
                color: AppColors.kora,
                borderRadius: BorderRadius.circular(AppRadius.base),
                child: InkWell(
                  onTap: () => KoraToaster.show(context, l10n.colisDraftFinish),
                  borderRadius: BorderRadius.circular(AppRadius.base),
                  child: Container(
                    height: 44,
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    alignment: Alignment.center,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.qr_code_2_rounded,
                          size: 18,
                          color: AppColors.gold,
                        ),
                        const SizedBox(width: 7),
                        Text(
                          l10n.colisDraftFinish,
                          style: const TextStyle(
                            color: AppColors.white,
                            fontSize: 13,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// L'onglet vide : ce qu'il n'y a pas, et les deux gestes qui y
/// remédient.
///
/// Une liste vide sans explication ressemble à une panne. Celle-ci dit
/// pourquoi elle est vide et ce qu'on peut faire à la place.
class _EmptyShelf extends StatelessWidget {
  const _EmptyShelf({required this.onCreate, required this.onTrack});

  final VoidCallback onCreate;
  final VoidCallback onTrack;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Center(
      child: SingleChildScrollView(
        child: Container(
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(
            color: koraColors.surface,
            borderRadius: BorderRadius.circular(AppRadius.md),
            boxShadow: kColisCardShadow,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Le disque gris et sa loupe dorée : la maquette dit en
              // une image qu'on a cherché, pas que l'écran a échoué.
              SizedBox(
                width: 80,
                height: 80,
                child: Stack(
                  children: [
                    Container(
                      width: 80,
                      height: 80,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: koraColors.background,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.inventory_2_outlined,
                        size: 38,
                        color: context.koraTitleColor,
                      ),
                    ),
                    Positioned(
                      top: 0,
                      right: 0,
                      child: Container(
                        width: 26,
                        height: 26,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: AppColors.gold.withValues(alpha: 0.3),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          Icons.search_rounded,
                          size: 15,
                          color: context.koraTitleColor,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              Text(
                l10n.colisEmptyTitle,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 17,
                  height: 1.25,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 6),
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 290),
                child: Text(
                  l10n.colisEmptyText,
                  textAlign: TextAlign.center,
                  style: AppTextStyles.body(koraColors.textMuted),
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              KoraButton(
                label: l10n.parcelsCreateTitle,
                icon: Icons.add_rounded,
                onPressed: onCreate,
              ),
              const SizedBox(height: 8),
              // Le second geste sur fond gris : suivre un colis qu'on
              // n'a pas envoyé reste une alternative, pas l'action de
              // l'écran.
              Material(
                color: koraColors.background,
                borderRadius: BorderRadius.circular(AppRadius.md),
                child: InkWell(
                  onTap: onTrack,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  child: Container(
                    height: 48,
                    alignment: Alignment.center,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.qr_code_scanner_rounded,
                          size: 18,
                          color: context.koraTitleColor,
                        ),
                        const SizedBox(width: 7),
                        Text(
                          l10n.colisTrackParcel,
                          style: TextStyle(
                            color: context.koraTitleColor,
                            fontSize: 13.5,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Suivre un colis qu'on n'a pas envoyé, avec le code reçu par message.
class _TrackExternalSheet extends StatefulWidget {
  const _TrackExternalSheet();

  @override
  State<_TrackExternalSheet> createState() => _TrackExternalSheetState();
}

class _TrackExternalSheetState extends State<_TrackExternalSheet> {
  final _reference = TextEditingController();

  @override
  void dispose() {
    _reference.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Padding(
      padding: EdgeInsets.fromLTRB(
        18,
        10,
        18,
        18 + MediaQuery.viewInsetsOf(context).bottom,
      ),
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
          const SizedBox(height: AppSpacing.md),
          Text(
            l10n.colisTrackExternal,
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 17,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            l10n.colisTrackExternalHint,
            style: AppTextStyles.body(koraColors.textMuted),
          ),
          const SizedBox(height: AppSpacing.md),
          Text(
            l10n.colisReferenceField,
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
          const SizedBox(height: 6),
          KoraInput(
            hint: l10n.parcelsReferenceHint,
            controller: _reference,
            prefixIcon: Icons.search_rounded,
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: AppSpacing.md),
          KoraButton(
            label: l10n.colisSearchStatus,
            icon: Icons.arrow_forward_rounded,
            iconPosition: KoraButtonIconPosition.edges,
            // Un code vide ne mène nulle part : le bouton le dit en
            // restant inerte plutôt qu'en ouvrant un écran vide.
            onPressed: _reference.text.trim().isEmpty
                ? null
                : () {
                    final reference = _reference.text.trim();
                    Navigator.of(context).pop();
                    context.push(AppRouter.colisTrackingPath, extra: reference);
                  },
          ),
          const SizedBox(height: AppSpacing.sm),
        ],
      ),
    );
  }
}

/// Les trois étapes d'un envoi, et la barre qui dit où il en est.
///
/// Déposé, en route, prêt au retrait : ce sont les trois moments où le
/// voyageur a quelque chose à faire, ou à ne pas faire. Les cinq jalons
/// du détail sont trop fins pour une liste.
class _ProgressStrip extends StatelessWidget {
  const _ProgressStrip({required this.parcel});

  final ColisTrackedParcel parcel;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    // Les trois états tiennent dans les cinq jalons : le dépôt est le
    // premier, le retrait possible le quatrième.
    final dropped = parcel.step >= 1;
    final rolling = parcel.step >= 2;
    final ready = parcel.step >= 4;

    final steps = <(String, bool, bool)>[
      (l10n.parcelsStepDropped, dropped, !rolling),
      (l10n.colisMilestoneTransit, rolling, rolling && !ready),
      (l10n.colisStatusReady, ready, ready),
    ];

    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: koraColors.background,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              for (final step in steps) ...[
                Expanded(
                  child: Row(
                    children: [
                      Icon(
                        step.$2
                            ? (step.$3
                                  ? Icons.radio_button_checked
                                  : Icons.check_circle_rounded)
                            : Icons.radio_button_unchecked,
                        size: 14,
                        color: step.$3
                            ? AppColors.digital
                            : step.$2
                            ? AppColors.success
                            : koraColors.textMuted,
                      ),
                      const SizedBox(width: 5),
                      Flexible(
                        child: Text(
                          step.$1,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: step.$3
                                ? AppColors.digital
                                : koraColors.textMuted,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(AppRadius.pill),
            child: LinearProgressIndicator(
              value: parcel.step / parcel.totalSteps,
              minHeight: 6,
              backgroundColor: koraColors.line,
              valueColor: const AlwaysStoppedAnimation(AppColors.digital),
            ),
          ),
          const SizedBox(height: 8),
          // Les deux bouts de l'attente, sous la barre : à quelle heure
          // le colis est parti, à quelle heure il arrive. Hors de
          // l'encart, ils flottaient sans se rattacher à rien.
          Row(
            children: [
              Expanded(
                child: Text(
                  l10n.colisDroppedAt(parcel.droppedAtTime),
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ),
              Text(
                parcel.status == ColisTrackingStatus.readyForPickup
                    ? l10n.colisReadySince(parcel.etaTime)
                    : l10n.colisEtaShort(parcel.etaTime),
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
