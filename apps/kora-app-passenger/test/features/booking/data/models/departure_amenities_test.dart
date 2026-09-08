// Les services à bord, du JSON du serveur jusqu'au libellé affiché.
//
// Le contrat est celui de `DepartureResource` : `bus.amenities` est une
// liste d'objets `{uuid, code, label, icon}`, déjà filtrée sur les
// équipements actifs. L'app n'en retient que le code — qui fait autorité —
// et le libellé, qui sert de repli.

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/booking/data/models/departure_model.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/amenity.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/presentation/widgets/amenity_view.dart';

/// Le départ construit depuis [json], dont on sait qu'il est valide :
/// `toEntity` ne rend `null` que sans date de départ exploitable, et
/// chaque cas ci-dessous en fournit une.
Departure entityFrom(Map<String, dynamic> json) {
  final departure = DepartureModel.fromJson(json).toEntity();
  expect(departure, isNotNull, reason: 'départ illisible : $json');
  return departure!;
}

/// Un départ minimal, dont seul le bloc `bus` nous intéresse ici.
Map<String, dynamic> departureJson(Object? amenities) => {
  'uuid': 'dep-1',
  'departure_date': '2026-09-01',
  'departure_time': '20:00',
  'status': 'scheduled',
  'bus': {
    'uuid': 'bus-1',
    'registration_number': 'AA-123-RB',
    'brand': 'Mercedes',
    'model': 'O500',
    'seat_count': 55,
    'amenities': ?amenities,
  },
  'available_seats_count': 12,
};

void main() {
  setUpAll(() => AppLocalization.preloadAll());

  group('lecture de la réponse', () {
    test('retient le code et le libellé de chaque service', () {
      final departure = entityFrom(
        departureJson([
          {
            'uuid': 'a-1',
            'code': 'wifi',
            'label': 'Wi-Fi à bord',
            'icon': 'wifi',
          },
          {
            'uuid': 'a-2',
            'code': 'usb_charging',
            'label': 'Prises USB',
            'icon': 'usb',
          },
        ]),
      );

      expect(departure.amenities, [
        const Amenity(code: 'wifi', label: 'Wi-Fi à bord'),
        const Amenity(code: 'usb_charging', label: 'Prises USB'),
      ]);
    });

    test('un bus sans services ne promet rien', () {
      // Le cas d'un départ dont le bus n'est pas encore affecté : la liste
      // vide laisse les écrans muets, ce qui vaut mieux qu'un Wi-Fi
      // affiché par défaut.
      final departure = entityFrom(departureJson(null));

      expect(departure.amenities, isEmpty);
    });

    test('ignore une entrée sans code', () {
      // Sans code, rien ne permet ni de choisir un pictogramme ni de
      // traduire : la tuile serait vide.
      final departure = entityFrom(
        departureJson([
          {'uuid': 'a-1', 'code': '', 'label': 'Mystère'},
          {'uuid': 'a-2', 'code': 'wifi', 'label': 'Wi-Fi à bord'},
        ]),
      );

      expect(departure.amenities.map((a) => a.code), ['wifi']);
    });
  });

  group('libellé affiché', () {
    /// Rend [amenity] dans un arbre localisé et retourne son libellé.
    Future<String> labelOf(
      WidgetTester tester,
      Amenity amenity,
      Locale locale,
    ) async {
      late String seen;

      await tester.pumpWidget(
        MaterialApp(
          locale: locale,
          supportedLocales: const [Locale('fr'), Locale('en')],
          localizationsDelegates: const [
            AppLocalization.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          home: Builder(
            builder: (context) {
              seen = amenity.displayLabel(context);
              return const SizedBox.shrink();
            },
          ),
        ),
      );
      await tester.pumpAndSettle();

      return seen;
    }

    testWidgets('traduit les codes que l’app connaît', (tester) async {
      // Le serveur ne rédige qu'en français : sans cette traduction, un
      // voyageur en anglais verrait « Climatisation » au milieu d'un écran
      // anglais.
      const amenity = Amenity(code: 'air_conditioning', label: 'Climatisation');

      expect(
        await labelOf(tester, amenity, const Locale('fr')),
        'Climatisation',
      );
      expect(
        await labelOf(tester, amenity, const Locale('en')),
        'Air conditioning',
      );
    });

    testWidgets('retombe sur le libellé du serveur pour un code inconnu', (
      tester,
    ) async {
      // Un équipement ajouté côté back après cette version : en français,
      // mais lisible — plutôt que le code brut.
      const amenity = Amenity(code: 'massage_seat', label: 'Siège massant');

      expect(
        await labelOf(tester, amenity, const Locale('en')),
        'Siège massant',
      );
    });

    testWidgets('affiche le code en dernier recours', (tester) async {
      // Ni traduction ni libellé serveur. Ce n'est pas beau, mais une
      // tuile vide le serait moins.
      const amenity = Amenity(code: 'massage_seat');

      expect(
        await labelOf(tester, amenity, const Locale('fr')),
        'massage_seat',
      );
    });
  });

  test('chaque code connu a son pictogramme', () {
    // Une icône neutre pour un code que le serveur sème est un oubli, pas
    // un repli : les 28 codes du seeder doivent être dessinés.
    const seeded = [
      'wifi',
      'air_conditioning',
      'reclining_seats',
      'extra_legroom',
      'individual_seat',
      'headrest',
      'usb_charging',
      'power_outlet',
      'reading_light',
      'tv_screen',
      'entertainment',
      'headphones',
      'onboard_toilet',
      'onboard_snack',
      'bottled_water',
      'meal_service',
      'priority_luggage',
      'luggage_storage',
      'extra_luggage',
      'seat_belt',
      'gps_tracking',
      'security_camera',
      'wheelchair_accessible',
      'accessible_seat',
      'priority_boarding',
      'premium_seat',
      'blanket',
      'pillow',
    ];

    final neutral = const Amenity(code: 'inconnu').icon;
    final missing = seeded
        .where((code) => Amenity(code: code).icon == neutral)
        .toList();

    expect(missing, isEmpty, reason: 'codes sans pictogramme : $missing');
  });
}
