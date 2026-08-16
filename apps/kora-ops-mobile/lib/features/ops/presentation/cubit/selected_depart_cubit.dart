import 'package:flutter_bloc/flutter_bloc.dart';
import '../../domain/entities/depart.dart';

/// Départ actuellement géré par l'agent — partagé entre `AccueilPage`,
/// `ManifestePage`, `ScanBoardingPage` et `DescenteAnticipeePage` (voir
/// document.md § Sélection du départ : accessible à la fois comme écran
/// dédié depuis l'accueil et comme sélecteur dans l'en-tête du Manifeste).
class SelectedDepartCubit extends Cubit<Depart?> {
  SelectedDepartCubit() : super(null);

  void select(Depart depart) => emit(depart);

  void clear() => emit(null);
}
