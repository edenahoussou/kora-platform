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
    stops: [
      CityStop(uuid: 'uuid-dantokpa', name: 'Gare de Dantokpa'),
      CityStop(uuid: 'uuid-godomey', name: 'Gare de Godomey'),
    ],
  );

  const bohicon = City(id: 'Bohicon', name: 'Bohicon', code: 'BOH');

  const come = City(
    id: 'Comè',
    name: 'Comè',
    code: 'COM',
    stops: [
      CityStop(uuid: 'uuid-come', name: 'Quartier Jonquet, en face de la PTT'),
    ],
  );

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
          cities: const [cotonou, bohicon, come],
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
    // Chaque nom est précédé d'une épingle bleue : la ligne n'est donc
    // plus un simple `Text`, et son contenu se lit en texte brut.
    expect(find.textContaining('Gare de Dantokpa'), findsOneWidget);
    expect(find.textContaining('Gare de Godomey'), findsOneWidget);
  });

  testWidgets('sépare les arrêts par une épingle bleue', (tester) async {
    // Le point médian se perdait dans le gris de la ligne : on ne voyait
    // pas où un arrêt finissait et où le suivant commençait.
    await pumpPicker(tester);

    final pins = tester
        .widgetList<Icon>(find.byIcon(Icons.place_rounded))
        .where((icon) => icon.color == AppColors.digital)
        .toList();

    // Une épingle par arrêt : deux pour Cotonou, une pour Comè.
    expect(pins, hasLength(3));
    expect(pins.first.size, 11);
  });

  testWidgets('nomme les mêmes arrêts à l’arrivée', (tester) async {
    await pumpPicker(tester, field: CityField.destination);

    expect(find.text('Où allez-vous ?'), findsOneWidget);
    expect(find.textContaining('Gare de Dantokpa'), findsOneWidget);
  });

  testWidgets('annonce le nombre d’arrêts quand la ville en a plusieurs', (
    tester,
  ) async {
    // Une même ville peut porter deux ou trois points d'embarquement :
    // le voyageur doit le savoir avant de choisir, pas une fois devant
    // la mauvaise gare.
    await pumpPicker(tester);

    expect(find.text('2 arrêts KORA'), findsOneWidget);
  });

  testWidgets('un arrêt unique ne s’annonce pas en nombre', (tester) async {
    // « 1 arrêt KORA » n'apprend rien que son nom ne dise déjà.
    await pumpPicker(tester);

    expect(
      find.textContaining('Quartier Jonquet, en face de la PTT'),
      findsOneWidget,
    );
    expect(find.text('1 arrêt KORA'), findsNothing);
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

    expect(find.text('Guichet colis KORA'), findsNWidgets(3));
    expect(find.text('2 arrêts KORA'), findsNothing);
    expect(find.textContaining('Gare de Dantokpa'), findsNothing);
    expect(find.byIcon(Icons.place_rounded), findsNothing);
  });

  testWidgets('une ville à plusieurs arrêts demande lequel', (tester) async {
    // Le voyageur doit savoir d'où il part. On lui demande donc l'arrêt,
    // mais seulement quand il y a un choix à faire, et sans l'y forcer :
    // « Tous les arrêts » garde la recherche par ville.
    City? chosen;

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
        home: Builder(
          builder: (context) => TextButton(
            onPressed: () async {
              chosen = await Navigator.of(context).push<City>(
                MaterialPageRoute(
                  builder: (_) => const CityPickerPage(
                    field: CityField.origin,
                    cities: [cotonou, bohicon],
                  ),
                ),
              );
            },
            child: const Text('Ouvrir'),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Ouvrir'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Cotonou'));
    await tester.pumpAndSettle();

    // La feuille propose le repli d'abord, puis les deux arrêts.
    expect(find.text('Quel arrêt à Cotonou ?'), findsOneWidget);
    expect(find.text('Tous les arrêts'), findsOneWidget);

    await tester.tap(find.text('Gare de Godomey'));
    await tester.pumpAndSettle();

    // La ville revient avec son arrêt : son identifiant pour le serveur,
    // son nom pour que le voyageur relise son choix.
    expect(chosen?.stopUuid, 'uuid-godomey');
    expect(chosen?.station, 'Gare de Godomey');
    expect(chosen?.label, 'Cotonou · Gare de Godomey');
  });

  testWidgets('une ville à un seul arrêt ne demande rien', (tester) async {
    City? chosen;

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
        home: Builder(
          builder: (context) => TextButton(
            onPressed: () async {
              chosen = await Navigator.of(context).push<City>(
                MaterialPageRoute(
                  builder: (_) => const CityPickerPage(
                    field: CityField.origin,
                    cities: [come],
                  ),
                ),
              );
            },
            child: const Text('Ouvrir'),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Ouvrir'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Comè'));
    await tester.pumpAndSettle();

    // Pas de feuille, pas de geste en plus : la ville part telle quelle.
    expect(find.text('Quel arrêt à Comè ?'), findsNothing);
    expect(chosen?.stopUuid, isNull);
    expect(chosen?.label, 'Comè');
  });
}
