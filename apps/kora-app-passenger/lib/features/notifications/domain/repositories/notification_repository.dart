import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/notification_feed.dart';

/// Le centre de notifications du voyageur — écran `notifications`
/// (HOME-02).
///
/// C'est la file tenue par le serveur, celle-là même qui alimente les
/// notifications push. L'application la relit ; elle ne la reconstruit pas
/// à partir des messages reçus, qui manqueraient dès qu'une notification
/// arrive téléphone éteint ou sur un autre appareil.
abstract class NotificationRepository {
  /// Une page de notifications, de la plus récente à la plus ancienne.
  Future<Either<Failure, NotificationFeed>> fetch({int page, int perPage});

  /// Marque une notification lue.
  Future<Either<Failure, Unit>> markRead(String uuid);

  /// Marque toutes les notifications lues.
  Future<Either<Failure, Unit>> markAllRead();
}
