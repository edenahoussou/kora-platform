// Tests du centre d'aide et de l'écran de contact.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/support/domain/entities/support_chat_configuration.dart';
import 'package:kora_app_passenger/features/support/domain/repositories/support_chat_repository.dart';
import 'package:kora_app_passenger/features/support/presentation/bloc/support_chat_cubit.dart';
import 'package:kora_app_passenger/features/support/presentation/pages/contact_page.dart';
import 'package:kora_app_passenger/features/support/presentation/pages/help_page.dart';

/// Un serveur de pacotille : il rend la configuration qu'on lui donne, ou
/// la panne qu'on lui demande de simuler.
class FakeSupportChatRepository implements SupportChatRepository {
  FakeSupportChatRepository({this.rendered, this.failure});

  SupportChatConfiguration? rendered;
  Failure? failure;
  var calls = 0;

  @override
  Future<Either<Failure, SupportChatConfiguration>> configuration() async {
    calls++;
    final error = failure;
    if (error != null) return Left(error);

    return Right(rendered ?? const SupportChatConfiguration());
  }
}

/// Le chat coupé : l'état par défaut des écrans qui ne le testent pas.
SupportChatCubit _noChat() => SupportChatCubit(FakeSupportChatRepository());

void main() {
  Future<void> pump(
    WidgetTester tester,
    Widget page, {
    Locale locale = const Locale('fr'),
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 1400 * 3);
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
        home: page,
      ),
    );
    await tester.pumpAndSettle();
  }

  group('Centre d’aide', () {
    testWidgets('liste les cinq questions, réponses repliées', (tester) async {
      await pump(tester, const HelpPage());

      expect(find.text('CENTRE D’AIDE'), findsOneWidget);
      expect(find.text('Comment retrouver mon billet ?'), findsOneWidget);
      expect(find.text('Où retirer mon colis ?'), findsOneWidget);

      // La réponse n'apparaît qu'une fois la question ouverte.
      expect(
        find.textContaining('disponibles dans l’onglet Voyages'),
        findsNothing,
      );
    });

    testWidgets('déplie une réponse, et la referme', (tester) async {
      await pump(tester, const HelpPage());

      await tester.tap(find.text('Comment retrouver mon billet ?'));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('disponibles dans l’onglet Voyages'),
        findsOneWidget,
      );

      await tester.tap(find.text('Comment retrouver mon billet ?'));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('disponibles dans l’onglet Voyages'),
        findsNothing,
      );
    });

    testWidgets('n’ouvre qu’une réponse à la fois', (tester) async {
      await pump(tester, const HelpPage());

      await tester.tap(find.text('Comment retrouver mon billet ?'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Mon bus est en retard'));
      await tester.pumpAndSettle();

      expect(
        find.textContaining('recalculées à chaque escale'),
        findsOneWidget,
      );
      expect(
        find.textContaining('disponibles dans l’onglet Voyages'),
        findsNothing,
      );
    });

    testWidgets('la recherche filtre aussi sur le texte des réponses', (
      tester,
    ) async {
      await pump(tester, const HelpPage());

      // « hors ligne » n'est que dans la réponse de la première question.
      await tester.enterText(find.byType(TextField), 'hors ligne');
      await tester.pumpAndSettle();

      expect(find.text('Comment retrouver mon billet ?'), findsOneWidget);
      expect(find.text('Mon bus est en retard'), findsNothing);
    });

    testWidgets('le dit quand rien ne correspond', (tester) async {
      await pump(tester, const HelpPage());

      await tester.enterText(find.byType(TextField), 'trottinette');
      await tester.pumpAndSettle();

      expect(
        find.text('Aucune réponse ne correspond à votre recherche.'),
        findsOneWidget,
      );
    });

    testWidgets('mène à l’assistance', (tester) async {
      var contacted = false;
      await pump(tester, HelpPage(onContact: () => contacted = true));

      await tester.tap(find.text('Contacter l’assistance'));
      await tester.pumpAndSettle();

      expect(contacted, isTrue);
    });
  });

  group('Contacter KORA', () {
    testWidgets('propose WhatsApp, le téléphone et l’e-mail', (tester) async {
      await pump(tester, ContactPage(cubit: _noChat()));

      expect(find.text('Parlons sur WhatsApp'), findsOneWidget);
      expect(find.text('+229 90 10 96 48'), findsOneWidget);
      expect(find.text('contact@kora.express'), findsOneWidget);
      expect(find.textContaining('référence de réservation'), findsOneWidget);
    });

    testWidgets('ouvre vraiment chaque canal', (tester) async {
      final opened = <Uri>[];
      await pump(
        tester,
        ContactPage(
          cubit: _noChat(),
          launcher: (uri) async {
            opened.add(uri);
            return true;
          },
        ),
      );

      await tester.tap(find.text('Démarrer la conversation'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Téléphone'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Email'));
      await tester.pumpAndSettle();

      expect(opened.map((uri) => uri.toString()), [
        'https://wa.me/22990109648',
        'tel:+22990109648',
        'mailto:contact@kora.express',
      ]);
    });

    testWidgets('le dit quand aucune application ne répond', (tester) async {
      await pump(
        tester,
        ContactPage(cubit: _noChat(), launcher: (_) async => false),
      );

      await tester.tap(find.text('Démarrer la conversation'));
      await tester.pumpAndSettle();

      expect(
        find.text(
          'Aucune application ne peut ouvrir WhatsApp sur cet appareil.',
        ),
        findsOneWidget,
      );

      // On laisse le minuteur du toast finir.
      await tester.pump(const Duration(seconds: 3));
      await tester.pumpAndSettle();
    });
  });

  group('Le chat avec un agent', () {
    const live = SupportChatConfiguration(
      enabled: true,
      propertyId: 'abc123def456',
      widgetId: 'default',
      directChatUrl: 'https://tawk.to/chat/abc123def456/default',
      secureHash: 'hmac-de-l-email',
    );

    testWidgets('n’apparaît que si le serveur le donne', (tester) async {
      final repository = FakeSupportChatRepository(rendered: live);

      await pump(tester, ContactPage(cubit: SupportChatCubit(repository)));

      // La carte du haut devient l'entrée du chat, et l'entrée répétée
      // de la liste disparaît : un même canal, deux fois sur le même
      // écran, n'aide personne.
      expect(find.text('Discutons maintenant'), findsOneWidget);
      expect(find.text('Parlons sur WhatsApp'), findsNothing);
      expect(find.text('Discuter avec un agent'), findsNothing);
      // WhatsApp garde sa place parmi les autres moyens : le canal ne
      // doit pas disparaître de l'écran.
      expect(find.text('WhatsApp'), findsOneWidget);
      // La configuration est demandée à l'ouverture : elle peut avoir
      // changé dans OPS depuis la dernière fois.
      expect(repository.calls, 1);
    });

    testWidgets('reste caché quand l’intégration est éteinte', (tester) async {
      await pump(tester, ContactPage(cubit: _noChat()));

      // Intégration éteinte : WhatsApp reprend la carte du haut, pour
      // que l'écran garde un canal principal.
      expect(find.text('Parlons sur WhatsApp'), findsOneWidget);
      expect(find.text('Discuter avec un agent'), findsNothing);
      // Les autres canaux, eux, ne dépendent de rien.
      expect(find.text('Téléphone'), findsOneWidget);
    });

    testWidgets('reste caché si l’adresse manque', (tester) async {
      // Intégration allumée mais incomplète : ouvrir une page blanche
      // serait pire qu'une entrée absente.
      final repository = FakeSupportChatRepository(
        rendered: const SupportChatConfiguration(enabled: true),
      );

      await pump(tester, ContactPage(cubit: SupportChatCubit(repository)));

      expect(find.text('Discuter avec un agent'), findsNothing);
    });

    testWidgets('une panne ne montre rien, et ne dit rien', (tester) async {
      final repository = FakeSupportChatRepository(
        failure: const NetworkFailure('réseau indisponible'),
      );

      await pump(tester, ContactPage(cubit: SupportChatCubit(repository)));

      expect(find.text('Discuter avec un agent'), findsNothing);
      expect(find.textContaining('indisponible'), findsNothing);
      expect(find.text('Parlons sur WhatsApp'), findsOneWidget);
    });

    testWidgets('transmet la configuration à l’écran de chat', (tester) async {
      SupportChatConfiguration? opened;
      final repository = FakeSupportChatRepository(rendered: live);

      await pump(
        tester,
        ContactPage(
          cubit: SupportChatCubit(repository),
          onOpenChat: (configuration) => opened = configuration,
        ),
      );

      await tester.tap(find.text('Démarrer la conversation'));
      await tester.pumpAndSettle();

      expect(opened, live);
      // L'empreinte vient du serveur et voyage telle quelle : c'est elle
      // qui prouve à tawk.to l'identité du passager.
      expect(opened?.secureHash, 'hmac-de-l-email');
    });
  });
}
