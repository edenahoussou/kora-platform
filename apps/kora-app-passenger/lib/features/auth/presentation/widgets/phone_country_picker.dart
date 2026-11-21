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
    favorite: _corridors,
    countryListTheme: _listTheme(context),
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

/// Les pays desservis par KORA, épinglés en tête des deux feuilles.
const _corridors = ['BJ', 'TG', 'NG', 'NE', 'BF', 'CI', 'GH', 'SN', 'ML'];

/// L'habillage KORA de la feuille du paquet, le même pour l'indicatif et
/// pour la nationalité : deux listes de pays qui ne se ressembleraient
/// pas se remarqueraient dans le même formulaire.
CountryListThemeData _listTheme(BuildContext context) {
  final koraColors = context.koraColors;

  return CountryListThemeData(
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
  );
}

/// Le pays d'une nationalité déclarée.
class NationalityChoice {
  const NationalityChoice({required this.code, required this.name});

  /// Code ISO 3166-1 alpha-2 (`BJ`, `MA`). C'est exactement ce
  /// qu'attend `passengers[].nationality` : deux caractères, ni plus
  /// ni moins.
  final String code;

  /// Le nom du pays, pour l'affichage seul.
  final String name;
}

/// Ouvre la liste complète des pays pour déclarer une nationalité.
///
/// Le menu déroulant d'origine n'en proposait que quatre — celles des
/// pays que traverse le réseau. Mais la nationalité d'un voyageur n'a
/// rien à voir avec le trajet qu'il achète : un passager marocain
/// montant à Cotonou ne pouvait tout simplement pas déclarer la
/// sienne, et la liste lui en imposait une autre.
///
/// Rend `null` si la feuille est refermée sans choix.
Future<NationalityChoice?> pickNationality(BuildContext context) {
  final completer = Completer<NationalityChoice?>();

  showCountryPicker(
    context: context,
    useSafeArea: true,
    favorite: _corridors,
    countryListTheme: _listTheme(context),
    onSelect: (country) => completer.complete(
      NationalityChoice(
        code: country.countryCode,
        name: country.displayNameNoCountryCode,
      ),
    ),
    onClosed: () {
      if (!completer.isCompleted) completer.complete(null);
    },
  );

  return completer.future;
}

/// Le nom du pays d'un code ISO déjà enregistré (`BJ` → `Bénin`).
///
/// Dans la langue de l'app quand le paquet la connaît, sinon le nom
/// d'origine — et en dernier recours le code lui-même, qui vaut mieux
/// qu'un champ vide là où le voyageur a bel et bien choisi.
String nationalityName(BuildContext context, String code) {
  if (code.isEmpty) return '';

  return CountryLocalizations.of(context)?.countryName(countryCode: code) ??
      CountryService().findByCode(code)?.displayNameNoCountryCode ??
      code;
}

/// Retrouve l'indicatif d'un `country_code` enregistré, hors corridors
/// compris.
///
/// `PhoneCountry.fromCode` ne connaît que les onze du catalogue et rend
/// le Bénin pour tout le reste : le titulaire joignable au `+212`
/// voyait son propre numéro reparaitre sous un drapeau béninois, et la
/// règle des dix chiffres s'y appliquer. Ici le paquet fournit le
/// drapeau et le nom, et [PhoneCountry.beyond] renonce à juger la
/// longueur du numéro.
PhoneCountry phoneCountryOf(String? dialCode) {
  final code = dialCode?.trim() ?? '';
  if (code.isEmpty) return PhoneCountry.benin;

  for (final country in PhoneCountry.all) {
    if (country.code == code) return country;
  }

  final found = CountryService().findByPhoneCode(code.replaceAll('+', ''));
  if (found == null) return PhoneCountry.benin;

  return PhoneCountry.beyond(
    code: '+${found.phoneCode}',
    flag: found.flagEmoji,
    name: found.displayNameNoCountryCode,
    example: found.example,
  );
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

/// L'indicatif d'un numéro, ouvrant [pickPhoneCountry].
///
/// L'inscription, la connexion et la fiche passager en dessinaient
/// chacune une copie. Celle-ci est posée ici pour que les écrans qui
/// s'ajoutent — le destinataire d'un colis, le premier — n'en fassent
/// pas une de plus.
class PhoneCountryButton extends StatelessWidget {
  const PhoneCountryButton({
    super.key,
    required this.country,
    required this.onCountry,
  });

  final PhoneCountry country;
  final ValueChanged<PhoneCountry> onCountry;

  Future<void> _pick(BuildContext context) async {
    final picked = await pickPhoneCountry(context);
    if (picked != null) onCountry(picked);
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return SizedBox(
      width: 124,
      height: 56,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => _pick(context),
          borderRadius: BorderRadius.circular(AppRadius.base),
          child: Ink(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(AppRadius.base),
              border: Border.all(color: koraColors.line),
            ),
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    '${country.flag}  ${country.code}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.body(koraColors.textPrimary),
                  ),
                ),
                Icon(
                  Icons.arrow_drop_down,
                  color: koraColors.textMuted,
                  size: 20,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
