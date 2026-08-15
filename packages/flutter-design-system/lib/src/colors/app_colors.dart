import 'package:flutter/material.dart';

/// Palette de couleurs officielle KORA EXPRESS.
///
/// Toutes ces valeurs sont extraites directement du prototype de référence
/// (`docs/maquettes/kora-app-passenger/css/style.css`, bloc `:root`). Ne jamais écrire une
/// couleur "en dur" (ex: `Color(0xFF001D4E)`) ailleurs dans le code : on
/// passe toujours par une constante définie ici.
///
/// Astuce pour un débutant : si demain le service marketing change le bleu
/// KORA, il suffit de modifier UNE seule ligne ici, et toutes les apps qui
/// dépendent de `flutter_design_system` se mettent à jour automatiquement.
class AppColors {
  const AppColors._();

  // --- Couleurs de marque (identiques en light & dark) ---

  /// Bleu marine KORA — couleur principale de la marque.
  /// CSS : --kora: #001D4E
  static const Color kora = Color(0xFF001D4E);

  /// Jaune/or KORA — couleur d'accent (boutons principaux, highlights).
  /// CSS : --gold: #F6B000
  static const Color gold = Color(0xFFF6B000);

  /// Bleu "digital" — utilisé pour les liens et accents secondaires.
  /// CSS : --digital: #0057B8
  static const Color digital = Color(0xFF0057B8);

  static const Color white = Color(0xFFFFFFFF);

  // --- Couleurs de fond & texte (mode clair) ---

  /// Fond gris très clair utilisé derrière les cards / sections.
  /// CSS : --mist: #F4F6F8
  static const Color mist = Color(0xFFF4F6F8);

  /// Couleur de texte principal en mode clair.
  /// CSS : --graphite: #111827
  static const Color graphite = Color(0xFF111827);

  /// Texte secondaire / atténué en mode clair (graphite à 60% d'opacité).
  static const Color mutedLight = Color(0x99111827);

  /// Couleur des séparateurs / bordures en mode clair (kora à 10%).
  ///
  /// Source de vérité : `--border`/`--input: rgba(0, 29, 78, 0.1)`, tokens
  /// réellement livrés dans l'app web (`apps/kora-ops-web/resources/css/
  /// app.css`). La maquette HTML statique passager (`docs/maquettes/
  /// kora-app-passenger/css/style.css`, `:root`) affichait `.11` — écart
  /// mineur avec l'app réelle, non retenu ici au profit de la valeur
  /// effectivement livrée (et aussi celle de la maquette ops-web, `.10`).
  static const Color lineLight = Color(0x1A001D4E);

  /// Bordure des champs de saisie (KoraInput) en mode clair.
  ///
  /// Distincte de [lineLight] (séparateurs génériques, 10%) : la maquette
  /// dédie une valeur légèrement plus marquée aux contours de champ pour
  /// qu'ils restent visibles sur un fond blanc.
  /// CSS : `.field-control { border: 1px solid rgba(0,29,78,.12) }`
  /// (`docs/maquettes/kora-app-passenger/css/style.css`, L339-347).
  static const Color inputBorderLight = Color(0x1F001D4E);

  /// Ligne de trajet (DepartureCard) — toujours cette valeur claire, quel
  /// que soit le thème de l'app : voir la note sur [DepartureCard], dont
  /// la carte reste blanche en toutes circonstances (comme [TicketView]).
  /// CSS : `.route-line { background: rgba(0,29,78,.16) }`
  /// (`docs/maquettes/kora-app-passenger/css/style.css`, L553).
  static const Color routeLineLight = Color(0x29001D4E);

  /// Bordure de [ConnectionResultCard] — toujours cette valeur claire,
  /// même remarque que [routeLineLight] : la carte reste blanche quel que
  /// soit le thème.
  /// CSS : `.connection-result-card { border: 1px solid rgba(246,176,0,.55) }`
  /// (`docs/maquettes/kora-app-passenger/css/style.css`, L2176).
  static const Color connectionBorderLight = Color(0x8CF6B000);

  // --- SeatMap (`.premium-bus-shell` / `.seat`) — toujours ces valeurs
  // claires, même remarque : le plan de sièges reste blanc quel que soit
  // le thème (`docs/maquettes/kora-app-passenger/css/style.css`,
  // L565-592 et L1541-1558 ; aucune de ces classes n'est touchée par les
  // 6 règles `.dark-mode` du fichier).

  /// Bordure du panneau (bus cabin). CSS : `.premium-bus-shell { border:
  /// 1px solid rgba(0,29,78,.08) }`.
  static const Color seatPanelBorderLight = Color(0x14001D4E);

  /// Bordure d'un siège libre. CSS : `.seat { border: 1px solid
  /// rgba(0,29,78,.15) }`.
  static const Color seatBorderLight = Color(0x26001D4E);

  /// Fond d'un siège "Réservé" (`held` — réservation temporaire en cours,
  /// minuteur affiché). CSS : `.seat.held { background: rgba(246,176,0,.18) }`.
  static const Color seatHeldBackgroundLight = Color(0x2EF6B000);

  /// Texte d'un siège "Bloqué" (barré).
  ///
  /// Le CSS descend à `rgba(17,24,39,.35)` (`.seat.blocked`), mais à cette
  /// opacité le trait de barré — un filet d'un pixel — devient illisible
  /// sur l'écran d'un téléphone, alors que c'est lui qui porte
  /// l'information « siège indisponible ». On remonte à 55 % : le siège
  /// reste visiblement éteint par rapport aux autres, sans que la barre
  /// disparaisse.
  static const Color seatBlockedTextLight = Color(0x8C111827);

  // --- Couleurs de fond & texte (mode sombre) ---
  //
  // Le prototype HTML applique `.dark-mode` en gardant le bleu KORA comme
  // fond d'écran principal (voir css/style.css ~L1714). On garde cette
  // logique : en dark mode, le "fond de page" devient le bleu marine KORA
  // et les cards passent sur une variante plus soutenue de ce bleu.

  /// Fond d'écran principal en mode sombre (repris du `.dark-mode` du CSS).
  static const Color backgroundDark = kora;

  /// Fond des cards en mode sombre (légèrement plus clair que le fond,
  /// pour créer de la profondeur — pratique courante en dark mode).
  static const Color surfaceDark = Color(0xFF00153E);

  /// Texte principal en mode sombre.
  static const Color textDark = white;

  /// Texte secondaire en mode sombre.
  static const Color mutedDark = Color(0x8CFFFFFF);

  /// Séparateurs / bordures en mode sombre.
  static const Color lineDark = Color(0x24FFFFFF);

  // --- États sémantiques (succès / erreur), utiles pour Wallet/colis ---

  /// Vert utilisé pour les statuts positifs (ex: livraison réussie).
  static const Color success = Color(0xFF2D7A51);

  /// Rouge utilisé pour les statuts négatifs (ex: incident, erreur).
  static const Color danger = Color(0xFF9C2430);
}
