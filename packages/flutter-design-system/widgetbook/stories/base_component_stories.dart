import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:widgetbook/widgetbook.dart';

import '../widgets/story_scaffold.dart';

/// Stories des composants de base du design system (pas de logique
/// métier KORA) : bouton, champ de saisie, card, badge, alerte,
/// indicateur de chargement et brique de squelette.
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
          name: 'Avec icône — avant le libellé',
          builder: (context) => StoryScaffold(
            child: KoraButton(
              label: 'Payer maintenant',
              icon: Icons.lock_outline,
              onPressed: () {},
            ),
          ),
        ),
        WidgetbookUseCase(
          // L'icône qui indique la progression se place APRÈS le libellé,
          // contrairement à celle qui qualifie l'action (`🔒 Payer
          // maintenant`, ci-dessus).
          name: 'Avec icône — après le libellé',
          builder: (context) => StoryScaffold(
            child: KoraButton(
              label: 'Continuer',
              icon: Icons.arrow_forward_rounded,
              iconPosition: KoraButtonIconPosition.trailing,
              onPressed: () {},
            ),
          ),
        ),
        WidgetbookUseCase(
          // Libellé et icône aux deux bouts du bouton — la disposition des
          // barres de validation (`.seat-checkout button`, `.welcome-primary`).
          name: 'Avec icône — aux deux bouts',
          builder: (context) => StoryScaffold(
            child: KoraButton(
              label: 'Continuer',
              icon: Icons.arrow_forward_rounded,
              iconPosition: KoraButtonIconPosition.edges,
              onPressed: () {},
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'État — chargement',
          builder: (context) => StoryScaffold(
            child: KoraButton(
              label: 'Continuer',
              isLoading: true,
              onPressed: () {},
            ),
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
            child: KoraInput(
              label: 'Mot de passe',
              hint: '••••••••',
              obscureText: true,
            ),
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
            child: KoraInput(
              label: 'Code promo',
              hint: 'Indisponible',
              enabled: false,
            ),
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
            child: KoraCard(onTap: () {}, child: const Text('Tape-moi')),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'KoraTag',
      useCases: [
        WidgetbookUseCase(
          name: 'Neutral',
          builder: (context) =>
              const StoryScaffold(child: KoraTag(label: 'En attente')),
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
            child: KoraBadge(
              child: Icon(Icons.notifications_outlined, size: 28),
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Avec compteur',
          builder: (context) => const StoryScaffold(
            child: KoraBadge(
              count: 3,
              child: Icon(Icons.mail_outline, size: 28),
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Compteur > 999',
          builder: (context) => const StoryScaffold(
            child: KoraBadge(
              count: 1280,
              child: Icon(Icons.mail_outline, size: 28),
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Sur KoraIconButton',
          builder: (context) => StoryScaffold(
            child: KoraIconButton(
              icon: Icons.notifications_outlined,
              showBadge: true,
              onTap: () {},
            ),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'KoraLoadingIndicator',
      useCases: [
        WidgetbookUseCase(
          name: 'Spinner — petit (20)',
          builder: (context) =>
              const StoryScaffold(child: KoraLoadingIndicator(size: 20)),
        ),
        WidgetbookUseCase(
          name: 'Spinner — moyen (28, défaut)',
          builder: (context) =>
              const StoryScaffold(child: KoraLoadingIndicator()),
        ),
        WidgetbookUseCase(
          name: 'Spinner — grand (44)',
          builder: (context) =>
              const StoryScaffold(child: KoraLoadingIndicator(size: 44)),
        ),
        WidgetbookUseCase(
          name: 'Skeleton — 1 ligne',
          builder: (context) => const StoryScaffold(
            child: SizedBox(
              width: 260,
              child: KoraLoadingIndicator(
                variant: KoraLoadingIndicatorVariant.skeleton,
              ),
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
    WidgetbookComponent(
      name: 'KoraAlert',
      useCases: [
        // `.connection-alert` du prototype (`js/app.js`, L613).
        WidgetbookUseCase(
          name: 'Info — doré translucide',
          builder: (context) => const StoryScaffold(
            child: KoraAlert(
              title: 'Changement de bus à Bohicon',
              message: 'Temps prévu : 35 minutes · assistance KORA sur place',
            ),
          ),
        ),
        // `.offline-ride-banner` (`js/app.js`, L1762).
        WidgetbookUseCase(
          name: 'Warning — doré plein',
          builder: (context) => const StoryScaffold(
            child: KoraAlert(
              variant: KoraAlertVariant.warning,
              icon: Icons.wifi_off_rounded,
              title: 'Mode hors ligne',
              message:
                  'Course enregistrée · synchronisation automatique au '
                  'retour du réseau',
            ),
          ),
        ),
        // `.incident-alert` (`js/app.js`, L977).
        WidgetbookUseCase(
          name: 'Critical — bleu KORA',
          builder: (context) => const StoryScaffold(
            child: KoraAlert(
              variant: KoraAlertVariant.critical,
              title: 'Votre signalement sera prioritaire',
              message:
                  'KORA ouvre un dossier et informe immédiatement '
                  'l’expéditeur.',
            ),
          ),
        ),
        // Un titre qui se suffit à lui-même : pas de message, pas de
        // deuxième ligne vide.
        WidgetbookUseCase(
          name: 'Titre seul',
          builder: (context) => const StoryScaffold(
            child: KoraAlert(
              icon: Icons.schedule_rounded,
              title: 'Embarquement 30 min avant le départ',
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Avec action',
          builder: (context) => StoryScaffold(
            child: KoraAlert(
              variant: KoraAlertVariant.critical,
              title: 'Paiement en attente',
              message: 'Votre place est bloquée encore 9 minutes.',
              trailing: KoraIconButton(
                icon: Icons.chevron_right_rounded,
                size: 34,
                onTap: () {},
              ),
            ),
          ),
        ),
        // Les trois tons côte à côte : c'est la comparaison qui dit
        // laquelle choisir, pas la définition de chacune prise seule.
        WidgetbookUseCase(
          name: 'Les trois tons',
          builder: (context) => const StoryScaffold(
            child: Column(
              children: [
                KoraAlert(
                  title: 'Changement de bus à Bohicon',
                  message: 'Temps prévu : 35 minutes',
                ),
                SizedBox(height: KoraSpacing.md),
                KoraAlert(
                  variant: KoraAlertVariant.warning,
                  icon: Icons.wifi_off_rounded,
                  title: 'Mode hors ligne',
                  message: 'Synchronisation au retour du réseau',
                ),
                SizedBox(height: KoraSpacing.md),
                KoraAlert(
                  variant: KoraAlertVariant.critical,
                  title: 'Votre signalement sera prioritaire',
                  message: 'KORA ouvre un dossier immédiatement.',
                ),
              ],
            ),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'KoraSkeleton',
      useCases: [
        WidgetbookUseCase(
          name: 'Ligne de texte',
          builder: (context) => const StoryScaffold(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                KoraSkeleton(height: 18, width: 160),
                SizedBox(height: KoraSpacing.sm),
                KoraSkeleton(height: 12),
                SizedBox(height: KoraSpacing.sm),
                KoraSkeleton(height: 12, width: 220),
              ],
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Pastille et bloc',
          builder: (context) => const StoryScaffold(
            child: Row(
              children: [
                KoraSkeleton(width: 38, height: 38, radius: 12),
                SizedBox(width: KoraSpacing.md),
                Expanded(
                  child: KoraSkeleton(height: 58, radius: KoraRadius.lg),
                ),
              ],
            ),
          ),
        ),
        // `onWhite` : sur une carte blanche, le bloc ne doit pas
        // s'éclaircir avec le thème sombre — il disparaîtrait.
        WidgetbookUseCase(
          name: 'Sur une carte blanche (onWhite)',
          builder: (context) => StoryScaffold(
            child: Container(
              padding: const EdgeInsets.all(KoraSpacing.lg),
              decoration: BoxDecoration(
                color: AppColors.white,
                borderRadius: BorderRadius.circular(KoraRadius.lg),
                boxShadow: AppShadows.card,
              ),
              child: const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  KoraSkeleton(height: 18, width: 120, onWhite: true),
                  SizedBox(height: KoraSpacing.sm),
                  KoraSkeleton(height: 12, onWhite: true),
                ],
              ),
            ),
          ),
        ),
      ],
    ),
    WidgetbookComponent(
      name: 'KoraToast',
      useCases: [
        // Le message tel qu'il s'affiche, sorti de son coin d'écran pour
        // pouvoir l'inspecter.
        WidgetbookUseCase(
          name: 'Message',
          builder: (context) => const StoryScaffold(
            child: Align(
              alignment: Alignment.centerRight,
              child: KoraToast(message: 'Un nouveau code a été envoyé.'),
            ),
          ),
        ),
        WidgetbookUseCase(
          name: 'Message long (deux lignes)',
          builder: (context) => const StoryScaffold(
            child: Align(
              alignment: Alignment.centerRight,
              child: KoraToast(
                message:
                    'Les 10 billets ont été préparés pour WhatsApp et vous '
                    'seront envoyés dans un instant.',
              ),
            ),
          ),
        ),
        // Le comportement réel : posé en bas à droite par-dessus l'écran,
        // empilé si on enchaîne, et retiré tout seul au bout de 2,6 s.
        WidgetbookUseCase(
          name: 'En situation (KoraToaster)',
          builder: (context) => StoryScaffold(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                KoraButton(
                  label: 'Renvoyer le code',
                  onPressed: () => KoraToaster.show(
                    context,
                    'Un nouveau code a été envoyé.',
                  ),
                ),
                const SizedBox(height: KoraSpacing.md),
                KoraButton(
                  label: 'Inverser les villes',
                  variant: KoraButtonVariant.ghost,
                  onPressed: () => KoraToaster.show(
                    context,
                    'Origine et destination inversées',
                  ),
                ),
                const SizedBox(height: KoraSpacing.xl),
                Text(
                  'Enchaînez les deux boutons : les messages s’empilent au '
                  'lieu de se remplacer.',
                  style: AppTextStyles.lead(context.koraColors.textMuted),
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  ],
);
