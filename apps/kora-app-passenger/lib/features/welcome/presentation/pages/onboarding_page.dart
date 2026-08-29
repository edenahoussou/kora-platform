import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../widgets/kora_wordmark.dart';
import '../widgets/onboarding_scenes.dart';

/// Les trois temps de l'onboarding : chercher, choisir, embarquer.
///
/// Même découpe que l'écran de bienvenue — scène navy en haut, arrondie de
/// 54 en bas, texte sur fond clair en dessous. Ce n'est pas de la
/// paresse : l'écran qui suit immédiatement l'onboarding est justement
/// celui-là, et changer de structure entre les deux ferait croire à deux
/// applications.
///
/// Trois écrans et pas cinq : au-delà, on ne les lit plus, on cherche le
/// bouton « Passer ».
class OnboardingPage extends StatefulWidget {
  const OnboardingPage({super.key, required this.onDone});

  /// Appelé à la fin comme au « Passer » : dans les deux cas l'onboarding
  /// est vu, et le redonner à la prochaine ouverture serait une punition.
  final VoidCallback onDone;

  @override
  State<OnboardingPage> createState() => _OnboardingPageState();
}

class _OnboardingPageState extends State<OnboardingPage> {
  final _pages = PageController();
  var _at = 0;

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  void _next(int count) {
    if (_at >= count - 1) {
      widget.onDone();
      return;
    }

    _pages.nextPage(
      duration: const Duration(milliseconds: 320),
      curve: Curves.easeOutCubic,
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    final slides = <_Slide>[
      _Slide(
        scene: const OnboardingSearchScene(),
        title: l10n.onboardingSearchTitle,
        highlight: l10n.onboardingSearchHighlight,
        text: l10n.onboardingSearchText,
      ),
      _Slide(
        scene: const OnboardingSeatsScene(),
        title: l10n.onboardingSeatsTitle,
        highlight: l10n.onboardingSeatsHighlight,
        text: l10n.onboardingSeatsText,
      ),
      _Slide(
        scene: const OnboardingTicketScene(),
        title: l10n.onboardingTicketTitle,
        highlight: l10n.onboardingTicketHighlight,
        text: l10n.onboardingTicketText,
      ),
    ];

    return Scaffold(
      body: PageView.builder(
        controller: _pages,
        itemCount: slides.length,
        onPageChanged: (index) => setState(() => _at = index),
        itemBuilder: (context, index) => _SlideView(
          slide: slides[index],
          index: index,
          count: slides.length,
          onNext: () => _next(slides.length),
          onSkip: widget.onDone,
        ),
      ),
    );
  }
}

class _Slide {
  const _Slide({
    required this.scene,
    required this.title,
    required this.highlight,
    required this.text,
  });

  final Widget scene;

  /// Le titre se lit sur deux lignes : [title] en navy, [highlight] en
  /// bleu digital dessous. La coupure est portée par la traduction, pas
  /// par la largeur de l'écran.
  final String title;
  final String highlight;
  final String text;
}

class _SlideView extends StatelessWidget {
  const _SlideView({
    required this.slide,
    required this.index,
    required this.count,
    required this.onNext,
    required this.onSkip,
  });

  final _Slide slide;
  final int index;
  final int count;
  final VoidCallback onNext;
  final VoidCallback onSkip;

  bool get _isLast => index == count - 1;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SizedBox(
          height: 330,
          child: ClipRRect(
            borderRadius: const BorderRadius.vertical(
              bottom: Radius.circular(54),
            ),
            child: DecoratedBox(
              decoration: const BoxDecoration(gradient: kKoraSceneGradient),
              child: SafeArea(bottom: false, child: slide.scene),
            ),
          ),
        ),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(22, 28, 22, 22),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text.rich(
                  TextSpan(
                    children: [
                      TextSpan(text: '${slide.title}\n'),
                      TextSpan(
                        text: slide.highlight,
                        style: const TextStyle(color: AppColors.digital),
                      ),
                    ],
                  ),
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 25,
                    height: 1.14,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.6,
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                Text(
                  slide.text,
                  style: TextStyle(
                    color: koraColors.textMuted,
                    fontSize: 13.5,
                    height: 1.6,
                  ),
                ),
                const Spacer(),
                _Dots(count: count, at: index),
                const SizedBox(height: AppSpacing.md),
                if (_isLast)
                  KoraButton(label: l10n.onboardingStart, onPressed: onNext)
                else
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      // `ghost` et non un `TextButton` : sans contour, le
                      // libellé flottait sur le fond et ne se lisait pas
                      // comme un bouton — dans les deux thèmes. Le variant
                      // du design system porte déjà le filet qui convient
                      // à chacun.
                      SizedBox(
                        width: 132,
                        child: KoraButton(
                          label: l10n.onboardingSkip,
                          variant: KoraButtonVariant.ghost,
                          onPressed: onSkip,
                        ),
                      ),
                      SizedBox(
                        width: 132,
                        child: KoraButton(
                          label: l10n.onboardingNext,
                          onPressed: onNext,
                        ),
                      ),
                    ],
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

/// Les puces de progression : la courante s'allonge au lieu de changer de
/// couleur seulement — c'est lisible sans distinguer les teintes.
class _Dots extends StatelessWidget {
  const _Dots({required this.count, required this.at});

  final int count;
  final int at;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        for (var i = 0; i < count; i++) ...[
          AnimatedContainer(
            duration: const Duration(milliseconds: 240),
            width: i == at ? 20 : 7,
            height: 7,
            decoration: BoxDecoration(
              color: i == at ? AppColors.gold : context.koraColors.line,
              borderRadius: BorderRadius.circular(999),
            ),
          ),
          if (i < count - 1) const SizedBox(width: 6),
        ],
      ],
    );
  }
}
