// Le sélecteur de ville : ce qu'il nomme sous chaque ville.
//
// « Cotonou » ne dit pas d'où part le bus — les clients appellent pour
// le demander. La ligne sous la ville porte donc les arrêts KORA, sans
// pour autant les rendre choisissables : la recherche part au nom de la
// ville, et c'est le serveur qui explore tous ses points d'arrêt.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/city.dart';
import 'package:kora_app_passenger/features/home/presentation/pages/city_picker_page.dart';

void main() {
  setUpAll(AppLocalization.preloadAll);

  const cotonou = City(
    id: 'Cotonou',
    name: 'Cotonou',
    code: 'COT',
    stops: ['Gare de Dantokpa', 'Gare de Godomey'],
  );

  const bohicon = City(id: 'Bohicon', name: 'Bohicon', code: 'BOH');

  Future<void> pumpPicker(
    WidgetTester tester, {
    CityField field = CityField.origin,
    CityPickerPurpose purpose = CityPickerPurpose.trip,
  }) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('fr', 'FR'),
        supportedLocales: const [Locale('fr', 'FR')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: CityPickerPage(
          field: field,
          cities: const [cotonou, bohicon],
          purpose: purpose,
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('nomme les arrêts KORA sous la ville de départ', (tester) async {
    await pumpPicker(tester);

    expect(find.text('D’où partez-vous ?'), findsOneWidget);
    expect(find.text('Cotonou'), findsOneWidget);
    expect(find.text('Gare de Dantokpa · Gare de Godomey'), findsOneWidget);
  });

  testWidgets('nomme les mêmes arrêts à l’arrivée', (tester) async {
    await pumpPicker(tester, field: CityField.destination);

    expect(find.text('Où allez-vous ?'), findsOneWidget);
    expect(find.text('Gare de Dantokpa · Gare de Godomey'), findsOneWidget);
  });

  testWidgets('une ville sans arrêt nommé garde la mention générique', (
    tester,
  ) async {
    // Une ville mockée, ou une recherche récente reconstruite depuis un
    // billet : elle n'a pas de points d'arrêt à nommer. Mieux vaut la
    // mention générique qu'une ligne vide.
    await pumpPicker(tester);

    expect(find.text('Bohicon'), findsOneWidget);
    expect(find.text('Arrêt KORA'), findsOneWidget);
  });

  testWidgets('le guichet colis n’emprunte pas les arrêts bus', (tester) async {
    // Les points d'arrêt bus ne sont pas des guichets colis : les
    // annoncer comme tels enverrait le client à la mauvaise porte.
    await pumpPicker(tester, purpose: CityPickerPurpose.parcel);

    expect(find.text('Guichet colis KORA'), findsNWidgets(2));
    expect(find.text('Gare de Dantokpa · Gare de Godomey'), findsNothing);
  });
}
