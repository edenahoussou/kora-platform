import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../bloc/colis_draft_cubit.dart';
import '../widgets/colis_step_scaffold.dart';
import '../widgets/colis_surface.dart';

/// Ouvre l'étape [step] en annonçant qu'on y vient pour corriger.
///
/// Le drapeau posé sur le brouillon fait que « Continuer » ramènera ici
/// au lieu de relancer le parcours à la suite — on ne refait pas trois
/// écrans pour un numéro mal saisi.
void _edit(BuildContext context, String step) {
  context.read<ColisDraftCubit>().editFromSummary();
  context.go(step);
}

/// Écran 05 — le récapitulatif avant pré-enregistrement.
///
/// Chaque bloc porte son « Modifier » : le ticket demande qu'on puisse
/// revenir corriger une étape sans refaire le parcours, et c'est ici que
/// le besoin se voit le mieux — on relit, donc on se corrige.
///
/// **Aucun prix ferme n'est annoncé.** Le total dépend du poids réel, que
/// seule la balance du hub donnera : l'écran s'en tient à une fourchette.
class ColisSummaryPage extends StatelessWidget {
  const ColisSummaryPage({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return BlocBuilder<ColisDraftCubit, ColisDraft>(
      builder: (context, draft) {
        final origin = draft.originHub;
        final destination = draft.destinationHub;
        final (low, high) = draft.indicativeRange;

        return ColisStepScaffold(
          step: 4,
          stepLabel: l10n.colisStepSummary,
          title: l10n.colisSummaryTitle,
          subtitle: l10n.colisSummarySubtitle,
          footer: ColisStepFooter(
            label: l10n.colisSummaryCta,
            hint: l10n.colisSummaryHint,
            onContinue: () {
              context.read<ColisDraftCubit>().preregister();
              context.push(AppRouter.colisPreregisteredPath);
            },
            onCancel: () => context.go('/colis'),
          ),
          children: [
            _SummaryCard(
              icon: Icons.alt_route_rounded,
              title: l10n.colisCorridorTitle,
              onEdit: () => _edit(context, AppRouter.colisRoutePath),
              children: [
                _CorridorRail(origin: origin, destination: destination),
                const SizedBox(height: 12),
                Row(
                  children: [
                    const Icon(
                      Icons.check_circle_outline_rounded,
                      size: 15,
                      color: AppColors.success,
                    ),
                    const SizedBox(width: 7),
                    Expanded(
                      child: Text(
                        l10n.colisServiceLine,
                        style: AppTextStyles.caption(
                          context.koraColors.textMuted,
                        ),
                      ),
                    ),
                  ],
                ),
                // Le corridor n'est pas choisi par le voyageur : il est
                // assigné au guichet. Le dire ici évite qu'on cherche
                // plus tard à changer de bus.
                const SizedBox(height: 4),
                Text(
                  l10n.colisAssignedByOps,
                  style: AppTextStyles.caption(context.koraColors.textMuted),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            _SummaryCard(
              icon: Icons.inventory_2_outlined,
              title: l10n.colisContentCardTitle,
              onEdit: () => _edit(context, AppRouter.colisContentPath),
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: colisInsetDecoration(context),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: _Tile(
                          label: l10n.colisNatureLabel,
                          value: draft.content,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _Tile(
                          label: l10n.colisDeclaredWeightLabel,
                          value: l10n.colisWeightWithUnit(
                            colisWeightText(draft.declaredWeightKg),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(
                      Icons.warning_amber_rounded,
                      size: 15,
                      color: AppColors.gold,
                    ),
                    const SizedBox(width: 7),
                    Expanded(
                      child: Text(
                        l10n.colisPackagingNote,
                        style: AppTextStyles.caption(
                          context.koraColors.textMuted,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            _SummaryCard(
              icon: Icons.people_alt_outlined,
              title: l10n.colisContactsCardTitle,
              onEdit: () => _edit(context, AppRouter.colisContactsPath),
              children: [
                _Party(
                  tag: l10n.colisSenderTag2,
                  name: draft.senderName,
                  phone: draft.senderPhone,
                ),
                const SizedBox(height: 8),
                _Party(
                  tag: l10n.colisRecipientTag2,
                  name: draft.recipientName,
                  phone: draft.recipientPhoneFull,
                ),
                // Le nom saisi n'a de valeur que présenté avec une
                // pièce : c'est sous les deux noms qu'il faut le lire.
                const SizedBox(height: 10),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      Icons.badge_outlined,
                      size: 15,
                      color: context.koraColors.textMuted,
                    ),
                    const SizedBox(width: 7),
                    Expanded(
                      child: Text(
                        l10n.colisIdentityAtPickup,
                        style: AppTextStyles.caption(
                          context.koraColors.textMuted,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            // La fourchette, jamais un prix : c'est ce qui distingue ce
            // récapitulatif d'un paiement.
            _IndicativeFare(low: low, high: high),
            const SizedBox(height: AppSpacing.sm),
            // Pourquoi ce montant n'engage à rien : la pesée au guichet
            // peut le déplacer, et personne ne paie à cette étape.
            KoraAlert(
              // Le jaune d'information, pas le pavé doré : ce tarif
              // n'est pas un danger, c'est une réserve.
              variant: KoraAlertVariant.info,
              icon: Icons.warning_amber_rounded,
              title: l10n.colisProvisionalTitle,
              message: l10n.colisProvisionalText,
            ),
            const SizedBox(height: AppSpacing.sm),
            // Ce qui attend le voyageur après ce bouton : trois gestes,
            // dont un seul lui demande de se déplacer.
            _NextSteps(city: draft.originHub?.city ?? ''),
          ],
        );
      },
    );
  }
}

/// Un bloc du récapitulatif, avec son retour vers l'étape concernée.
class _SummaryCard extends StatelessWidget {
  const _SummaryCard({
    required this.icon,
    required this.title,
    required this.onEdit,
    required this.children,
  });

  final IconData icon;
  final String title;
  final VoidCallback onEdit;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
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
              Icon(icon, size: 17, color: koraColors.textMuted),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              TextButton(
                onPressed: onEdit,
                style: TextButton.styleFrom(
                  padding: EdgeInsets.zero,
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                child: Text(context.l10n.colisEdit),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          ...children,
        ],
      ),
    );
  }
}

/// Une valeur encadrée : nature du colis, poids déclaré.
class _Tile extends StatelessWidget {
  const _Tile({required this.label, required this.value});

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
              fontSize: 13,
              height: 1.25,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

/// Un intervenant : qui dépose, qui retire.
class _Party extends StatelessWidget {
  const _Party({required this.tag, required this.name, required this.phone});

  final String tag;
  final String name;
  final String phone;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(
        color: koraColors.background,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  name,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 2),
                Text(phone, style: AppTextStyles.caption(koraColors.textMuted)),
              ],
            ),
          ),
          KoraTag(label: tag, variant: KoraTagVariant.outline),
        ],
      ),
    );
  }
}

/// Le tarif de référence : une estimation, son cartouche, et la
/// fourchette qui la borne.
///
/// L'alerte d'avant disait la fourchette sans dire qu'on ne paie pas
/// ici. Les deux informations se lisent ensemble ou pas du tout.
class _IndicativeFare extends StatelessWidget {
  const _IndicativeFare({required this.low, required this.high});

  final int low;
  final int high;

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
              Expanded(
                child: Text(
                  l10n.colisIndicativeTitle,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              // Où le règlement aura lieu : en escale, pas ici.
              KoraTag(
                label: l10n.colisPaymentAtHub,
                variant: KoraTagVariant.outline,
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            l10n.colisIndicativeEstimate,
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
          const SizedBox(height: 2),
          Text(
            '~ ${l10n.colisAmount(high)}',
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            l10n.colisIndicativeText(
              l10n.colisAmount(low),
              l10n.colisAmount(high),
            ),
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
        ],
      ),
    );
  }
}

/// Les trois gestes qui suivent le pré-enregistrement.
///
/// Le récapitulatif annonce un bouton, pas une fin : sans cette liste,
/// on ne sait pas qu'il reste à se déplacer, à faire peser, et à payer.
class _NextSteps extends StatelessWidget {
  const _NextSteps({required this.city});

  final String city;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    final steps = <(String, String)>[
      (l10n.colisNextStep1Title, l10n.colisNextStep1Text(city)),
      (l10n.colisNextStep2Title, l10n.colisNextStep2Text),
      (l10n.colisNextStep3Title, l10n.colisNextStep3Text),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          l10n.colisNextStepsTitle.toUpperCase(),
          style: AppTextStyles.eyebrow(koraColors.textMuted),
        ),
        const SizedBox(height: 9),
        for (var index = 0; index < steps.length; index++) ...[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Le rang se lit avant le titre : c'est un ordre, pas une
              // liste de possibilités.
              Container(
                width: 26,
                height: 26,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: koraColors.background,
                  shape: BoxShape.circle,
                ),
                child: Text(
                  '${index + 1}',
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 12,
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
                      steps[index].$1,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      steps[index].$2,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (index != steps.length - 1) const SizedBox(height: 12),
        ],
      ],
    );
  }
}

/// Le rail du corridor : deux pastilles reliées, et les hubs en regard.
///
/// La maquette l'encastre dans un bloc gris : c'est un rappel de ce
/// qu'on a choisi, pas une nouvelle carte à lire.
class _CorridorRail extends StatelessWidget {
  const _CorridorRail({required this.origin, required this.destination});

  final ColisHub? origin;
  final ColisHub? destination;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: colisInsetDecoration(context),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 12,
                  height: 12,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.kora,
                  ),
                ),
                Container(
                  width: 2,
                  height: 30,
                  margin: const EdgeInsets.symmetric(vertical: 2),
                  color: koraColors.line,
                ),
                Container(
                  width: 12,
                  height: 12,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.gold,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                _RailPoint(
                  label: l10n.colisOriginHub,
                  city: origin?.city ?? '',
                  hub: origin?.name ?? '',
                ),
                const SizedBox(height: 10),
                _RailPoint(
                  label: l10n.colisDestinationHub,
                  city: destination?.city ?? '',
                  hub: destination?.name ?? '',
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Un bout du corridor : son intitulé, sa ville, et sa gare entre
/// parenthèses — comme la maquette les écrit.
class _RailPoint extends StatelessWidget {
  const _RailPoint({
    required this.label,
    required this.city,
    required this.hub,
  });

  final String label;
  final String city;
  final String hub;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(label, style: AppTextStyles.caption(koraColors.textMuted)),
        const SizedBox(height: 2),
        RichText(
          text: TextSpan(
            text: city,
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 15,
              fontWeight: FontWeight.w800,
            ),
            children: [
              TextSpan(
                text: '  ($hub)',
                style: TextStyle(
                  color: koraColors.textMuted,
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
