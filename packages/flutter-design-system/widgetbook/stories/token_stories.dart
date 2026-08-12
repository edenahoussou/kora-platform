import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:widgetbook/widgetbook.dart';

import '../widgets/story_scaffold.dart';

/// Pages de référence pour les tokens bruts (couleurs, typographie,
/// espacements, rayons, ombres) — pas des composants interactifs, juste
/// un aperçu visuel pour validation design.
final tokenStories = WidgetbookFolder(
  name: 'Tokens',
  children: [
    WidgetbookComponent(
      name: 'Couleurs',
      useCases: [
        WidgetbookUseCase(
          name: 'Palette',
          builder: (context) => const StoryScaffold(child: _ColorSwatches()),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'Typographie',
      useCases: [
        WidgetbookUseCase(
          name: 'Échelle',
          builder: (context) =>
              const StoryScaffold(child: _TypographySpecimen()),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'Espacements',
      useCases: [
        WidgetbookUseCase(
          name: 'Échelle',
          builder: (context) => const StoryScaffold(child: _SpacingScale()),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'Rayons & Ombres',
      useCases: [
        WidgetbookUseCase(
          name: 'Échelle',
          builder: (context) =>
              const StoryScaffold(child: _RadiusAndShadowScale()),
        ),
      ],
    ),
  ],
);

class _ColorSwatches extends StatelessWidget {
  const _ColorSwatches();

  static const _entries = [
    (name: 'kora — primaire', hex: '#001D4E', color: AppColors.kora),
    (name: 'gold — secondaire', hex: '#F6B000', color: AppColors.gold),
    (name: 'digital — accent', hex: '#0057B8', color: AppColors.digital),
    (name: 'mist', hex: '#F4F6F8', color: AppColors.mist),
    (name: 'graphite', hex: '#111827', color: AppColors.graphite),
    (name: 'white', hex: '#FFFFFF', color: AppColors.white),
    (name: 'success', hex: '#2D7A51', color: AppColors.success),
    (name: 'danger', hex: '#9C2430', color: AppColors.danger),
  ];

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 16,
      runSpacing: 16,
      children: [
        for (final entry in _entries)
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 120,
                height: 80,
                decoration: BoxDecoration(
                  color: entry.color,
                  borderRadius: BorderRadius.circular(KoraRadius.md),
                  border: Border.all(color: Colors.black12),
                ),
              ),
              const SizedBox(height: 6),
              Text(
                entry.name,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
              Text(
                entry.hex,
                style: TextStyle(
                  fontSize: 11,
                  color: context.koraColors.textMuted,
                ),
              ),
            ],
          ),
      ],
    );
  }
}

class _TypographySpecimen extends StatelessWidget {
  const _TypographySpecimen();

  @override
  Widget build(BuildContext context) {
    // Couleur du thème actif (KoraColors.light/dark), pas une valeur en
    // dur : sinon le texte reste noir sur fond navy en mode sombre (voir
    // capture de bug — texte quasi invisible sur le thème "Sombre" du
    // sélecteur Widgetbook).
    final color = context.koraColors.textPrimary;
    const sample = 'KORA EXPRESS — Cotonou → Porto-Novo';

    final specimens = <(String, TextStyle)>[
      ('heading1 — 23px / 800', KoraTypography.heading1(color)),
      ('heading2 — 17px / 800', KoraTypography.heading2(color)),
      ('titleMedium — 15px / 800', KoraTypography.titleMedium(color)),
      ('lead — 13px / 400', KoraTypography.lead(color)),
      ('body — 12px / 400', KoraTypography.body(color)),
      ('caption — 11px / 500', KoraTypography.caption(color)),
      ('eyebrow — 10px / 800', KoraTypography.eyebrow(color)),
      ('button — 14px / 900', KoraTypography.button(color)),
      ('tag — 9px / 900', KoraTypography.tag(color)),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final specimen in specimens)
          Padding(
            padding: const EdgeInsets.only(bottom: 22),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  specimen.$1,
                  style: TextStyle(
                    fontSize: 10,
                    color: context.koraColors.textMuted,
                  ),
                ),
                const SizedBox(height: 4),
                Text(sample, style: specimen.$2),
              ],
            ),
          ),
      ],
    );
  }
}

class _SpacingScale extends StatelessWidget {
  const _SpacingScale();

  static const _entries = [
    ('xs (4)', KoraSpacing.xs),
    ('sm (8)', KoraSpacing.sm),
    ('md (12)', KoraSpacing.md),
    ('lg (16)', KoraSpacing.lg),
    ('xl (24)', KoraSpacing.xl),
    ('xxl (32)', KoraSpacing.xxl),
  ];

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final entry in _entries)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Row(
              children: [
                SizedBox(width: 90, child: Text(entry.$1)),
                Container(width: entry.$2, height: 20, color: AppColors.gold),
              ],
            ),
          ),
      ],
    );
  }
}

class _RadiusAndShadowScale extends StatelessWidget {
  const _RadiusAndShadowScale();

  static const _radii = [
    ('sm (10)', KoraRadius.sm),
    ('base (12 — socle web)', KoraRadius.base),
    ('input (14 — KoraInput)', KoraRadius.input),
    ('md (15 — boutons)', KoraRadius.md),
    ('lg (20)', KoraRadius.lg),
    ('xl (24)', KoraRadius.xl),
    ('pill (999)', KoraRadius.pill),
  ];

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: 16,
          runSpacing: 16,
          children: [
            for (final entry in _radii)
              Column(
                children: [
                  Container(
                    width: 70,
                    height: 70,
                    decoration: BoxDecoration(
                      // `koraColors.surface` (pas `AppColors.mist` en dur) :
                      // sinon en thème clair le carré est mist-sur-mist,
                      // exactement la même couleur que le fond de la page
                      // (`koraColors.background`) — invisible, comme le
                      // bug repéré plus haut sur la typographie. La
                      // bordure garantit un contour visible dans les deux
                      // thèmes même si surface/background se ressemblent.
                      color: koraColors.surface,
                      border: Border.all(color: koraColors.line),
                      borderRadius: BorderRadius.circular(
                        math.min(entry.$2, 35),
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(entry.$1, style: const TextStyle(fontSize: 11)),
                ],
              ),
          ],
        ),
        const SizedBox(height: 32),
        const Text('Shadows', style: TextStyle(fontWeight: FontWeight.bold)),
        const SizedBox(height: 16),
        Wrap(
          spacing: 24,
          runSpacing: 24,
          children: [
            _ShadowSample('card', AppShadows.card),
            _ShadowSample('primaryButton', AppShadows.primaryButton),
            _ShadowSample('base (socle web)', AppShadows.base),
          ],
        ),
      ],
    );
  }
}

class _ShadowSample extends StatelessWidget {
  const _ShadowSample(this.label, this.shadow);

  final String label;
  final List<BoxShadow> shadow;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          width: 110,
          height: 70,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            boxShadow: shadow,
          ),
        ),
        const SizedBox(height: 20),
        Text(label, style: const TextStyle(fontSize: 11)),
      ],
    );
  }
}
