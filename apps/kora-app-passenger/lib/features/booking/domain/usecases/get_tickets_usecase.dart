import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/usecases/usecase.dart';
import '../entities/ticket.dart';
import '../repositories/booking_repository.dart';

/// Couche DOMAIN : récupère les billets du voyageur pour l'onglet
/// « Mes voyages ». Une liste vide signifie qu'il n'a encore rien réservé.
class GetTicketsUseCase implements UseCase<List<Ticket>, NoParams> {
  GetTicketsUseCase(this._repository);

  final BookingRepository _repository;

  @override
  Future<Either<Failure, List<Ticket>>> call(NoParams params) {
    return _repository.getTickets();
  }
}
