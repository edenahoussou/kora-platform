import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/widgets/brand_mini.dart';
import '../../../../core/widgets/screen_header.dart';

/// Écran `parcel-search` de la maquette — l'onglet « Colis » : suivre un
/// envoi par sa référence, en créer un, et retrouver les envois en cours.
///
/// Les données sont figées ici : la feature `parcel` n'existe pas encore
/// (aucun domaine, aucun repository). L'écran est complet visuellement et
/// se branchera sur une vraie source sans changer de forme, comme l'a fait
/// le parcours Bus.
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
                action: KoraIconButton(
                  icon: Icons.help_outline_rounded,
                  onTap: () => KoraToaster.show(context, l10n.parcelsHelp),
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
                    _CreateCta(onTap: _soon),
                    const SizedBox(height: 18),
                    _SectionHead(
                      eyebrow: l10n.parcelsRecentEyebrow,
                      title: l10n.parcelsRecentTitle,
                      actionLabel: l10n.actionSeeAll,
                      onAction: _soon,
                    ),
                    const SizedBox(height: 9),
                    _ActiveParcelCard(onTap: _soon),
                    const SizedBox(height: 9),
                    _HelpLink(onTap: _soon),
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
          // `radial-gradient(circle at 95% 0, rgba(0,87,184,.42),
          // transparent 43%)` : le halo bleu du coin haut droit.
          const Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: RadialGradient(
                  center: Alignment(0.9, -1),
                  radius: 0.86,
                  colors: [Color(0x6B0057B8), Colors.transparent],
                ),
              ),
            ),
          ),
          // `.parcel-route-art { right: -12px; top: 24px }`
          const Positioned(right: -12, top: 24, child: _RouteArt()),
          // `.parcel-hero::after` : le biseau doré.
          Positioned(
            right: -75,
            bottom: 37,
            child: Transform.rotate(
              angle: -27 * 3.1415926535 / 180,
              child: Container(
                width: 230,
                height: 5,
                decoration: BoxDecoration(
                  color: AppColors.gold,
                  borderRadius: BorderRadius.circular(AppRadius.pill),
                ),
              ),
            ),
          ),
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
                // `max-width: 190px` : le texte passe à la ligne plutôt
                // que de courir sous l'orbite, à droite de la carte.
                SizedBox(
                  width: 190,
                  child: Text(
                    l10n.parcelsHeroText,
                    style: TextStyle(
                      color: AppColors.white.withValues(alpha: 0.62),
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

/// `.parcel-route-art` : trois cercles concentriques, trois escales et le
/// K en filigrane.
class _RouteArt extends StatelessWidget {
  const _RouteArt();

  @override
  Widget build(BuildContext context) {
    Widget ring(double inset, double alpha) => Positioned.fill(
      child: Padding(
        padding: EdgeInsets.all(inset),
        child: DecoratedBox(
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.white.withValues(alpha: alpha)),
          ),
        ),
      ),
    );

    Widget stop({required Color border}) => Container(
      width: 7,
      height: 7,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.kora,
        border: Border.all(color: border, width: 2),
      ),
    );

    return SizedBox(
      width: 118,
      height: 118,
      child: Stack(
        children: [
          ring(0, 0.1),
          ring(17, 0.08),
          ring(38, 0.08),
          // `.parcel-route-art b { left: 48px; top: 47px; font-size: 37px }`
          // — le K n'est pas centré sur les cercles : il est posé plus
          // bas et déborde du petit cercle central. Pas de `height`
          // forcée : la hauteur de ligne par défaut reproduit celle du
          // navigateur, dont ces coordonnées dépendent.
          Positioned(
            left: 48,
            top: 47,
            child: Text(
              'K',
              style: TextStyle(
                color: AppColors.white.withValues(alpha: 0.12),
                fontSize: 37,
                fontStyle: FontStyle.italic,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Positioned(
            left: 14,
            bottom: 27,
            child: stop(border: AppColors.white),
          ),
          Positioned(left: 48, top: 18, child: stop(border: AppColors.white)),
          Positioned(
            right: 12,
            bottom: 35,
            child: stop(border: AppColors.gold),
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
        borderRadius: BorderRadius.circular(19),
        border: Border.all(color: koraColors.line),
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
          Text(
            l10n.parcelsReferenceLabel.toUpperCase(),
            style: AppTextStyles.eyebrow(koraColors.textMuted),
          ),
          const SizedBox(height: 7),
          Container(
            height: 44,
            padding: const EdgeInsets.symmetric(horizontal: 7),
            decoration: BoxDecoration(
              color: koraColors.background,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: koraColors.line),
            ),
            child: Row(
              children: [
                const _ScanIcon(),
                const SizedBox(width: 6),
                Expanded(
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
                const SizedBox(width: 4),
                // `.parcel-search-box label button` : le carré doré du
                // scan, dans le champ lui-même.
                Material(
                  color: AppColors.gold.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(8),
                  child: InkWell(
                    onTap: onScan,
                    borderRadius: BorderRadius.circular(8),
                    child: SizedBox(
                      width: 28,
                      height: 28,
                      child: Icon(
                        Icons.qr_code_scanner_rounded,
                        size: 16,
                        color: isDark ? koraColors.textPrimary : AppColors.kora,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 9),
          KoraButton(
            label: l10n.parcelsTrackCta,
            icon: Icons.arrow_forward_rounded,
            iconPosition: KoraButtonIconPosition.edges,
            onPressed: onTrack,
          ),
        ],
      ),
    );
  }
}

/// `.parcel-scan-icon` : le petit rectangle avec sa ligne de scan dorée.
class _ScanIcon extends StatelessWidget {
  const _ScanIcon();

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final border = isDark ? koraColors.textPrimary : AppColors.kora;

    return Container(
      width: 19,
      height: 17,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(3),
        border: Border.all(color: border.withValues(alpha: 0.72), width: 1.5),
      ),
      child: Container(width: 11, height: 1, color: AppColors.gold),
    );
  }
}

/// `.parcel-create-cta` : la ligne « Envoyer un nouveau colis », avec son
/// carré doré.
///
/// Volontairement pas un [KoraMenuList] d'un seul élément : la maquette
/// lui donne un pictogramme **doré** (`background: var(--gold)`) là où une
/// liste de menu porte un carré neutre.
class _CreateCta extends StatelessWidget {
  const _CreateCta({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final strong = isDark ? koraColors.textPrimary : AppColors.kora;

    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(15),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(15),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(15),
            border: Border.all(color: koraColors.line),
          ),
          child: Row(
            children: [
              Container(
                width: 32,
                height: 32,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.gold,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(
                  Icons.add_rounded,
                  size: 19,
                  color: AppColors.kora,
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.parcelsCreateTitle,
                      style: TextStyle(
                        color: strong,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      l10n.parcelsCreateSubtitle,
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Icon(
                Icons.chevron_right_rounded,
                size: 18,
                color: koraColors.textMuted,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.parcel-section-head` : surtitre, titre, et le lien à droite.
class _SectionHead extends StatelessWidget {
  const _SectionHead({
    required this.eyebrow,
    required this.title,
    required this.actionLabel,
    required this.onAction,
  });

  final String eyebrow;
  final String title;
  final String actionLabel;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              eyebrow.toUpperCase(),
              style: AppTextStyles.eyebrow(koraColors.textMuted),
            ),
            const SizedBox(height: 2),
            Text(
              title,
              style: TextStyle(
                color: isDark ? koraColors.textPrimary : AppColors.kora,
                fontSize: 15,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
        TextButton(
          onPressed: onAction,
          style: TextButton.styleFrom(
            padding: EdgeInsets.zero,
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
          child: Text(actionLabel),
        ),
      ],
    );
  }
}

/// `.parcel-active-card` : l'envoi en cours, avec sa pastille qui bat.
class _ActiveParcelCard extends StatelessWidget {
  const _ActiveParcelCard({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final strong = isDark ? koraColors.textPrimary : AppColors.kora;

    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(19),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(19),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(19),
            border: Border.all(color: koraColors.line),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Container(
                    width: 7,
                    height: 7,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: AppColors.gold,
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.gold.withValues(alpha: 0.15),
                          spreadRadius: 4,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    l10n.parcelsStatusTransit.toUpperCase(),
                    style: AppTextStyles.eyebrow(AppColors.digital),
                  ),
                ],
              ),
              const SizedBox(height: 13),
              Row(
                children: [
                  Expanded(
                    child: _RoutePoint(
                      label: l10n.parcelsFrom,
                      city: 'Cotonou',
                      strong: strong,
                    ),
                  ),
                  SizedBox(
                    width: 56,
                    child: Row(
                      children: [
                        _Dot(color: strong),
                        Expanded(
                          child: Container(height: 1, color: koraColors.line),
                        ),
                        const _Dot(color: AppColors.gold),
                      ],
                    ),
                  ),
                  Expanded(
                    child: _RoutePoint(
                      label: l10n.parcelsTo,
                      city: 'Parakou',
                      strong: strong,
                      alignEnd: true,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 13),
              Divider(height: 1, color: koraColors.line),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'KC-…40982',
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 11,
                      ),
                    ),
                  ),
                  Text(
                    '${l10n.relativeDays(0)} · 16:30',
                    style: TextStyle(
                      color: strong,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(width: 8),
                  const Icon(
                    Icons.chevron_right_rounded,
                    size: 18,
                    color: AppColors.digital,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RoutePoint extends StatelessWidget {
  const _RoutePoint({
    required this.label,
    required this.city,
    required this.strong,
    this.alignEnd = false,
  });

  final String label;
  final String city;
  final Color strong;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: alignEnd
          ? CrossAxisAlignment.end
          : CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          label,
          style: TextStyle(color: context.koraColors.textMuted, fontSize: 10),
        ),
        const SizedBox(height: 2),
        Text(
          city,
          style: TextStyle(
            color: strong,
            fontSize: 13,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _Dot extends StatelessWidget {
  const _Dot({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 7,
      height: 7,
      decoration: BoxDecoration(shape: BoxShape.circle, color: color),
    );
  }
}

/// `.parcel-help-link` : le renvoi vers l'aide quand une référence ne
/// donne rien.
class _HelpLink extends StatelessWidget {
  const _HelpLink({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 9),
          child: Row(
            children: [
              Container(
                width: 25,
                height: 25,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: koraColors.background,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  '?',
                  style: TextStyle(
                    color: isDark ? koraColors.textPrimary : AppColors.kora,
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.parcelsHelpTitle,
                      style: TextStyle(
                        color: isDark ? koraColors.textPrimary : AppColors.kora,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      l10n.parcelsHelpSubtitle,
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(
                Icons.chevron_right_rounded,
                size: 18,
                color: koraColors.textMuted,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
