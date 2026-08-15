import 'package:flutter/material.dart';

import '../colors/app_colors.dart';

/// Tokens de dimension (espacements, rayons, ombres) de l'application.
///
/// Objectif : ne jamais écrire `padding: 16` ou `borderRadius: 20` au hasard
/// dans un widget. On utilise toujours une constante d'ici pour garder une
/// cohérence visuelle sur toute l'app (c'est exactement ce que fait le
/// prototype avec ses variables CSS).
class AppRadius {
  const AppRadius._();

  static const double sm = 10; // petits éléments (tags, chips)

  /// Boutons. CSS : `.primary, .secondary, .ghost, .danger-button`
  /// (`border-radius: 15px`) — confondu un temps avec les champs de
  /// saisie (voir [input] ci-dessous, qui a sa propre valeur : 14px).
  static const double md = 15;

  static const double lg = 20; // cards standards
  static const double xl = 24; // grandes sections / bottom sheets
  static const double pill = 999; // badges, pastilles, boutons ronds

  /// Champs de saisie (KoraInput). CSS : `.field-control { border-radius:
  /// 14px }` (`docs/maquettes/kora-app-passenger/css/style.css`, L339-347)
  /// — proche de [md] (15, boutons) mais pas identique ; les deux
  /// réutilisaient [md] par erreur avant que l'écart ne soit remarqué.
  static const double input = 14;

  /// Rayon "socle" du design system.
  ///
  /// Source de vérité : `--radius: 0.75rem` (12px), le token Tailwind
  /// réellement livré dans l'app web (`apps/kora-ops-web/resources/css/
  /// app.css`, bloc `@theme`/`:root`). La maquette HTML statique
  /// (`docs/maquettes/kora-ops-web/css/style.css`, `:root`, L1) affichait
  /// encore `--radius: 18px` à ce moment-là — valeur dépassée par l'app
  /// réellement construite, donc pas retenue ici.
  /// Le reste du CSS (prototypes comme app réelle) utilise des rayons en
  /// dur au cas par cas, d'où l'échelle `sm/md/lg/xl` ci-dessus, dérivée
  /// des valeurs récurrentes de la maquette passager plutôt que de ce
  /// token unique.
  /// À utiliser quand un composant correspond à un élément "socle" (KPI
  /// cards, panels...) plutôt qu'à `lg` (20) par défaut.
  static const double base = 12;
}

class AppSpacing {
  const AppSpacing._();

  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 24;
  static const double xxl = 32;
}

/// Ombres portées, reprises des `box-shadow` du prototype CSS.
class AppShadows {
  const AppShadows._();

  /// Ombre douce utilisée sous les cards blanches en mode clair.
  /// CSS de référence : 0 8px 25px rgba(0,29,78,.055)
  static List<BoxShadow> card = [
    BoxShadow(
      color: AppColors.kora.withValues(alpha: 0.055),
      offset: const Offset(0, 8),
      blurRadius: 25,
    ),
  ];

  /// Ombre du bouton rond ([KoraIconButton]) : c'est elle qui détache la
  /// pastille du fond, y compris quand les deux sont blancs — cas courant,
  /// le bouton étant souvent posé sur une card.
  /// CSS de référence : `.icon-button { box-shadow: 0 4px 15px
  /// rgba(0,29,78,.08) }` (`docs/maquettes/kora-app-passenger/css/
  /// style.css`, L231-244).
  static List<BoxShadow> iconButton = [
    BoxShadow(
      color: AppColors.kora.withValues(alpha: 0.08),
      offset: const Offset(0, 4),
      blurRadius: 15,
    ),
  ];

  /// Ombre plus marquée utilisée sous le bouton principal (gold).
  /// CSS de référence : 0 9px 22px rgba(246,176,0,.2)
  static List<BoxShadow> primaryButton = [
    BoxShadow(
      color: AppColors.gold.withValues(alpha: 0.2),
      offset: const Offset(0, 9),
      blurRadius: 22,
    ),
  ];

  /// Ombre "socle" du design system — extraite telle quelle de la
  /// variable CSS partagée `--shadow: 0 24px 60px rgba(0,29,78,.14)`,
  /// déclarée sous le commentaire `/* Fondations KORA partagées */` dans
  /// `kora-ops-web/css/style.css` (L41-43, où elle écrase volontairement
  /// la valeur provisoire du premier `:root`) et réutilisée telle quelle
  /// dans plusieurs composants flottants (menu de rôle, filtres avancés).
  /// C'est l'ombre à utiliser par défaut pour tout élément élevé qui n'a
  /// pas d'ombre plus spécifique définie ci-dessus ([card], [primaryButton]).
  static List<BoxShadow> base = [
    BoxShadow(
      color: AppColors.kora.withValues(alpha: 0.14),
      offset: const Offset(0, 24),
      blurRadius: 60,
    ),
  ];

  /// Ombre portée du billet numérique ([TicketView]).
  /// CSS de référence : `.digital-ticket { box-shadow: 0 20px 45px rgba(0,29,78,.2) }`
  /// (`docs/maquettes/kora-app-passenger/css/style.css`, L619-626).
  static List<BoxShadow> ticket = [
    BoxShadow(
      color: AppColors.kora.withValues(alpha: 0.2),
      offset: const Offset(0, 20),
      blurRadius: 45,
    ),
  ];

  /// Pas d'ombre en mode sombre : le contraste vient des couleurs, pas
  /// des ombres (une ombre est peu visible sur un fond déjà sombre).
  static const List<BoxShadow> none = [];
}
