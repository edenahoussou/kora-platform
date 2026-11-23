import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/brand_mini.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../data/datasources/colis_mock_data.dart';
import '../bloc/colis_draft_cubit.dart';
import '../widgets/colis_surface.dart';

/// Écran 01 de la maquette KORA Colis — l'accueil de l'onglet : suivre un
/// envoi par sa référence, en ouvrir un nouveau, et voir où en sont ceux
/// qui roulent.
///
/// Les données sont figées dans [kMockColisShipments] : le socle serveur
/// existe (ADR 0028) mais l'OpenAPI mobile ne publie encore aucun endpoint
/// `parcel`. L'écran se branchera sur une vraie source sans changer de
/// forme, comme l'a fait le parcours Bus.
class ColisPage extends StatefulWidget {
  const ColisPage({super.key});

  @override
  State<ColisPage> createState() => _ColisPageState();
}

class _ColisPageState extends State<ColisPage> {
  final _reference = TextEditingController();

  @override
  void dispose() {
    _reference.dispose();
    super.dispose();
  }

  void _soon() => KoraToaster.show(context, context.l10n.parcelsSoon);

  void _track() {
    if (_reference.text.trim().isEmpty) {
      KoraToaster.show(context, context.l10n.parcelsTrackEmpty);
      return;
    }
    _soon();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                showBack: false,
                leading: const BrandMini(),
                eyebrow: l10n.parcelsEyebrow,
                title: l10n.parcelsTitle,
                // La cloche de la maquette, et non une icône d'aide :
                // l'écran de notifications existe déjà, celui de l'aide
                // Colis non.
                action: KoraIconButton(
                  icon: Icons.notifications_none_rounded,
                  onTap: () => context.push(AppRouter.notificationsPath),
                ),
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.only(bottom: 100),
                  // La boîte de recherche remonte de 22px sur le hero
                  // (`margin: -22px 11px 0`) : elle est posée dessus, pas
                  // à la suite. Une `Column` peignant ses enfants dans
                  // l'ordre, la carte passe bien par-dessus.
                  children: [
                    const _Hero(),
                    Transform.translate(
                      offset: const Offset(0, -22),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 11),
                        child: _SearchBox(
                          controller: _reference,
                          onScan: _soon,
                          onTrack: _track,
                        ),
                      ),
                    ),
                    const SizedBox(height: 9),
                    _CreateCta(
                      onTap: () {
                        // Le brouillon repart de zéro à chaque envoi,
                        // mais garde le déposant : c'est le compte.
                        //
                        // Pris au conteneur, et non au contexte :
                        // l'accueil vit dans la coquille à onglets, hors
                        // du fournisseur qui couvre les cinq écrans du
                        // parcours.
                        getIt<ColisDraftCubit>().start(
                          senderName: 'Koffi MENSAH',
                          senderPhone: '+229 97 12 34 45',
                        );
                        context.push(AppRouter.colisRoutePath);
                      },
                    ),
                    const SizedBox(height: 18),
                    _ActiveHead(
                      count: kMockColisShipments.length,
                      onSeeAll: () =>
                          context.push(AppRouter.colisShipmentsPath),
                    ),
                    const SizedBox(height: 9),
                    for (final shipment in kMockColisShipments)
                      _ShipmentCard(
                        shipment: shipment,
                        onDetails: () => context.push(
                          AppRouter.colisTrackingPath,
                          extra: shipment.reference,
                        ),
                      ),
                    // Ce qui vaut pour tous les envois, et qu'on ne lit
                    // nulle part ailleurs : où l'on dépose, où l'on
                    // retire, et ce qu'il faut présenter.
                    const SizedBox(height: 6),
                    ColisNote(
                      icon: Icons.verified_user_outlined,
                      title: l10n.colisSecurityProtocolTitle,
                      message: l10n.colisSecurityProtocolText,
                    ),
                    // Le bouton d'assistance flotte à 84 px du bord :
                    // sans cette marge, il recouvrait cette carte une
                    // fois la liste arrivée en bout de course.
                    const SizedBox(height: 104),
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

/// `.parcel-hero` : bandeau navy, accroche, et l'orbite en fond.
class _Hero extends StatelessWidget {
  const _Hero();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      constraints: const BoxConstraints(minHeight: 178),
      clipBehavior: Clip.antiAlias,
      // Le contour passe en `foregroundDecoration` : dans une
      // `BoxDecoration`, il se peint DERRIÈRE l'enfant, et le calque du
      // halo le recouvrait entièrement — la carte se retrouvait sans
      // bords sur le fond navy du thème sombre.
      foregroundDecoration: isDark
          ? BoxDecoration(
              borderRadius: BorderRadius.circular(25),
              border: Border.all(color: koraColors.line),
            )
          : null,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(25),
        // Fond navy et halo bleu : deux couches, comme en CSS
        // (`radial-gradient(...), var(--kora)`). Les donner tous deux à la
        // même `BoxDecoration` ne les superpose pas — le gradient
        // remplacerait la couleur.
        color: AppColors.kora,
        boxShadow: [
          BoxShadow(
            color: AppColors.kora.withValues(alpha: 0.19),
            offset: const Offset(0, 20),
            blurRadius: 46,
          ),
        ],
      ),
      // Le décor — halo, orbite, biseau — est posé sur TOUTE la carte,
      // pas seulement sur sa zone de texte : le retrait est appliqué au
      // contenu, en dernier. Autrement le halo s'arrête net au bord du
      // texte et ressemble à un projecteur rectangulaire.
      child: Stack(
        children: [
          // Le corridor en fond : la carte annonce un acheminement, et
          // une image le dit avant le texte.
          //
          // À demi-transparente, pour que le navy de la carte remonte
          // au travers : à pleine opacité, le ciel et le parvis
          // passaient sous le surtitre doré et l'accroche blanche, qui
          // ne se lisaient plus. C'est le fond qui doit céder devant le
          // texte, pas l'inverse.
          Positioned.fill(
            child: Opacity(
              opacity: 0.45,
              child: Image.asset(
                'assets/images/colis_hero.jpg',
                fit: BoxFit.cover,
                alignment: Alignment.centerRight,
                // Le navy du thème reste derrière : si l'image manque,
                // la carte garde sa couleur plutôt que de virer au
                // blanc.
                errorBuilder: (context, error, stack) =>
                    const SizedBox.shrink(),
              ),
            ),
          ),
          // `radial-gradient(circle at 95% 0, rgba(0,87,184,.42),
          // transparent 43%)` : le halo bleu du coin haut droit, posé
          // par-dessus l'image pour la teinter sans l'effacer.
          const Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: RadialGradient(
                  center: Alignment(0.9, -1),
                  radius: 0.86,
                  colors: [Color(0x260057B8), Colors.transparent],
                ),
              ),
            ),
          ),
          // L'orbite et le biseau de la maquette CSS ne sont plus
          // peints : ils tenaient lieu d'illustration tant que le fond
          // était uni. Sur l'image, l'orbite se posait sur l'agence et
          // le biseau barrait les colis.
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 19, vertical: 21),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  l10n.parcelsHeroEyebrow.toUpperCase(),
                  style: AppTextStyles.eyebrow(AppColors.gold),
                ),
                const SizedBox(height: 13),
                Text(
                  l10n.parcelsHeroTitle,
                  style: const TextStyle(
                    color: AppColors.white,
                    fontSize: 24,
                    height: 1.02,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.84,
                  ),
                ),
                const SizedBox(height: 7),
                // La maquette CSS disait `max-width: 190px`, quand le
                // fond était uni. Sur l'image, la première ligne allait
                // alors mourir dans le phare du fourgon, qui commence
                // vers 162 : 145 la coupe un mot plus tôt, bien avant.
                SizedBox(
                  width: 145,
                  child: Text(
                    l10n.parcelsHeroText,
                    style: TextStyle(
                      color: AppColors.white.withValues(alpha: 0.74),
                      fontSize: 11,
                      height: 1.4,
                    ),
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

/// `.parcel-search-box` : le champ de référence, son scan et le bouton.
class _SearchBox extends StatelessWidget {
  const _SearchBox({
    required this.controller,
    required this.onScan,
    required this.onTrack,
  });

  final TextEditingController controller;
  final VoidCallback onScan;
  final VoidCallback onTrack;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        // Cette carte-ci flotte au-dessus du bandeau : elle garde son
        // ombre portée, plus marquée que celle des cartes de contenu.
        boxShadow: [
          BoxShadow(
            color: AppColors.kora.withValues(alpha: isDark ? 0.33 : 0.11),
            offset: const Offset(0, 15),
            blurRadius: 35,
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Icon(
                Icons.search_rounded,
                size: 15,
                color: context.koraTitleColor,
              ),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  l10n.parcelsReferenceLabel,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              // La maquette précise à droite ce que le champ accepte :
              // le numéro d'envoi, mais aussi celui du récépissé remis
              // au guichet. Les deux textes plient plutôt que de pousser
              // la ligne hors de l'écran sur un petit téléphone.
              Flexible(
                child: Text(
                  l10n.parcelsReferenceCaption,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.end,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ),
            ],
          ),
          const SizedBox(height: 7),
          Row(
            children: [
              Expanded(
                child: Container(
                  height: 44,
                  padding: const EdgeInsets.symmetric(horizontal: 13),
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: koraColors.background,
                    borderRadius: BorderRadius.circular(AppRadius.md),
                  ),
                  child: TextField(
                    controller: controller,
                    textCapitalization: TextCapitalization.characters,
                    style: TextStyle(
                      color: isDark ? koraColors.textPrimary : AppColors.kora,
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                    decoration: InputDecoration(
                      isDense: true,
                      border: InputBorder.none,
                      hintText: l10n.parcelsReferenceHint,
                      hintStyle: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              // Le scan est un bouton à part, carré, à côté du champ :
              // logé dedans, il rognait la saisie et se confondait avec
              // un pictogramme décoratif.
              Material(
                color: koraColors.background,
                borderRadius: BorderRadius.circular(AppRadius.md),
                child: InkWell(
                  onTap: onScan,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  child: SizedBox(
                    width: 44,
                    height: 44,
                    child: Icon(
                      Icons.qr_code_scanner_rounded,
                      size: 20,
                      color: context.koraTitleColor,
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 9),
          // Discret, et non doré : la maquette ne garde qu'une action
          // dominante par écran, et c'est « Nouvel envoi » juste en
          // dessous. Deux pavés dorés l'un sur l'autre ne laissaient
          // plus voir lequel était le geste principal.
          KoraButton(
            label: l10n.parcelsTrackCta,
            variant: KoraButtonVariant.secondary,
            icon: Icons.arrow_forward_rounded,
            onPressed: onTrack,
          ),
        ],
      ),
    );
  }
}

/// « Nouvel envoi » : la seule action dorée de l'écran.
///
/// La maquette en fait une carte pleine, pas une ligne de liste : c'est
/// l'entrée du parcours d'envoi, et elle doit se voir avant les envois
/// déjà partis.
class _CreateCta extends StatelessWidget {
  const _CreateCta({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    // La carte est dorée dans les deux thèmes : le navy du texte se lit
    // sur l'or, de jour comme de nuit, et c'est la seule action de
    // l'écran qui doit sauter aux yeux.
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(15),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(15),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          child: Row(
            children: [
              Container(
                width: 32,
                height: 32,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.kora,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(
                  Icons.add_rounded,
                  size: 19,
                  color: AppColors.white,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.parcelsCreateTitle,
                      style: const TextStyle(
                        color: AppColors.kora,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      l10n.parcelsCreateSubtitle,
                      style: TextStyle(
                        color: AppColors.kora.withValues(alpha: 0.72),
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Container(
                width: 24,
                height: 24,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.kora.withValues(alpha: 0.12),
                ),
                child: const Icon(
                  Icons.chevron_right_rounded,
                  size: 17,
                  color: AppColors.kora,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Le titre de la section des envois, et le lien vers la liste.
///
/// Le compte est dans le titre : « Envois en cours (1) » dit d'un coup
/// qu'il y a quelque chose à surveiller, là où un surtitre « Activité
/// récente » laissait la question ouverte.
class _ActiveHead extends StatelessWidget {
  const _ActiveHead({required this.count, required this.onSeeAll});

  final int count;
  final VoidCallback onSeeAll;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Flexible(
          child: Text(
            l10n.parcelsActiveTitle(count),
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 15,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        TextButton(
          onPressed: onSeeAll,
          style: TextButton.styleFrom(
            padding: EdgeInsets.zero,
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
          child: Text(l10n.parcelsSeeAll),
        ),
      ],
    );
  }
}

/// Un envoi en cours : son état, son trajet, et où il en est.
///
/// Les trois étapes ne sont pas décoratives — elles portent la promesse
/// du module : le voyageur sait, sans appeler personne, que son colis est
/// parti et quand il sera retirable.
class _ShipmentCard extends StatelessWidget {
  const _ShipmentCard({required this.shipment, required this.onDetails});

  final ColisShipment shipment;
  final VoidCallback onDetails;

  Future<void> _copyReference(BuildContext context) async {
    final confirmation = context.l10n.parcelsReferenceCopied;
    await Clipboard.setData(ClipboardData(text: shipment.reference));
    if (context.mounted) KoraToaster.show(context, confirmation);
  }

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
              const _StatusChip(),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  shipment.reference,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.end,
                  style: TextStyle(
                    color: koraColors.textMuted,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    // Une référence se relit caractère par caractère quand
                    // on la dicte au guichet.
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                ),
              ),
              const SizedBox(width: 4),
              // Dictée au téléphone, elle se trompe d'un chiffre ; copiée,
              // elle part juste.
              InkWell(
                onTap: () => _copyReference(context),
                borderRadius: BorderRadius.circular(6),
                child: Padding(
                  padding: const EdgeInsets.all(3),
                  child: Icon(
                    Icons.copy_rounded,
                    size: 14,
                    color: koraColors.textMuted,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 30,
                height: 30,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: koraColors.background,
                  borderRadius: BorderRadius.circular(9),
                ),
                child: Icon(
                  Icons.inventory_2_outlined,
                  size: 16,
                  color: context.koraTitleColor,
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      '${shipment.originCity} (${shipment.originHub})'
                      '  →  '
                      '${shipment.destinationCity} '
                      '(${shipment.destinationHub})',
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 13,
                        height: 1.25,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      l10n.parcelsLine(shipment.line),
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _Timeline(shipment: shipment),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _InfoTile(
                  label: l10n.parcelsLastUpdate,
                  value: l10n.parcelsLastUpdateValue(shipment.lastUpdateTime),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _InfoTile(
                  label: l10n.parcelsEta,
                  value: l10n.parcelsEtaValue(shipment.etaTime),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          // Le code de retrait est le seul secret du parcours : il ne
          // passe pas par l'app, et qui envoie doit le savoir avant de
          // chercher où le lire.
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.sms_outlined, size: 14, color: koraColors.textMuted),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  l10n.parcelsWithdrawalNote,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          KoraButton(
            label: l10n.parcelsDetailsCta,
            variant: KoraButtonVariant.secondary,
            icon: Icons.arrow_forward_rounded,
            iconPosition: KoraButtonIconPosition.edges,
            onPressed: onDetails,
          ),
        ],
      ),
    );
  }
}

/// La pastille d'état, verte tant que le colis avance.
class _StatusChip extends StatelessWidget {
  const _StatusChip();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: AppColors.success.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(AppRadius.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.success,
            ),
          ),
          const SizedBox(width: 6),
          Text(
            context.l10n.parcelsStatusTransit.toUpperCase(),
            style: AppTextStyles.eyebrow(AppColors.success),
          ),
        ],
      ),
    );
  }
}

/// Les trois étapes de l'acheminement : dépôt, route, hub d'arrivée.
class _Timeline extends StatelessWidget {
  const _Timeline({required this.shipment});

  final ColisShipment shipment;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    final labels = [
      l10n.parcelsStepDropped,
      l10n.parcelsStepTransit,
      l10n.parcelsStepHub(shipment.steps.last.hubCity ?? ''),
    ];
    final captions = [
      shipment.steps[0].time,
      l10n.parcelsStepDeparted(shipment.steps[1].time),
      l10n.parcelsStepPlanned(shipment.steps[2].time),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        // Les pastilles et leurs liaisons, sur une seule ligne : les
        // libellés dessous suivent la même répartition.
        SizedBox(
          height: 18,
          child: Row(
            children: [
              for (final (index, step) in shipment.steps.indexed) ...[
                if (index > 0)
                  Expanded(
                    child: Container(
                      height: 2,
                      color: step.state == ColisStepState.planned
                          ? koraColors.line
                          : AppColors.kora,
                    ),
                  ),
                _StepDot(state: step.state),
              ],
            ],
          ),
        ),
        const SizedBox(height: 6),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (final (index, step) in shipment.steps.indexed)
              Expanded(
                child: Column(
                  crossAxisAlignment: index == 0
                      ? CrossAxisAlignment.start
                      : (index == shipment.steps.length - 1
                            ? CrossAxisAlignment.end
                            : CrossAxisAlignment.center),
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      labels[index],
                      style: TextStyle(
                        color: step.state == ColisStepState.current
                            ? AppColors.gold
                            : (step.state == ColisStepState.planned
                                  ? koraColors.textMuted
                                  : context.koraTitleColor),
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      captions[index],
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ],
    );
  }
}

/// La pastille d'une étape : pleine et cochée derrière, dorée au présent,
/// creuse devant.
class _StepDot extends StatelessWidget {
  const _StepDot({required this.state});

  final ColisStepState state;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return switch (state) {
      ColisStepState.done => Container(
        width: 18,
        height: 18,
        alignment: Alignment.center,
        decoration: const BoxDecoration(
          shape: BoxShape.circle,
          color: AppColors.kora,
        ),
        child: const Icon(Icons.check_rounded, size: 11, color: Colors.white),
      ),
      ColisStepState.current => Container(
        width: 18,
        height: 18,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: AppColors.gold,
          border: Border.all(
            color: AppColors.gold.withValues(alpha: 0.28),
            width: 3,
          ),
        ),
      ),
      ColisStepState.planned => Container(
        width: 18,
        height: 18,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: koraColors.surface,
          border: Border.all(color: koraColors.line, width: 2),
        ),
      ),
    };
  }
}

/// Une tuile d'information sous la frise : un intitulé, une valeur.
class _InfoTile extends StatelessWidget {
  const _InfoTile({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(
        color: koraColors.background,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(label, style: AppTextStyles.caption(koraColors.textMuted)),
          const SizedBox(height: 3),
          Text(
            value,
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}
