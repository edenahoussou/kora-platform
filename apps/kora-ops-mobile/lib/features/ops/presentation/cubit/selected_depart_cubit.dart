import 'dart:async';

import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../data/datasources/selected_departure_local_datasource.dart';
import '../../domain/entities/depart.dart';

/// Départ actuellement géré par l'agent — partagé entre `AccueilPage`,
/// `ManifestePage`, `ScanBoardingPage` et `DescenteAnticipeePage` (voir
/// document.md § Sélection du départ). Persisté localement (MEZ-92) :
/// [bootstrap] restaure le dernier départ sélectionné après un
/// redémarrage à froid du P501, sans attendre de réseau — le manifeste
/// associé se recharge alors automatiquement (voir `ManifesteCubit`, qui
/// écoute ce cubit).
class SelectedDepartCubit extends Cubit<Depart?> {
  SelectedDepartCubit(this._local, AuthCubit authCubit) : super(null) {
    // Un agent qui se déconnecte ne doit pas laisser son départ géré
    // visible pour le prochain agent qui se connecte sur le même
    // terminal — contrairement au terminal P501 lui-même, qui reste lié.
    _authSubscription = authCubit.stream.listen((AuthState state) {
      if (state is AuthUnauthenticated) clear();
    });
  }

  final SelectedDepartureLocalDataSource _local;
  late final StreamSubscription<AuthState> _authSubscription;

  /// À appeler une fois au démarrage (voir injection.dart/SplashPage) :
  /// relit le départ en cache, sans appel réseau. Le manifeste associé se
  /// rechargera de lui-même via `ManifesteCubit` (qui écoute ce cubit),
  /// avec son propre repli hors ligne.
  Future<void> bootstrap() async {
    final Depart? cached = await _local.readSnapshot();
    if (cached != null) emit(cached);
  }

  Future<void> select(Depart depart) async {
    emit(depart);
    await _local.saveSnapshot(depart);
  }

  void clear() {
    emit(null);
    unawaited(_local.clear());
  }

  @override
  Future<void> close() {
    unawaited(_authSubscription.cancel());
    return super.close();
  }
}
