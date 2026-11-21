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

/// Écran 03 — « Que contient votre colis ? ».
///
/// Le poids saisi ici est **déclaré**, pas pesé. Toute la suite du
/// parcours tient à cette nuance : il sert à annoncer une fourchette, et
/// la balance du hub le remplacera. L'écran le dit, pour qu'un écart au
/// guichet ne passe pas pour une mauvaise surprise.
class ColisContentPage extends StatefulWidget {
  const ColisContentPage({super.key});

  @override
  State<ColisContentPage> createState() => _ColisContentPageState();
}

class _ColisContentPageState extends State<ColisContentPage> {
  late final TextEditingController _content;
  late final TextEditingController _value;

  /// La limite affichée par le compteur de la maquette.
  static const int _maxContent = 150;

  @override
  void initState() {
    super.initState();
    final draft = context.read<ColisDraftCubit>().state;
    _content = TextEditingController(text: draft.content);
    _value = TextEditingController(
      text: draft.declaredValue == 0 ? '' : '${draft.declaredValue}',
    );
  }

  @override
  void dispose() {
    _content.dispose();
    _value.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return BlocBuilder<ColisDraftCubit, ColisDraft>(
      builder: (context, draft) {
        final cubit = context.read<ColisDraftCubit>();

        return ColisStepScaffold(
          step: 2,
          stepLabel: l10n.colisStepContent,
          title: l10n.colisContentTitle,
          subtitle: l10n.colisContentSubtitle,
          footer: ColisStepFooter(
            label: l10n.colisContentCta,
            hint: l10n.colisContentHint,
            onContinue: draft.isContentComplete
                ? () => colisAdvance(context, AppRouter.colisContactsPath)
                : null,
            onCancel: () => context.go('/colis'),
          ),
          children: [
            // L'itinéraire reste sous les yeux : c'est lui qui fixe le
            // tarif, et le voyageur peut encore reculer d'un écran.
            _SelectedRoute(draft: draft),
            const SizedBox(height: AppSpacing.md),
            // La nature commande le contrôle au guichet : elle se
            // déclare avant qu'on décrive le détail.
            Text(
              l10n.colisNatureField,
              style: TextStyle(
                color: context.koraTitleColor,
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            _NatureField(nature: draft.nature, onChanged: cubit.chooseNature),
            const SizedBox(height: AppSpacing.md),
            Row(
              children: [
                Expanded(
                  child: Text(
                    l10n.colisContentLabel,
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Text(
                  '${draft.content.characters.length}/$_maxContent',
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ],
            ),
            const SizedBox(height: 6),
            TextField(
              controller: _content,
              maxLines: 3,
              maxLength: _maxContent,
              onChanged: cubit.describe,
              style: AppTextStyles.body(context.koraTitleColor),
              decoration: InputDecoration(
                counterText: '',
                hintText: l10n.colisContentPlaceholder,
                filled: true,
                fillColor: koraColors.surface,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  borderSide: BorderSide(color: koraColors.line),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  borderSide: BorderSide(color: koraColors.line),
                ),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              l10n.colisContentHelp,
              style: AppTextStyles.caption(koraColors.textMuted),
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              l10n.colisWeightLabel,
              style: TextStyle(
                color: context.koraTitleColor,
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            _WeightStepper(
              weightKg: draft.declaredWeightKg,
              onStep: cubit.stepWeight,
            ),
            const SizedBox(height: AppSpacing.md),
            KoraInput(
              label: l10n.colisDeclaredValue,
              hint: l10n.colisDeclaredValueHint,
              controller: _value,
              keyboardType: TextInputType.number,
              onChanged: (text) => cubit.setDeclaredValue(
                int.tryParse(text.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              l10n.colisDeclaredValueHelp,
              style: AppTextStyles.caption(koraColors.textMuted),
            ),
            const SizedBox(height: AppSpacing.sm),
            // La phrase que tout le parcours doit tenir : ce poids n'est
            // pas celui qui sera facturé.
            KoraAlert(
              icon: Icons.scale_outlined,
              title: l10n.colisWeighingTitle,
              message: l10n.colisWeighingText,
            ),
            const SizedBox(height: AppSpacing.sm),
            // Ce que le guichet refusera : mieux vaut le lire ici que
            // devant l'agent, le colis déjà emballé.
            KoraAlert(
              variant: KoraAlertVariant.critical,
              icon: Icons.dangerous_outlined,
              title: l10n.colisForbiddenTitle,
              message: l10n.colisForbiddenText,
            ),
          ],
        );
      },
    );
  }
}

/// L'itinéraire déjà retenu, rappelé en tête d'étape.
class _SelectedRoute extends StatelessWidget {
  const _SelectedRoute({required this.draft});

  final ColisDraft draft;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final origin = draft.originHub;
    final destination = draft.destinationHub;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: koraColors.line),
      ),
      child: Row(
        children: [
          Icon(Icons.alt_route_rounded, size: 17, color: koraColors.textMuted),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  context.l10n.colisSelectedRoute.toUpperCase(),
                  style: AppTextStyles.eyebrow(koraColors.textMuted),
                ),
                const SizedBox(height: 3),
                Text(
                  '${origin?.city} (${origin?.name})'
                  '  →  '
                  '${destination?.city} (${destination?.name})',
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    height: 1.25,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 6),
          const Icon(
            Icons.check_circle_rounded,
            size: 18,
            color: AppColors.success,
          ),
        ],
      ),
    );
  }
}

/// Le sélecteur de poids : deux boutons et une valeur, au demi-kilo.
///
/// Pas un champ libre : au guichet on annonce un ordre de grandeur, et
/// un clavier numérique laisserait saisir « 2,37 » ou « 300 ».
class _WeightStepper extends StatelessWidget {
  const _WeightStepper({required this.weightKg, required this.onStep});

  final double weightKg;
  final ValueChanged<int> onStep;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: koraColors.line),
      ),
      child: Row(
        children: [
          _StepButton(
            icon: Icons.remove_rounded,
            onTap: weightKg > kColisWeightStepKg ? () => onStep(-1) : null,
          ),
          Expanded(
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.baseline,
              textBaseline: TextBaseline.alphabetic,
              children: [
                Text(
                  l10n.colisWeightValue(colisWeightText(weightKg)),
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(width: 6),
                Text(
                  l10n.colisWeightUnit,
                  style: AppTextStyles.body(koraColors.textMuted),
                ),
              ],
            ),
          ),
          _StepButton(
            icon: Icons.add_rounded,
            onTap: weightKg < kColisMaxWeightKg ? () => onStep(1) : null,
          ),
        ],
      ),
    );
  }
}

class _StepButton extends StatelessWidget {
  const _StepButton({required this.icon, required this.onTap});

  final IconData icon;

  /// `null` aux bornes : 0,5 kg au plancher, 30 kg au plafond du runbook.
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Material(
      color: koraColors.background,
      borderRadius: BorderRadius.circular(AppRadius.base),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.base),
        child: SizedBox(
          width: 44,
          height: 44,
          child: Icon(
            icon,
            size: 20,
            color: onTap == null
                ? koraColors.textMuted
                : context.koraTitleColor,
          ),
        ),
      ),
    );
  }
}

/// La nature de l'envoi : quatre choix fermés, comme la maquette.
class _NatureField extends StatelessWidget {
  const _NatureField({required this.nature, required this.onChanged});

  final ColisNature nature;
  final ValueChanged<ColisNature> onChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final value in ColisNature.values)
          Material(
            color: value == nature ? AppColors.kora : koraColors.surface,
            borderRadius: BorderRadius.circular(AppRadius.pill),
            child: InkWell(
              onTap: () => onChanged(value),
              borderRadius: BorderRadius.circular(AppRadius.pill),
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 13,
                  vertical: 10,
                ),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(AppRadius.pill),
                  border: Border.all(
                    color: value == nature ? AppColors.kora : koraColors.line,
                  ),
                ),
                child: Text(
                  colisNatureLabel(l10n, value),
                  style: TextStyle(
                    color: value == nature
                        ? AppColors.white
                        : koraColors.textMuted,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}

/// Le libellé d'une nature, nommé à la compilation.
String colisNatureLabel(AppLocalization l10n, ColisNature nature) =>
    switch (nature) {
      ColisNature.clothing => l10n.colisNatureClothing,
      ColisNature.documents => l10n.colisNatureDocuments,
      ColisNature.electronics => l10n.colisNatureElectronics,
      ColisNature.other => l10n.colisNatureOther,
    };
