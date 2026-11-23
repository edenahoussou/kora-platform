import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
            _Field(
              label: l10n.colisNatureField,
              required: true,
              child: _NatureSelect(
                nature: draft.nature,
                onChanged: cubit.chooseNature,
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            _Field(
              label: l10n.colisContentLabel,
              required: true,
              trailing: '${_content.text.characters.length}/$_maxContent',
              help: l10n.colisContentHelp,
              child: _ContentField(
                controller: _content,
                maxLength: _maxContent,
                hint: l10n.colisContentPlaceholder,
                onChanged: (value) {
                  cubit.describe(value);
                  setState(() {});
                },
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            _Field(
              label: l10n.colisWeightLabel,
              required: true,
              child: _WeightStepper(
                weightKg: draft.declaredWeightKg,
                onStep: cubit.stepWeight,
              ),
            ),
            // La pesée certifiée se lit **sous le compteur**, collée à
            // lui : c'est le poids qu'on vient de saisir qu'elle
            // corrige, pas une remarque générale de l'écran.
            const SizedBox(height: 8),
            _Inset(
              icon: Icons.scale_rounded,
              iconColor: AppColors.digital,
              title: l10n.colisWeighingTitle,
              text: l10n.colisWeighingText,
            ),
            const SizedBox(height: AppSpacing.md),
            _Field(
              label: l10n.colisDeclaredValue,
              required: true,
              help: l10n.colisDeclaredValueHelp,
              child: _ContentField(
                controller: _value,
                hint: l10n.colisDeclaredValueHint,
                lines: 1,
                digitsOnly: true,
                onChanged: (value) =>
                    cubit.setDeclaredValue(int.tryParse(value) ?? 0),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            // Les interdits, la liste et la certification : une seule
            // carte. Les séparer laissait croire qu'on pouvait cocher
            // sans avoir lu.
            _ForbiddenCard(
              certified: draft.certified,
              onCertify: cubit.certify,
            ),
            const SizedBox(height: AppSpacing.sm),
            // Le rappel de fin : une bande grise, pas une carte — il
            // rassure, il n'informe pas.
            _Reassurance(text: l10n.colisTraceabilityText),
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

/// Le libellé d'une nature, nommé à la compilation.
String colisNatureLabel(AppLocalization l10n, ColisNature nature) =>
    switch (nature) {
      ColisNature.clothing => l10n.colisNatureClothing,
      ColisNature.documents => l10n.colisNatureDocuments,
      ColisNature.electronics => l10n.colisNatureElectronics,
      ColisNature.other => l10n.colisNatureOther,
    };

/// Un champ du formulaire : son intitulé, le contrôle, et l'aide.
///
/// La maquette met l'intitulé **au-dessus** du champ, jamais dedans :
/// un libellé qui disparaît à la première frappe ne dit plus ce qu'on
/// est en train de remplir.
class _Field extends StatelessWidget {
  const _Field({
    required this.label,
    required this.child,
    this.required = false,
    this.trailing,
    this.help,
  });

  final String label;
  final Widget child;

  /// Marqué d'une astérisque rouge, comme la maquette.
  final bool required;

  /// Le compteur de caractères, à droite de l'intitulé.
  final String? trailing;

  /// La ligne d'aide sous le champ, précédée de son pictogramme.
  final String? help;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            Expanded(
              child: RichText(
                text: TextSpan(
                  text: label,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                  children: [
                    if (required)
                      const TextSpan(
                        text: ' *',
                        style: TextStyle(color: AppColors.danger),
                      ),
                  ],
                ),
              ),
            ),
            if (trailing case final trailing?)
              Text(
                trailing,
                style: AppTextStyles.caption(koraColors.textMuted),
              ),
          ],
        ),
        const SizedBox(height: 7),
        child,
        if (help case final help?) ...[
          const SizedBox(height: 6),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.info_outline_rounded,
                size: 15,
                color: koraColors.textMuted,
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  help,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }
}

/// Un champ de saisie posé sur une carte blanche, sans bordure.
///
/// La maquette ne borde pas ses champs : elle les pose en blanc sur le
/// fond mist, avec l'ombre des cartes. Une bordure en plus donnait deux
/// signaux pour une seule limite.
class _ContentField extends StatelessWidget {
  const _ContentField({
    required this.controller,
    required this.hint,
    required this.onChanged,
    this.maxLength,
    this.lines = 3,
    this.digitsOnly = false,
  });

  final TextEditingController controller;
  final String hint;
  final ValueChanged<String> onChanged;
  final int? maxLength;
  final int lines;
  final bool digitsOnly;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
      ),
      child: TextField(
        controller: controller,
        maxLines: lines,
        maxLength: maxLength,
        keyboardType: digitsOnly ? TextInputType.number : null,
        inputFormatters: digitsOnly
            ? [FilteringTextInputFormatter.digitsOnly]
            : null,
        onChanged: onChanged,
        style: AppTextStyles.body(context.koraTitleColor),
        decoration: InputDecoration(
          counterText: '',
          hintText: hint,
          hintStyle: AppTextStyles.body(koraColors.textMuted),
          filled: false,
          border: InputBorder.none,
          contentPadding: const EdgeInsets.all(14),
        ),
      ),
    );
  }
}

/// La nature de l'envoi, en liste déroulante.
///
/// Quatre pastilles prenaient deux lignes pour un choix unique, et
/// poussaient le reste du formulaire sous le pli. Une liste déroulante
/// dit la même chose sur une ligne.
class _NatureSelect extends StatelessWidget {
  const _NatureSelect({required this.nature, required this.onChanged});

  final ColisNature nature;
  final ValueChanged<ColisNature> onChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: kColisCardShadow,
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<ColisNature>(
          value: nature,
          isExpanded: true,
          borderRadius: BorderRadius.circular(AppRadius.md),
          padding: const EdgeInsets.symmetric(vertical: 4),
          icon: Icon(Icons.expand_more_rounded, color: koraColors.textMuted),
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: 15,
            fontWeight: FontWeight.w700,
          ),
          onChanged: (value) {
            if (value != null) onChanged(value);
          },
          items: [
            for (final item in ColisNature.values)
              DropdownMenuItem(
                value: item,
                child: Text(colisNatureLabel(l10n, item)),
              ),
          ],
        ),
      ),
    );
  }
}

/// Un encart gris collé au champ qu'il commente.
class _Inset extends StatelessWidget {
  const _Inset({
    required this.icon,
    required this.title,
    required this.text,
    this.iconColor,
  });

  final IconData icon;
  final String title;
  final String text;
  final Color? iconColor;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: colisInsetDecoration(context),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 1),
            child: Icon(
              icon,
              size: 20,
              color: iconColor ?? context.koraTitleColor,
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
                const SizedBox(height: 3),
                Text(text, style: AppTextStyles.caption(koraColors.textMuted)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Les articles interdits, et la certification qui les suit.
///
/// Une seule carte : le pictogramme rouge, la liste encastrée, puis la
/// case à cocher. Les séparer laissait certifier sans avoir lu, et
/// c'est précisément ce que cette case engage.
class _ForbiddenCard extends StatelessWidget {
  const _ForbiddenCard({required this.certified, required this.onCertify});

  final bool certified;
  final ValueChanged<bool> onCertify;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    final items = [
      l10n.colisForbidden1,
      l10n.colisForbidden2,
      l10n.colisForbidden3,
      l10n.colisForbidden4,
      l10n.colisForbidden5,
    ];

    return Container(
      padding: const EdgeInsets.all(16),
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
              // Le bouclier sur fond rouge pâle : l'interdit se signale
              // par la couleur, pas par un encadré entier en rouge.
              Container(
                width: 28,
                height: 28,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.danger.withValues(alpha: 0.14),
                ),
                child: const Icon(
                  Icons.shield_outlined,
                  size: 16,
                  color: AppColors.danger,
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  l10n.colisForbiddenTitle,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: colisInsetDecoration(context),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  l10n.colisForbiddenLead,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
                const SizedBox(height: 8),
                for (final item in items) ...[
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Une puce ronde et rouge, de six pixels : c'est
                      // la liste d'interdits de la maquette, pas une
                      // colonne de pictogrammes.
                      Container(
                        width: 6,
                        height: 6,
                        margin: const EdgeInsets.only(top: 7, right: 9),
                        decoration: const BoxDecoration(
                          shape: BoxShape.circle,
                          color: AppColors.danger,
                        ),
                      ),
                      Expanded(
                        child: Text(
                          item,
                          style: TextStyle(
                            color: context.koraTitleColor,
                            fontSize: 13,
                            height: 1.4,
                          ),
                        ),
                      ),
                    ],
                  ),
                  if (item != items.last) const SizedBox(height: 6),
                ],
              ],
            ),
          ),
          const SizedBox(height: 14),
          // La case : carrée, grise au repos, marine une fois cochée.
          InkWell(
            onTap: () => onCertify(!certified),
            borderRadius: BorderRadius.circular(AppRadius.base),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 2),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 20,
                    height: 20,
                    margin: const EdgeInsets.only(top: 2),
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: certified ? AppColors.kora : koraColors.background,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: certified
                        ? const Icon(
                            Icons.check_rounded,
                            size: 15,
                            color: AppColors.white,
                          )
                        : null,
                  ),
                  const SizedBox(width: 11),
                  Expanded(
                    child: RichText(
                      text: TextSpan(
                        text: l10n.colisCertifyLabel,
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 13,
                          height: 1.4,
                        ),
                        children: const [
                          TextSpan(
                            text: ' *',
                            style: TextStyle(color: AppColors.danger),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// La bande de réassurance qui ferme l'étape.
class _Reassurance extends StatelessWidget {
  const _Reassurance({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: colisInsetDecoration(context),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(
            Icons.verified_user_rounded,
            size: 18,
            color: AppColors.success,
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              text,
              style: AppTextStyles.caption(koraColors.textMuted),
            ),
          ),
        ],
      ),
    );
  }
}
