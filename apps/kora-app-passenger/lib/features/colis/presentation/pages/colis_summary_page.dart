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
                _Line(
                  label: l10n.colisOriginHub,
                  value: origin?.label ?? '',
                  caption: origin?.counter,
                ),
                const SizedBox(height: 10),
                _Line(
                  label: l10n.colisDestinationHub,
                  value: destination?.label ?? '',
                  caption: destination?.counter,
                ),
                const SizedBox(height: 10),
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
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            _SummaryCard(
              icon: Icons.inventory_2_outlined,
              title: l10n.colisContentCardTitle,
              onEdit: () => _edit(context, AppRouter.colisContentPath),
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: _Tile(
                        label: l10n.colisNatureLabel,
                        value: draft.content,
                      ),
                    ),
                    const SizedBox(width: 8),
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
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            // La fourchette, jamais un prix : c'est ce qui distingue ce
            // récapitulatif d'un paiement.
            KoraAlert(
              icon: Icons.sell_outlined,
              title: l10n.colisIndicativeTitle,
              message: l10n.colisIndicativeText(
                l10n.colisAmount(low),
                l10n.colisAmount(high),
              ),
            ),
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
        borderRadius: BorderRadius.circular(19),
        border: Border.all(color: koraColors.line),
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

/// Un point du corridor : son rôle, son hub, son guichet.
class _Line extends StatelessWidget {
  const _Line({required this.label, required this.value, this.caption});

  final String label;
  final String value;
  final String? caption;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          label.toUpperCase(),
          style: AppTextStyles.eyebrow(koraColors.textMuted),
        ),
        const SizedBox(height: 3),
        Text(
          value,
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: 14,
            fontWeight: FontWeight.w900,
          ),
        ),
        if (caption != null) ...[
          const SizedBox(height: 2),
          Text(caption!, style: AppTextStyles.caption(koraColors.textMuted)),
        ],
      ],
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
