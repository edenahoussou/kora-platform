// Vérifie le socle de traduction : les deux fichiers de langue portent
// exactement les mêmes clés, chaque getter trouve son texte, et les jetons
// `{nom}` sont bien remplacés.

import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/language/domain/entities/app_language.dart';

Map<String, dynamic> _readLanguageFile(String code) {
  final raw = File('assets/languages/$code.json').readAsStringSync();
  return json.decode(raw) as Map<String, dynamic>;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('fichiers de langue', () {
    test('portent exactement les mêmes clés', () {
      final fr = _readLanguageFile('fr').keys.toSet();
      final en = _readLanguageFile('en').keys.toSet();

      expect(
        fr.difference(en),
        isEmpty,
        reason: 'clés présentes en français mais absentes en anglais',
      );
      expect(
        en.difference(fr),
        isEmpty,
        reason: 'clés présentes en anglais mais absentes en français',
      );
    });

    test('n’ont aucune valeur vide', () {
      for (final code in AppLanguage.values.map((l) => l.code)) {
        _readLanguageFile(code).forEach((key, value) {
          expect(
            '$value'.trim(),
            isNotEmpty,
            reason: 'la clé "$key" est vide dans $code.json',
          );
        });
      }
    });

    test('déclarent les mêmes jetons {nom} dans les deux langues', () {
      final fr = _readLanguageFile('fr');
      final en = _readLanguageFile('en');
      final token = RegExp(r'\{(\w+)\}');

      Set<String> tokensOf(Object? value) =>
          token.allMatches('$value').map((m) => m.group(1)!).toSet();

      for (final key in fr.keys) {
        expect(
          tokensOf(en[key]),
          tokensOf(fr[key]),
          reason:
              'la clé "$key" n’a pas les mêmes variables en français et en '
              'anglais — la traduction perdrait une valeur',
        );
      }
    });
  });

  group('AppLocalization', () {
    test('charge le français et rend ses textes', () async {
      final l10n = AppLocalization(const Locale('fr'));
      await l10n.load();

      expect(l10n.ticketsTitle, 'Mes voyages');
      expect(l10n.navTrips, 'Voyages');
    });

    test('charge l’anglais et rend ses textes', () async {
      final l10n = AppLocalization(const Locale('en'));
      await l10n.load();

      expect(l10n.ticketsTitle, 'My trips');
      expect(l10n.navTrips, 'Trips');
    });

    test('remplace les jetons par leur valeur', () async {
      final fr = AppLocalization(const Locale('fr'));
      await fr.load();
      final en = AppLocalization(const Locale('en'));
      await en.load();

      expect(fr.ticketsTabUpcoming(3), 'À venir · 3');
      expect(en.ticketsTabUpcoming(3), 'Upcoming · 3');
      expect(fr.ticketsSeat('4A'), 'Siège 4A');
      expect(en.ticketsSeat('4A'), 'Seat 4A');
    });

    test('décrit le délai avant un départ dans les deux langues', () async {
      final fr = AppLocalization(const Locale('fr'));
      await fr.load();
      final en = AppLocalization(const Locale('en'));
      await en.load();

      expect(fr.relativeDays(0), 'aujourd’hui');
      expect(fr.relativeDays(1), 'demain');
      expect(fr.relativeDays(7), 'dans 7 jours');

      expect(en.relativeDays(0), 'today');
      expect(en.relativeDays(1), 'tomorrow');
      expect(en.relativeDays(7), 'in 7 days');
    });

    test(
      'tous les getters trouvent leur texte, dans les deux langues',
      () async {
        // Un getter dont la clé manque retomberait sur la clé brute
        // (`tickets_title`) : on le repère à la présence d'un tiret bas, que
        // n'a aucun texte affiché.
        for (final language in AppLanguage.values) {
          final l10n = AppLocalization(Locale(language.code));
          await l10n.load();

          final texts = <String>[
            l10n.appTagline,
            l10n.actionRetry,
            l10n.actionContinue,
            l10n.actionSeeAll,
            l10n.navHome,
            l10n.navTrips,
            l10n.navParcels,
            l10n.navWallet,
            l10n.navProfile,
            l10n.ticketsEyebrow,
            l10n.ticketsTitle,
            l10n.ticketsNotifications,
            l10n.ticketsTabUpcoming(1),
            l10n.ticketsTabPast(2),
            l10n.ticketsTabCancelled(0),
            l10n.ticketsStatusConfirmed,
            l10n.ticketsStatusCompleted,
            l10n.ticketsStatusCancelled,
            l10n.ticketsSeeTicket,
            l10n.ticketsSeat('4A'),
            l10n.ticketsDepartureIn('demain'),
            l10n.ticketsNewDestination,
            l10n.ticketsBookTrip,
            l10n.ticketsEmptyUpcomingTitle,
            l10n.ticketsEmptyUpcomingText,
            l10n.ticketsEmptyPastTitle,
            l10n.ticketsEmptyPastText,
            l10n.ticketsEmptyCancelledTitle,
            l10n.ticketsEmptyCancelledText,
            l10n.ticketsErrorTitle,
            l10n.languageTitle,
          ];

          for (final text in texts) {
            expect(
              text,
              isNot(contains('_')),
              reason: 'clé non traduite en ${language.code} : "$text"',
            );
          }
        }
      },
    );

    test(
      'signale une clé absente au lieu de l’afficher telle quelle',
      () async {
        // En développement, une clé oubliée doit se voir tout de suite :
        // l'assertion d'AppLocalization arrête le build plutôt que de laisser
        // « tickets_title » s'afficher à l'écran. En production, où les
        // assertions sautent, c'est la clé qui s'affiche — moche mais jamais
        // vide.
        final l10n = AppLocalization(const Locale('fr'));
        await l10n.load();

        expect(() => l10n.translate('cle_inexistante'), throwsAssertionError);
      },
    );
  });
}
