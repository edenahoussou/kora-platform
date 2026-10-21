import 'dart:async';

import 'package:country_picker/country_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../domain/entities/phone_country.dart';

/// Ouvre la liste complète des indicatifs téléphoniques.
///
/// Le menu déroulant d'origine ne proposait que les onze corridors
/// desservis par KORA. C'est juste pour choisir un trajet, pas pour
/// s'identifier : un passager joignable sur un numéro étranger n'avait
/// aucun moyen de se connecter ni de créer son compte. La liste complète
/// ne tient pas dans un menu déroulant — d'où cette feuille, avec une
/// recherche et les pays desservis épinglés en tête.
///
/// Rend `null` si la feuille est refermée sans choix.
Future<PhoneCountry?> pickPhoneCountry(BuildContext context) {
  final koraColors = context.koraColors;
  final completer = Completer<PhoneCountry?>();

  showCountryPicker(
    context: context,
    showPhoneCode: true,
    // Sans ça la feuille monte jusqu'au bord de l'écran et recouvre
    // l'heure, le réseau et la batterie : le paquet ouvre sa feuille
    // sans marge de sécurité.
    useSafeArea: true,
    // Les corridors desservis remontent en tête : c'est là que se
    // trouvent la quasi-totalité des passagers.
    favorite: const ['BJ', 'TG', 'NG', 'NE', 'BF', 'CI', 'GH', 'SN', 'ML'],
    countryListTheme: CountryListThemeData(
      backgroundColor: koraColors.surface,
      textStyle: AppTextStyles.body(koraColors.textPrimary),
      searchTextStyle: AppTextStyles.body(koraColors.textPrimary),
      borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      inputDecoration: InputDecoration(
        hintText: MaterialLocalizations.of(context).searchFieldLabel,
        hintStyle: AppTextStyles.body(koraColors.textMuted),
        prefixIcon: Icon(Icons.search_rounded, color: koraColors.textMuted),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: koraColors.line),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: koraColors.line),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.gold),
        ),
      ),
    ),
    onSelect: (country) => completer.complete(
      PhoneCountry.beyond(
        code: '+${country.phoneCode}',
        flag: country.flagEmoji,
        name: country.displayNameNoCountryCode,
        example: country.example,
      ),
    ),
    // Refermée d'un glissement ou du bouton retour : aucun choix n'a été
    // fait, et l'indicatif en place ne doit pas bouger.
    onClosed: () {
      if (!completer.isCompleted) completer.complete(null);
    },
  );

  return completer.future;
}

/// La liste des pays traduite, chargée sans faire attendre l'écran.
///
/// Le délégué du paquet rend un `Future.value` : `Localizations` ne peut
/// donc rien construire avant la frame suivante, et **toute** l'app
/// attend — l'écran de lancement restait affiché une frame de plus. Les
/// traductions sont pourtant déjà en mémoire, compilées dans le paquet :
/// rien ne justifie d'attendre. Ce délégué rend la même chose, tout de
/// suite.
class SyncCountryLocalizationsDelegate
    extends LocalizationsDelegate<CountryLocalizations> {
  const SyncCountryLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) =>
      CountryLocalizations.delegate.isSupported(locale);

  @override
  Future<CountryLocalizations> load(Locale locale) =>
      SynchronousFuture(CountryLocalizations(locale));

  @override
  bool shouldReload(SyncCountryLocalizationsDelegate old) => false;
}
