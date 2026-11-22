import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/brand_mini.dart';
import '../../../../core/widgets/screen_header.dart';
import '../bloc/colis_draft_cubit.dart';
import 'colis_surface.dart';

/// Valide l'étape courante et passe à [next] — sauf si le voyageur est
/// venu corriger depuis le récapitulatif.
///
/// Dans ce cas il y retourne directement : on ne refait pas les trois
/// écrans suivants pour un numéro mal saisi. Le drapeau se range au
/// passage, pour que l'étape redevienne une étape.
void colisAdvance(BuildContext context, String next) {
  final cubit = context.read<ColisDraftCubit>();
  if (!cubit.state.editing) {
    context.push(next);
    return;
  }
  cubit.doneEditing();
  context.go(AppRouter.colisSummaryPath);
}

/// La coquille commune aux quatre étapes du parcours d'envoi.
///
/// La maquette répète la même ossature d'un écran à l'autre : l'en-tête
/// de l'onglet, le surtitre, la question posée en grand, puis la barre de
/// progression. L'écrire une fois évite qu'un écran dérive de ses voisins
/// à la première retouche.
class ColisStepScaffold extends StatelessWidget {
  const ColisStepScaffold({
    super.key,
    required this.step,
    required this.stepLabel,
    required this.title,
    required this.subtitle,
    required this.children,
    required this.footer,
  });

  /// Le rang de l'étape, de 1 à [totalSteps].
  final int step;

  /// Ce que l'étape traite — « Itinéraire & Hubs », « Contenu & Poids ».
  final String stepLabel;

  final String title;
  final String subtitle;

  /// Le corps défilant de l'écran.
  final List<Widget> children;

  /// Les actions, posées à la fin du contenu — elles défilent avec lui.
  final Widget footer;

  static const int totalSteps = 4;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final progress = step / totalSteps;

    // Le clavier recouvre le pied de l'écran au lieu de le pousser.
    //
    // Comprimé, l'espace restant entre le champ de recherche et le
    // bouton ne laissait plus voir qu'une demi-ligne : sur l'écran des
    // gares, le résultat filtré passait sous le bouton et devenait
    // inatteignable. La liste garde donc sa hauteur, et se prolonge
    // d'autant que le clavier mord dessus.
    final keyboard = MediaQuery.viewInsetsOf(context).bottom;

    return Scaffold(
      resizeToAvoidBottomInset: false,
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
              Row(
                children: [
                  Expanded(
                    child: Text(
                      l10n.colisNewShipment.toUpperCase(),
                      style: AppTextStyles.eyebrow(koraColors.textMuted),
                    ),
                  ),
                  KoraTag(
                    label: l10n.colisStepOf(step, totalSteps),
                    variant: KoraTagVariant.neutral,
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                title,
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 22,
                  height: 1.1,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.6,
                ),
              ),
              const SizedBox(height: 6),
              Text(subtitle, style: AppTextStyles.body(koraColors.textMuted)),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      stepLabel,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ),
                  Text(
                    l10n.colisProgress((progress * 100).round()),
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              // La barre dit où l'on en est sans compter les écrans : un
              // parcours de quatre étapes se tient dans la tête, pas dans
              // un fil d'Ariane.
              ClipRRect(
                borderRadius: BorderRadius.circular(AppRadius.pill),
                child: LinearProgressIndicator(
                  value: progress,
                  minHeight: 6,
                  backgroundColor: koraColors.line,
                  valueColor: const AlwaysStoppedAnimation(AppColors.gold),
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              // Le pied défile avec le contenu : épinglé en bas, il
              // mangeait le dernier bloc de la liste sur un écran court,
              // et sur un écran long on ne savait plus ce qui restait à
              // lire au-dessous de lui.
              Expanded(
                child: ListView(
                  padding: EdgeInsets.only(bottom: AppSpacing.lg + keyboard),
                  children: [
                    ...children,
                    const SizedBox(height: AppSpacing.lg),
                    footer,
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

/// Le pied commun aux étapes : l'action principale, ce qu'elle promet,
/// et la sortie de secours.
class ColisStepFooter extends StatelessWidget {
  const ColisStepFooter({
    super.key,
    required this.label,
    this.hint,
    required this.onContinue,
    required this.onCancel,
  });

  final String label;

  /// Ce que la prochaine étape demandera : le voyageur décide de
  /// continuer en sachant ce qui l'attend.
  /// Ce que l'étape promet, quand il y a quelque chose à promettre.
  ///
  /// Les « Étape suivante : … » ont disparu : la barre de progression
  /// dit déjà où l'on en est, et le bouton dit où l'on va.
  final String? hint;

  /// `null` tant que l'étape n'est pas remplie : le bouton se grise.
  final VoidCallback? onContinue;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        KoraButton(
          label: label,
          icon: Icons.arrow_forward_rounded,
          iconPosition: KoraButtonIconPosition.edges,
          onPressed: onContinue,
        ),
        if (hint case final hint?) ...[
          const SizedBox(height: 6),
          Text(
            hint,
            textAlign: TextAlign.center,
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
        ],
        const SizedBox(height: 10),
        // Deux boutons, pas plus : celui qui avance, et celui qui
        // renonce. Le retour à l'étape précédente a disparu — le
        // bouton système le fait déjà, et il doublait l'abandon d'un
        // geste de même poids.
        ColisFlatAction(
          label: context.l10n.colisCancel,
          icon: Icons.close_rounded,
          tint: koraColors.textMuted,
          onTap: onCancel,
        ),
      ],
    );
  }
}
