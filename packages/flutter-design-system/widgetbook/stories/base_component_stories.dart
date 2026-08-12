import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:widgetbook/widgetbook.dart';

import '../widgets/story_scaffold.dart';

/// Stories des composants de base du design system (pas de logique
/// métier KORA) : bouton, champ de saisie, card, badge, indicateur de
/// chargement.
final baseComponentStories = WidgetbookFolder(
  name: 'Composants de base',
  children: [
    WidgetbookComponent(
      name: 'KoraButton',
      useCases: [
        WidgetbookUseCase(
          name: 'Primary',
          builder: (context) => StoryScaffold(
            child: KoraButton(label: 'Continuer', onPressed: () {}),
          ),
        ),
        WidgetbookUseCase(
          name: 'Secondary',
          builder: (context) => StoryScaffold(
            child: KoraButton(
              label: 'Voir les détails',
              variant: KoraButtonVariant.secondary,
              onPressed: () {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Ghost',
          builder: (context) => StoryScaffold(
            child: KoraButton(
              label: 'Annuler',
              variant: KoraButtonVariant.ghost,
              onPressed: () {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Danger',
          builder: (context) => StoryScaffold(
            child: KoraButton(
              label: 'Supprimer le trajet',
              variant: KoraButtonVariant.danger,
              onPressed: () {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Avec icône',
          builder: (context) => StoryScaffold(
            child: KoraButton(
              label: 'Payer maintenant',
              icon: Icons.lock_outline,
              onPressed: () {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'État — chargement',
          builder: (context) => StoryScaffold(
            child: KoraButton(label: 'Continuer', isLoading: true, onPressed: () {}),
          ),
        ),
        WidgetbookUseCase(
          name: 'État — désactivé',
          builder: (context) => const StoryScaffold(
            child: KoraButton(label: 'Continuer', onPressed: null),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'KoraInput',
      useCases: [
        WidgetbookUseCase(
          name: 'Par défaut',
          builder: (context) => const StoryScaffold(
            child: KoraInput(label: 'Adresse e-mail', hint: 'toi@exemple.com'),
          ),
        ),
        WidgetbookUseCase(
          name: 'Avec icône',
          builder: (context) => const StoryScaffold(
            child: KoraInput(
              label: 'Numéro de téléphone',
              hint: '+225 07 00 00 00',
              prefixIcon: Icons.call_outlined,
              keyboardType: TextInputType.phone,
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Mot de passe',
          builder: (context) => const StoryScaffold(
            child: KoraInput(label: 'Mot de passe', hint: '••••••••', obscureText: true),
          ),
        ),
        WidgetbookUseCase(
          name: 'État — erreur',
          builder: (context) => const StoryScaffold(
            child: KoraInput(
              label: 'Mot de passe',
              obscureText: true,
              errorText: 'Minimum 8 caractères',
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'État — désactivé',
          builder: (context) => const StoryScaffold(
            child: KoraInput(label: 'Code promo', hint: 'Indisponible', enabled: false),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'KoraCard',
      useCases: [
        WidgetbookUseCase(
          name: 'Surface',
          builder: (context) => const StoryScaffold(
            child: KoraCard(child: Text('Contenu de la card')),
          ),
        ),
        WidgetbookUseCase(
          name: 'Navy',
          builder: (context) => const StoryScaffold(
            child: KoraCard(
              variant: KoraCardVariant.navy,
              child: Text('Card mise en avant'),
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Gold',
          builder: (context) => const StoryScaffold(
            child: KoraCard(
              variant: KoraCardVariant.gold,
              child: Text('Card promotionnelle'),
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Mist',
          builder: (context) => const StoryScaffold(
            child: KoraCard(
              variant: KoraCardVariant.mist,
              child: Text('Card discrète'),
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'État — cliquable',
          builder: (context) => StoryScaffold(
            child: KoraCard(
              onTap: () {},
              child: const Text('Tape-moi'),
            ),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'KoraTag',
      useCases: [
        WidgetbookUseCase(
          name: 'Neutral',
          builder: (context) => const StoryScaffold(
            child: KoraTag(label: 'En attente'),
          ),
        ),
        WidgetbookUseCase(
          name: 'Gold',
          builder: (context) => const StoryScaffold(
            child: KoraTag(label: 'VIP', variant: KoraTagVariant.gold),
          ),
        ),
        WidgetbookUseCase(
          name: 'Blue',
          builder: (context) => const StoryScaffold(
            child: KoraTag(label: 'En cours', variant: KoraTagVariant.blue),
          ),
        ),
        WidgetbookUseCase(
          name: 'Outline',
          builder: (context) => const StoryScaffold(
            child: KoraTag(label: 'Livré', variant: KoraTagVariant.outline),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'KoraBadge',
      useCases: [
        WidgetbookUseCase(
          name: 'Point simple',
          builder: (context) => const StoryScaffold(
            child: KoraBadge(child: Icon(Icons.notifications_outlined, size: 28)),
          ),
        ),
        WidgetbookUseCase(
          name: 'Avec compteur',
          builder: (context) => const StoryScaffold(
            child: KoraBadge(count: 3, child: Icon(Icons.mail_outline, size: 28)),
          ),
        ),
        WidgetbookUseCase(
          name: 'Compteur > 999',
          builder: (context) => const StoryScaffold(
            child: KoraBadge(count: 1280, child: Icon(Icons.mail_outline, size: 28)),
          ),
        ),
        WidgetbookUseCase(
          name: 'Sur KoraIconButton',
          builder: (context) => StoryScaffold(
            child: KoraIconButton(icon: Icons.notifications_outlined, showBadge: true, onTap: () {}),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'KoraLoadingIndicator',
      useCases: [
        WidgetbookUseCase(
          name: 'Spinner — petit (20)',
          builder: (context) => const StoryScaffold(
            child: KoraLoadingIndicator(size: 20),
          ),
        ),
        WidgetbookUseCase(
          name: 'Spinner — moyen (28, défaut)',
          builder: (context) => const StoryScaffold(
            child: KoraLoadingIndicator(),
          ),
        ),
        WidgetbookUseCase(
          name: 'Spinner — grand (44)',
          builder: (context) => const StoryScaffold(
            child: KoraLoadingIndicator(size: 44),
          ),
        ),
        WidgetbookUseCase(
          name: 'Skeleton — 1 ligne',
          builder: (context) => const StoryScaffold(
            child: SizedBox(
              width: 260,
              child: KoraLoadingIndicator(variant: KoraLoadingIndicatorVariant.skeleton),
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Skeleton — 3 lignes',
          builder: (context) => const StoryScaffold(
            child: SizedBox(
              width: 260,
              child: KoraLoadingIndicator(
                variant: KoraLoadingIndicatorVariant.skeleton,
                skeletonLines: 3,
              ),
            ),
          ),
        ),
      ],
    ),
  ],
);
