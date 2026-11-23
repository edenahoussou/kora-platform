import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/brand_mini.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../bloc/colis_draft_cubit.dart';
import '../widgets/colis_surface.dart';

/// Écran 06 — « Pré-enregistrement validé ».
///
/// Le dossier existe côté escale, et **rien d'autre**. Pas de référence
/// officielle, pas de place réservée en soute, pas de prix ferme : trois
/// absences que l'écran énonce, parce qu'un voyageur qui croit avoir payé
/// se présente au guichet sans son argent.
class ColisPreregisteredPage extends StatelessWidget {
  const ColisPreregisteredPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return BlocBuilder<ColisDraftCubit, ColisDraft>(
      builder: (context, draft) {
        final origin = draft.originHub;
        final destination = draft.destinationHub;
        final (low, high) = draft.indicativeRange;

        return Scaffold(
          body: SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ScreenHeader(
                    leading: const BrandMini(),
                    eyebrow: l10n.parcelsEyebrow,
                    title: l10n.parcelsTitle,
                  ),
                  Expanded(
                    child: ListView(
                      padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                      children: [
                        _ValidatedBanner(hubCounter: origin?.counter ?? ''),
                        const SizedBox(height: AppSpacing.md),
                        _LinkCard(
                          origin: origin?.city ?? '',
                          originHub: origin?.name ?? '',
                          destination: destination?.city ?? '',
                          destinationHub: destination?.name ?? '',
                          weight: l10n.colisWeightDeclared(
                            l10n.colisWeightWithUnit(
                              colisWeightText(draft.declaredWeightKg),
                            ),
                          ),
                          nature: draft.content,
                          range: l10n.colisRange(
                            l10n.colisAmount(low),
                            l10n.colisAmount(high),
                          ),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        // Les quatre étapes dans une seule carte, comme
                        // la maquette : séparées, elles se lisaient
                        // comme quatre annonces sans lien entre elles.
                        _ProtocolCard(counter: origin?.counter ?? ''),
                        const SizedBox(height: AppSpacing.sm),
                        // Ce qu'on refuse au guichet, dit avant le
                        // déplacement : sans pièce d'identité, le
                        // voyage jusqu'à la gare est perdu.
                        _Compliance(),
                        const SizedBox(height: AppSpacing.sm),
                        // Les heures du comptoir, et comment le joindre.
                        // « Attendu au guichet » ne vaut rien sans
                        // savoir jusqu'à quelle heure.
                        if (draft.originHub case final hub?)
                          _CounterCard(hub: hub),
                        // Le pied défile avec le contenu : épinglé en
                        // bas, il recouvrait la fin de la liste.
                        const SizedBox(height: AppSpacing.md),
                        // Le guichet pèse, puis demande le règlement :
                        // c'est l'étape suivante du parcours.
                        KoraButton(
                          label: l10n.colisPreregisteredCta,
                          icon: Icons.arrow_forward_rounded,
                          iconPosition: KoraButtonIconPosition.edges,
                          // Le guichet pèse, puis le devis s'ouvre.
                          onPressed: () =>
                              context.push(AppRouter.colisQuotePath),
                        ),
                        const SizedBox(height: 10),
                        // Comment s'y rendre : la maquette en fait le
                        // second bouton de l'écran, parce que c'est le
                        // geste qui suit vraiment un pré-enregistrement.
                        ColisFlatAction(
                          label: l10n.colisCounterDirections,
                          icon: Icons.navigation_outlined,
                          onTap: () => KoraToaster.show(
                            context,
                            l10n.colisCounterDirections,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Center(
                          child: InkWell(
                            onTap: () => context.go('/colis'),
                            borderRadius: BorderRadius.circular(AppRadius.pill),
                            child: Padding(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 9,
                              ),
                              child: Text(
                                l10n.colisBackToParcels,
                                style: TextStyle(
                                  color: koraColors.textMuted,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

/// Le bandeau de confirmation : ce qui est acquis, et ce qui ne l'est pas.
class _ValidatedBanner extends StatelessWidget {
  const _ValidatedBanner({required this.hubCounter});

  final String hubCounter;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Le filet dégradé de la maquette : marine, doré, marine. Il
          // signe la carte sans rien dire de plus — et c'est à ça qu'on
          // reconnaît un écran de confirmation KORA.
          Container(
            height: 6,
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [AppColors.kora, AppColors.gold, AppColors.kora],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 16),
            child: _BannerBody(hubCounter: hubCounter),
          ),
        ],
      ),
    );
  }
}

/// Le corps du bandeau : la coche, l'état, le titre, et ce qui n'existe
/// pas encore.
class _BannerBody extends StatelessWidget {
  const _BannerBody({required this.hubCounter});

  final String hubCounter;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        // Le disque doré et la pastille : la maquette annonce le
        // succès au centre, sur fond clair. Le bandeau marine en
        // faisait un écran de confirmation définitive, alors que
        // rien n'est encore enregistré au guichet.
        Center(
          child: Container(
            width: 56,
            height: 56,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.gold.withValues(alpha: 0.22),
            ),
            child: const Icon(
              Icons.task_alt_rounded,
              size: 30,
              color: AppColors.gold,
            ),
          ),
        ),
        const SizedBox(height: 12),
        Center(
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: AppColors.gold.withValues(alpha: 0.22),
              borderRadius: BorderRadius.circular(AppRadius.pill),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 7,
                  height: 7,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.gold,
                  ),
                ),
                const SizedBox(width: 7),
                Text(
                  l10n.colisToDropOff,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 10),
        Text(
          l10n.colisPreregisteredTitle,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: 19,
            height: 1.2,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 6),
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 320),
            child: Text(
              l10n.colisPreregisteredText,
              textAlign: TextAlign.center,
              style: AppTextStyles.caption(koraColors.textMuted),
            ),
          ),
        ),
        // L'avertissement central du ticket, dans le bandeau même :
        // la référence n'existe pas encore, et c'est la première
        // chose à lire après « validé ».
        const SizedBox(height: 14),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: colisInsetDecoration(context),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(
                Icons.info_outline_rounded,
                size: 19,
                color: AppColors.gold,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      l10n.colisNoReferenceTitle,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      l10n.colisNoReferenceText,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        if (hubCounter.isNotEmpty) ...[
          const SizedBox(height: 10),
          Center(
            child: Text(
              hubCounter,
              style: AppTextStyles.caption(koraColors.textMuted),
            ),
          ),
        ],
      ],
    );
  }
}

/// La liaison interurbaine : les deux hubs, le poids déclaré, la
/// fourchette.
class _LinkCard extends StatelessWidget {
  const _LinkCard({
    required this.origin,
    required this.originHub,
    required this.destination,
    required this.destinationHub,
    required this.weight,
    required this.nature,
    required this.range,
  });

  final String origin;
  final String originHub;
  final String destination;
  final String destinationHub;
  final String weight;
  final String nature;
  final String range;

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
                Icons.local_shipping_outlined,
                size: 17,
                color: koraColors.textMuted,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisLinkTitle,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              KoraTag(
                label: l10n.colisDirectDeparture,
                variant: KoraTagVariant.outline,
              ),
            ],
          ),
          // Le filet sous l'en-tête : la maquette sépare le titre de la
          // carte de son contenu, et c'est ce trait qui fait la fiche.
          const SizedBox(height: 11),
          Divider(height: 1, color: koraColors.line),
          const SizedBox(height: 13),
          _Point(
            label: l10n.colisSenderHub,
            city: origin,
            hub: originHub,
            color: AppColors.kora,
          ),
          // Le filet vertical qui relie les deux hubs : sans lui, deux
          // puces de couleurs différentes ne font pas un trajet.
          Row(
            children: [
              SizedBox(
                width: 8,
                child: Center(
                  child: Container(
                    width: 2,
                    height: 18,
                    color: koraColors.line,
                  ),
                ),
              ),
            ],
          ),
          _Point(
            label: l10n.colisRecipientHub,
            city: destination,
            hub: destinationHub,
            color: AppColors.gold,
          ),
          const SizedBox(height: AppSpacing.md),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _Box(
                  label: l10n.colisNatureWeight,
                  value: weight,
                  caption: nature,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _Box(
                  label: l10n.colisIndicativeFare,
                  value: range,
                  caption: l10n.colisFinalAtCounter,
                ),
              ),
            ],
          ),
          // Qui décide du départ, et quand. Un pré-enregistrement ne
          // réserve pas de place dans un car : la rotation se règle au
          // guichet, une fois le colis entre les mains de l'agent.
          const SizedBox(height: 11),
          Container(
            padding: const EdgeInsets.all(11),
            decoration: colisInsetDecoration(context),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.alt_route_rounded,
                  size: 16,
                  color: koraColors.textMuted,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    l10n.colisRotationNote,
                    style: AppTextStyles.caption(koraColors.textMuted),
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

class _Point extends StatelessWidget {
  const _Point({
    required this.label,
    required this.city,
    required this.hub,
    required this.color,
  });

  final String label;
  final String city;
  final String hub;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(top: 5),
          child: Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(shape: BoxShape.circle, color: color),
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
              const SizedBox(height: 2),
              Text(
                city,
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 15,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(hub, style: AppTextStyles.caption(koraColors.textMuted)),
            ],
          ),
        ),
      ],
    );
  }
}

class _Box extends StatelessWidget {
  const _Box({required this.label, required this.value, required this.caption});

  final String label;
  final String value;
  final String caption;

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
              fontSize: 14,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            caption,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
        ],
      ),
    );
  }
}

/// Une étape du protocole de prise en charge.
class _ProtocolStep extends StatelessWidget {
  const _ProtocolStep({
    required this.rank,
    required this.title,
    required this.text,
  });

  final int rank;
  final String title;
  final String text;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 24,
          height: 24,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            // Bleu pâle et non doré : le doré dit « à faire
            // maintenant », et ces quatre étapes se feront au guichet.
            color: AppColors.digital.withValues(alpha: 0.14),
          ),
          child: Text(
            '$rank',
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
                title,
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(text, style: AppTextStyles.caption(koraColors.textMuted)),
            ],
          ),
        ),
      ],
    );
  }
}

/// Les rappels de conformité KORA : ce qui fait refuser un dépôt.
class _Compliance extends StatelessWidget {
  const _Compliance();

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
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Icon(
                Icons.verified_user_outlined,
                size: 17,
                color: context.koraTitleColor,
              ),
              const SizedBox(width: 8),
              // `Expanded` : « Rappels de conformité KORA » dépasse la
              // largeur d'un téléphone étroit, et une ligne de titre
              // n'a pas à déborder pour autant.
              Expanded(
                child: Text(
                  l10n.colisComplianceTitle,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          for (final rule in [
            (Icons.badge_outlined, l10n.colisCompliance1),
            (Icons.lock_outline_rounded, l10n.colisCompliance2),
          ]) ...[
            // Pictogramme vert et texte plein : ce sont des règles
            // tenues, pas des notes de bas de page. En gris sur gris,
            // elles se lisaient comme un avertissement de plus.
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.only(top: 1),
                  child: Icon(rule.$1, size: 17, color: AppColors.success),
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    rule.$2,
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 12.5,
                      height: 1.4,
                    ),
                  ),
                ),
              ],
            ),
            if (rule.$2 != l10n.colisCompliance2) const SizedBox(height: 8),
          ],
        ],
      ),
    );
  }
}

/// Le comptoir où le colis est attendu : ses heures et ses lignes.
///
/// « Votre colis est attendu au guichet » ne dit pas jusqu'à quelle
/// heure, ni à quel numéro appeler quand on arrive en retard. C'est la
/// première chose qu'on cherche après avoir lu l'écran.
class _CounterCard extends StatelessWidget {
  const _CounterCard({required this.hub});

  final ColisHub hub;

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
                Icons.storefront_outlined,
                size: 17,
                color: context.koraTitleColor,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  hub.counter,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              KoraTag(
                label: l10n.colisCounterOpen,
                variant: KoraTagVariant.success,
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(hub.address, style: AppTextStyles.caption(koraColors.textMuted)),
          const SizedBox(height: 6),
          Text(
            l10n.colisCounterHours(hub.weekdayHours),
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
          const SizedBox(height: 12),
          // Deux tuiles grises, comme la maquette : appeler et écrire
          // sont deux manières de joindre le même comptoir, pas deux
          // actions à mettre en avant. Les instructions du hub, elles,
          // ont rejoint le bas de l'écran.
          Row(
            children: [
              Expanded(
                child: ColisFlatAction(
                  label: hub.phone,
                  icon: Icons.phone_outlined,
                  background: koraColors.background,
                  height: 46,
                  onTap: () => KoraToaster.show(context, l10n.colisCounterCall),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ColisFlatAction(
                  label: l10n.colisCounterWhatsapp,
                  icon: Icons.chat_outlined,
                  iconTint: AppColors.success,
                  background: koraColors.background,
                  height: 46,
                  onTap: () =>
                      KoraToaster.show(context, l10n.colisCounterWhatsapp),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Le protocole de prise en charge : les quatre étapes du guichet.
///
/// Une seule carte, en-tête compris : la maquette compte les étapes à
/// droite du titre, pour qu'on sache d'emblée combien il en reste.
class _ProtocolCard extends StatelessWidget {
  const _ProtocolCard({required this.counter});

  final String counter;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    final steps = <(String, String)>[
      (l10n.colisProtocolStep1Title, l10n.colisProtocolStep1Text(counter)),
      (l10n.colisProtocolStep2Title, l10n.colisProtocolStep2Text),
      (l10n.colisProtocolStep3Title, l10n.colisProtocolStep3Text),
      (l10n.colisProtocolStep4Title, l10n.colisProtocolStep4Text),
    ];

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
              Expanded(
                child: Text(
                  l10n.colisProtocolTitle.toUpperCase(),
                  style: AppTextStyles.eyebrow(context.koraTitleColor),
                ),
              ),
              const SizedBox(width: 8),
              Text(
                l10n.colisProtocolSteps(steps.length),
                style: AppTextStyles.caption(koraColors.textMuted),
              ),
            ],
          ),
          const SizedBox(height: 13),
          for (final (index, step) in steps.indexed) ...[
            _ProtocolStep(rank: index + 1, title: step.$1, text: step.$2),
            if (index != steps.length - 1) const SizedBox(height: 12),
          ],
        ],
      ),
    );
  }
}
