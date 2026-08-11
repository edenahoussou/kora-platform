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
export 'src/widgets/business/departure_card.dart';
export 'src/widgets/business/passenger_tile.dart';
export 'src/widgets/business/seat_map.dart';
export 'src/widgets/business/ticket_view.dart';
export 'src/widgets/kora_badge.dart';
export 'src/widgets/kora_bottom_nav.dart';
export 'src/widgets/kora_button.dart';
export 'src/widgets/kora_card.dart';
export 'src/widgets/kora_icon_button.dart';
export 'src/widgets/kora_input.dart';
export 'src/widgets/kora_loading_indicator.dart';
export 'src/widgets/kora_tag.dart';
export 'src/widgets/placeholder_page.dart';
export 'tokens.dart';
