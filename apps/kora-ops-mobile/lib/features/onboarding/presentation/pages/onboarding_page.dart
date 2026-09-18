import 'package:flutter/material.dart';

import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../splash/presentation/widgets/kora_wordmark.dart';
import '../widgets/onboarding_scenes.dart';

/// Les trois temps de l'onboarding OPS : la tournée, le scan, la synchro.
///
/// Copie conforme de l'onboarding `kora-passager`
/// (`onboarding_page.dart`) : même découpe — scène navy en haut, arrondie
/// de 54 en bas, texte sur fond clair en dessous — pour que la première
/// image de l'app OPS s'inscrive dans le même vocabulaire que celle du
/// splash qui la précède immédiatement.
///
/// Trois écrans et pas cinq, pour la même raison que côté passager :
/// au-delà, on ne les lit plus, on cherche le bouton « Passer ».
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
    const slides = <_Slide>[
      _Slide(
        scene: OnboardingTourneeScene(),
        title: 'Votre tournée,',
        highlight: 'organisée.',
        text: 'Les courses qui vous sont assignées, l\'horaire et le bus, '
            'réunis au même endroit dès l\'ouverture de l\'app.',
      ),
      _Slide(
        scene: OnboardingScanScene(),
        title: 'L\'embarquement,',
        highlight: 'en un scan.',
        text: 'Le manifeste s\'ouvre passager par passager. Un scan suffit '
            'à valider un billet et à faire avancer la tournée.',
      ),
      _Slide(
        scene: OnboardingSynchroScene(),
        title: 'À jour,',
        highlight: 'même hors-ligne.',
        text: 'Le réseau manque parfois en gare. Chaque action se met en '
            'file et se synchronise seule dès la connexion retrouvée.',
      ),
    ];

    return Scaffold(
      body: PageView.builder(
        controller: _pages,
        itemCount: slides.length,
        onPageChanged: (index) => setState(() => _at = index),
        // Le swipe reste actif (on ne remplace pas `PageView`), mais
        // chaque slide s'estompe au lieu de glisser sèchement : l'opacité
        // suit la distance à la page courante, recalculée à chaque frame
        // de défilement comme à chaque `nextPage()` programmatique — les
        // deux passent par le même `PageController`.
        itemBuilder: (context, index) => AnimatedBuilder(
          animation: _pages,
          builder: (context, child) {
            final page = _pages.hasClients
                ? (_pages.page ?? _pages.initialPage.toDouble())
                : _pages.initialPage.toDouble();
            final opacity = (1 - (page - index).abs()).clamp(0.0, 1.0);
            return Opacity(opacity: opacity, child: child);
          },
          child: _SlideView(
            slide: slides[index],
            index: index,
            count: slides.length,
            onNext: () => _next(slides.length),
            onSkip: widget.onDone,
          ),
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
  /// bleu digital dessous — même traitement typographique que côté
  /// passager.
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
                const SizedBox(height: 16),
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
                const SizedBox(height: 16),
                if (_isLast)
                  KoraButton(label: 'Commencer', onPressed: onNext)
                else
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      // `ghost` et non un `TextButton` : sans contour, le
                      // libellé flottait sur le fond et ne se lisait pas
                      // comme un bouton, dans les deux thèmes.
                      SizedBox(
                        width: 132,
                        child: KoraButton(
                          label: 'Passer',
                          variant: KoraButtonVariant.ghost,
                          onPressed: onSkip,
                        ),
                      ),
                      SizedBox(
                        width: 132,
                        child: KoraButton(
                          label: 'Suivant',
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
