import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/support_chat_configuration.dart';

/// Le chat support, tel que l'application a le droit de le connaître.
///
/// Un seul appel : l'application ne décide de rien, elle demande au
/// serveur si le chat existe et où il se trouve. Tout le reste — la clé
/// d'API, le calcul de l'empreinte d'identité — appartient au serveur.
abstract class SupportChatRepository {
  /// La configuration du chat pour le passager connecté.
  Future<Either<Failure, SupportChatConfiguration>> configuration();
}
