// Le bouton d'assistance posé sur les écrans principaux.
//
// Il occupe en permanence le coin de l'écran : il ne doit donc
// apparaître que s'il mène quelque part. Même règle que l'entrée de
// l'écran de contact — intégration éteinte, adresse manquante ou serveur
// muet, et il n'y a rien à montrer.

import 'dart:async';

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
import 'package:kora_app_passenger/features/support/presentation/widgets/support_chat_fab.dart';

class FakeSupportChatRepository implements SupportChatRepository {
  FakeSupportChatRepository({this.rendered, this.failure});

  SupportChatConfiguration? rendered;
  Failure? failure;

  @override
  Future<Either<Failure, SupportChatConfiguration>> configuration() async {
    final error = failure;
    if (error != null) return Left(error);

    return Right(rendered ?? const SupportChatConfiguration());
  }
}

void main() {
  const live = SupportChatConfiguration(
    enabled: true,
    propertyId: 'abc123def456',
    widgetId: 'default',
    directChatUrl: 'https://tawk.to/chat/abc123def456/default',
    secureHash: 'hmac-de-l-email',
  );

  Future<List<SupportChatConfiguration>> pump(
    WidgetTester tester,
    SupportChatRepository repository,
  ) async {
    final opened = <SupportChatConfiguration>[];

    // Chargé par l'appelant : le bouton suit l'utilisateur d'une page à
    // l'autre et ne redemande pas la configuration à chaque écran.
    final cubit = SupportChatCubit(repository);
    unawaited(cubit.load());

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: Scaffold(
          floatingActionButton: SupportChatFab(
            cubit: cubit,
            onOpen: opened.add,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    return opened;
  }

  testWidgets('apparaît quand le serveur donne un chat', (tester) async {
    await pump(tester, FakeSupportChatRepository(rendered: live));

    expect(find.byType(FloatingActionButton), findsOneWidget);
  });

  testWidgets('reste absent quand l’intégration est éteinte', (tester) async {
    await pump(tester, FakeSupportChatRepository());

    expect(find.byType(FloatingActionButton), findsNothing);
  });

  testWidgets('reste absent si l’adresse manque', (tester) async {
    // Allumée mais incomplète : un bouton qui ouvre une page blanche est
    // pire qu'un bouton absent.
    await pump(
      tester,
      FakeSupportChatRepository(
        rendered: const SupportChatConfiguration(enabled: true),
      ),
    );

    expect(find.byType(FloatingActionButton), findsNothing);
  });

  testWidgets('une panne ne laisse pas un bouton mort', (tester) async {
    await pump(
      tester,
      FakeSupportChatRepository(
        failure: const NetworkFailure('réseau indisponible'),
      ),
    );

    expect(find.byType(FloatingActionButton), findsNothing);
  });

  testWidgets('passe la configuration à l’écran de chat', (tester) async {
    final opened = await pump(
      tester,
      FakeSupportChatRepository(rendered: live),
    );

    await tester.tap(find.byType(FloatingActionButton));
    await tester.pumpAndSettle();

    expect(opened.single, live);
    // L'empreinte voyage telle quelle : c'est elle qui prouve à tawk.to
    // l'identité du passager.
    expect(opened.single.secureHash, 'hmac-de-l-email');
  });

  testWidgets('tient au-dessus du Navigator, sans Overlay', (tester) async {
    // C'est là que `SupportChatOverlay` le pose : dans le `builder` de
    // `MaterialApp`, au-dessus du `Navigator` — donc sans `Overlay`.
    // Une infobulle y lève « No Overlay widget found » : écran rouge en
    // debug, zone grise en release, dès qu'on quitte les onglets.
    final cubit = SupportChatCubit(FakeSupportChatRepository(rendered: live));
    unawaited(cubit.load());

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        builder: (context, child) => Stack(
          children: [
            ?child,
            Align(
              alignment: Alignment.bottomRight,
              child: SupportChatFab(cubit: cubit, onOpen: (_) {}),
            ),
          ],
        ),
        home: const Scaffold(),
      ),
    );
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.byType(FloatingActionButton), findsOneWidget);
    // Sans infobulle, le bouton garde un nom pour les lecteurs d'écran.
    expect(find.bySemanticsLabel('Discuter avec un agent'), findsOneWidget);
  });
}
