/// Design tokens KORA EXPRESS — vue d'ensemble centralisée.
///
/// Ce fichier ne redéfinit aucune valeur : il ré-exporte les classes de
/// tokens déjà définies sous `src/`, sous les noms attendus par la
/// convention `Kora*` du design system (`KoraTypography`, `KoraSpacing`,
/// `KoraRadius`). Une app consommatrice peut importer soit ce fichier,
/// soit le barrel principal `flutter_design_system.dart` (qui l'inclut
/// déjà et ajoute les widgets/icônes/dialogs par-dessus).
///
/// **Source de vérité pour la parité web** : chaque token ci-dessous a
/// été extrait (et vérifié) contre deux prototypes du monorepo :
/// - `docs/maquettes/kora-app-passenger/css/style.css` — prototype passager (`:root`, L1-11)
/// - `docs/maquettes/kora-ops-web/css/style.css` — OPS web, en
///   particulier le bloc `/* Fondations KORA partagées */` (L41-43), qui
///   est la trace la plus proche d'un jeu de tokens formellement déclaré
///   côté web.
///
/// **À propos du nommage des couleurs** : la spec du design system
/// désigne le token couleur par `KoraColors`. Ce nom est déjà pris par la
/// classe [KoraColors] existante (`src/colors/kora_colors.dart`), qui
/// représente les couleurs **contextuelles** (fond/surface/texte, qui
/// changent selon le thème clair/sombre) — pas les couleurs de marque.
/// Les couleurs de marque fixes (primaire = bleu KORA, secondaire = or,
/// accent = digital...) vivent dans [AppColors]. Les deux sont exportées
/// ci-dessous et se complètent : [AppColors] répond à "quelle couleur",
/// [KoraColors] répond à "clair ou sombre".
library;

import 'src/dimens/app_dimens.dart';
import 'src/typography/app_text_styles.dart';

export 'src/colors/app_colors.dart';
export 'src/colors/kora_colors.dart';
export 'src/dimens/app_dimens.dart';
export 'src/motion/app_motion.dart';
export 'src/typography/app_text_styles.dart';

/// Alias : la spec design system désigne l'échelle typographique par
/// `KoraTypography`. En interne, la classe s'appelle [AppTextStyles] car
/// elle existait avant la formalisation de la convention `Kora*` — les
/// deux noms pointent vers la même classe, aucune valeur dupliquée.
typedef KoraTypography = AppTextStyles;

/// Alias pour l'échelle d'espacements — voir [AppSpacing].
typedef KoraSpacing = AppSpacing;

/// Alias pour l'échelle de rayons de bordure — voir [AppRadius].
typedef KoraRadius = AppRadius;
