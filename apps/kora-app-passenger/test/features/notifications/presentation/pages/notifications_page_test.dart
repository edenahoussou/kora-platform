// Tests du centre de notifications : la liste venue du serveur, les
// pastilles de non-lu, « Tout lire », la panne et la page suivante.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/notifications/domain/entities/app_notification.dart';
import 'package:kora_app_passenger/features/notifications/domain/entities/notification_feed.dart';
import 'package:kora_app_passenger/features/notifications/domain/repositories/notification_repository.dart';
import 'package:kora_app_passenger/features/notifications/presentation/bloc/notifications_cubit.dart';
import 'package:kora_app_passenger/features/notifications/presentation/pages/notifications_page.dart';

/// Un serveur de pacotille : il rend ce qu'on lui a dit de rendre, et note
/// ce qu'on lui a demandé.
class FakeRepository implements NotificationRepository {
  FakeRepository({this.pages = const [], this.failure});

  /// Une entrée par page, dans l'ordre où elles seront demandées.
  List<NotificationFeed> pages;
  Failure? failure;

  final readIds = <String>[];
  var readAllCalls = 0;
  var fetches = 0;

  @override
  Future<Either<Failure, NotificationFeed>> fetch({
    int page = 1,
    int perPage = 20,
  }) async {
    fetches++;
    final error = failure;
    if (error != null) return Left(error);

    return Right(pages[page - 1]);
  }

  @override
  Future<Either<Failure, Unit>> markRead(String uuid) async {
    readIds.add(uuid);
    return const Right(unit);
  }

  @override
  Future<Either<Failure, Unit>> markAllRead() async {
    readAllCalls++;
    return const Right(unit);
  }
}

void main() {
  /// Horloge figée : les libellés « Il y a 2 min » et « Hier » dépendent
  /// du moment où l'écran s'ouvre.
  final today = DateTime(2026, 8, 22, 10, 30);

  AppNotification notification({
    required String id,
    required String title,
    NotificationKind kind = NotificationKind.ticket,
    String text = 'Détail de la notification.',
    DateTime? receivedAt,
    bool isUnread = false,
  }) => AppNotification(
    id: id,
    kind: kind,
    title: title,
    text: text,
    receivedAt: receivedAt ?? today.subtract(const Duration(minutes: 2)),
    isUnread: isUnread,
  );

  late FakeRepository repository;

  setUpAll(() async {
    await initializeDateFormatting();
  });

  setUp(() {
    repository = FakeRepository(
      pages: [
        NotificationFeed(
          notifications: [
            notification(
              id: 'un',
              title: 'Réservation confirmée',
              isUnread: true,
            ),
            notification(
              id: 'deux',
              title: 'Départ retardé',
              kind: NotificationKind.delay,
              receivedAt: today.subtract(const Duration(days: 1)),
              isUnread: true,
            ),
            notification(
              id: 'trois',
              title: 'Rappel de voyage',
              kind: NotificationKind.reminder,
              receivedAt: today.subtract(const Duration(hours: 4)),
            ),
          ],
          unreadCount: 2,
        ),
      ],
    );
  });

  Future<void> pumpPage(
    WidgetTester tester, {
    Locale locale = const Locale('fr'),
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 1600 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: locale,
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: NotificationsPage(
          today: today,
          cubit: NotificationsCubit(repository),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  /// Le toast garde un minuteur : sans le laisser finir, le test
  /// s'achèverait avec un timer en attente.
  Future<void> settleToast(WidgetTester tester) async {
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
  }

  testWidgets('liste les notifications rendues par le serveur', (tester) async {
    await pumpPage(tester);

    expect(find.text('VOTRE ACTIVITÉ'), findsOneWidget);
    expect(find.text('Notifications'), findsOneWidget);

    expect(find.text('Réservation confirmée'), findsOneWidget);
    expect(find.text('Départ retardé'), findsOneWidget);
    expect(find.text('Rappel de voyage'), findsOneWidget);
    expect(repository.fetches, 1);
  });

  testWidgets('formule chaque date selon son ancienneté', (tester) async {
    await pumpPage(tester);

    expect(find.text('Il y a 2 min'), findsOneWidget);
    expect(find.text('Aujourd’hui'), findsOneWidget);
    expect(find.text('Hier'), findsOneWidget);
  });

  testWidgets('« Tout lire » prévient le serveur et retire les pastilles', (
    tester,
  ) async {
    await pumpPage(tester);

    expect(_goldDots(tester), 2);

    await tester.tap(find.text('Tout lire'));
    await tester.pumpAndSettle();

    expect(repository.readAllCalls, 1);
    expect(_goldDots(tester), 0);
    // Le lien disparaît : il n'a plus rien à faire.
    expect(find.text('Tout lire'), findsNothing);
    expect(
      find.text('Toutes les notifications sont marquées comme lues.'),
      findsOneWidget,
    );

    await settleToast(tester);
  });

  testWidgets('ouvrir une notification la marque lue au serveur', (
    tester,
  ) async {
    await pumpPage(tester);

    await tester.tap(find.text('Réservation confirmée'));
    await tester.pumpAndSettle();

    expect(repository.readIds, ['un']);
    expect(_goldDots(tester), 1);
    expect(
      find.text('Le détail de cette notification arrive bientôt.'),
      findsOneWidget,
    );

    await settleToast(tester);
  });

  testWidgets('une notification déjà lue n’est pas renvoyée au serveur', (
    tester,
  ) async {
    await pumpPage(tester);

    await tester.tap(find.text('Rappel de voyage'));
    await tester.pumpAndSettle();

    expect(repository.readIds, isEmpty);

    await settleToast(tester);
  });

  testWidgets('une panne propose de réessayer', (tester) async {
    repository.failure = const NetworkFailure('réseau indisponible');

    await pumpPage(tester);

    expect(find.text('Notifications indisponibles'), findsOneWidget);
    expect(find.text('Réessayer'), findsOneWidget);

    // Le serveur revient : le bouton rejoue la lecture.
    repository.failure = null;
    await tester.tap(find.text('Réessayer'));
    await tester.pumpAndSettle();

    expect(find.text('Réservation confirmée'), findsOneWidget);
  });

  testWidgets('sans notification, l’écran le dit', (tester) async {
    repository.pages = [const NotificationFeed()];

    await pumpPage(tester);

    expect(find.text('Rien de neuf pour le moment.'), findsOneWidget);
    expect(find.text('Tout lire'), findsNothing);
  });

  testWidgets('les plus anciennes s’ajoutent sans effacer les premières', (
    tester,
  ) async {
    repository.pages = [
      NotificationFeed(
        notifications: [notification(id: 'un', title: 'Réservation confirmée')],
        lastPage: 2,
      ),
      NotificationFeed(
        notifications: [notification(id: 'deux', title: 'Départ retardé')],
        page: 2,
        lastPage: 2,
      ),
    ];

    await pumpPage(tester);

    expect(find.text('Départ retardé'), findsNothing);

    await tester.tap(find.text('Voir les plus anciennes'));
    await tester.pumpAndSettle();

    expect(find.text('Réservation confirmée'), findsOneWidget);
    expect(find.text('Départ retardé'), findsOneWidget);
    // Plus rien à charger : le bouton s'efface.
    expect(find.text('Voir les plus anciennes'), findsNothing);
  });

  testWidgets('s’affiche en anglais quand la langue est l’anglais', (
    tester,
  ) async {
    await pumpPage(tester, locale: const Locale('en'));

    expect(find.text('YOUR ACTIVITY'), findsOneWidget);
    expect(find.text('Mark all read'), findsOneWidget);
    expect(find.text('2 min ago'), findsOneWidget);
    expect(find.text('Yesterday'), findsOneWidget);
  });
}

/// Compte les pastilles dorées de non-lu.
int _goldDots(WidgetTester tester) =>
    tester.widgetList<Container>(find.byType(Container)).where((container) {
      final decoration = container.decoration;
      return decoration is BoxDecoration &&
          decoration.shape == BoxShape.circle &&
          decoration.color == AppColors.gold;
    }).length;
