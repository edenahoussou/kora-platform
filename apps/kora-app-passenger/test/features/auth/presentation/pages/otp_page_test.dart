// Tests de la vérification du code : masquage de l'adresse, saisie des
// six cases, décompte de validité, expiration et renvoi.

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_flow_bloc.dart';
import 'package:kora_app_passenger/features/auth/presentation/pages/otp_page.dart';

import '../../../../support/stub_auth_repository.dart';

void main() {
  late int verified;
  late AuthFlowBloc flow;

  Future<void> pumpOtp(
    WidgetTester tester, {
    Duration validity = const Duration(minutes: 5),
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    verified = 0;

    // Le parcours arrive sur cet écran avec une adresse et un défi en
    // cours : c'est ce que « Renvoyer » réutilise pour redemander un code.
    flow = AuthFlowBloc(StubAuthRepository())
      ..add(const AuthFlowEmailSubmitted(email: 'aicha.hounkpe@example.com'));
    addTearDown(flow.close);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: BlocProvider<AuthFlowBloc>.value(
          value: flow,
          child: OtpPage(
            email: 'aicha.hounkpe@example.com',
            validity: validity,
            onVerified: () => verified++,
          ),
        ),
      ),
    );
    await tester.pump();
  }

  Future<void> enterCode(WidgetTester tester, String code) async {
    final boxes = find.byType(TextField);
    for (var i = 0; i < code.length; i++) {
      await tester.enterText(boxes.at(i), code[i]);
      await tester.pump();
    }
  }

  testWidgets('masque l’adresse et annonce le délai', (tester) async {
    await pumpOtp(tester);

    expect(find.text('Code de vérification'), findsOneWidget);
    expect(find.text('SÉCURITÉ'), findsOneWidget);

    // Seule la première lettre reste lisible : « aicha.hounkpe » compte
    // 13 caractères, donc 12 points après le « a ».
    final masked = 'a${'•' * 12}@example.com';
    expect(find.textContaining(masked), findsWidgets);
    expect(find.text('Code valable pendant 05:00'), findsOneWidget);
    expect(find.byType(TextField), findsNWidgets(6));
  });

  testWidgets('décompte réellement le délai de validité', (tester) async {
    await pumpOtp(tester);

    await tester.pump(const Duration(seconds: 1));
    expect(find.text('Code valable pendant 04:59'), findsOneWidget);

    await tester.pump(const Duration(seconds: 59));
    expect(find.text('Code valable pendant 04:00'), findsOneWidget);
  });

  testWidgets('refuse un code incomplet', (tester) async {
    await pumpOtp(tester);

    await enterCode(tester, '583');
    await tester.tap(find.text('Vérifier le code'));
    await tester.pump();

    expect(find.text('Saisissez les 6 chiffres du code'), findsOneWidget);
    expect(verified, 0);
  });

  testWidgets('accepte un code complet', (tester) async {
    await pumpOtp(tester);

    await enterCode(tester, '583142');
    await tester.tap(find.text('Vérifier le code'));
    await tester.pump();

    expect(verified, 1);
    expect(find.textContaining('Saisissez les'), findsNothing);
  });

  testWidgets('refuse un code expiré', (tester) async {
    await pumpOtp(tester, validity: const Duration(seconds: 2));

    await enterCode(tester, '583142');
    await tester.pump(const Duration(seconds: 3));
    expect(find.text('Code expiré'), findsOneWidget);

    await tester.tap(find.text('Vérifier le code'));
    await tester.pump();

    expect(find.text('Code expiré, demandez-en un nouveau'), findsOneWidget);
    expect(verified, 0);
  });

  testWidgets('le renvoi vide les cases et relance le décompte', (
    tester,
  ) async {
    await pumpOtp(tester, validity: const Duration(seconds: 2));

    await enterCode(tester, '583142');
    await tester.pump(const Duration(seconds: 3));
    expect(find.text('Code expiré'), findsOneWidget);

    await tester.tap(find.text('Renvoyer'));
    await tester.pump();

    // Le décompte ne repart qu'une fois le nouveau code émis par l'API :
    // le relancer avant la réponse annoncerait un code qui n'existe pas.
    await tester.pump();
    expect(find.text('Code valable pendant 00:02'), findsOneWidget);
    expect(find.text('Un nouveau code a été envoyé.'), findsOneWidget);

    final boxes = tester.widgetList<TextField>(find.byType(TextField));
    expect(boxes.every((box) => box.controller!.text.isEmpty), isTrue);

    // On laisse tout s'éteindre : le décompte, puis le toast (2,6 s
    // d'affichage et 250 ms de sortie). Un `Timer` encore actif à la fin
    // du test le ferait échouer, et `pumpAndSettle` n'avance pas l'horloge
    // pour un simple `Future.delayed`.
    await tester.pump(const Duration(seconds: 3));
    await tester.pump(const Duration(seconds: 3));
    await tester.pump(const Duration(milliseconds: 400));
  });
}
