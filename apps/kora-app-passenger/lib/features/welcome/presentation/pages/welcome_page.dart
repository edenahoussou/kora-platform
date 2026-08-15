import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../widgets/welcome_scene.dart';

/// Écran d'accueil de l'app — équivalent `welcome` de la maquette : une
/// scène illustrée sur fond navy, puis l'accroche, les garanties et les
/// deux actions d'entrée.
///
/// C'est le premier écran affiché au lancement (voir
/// `AppRouter.initialLocation`).
class WelcomePage extends StatelessWidget {
  const WelcomePage({super.key, this.onCreateAccount, this.onLogin});

  /// Actions injectées par la route, pour que l'écran reste testable sans
  /// routeur.
  final VoidCallback? onCreateAccount;
  final VoidCallback? onLogin;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      // La scène occupe le haut de l'écran et reste navy dans les deux
      // thèmes : la barre d'état doit toujours s'y afficher en clair.
      sized: false,
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: koraColors.background,
        body: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // La maquette fige la scène à 388px sur un écran de 844.
            // Gardée telle quelle, elle ne laisserait pas la place au
            // contenu sur un téléphone plus court : on conserve sa
            // proportion (46 % de la hauteur) sans jamais dépasser la
            // taille d'origine.
            WelcomeScene(
              height: math.min(388, MediaQuery.sizeOf(context).height * 0.46),
            ),
            Expanded(
              child: _WelcomeContent(
                onCreateAccount: onCreateAccount,
                onLogin: onLogin,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// `.welcome-content` : l'accroche, les garanties, les actions.
class _WelcomeContent extends StatelessWidget {
  const _WelcomeContent({this.onCreateAccount, this.onLogin});

  final VoidCallback? onCreateAccount;
  final VoidCallback? onLogin;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return SafeArea(
      top: false,
      // Défilement de secours : sur un écran très court, ou avec un texte
      // agrandi par les réglages d'accessibilité, le contenu doit pouvoir
      // s'étirer au lieu de déborder. Tant qu'il tient, la hauteur
      // minimale garde le `Spacer` actif et les boutons collés en bas.
      child: LayoutBuilder(
        builder: (context, constraints) => SingleChildScrollView(
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight),
            child: IntrinsicHeight(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 25, 20, 18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // `h1 { font-size: 27px; line-height: 1.02 }`, dont la seconde
                    // ligne passe en bleu `digital` (`h1 em`).
                    Text.rich(
                      TextSpan(
                        children: [
                          const TextSpan(text: 'Plus qu’un trajet.\n'),
                          TextSpan(
                            text: 'Une expérience KORA.',
                            style: TextStyle(
                              color: isDark
                                  ? AppColors.gold
                                  : AppColors.digital,
                            ),
                          ),
                        ],
                      ),
                      style: TextStyle(
                        color: isDark ? koraColors.textPrimary : AppColors.kora,
                        fontSize: 27,
                        fontWeight: FontWeight.w800,
                        height: 1.02,
                        letterSpacing: -1.215, // -.045em × 27
                      ),
                    ),
                    const SizedBox(height: 9),
                    Text(
                      'Réservez votre place, voyagez sereinement et gardez chaque '
                      'étape à portée de main.',
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 12,
                        height: 1.5,
                      ),
                    ),
                    const SizedBox(height: 14),
                    const _Benefits(),
                    const Spacer(),
                    _WelcomePrimaryButton(
                      label: 'Créer mon compte',
                      onPressed: onCreateAccount,
                    ),
                    const SizedBox(height: 8),
                    _LoginButton(onPressed: onLogin),
                    const SizedBox(height: 12),
                    const _Progress(step: 0, total: 3),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// `.welcome-benefits` : les trois garanties, en pastilles.
class _Benefits extends StatelessWidget {
  const _Benefits();

  static const _labels = [
    'Paiement sécurisé',
    'Billet hors ligne',
    'Suivi en direct',
  ];

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Wrap(
      spacing: 7,
      runSpacing: 7,
      children: [
        for (final label in _labels)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 6),
            decoration: BoxDecoration(
              color: koraColors.surface,
              borderRadius: BorderRadius.circular(AppRadius.pill),
              border: Border.all(color: koraColors.line),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 16,
                  height: 16,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.gold.withValues(alpha: 0.25),
                  ),
                  child: Icon(
                    Icons.check_rounded,
                    size: 10,
                    color: context.koraColors.textPrimary,
                  ),
                ),
                const SizedBox(width: 4),
                Text(
                  label,
                  style: TextStyle(
                    color: koraColors.textPrimary,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

/// `.welcome-primary` : bouton doré, libellé à gauche, flèche dans un carré
/// navy à droite.
///
/// Pas un `KoraButton` : sa flèche n'est pas une icône posée dans le
/// bouton mais une pastille à part entière, avec son propre fond.
class _WelcomePrimaryButton extends StatelessWidget {
  const _WelcomePrimaryButton({required this.label, this.onPressed});

  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.gold,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Container(
          height: 50,
          padding: const EdgeInsets.only(left: 18, right: 13),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppColors.kora,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Container(
                width: 31,
                height: 31,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.kora,
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                ),
                child: const Icon(
                  Icons.arrow_forward_rounded,
                  size: 16,
                  color: AppColors.white,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.welcome-login` : l'accès pour qui a déjà un compte.
class _LoginButton extends StatelessWidget {
  const _LoginButton({this.onPressed});

  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Container(
          height: 47,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(color: koraColors.line),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                'J’ai déjà un compte',
                style: TextStyle(
                  color: koraColors.textPrimary,
                  fontSize: 12,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(width: 7),
              const Icon(
                Icons.chevron_right_rounded,
                size: 18,
                color: AppColors.digital,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.welcome-progress` : les trois pastilles de progression, la première
/// active.
class _Progress extends StatelessWidget {
  const _Progress({required this.step, required this.total});

  final int step;
  final int total;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        for (var i = 0; i < total; i++) ...[
          if (i != 0) const SizedBox(width: 5),
          Container(
            width: i == step ? 21 : 5,
            height: 5,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(5),
              color: i == step
                  ? AppColors.gold
                  : koraColors.textPrimary.withValues(alpha: 0.15),
            ),
          ),
        ],
      ],
    );
  }
}
