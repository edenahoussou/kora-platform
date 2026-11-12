import 'package:intl/intl.dart';

/// Formats d'affichage du carnet de bord (séparateur de milliers français).
final NumberFormat _integer = NumberFormat.decimalPattern('fr_FR');
final NumberFormat _liters = NumberFormat('#,##0.##', 'fr_FR');

String formatKm(int value) => '${_integer.format(value)} km';

String formatFcfa(int value) => '${_integer.format(value)} FCFA';

String formatLiters(double value) => '${_liters.format(value)} L';

String formatInteger(int value) => _integer.format(value);

/// « 27/10 à 08h05 ».
String formatShortDateTime(DateTime value) {
  final DateTime d = value.toLocal();
  String two(int n) => n.toString().padLeft(2, '0');
  return '${two(d.day)}/${two(d.month)} à ${two(d.hour)}h${two(d.minute)}';
}
