/// Design system KORA EXPRESS — point d'entrée public du package.
///
/// Une app consommatrice n'importe jamais un fichier sous `lib/src/`
/// directement : elle importe uniquement ce barrel file.
///
/// ```dart
/// import 'package:flutter_design_system/flutter_design_system.dart';
/// ```
library;

export 'src/dialogs/kora_dialogs.dart';
export 'src/icons/kora_nav_icon.dart';
export 'src/icons/kora_vehicle_icon.dart';
export 'src/theme/app_theme.dart';
export 'src/widgets/business/connection_result_card.dart';
export 'src/widgets/business/departure_card.dart';
export 'src/widgets/business/departures_skeleton.dart';
export 'src/widgets/business/home_skeleton.dart';
export 'src/widgets/business/passenger_tile.dart';
export 'src/widgets/business/seat_map.dart';
export 'src/widgets/business/seats_badge.dart';
export 'src/widgets/business/ticket_view.dart';
export 'src/widgets/kora_alert.dart';
export 'src/widgets/kora_badge.dart';
export 'src/widgets/kora_bottom_nav.dart';
export 'src/widgets/kora_button.dart';
export 'src/widgets/kora_card.dart';
export 'src/widgets/kora_filter_chip.dart';
export 'src/widgets/kora_icon_button.dart';
export 'src/widgets/kora_input.dart';
export 'src/widgets/kora_loading_indicator.dart';
export 'src/widgets/kora_menu_list.dart';
export 'src/widgets/kora_skeleton.dart';
// Le bouton des inscriptions par réseau social n'est plus exporté.
//
// L'inscription par Google, Facebook ou TikTok n'allait pas jusqu'au
// bout côté serveur : elle a été retirée de l'app passager, et ce
// bouton n'a plus personne à servir. Le fichier reste au dépôt — le
// dessin, les logos et les états ont coûté du travail, et l'inscription
// sociale reviendra. Le jour venu, il suffira de rétablir cette ligne.
// export 'src/widgets/kora_social_button.dart';
export 'src/widgets/kora_tag.dart';
export 'src/widgets/kora_toast.dart';
export 'src/widgets/kora_toggle.dart';
export 'src/widgets/placeholder_page.dart';
export 'tokens.dart';
