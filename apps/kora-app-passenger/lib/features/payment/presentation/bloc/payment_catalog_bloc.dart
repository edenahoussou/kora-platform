import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../domain/entities/payment_catalog.dart';
import '../../domain/repositories/payment_repository.dart';

part 'payment_catalog_event.dart';
part 'payment_catalog_state.dart';

/// Le catalogue des moyens de paiement, chargé à l'entrée du tunnel de
/// paiement.
///
/// **Il ne vit pas dans un seul écran.** Le choix du moyen, l'écran Mobile
/// Money et, demain, l'initiation du paiement lisent tous le même
/// catalogue ; le recharger à chaque navigation ferait clignoter trois
/// écrans d'affilée pour une réponse que le serveur met en cache. Il est
/// donc fourni par la route mère du paiement, comme [SeatHoldBloc] l'est
/// pour le blocage des sièges.
class PaymentCatalogBloc
    extends Bloc<PaymentCatalogEvent, PaymentCatalogState> {
  PaymentCatalogBloc(this._repository) : super(const PaymentCatalogState()) {
    on<PaymentCatalogRequested>(_onRequested);
  }

  final PaymentRepository _repository;

  Future<void> _onRequested(
    PaymentCatalogRequested event,
    Emitter<PaymentCatalogState> emit,
  ) async {
    // Rien à recharger si le catalogue est déjà là : il change au rythme
    // de la configuration du serveur, pas à celui de la navigation.
    if (state.status == PaymentCatalogStatus.success && !event.force) return;

    emit(
      state.copyWith(status: PaymentCatalogStatus.loading, clearError: true),
    );

    final result = await _repository.catalog();

    result.fold(
      (failure) => emit(
        state.copyWith(
          status: PaymentCatalogStatus.failure,
          errorMessage: failure.message,
        ),
      ),
      (catalog) => emit(
        state.copyWith(
          status: PaymentCatalogStatus.success,
          catalog: catalog,
          clearError: true,
        ),
      ),
    );
  }
}
