import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/domain/entities/city.dart';

/// Lequel des deux champs de trajet cet écran renseigne.
enum CityField {
  origin,
  destination;

  /// Titre affiché en en-tête, repris de la maquette. Il se lit dans les
  /// fichiers de langue et ne peut donc pas être porté par l'enum, dont
  /// les valeurs sont constantes.
  String titleOf(AppLocalization l10n) => switch (this) {
    CityField.origin => l10n.cityPickerOriginTitle,
    CityField.destination => l10n.cityPickerDestinationTitle,
  };
}

/// Données passées à [CityPickerPage] par `go_router` (`extra`).
///
/// Les villes sont déjà chargées par l'accueil : les retransmettre évite
/// un second appel et un second écran de chargement pour la même liste.
/// C'est aussi pour ça que cette route n'est pas prévue pour un lien
/// profond — c'est une étape d'un formulaire, pas une destination.
class CityPickerArgs {
  const CityPickerArgs({
    required this.cities,
    this.recentCities = const [],
    this.selected,
  });

  final List<City> cities;
  final List<City> recentCities;
  final City? selected;
}

/// Écran de sélection d'une ville — équivalent `locationPicker` de la
/// maquette (`js/app.js`, écrans `origin` et `destination`) : en-tête avec
/// retour, champ de recherche, liste des arrêts, puis les recherches
/// récentes.
///
/// Écran **plein**, sans bottom nav (`noNav: true` dans la maquette) :
/// c'est une étape du formulaire, pas une destination de navigation. Il se
/// referme en renvoyant la [City] choisie à l'appelant :
///
/// ```dart
/// final city = await context.push<City>(AppRouter.originPath, extra: ...);
/// ```
///
/// Il ne connaît ni le Bloc de l'accueil ni la navigation qui l'a ouvert :
/// il reçoit ses données et rend un résultat, ce qui le rend testable seul
/// et réutilisable par le futur écran de modification de recherche.
class CityPickerPage extends StatefulWidget {
  const CityPickerPage({
    super.key,
    required this.field,
    this.cities = const [],
    this.recentCities = const [],
    this.selected,
  });

  final CityField field;
  final List<City> cities;
  final List<City> recentCities;

  /// Ville déjà renseignée dans le champ, pré-remplie dans la recherche.
  final City? selected;

  @override
  State<CityPickerPage> createState() => _CityPickerPageState();
}

class _CityPickerPageState extends State<CityPickerPage> {
  /// Le champ de recherche démarre **vide**, contrairement à la maquette
  /// qui y recopie la ville déjà choisie (`value="Cotonou"`) : ce
  /// pré-remplissage filtrerait d'emblée la liste sur cette seule ville, et
  /// la première frappe viendrait s'ajouter au nom existant au lieu de le
  /// remplacer. La ville en cours reste identifiable — c'est celle dont la
  /// pastille est dorée dans la liste.
  String _query = '';

  /// La recherche porte sur le libellé complet (`Cotonou · St Michel`),
  /// pour qu'on puisse aussi trouver une ville par le nom de sa gare.
  List<City> get _results {
    final query = _query.trim().toLowerCase();
    if (query.isEmpty) return widget.cities;
    return widget.cities
        .where((city) => city.label.toLowerCase().contains(query))
        .toList();
  }

  void _select(City city) => Navigator.of(context).pop(city);

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final results = _results;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: context.l10n.cityPickerEyebrow,
                title: widget.field.titleOf(context.l10n),
              ),
              KoraInput(
                hint: context.l10n.cityPickerSearch,
                prefixIcon: Icons.search_rounded,
                onChanged: (value) => setState(() => _query = value),
              ),
              const SizedBox(height: AppSpacing.lg),
              Expanded(
                child: results.isEmpty
                    ? _NoCityFound(query: _query)
                    : ListView(
                        padding: EdgeInsets.zero,
                        children: [
                          Text(
                            context.l10n.cityPickerSuggestions,
                            style: AppTextStyles.eyebrow(koraColors.textMuted),
                          ),
                          const SizedBox(height: 9),
                          _CityList(
                            cities: results,
                            selected: widget.selected,
                            onSelect: _select,
                          ),
                          if (widget.recentCities.isNotEmpty) ...[
                            const SizedBox(height: 22),
                            Text(
                              context.l10n.cityPickerRecent,
                              style: AppTextStyles.heading2(
                                context.koraTitleColor,
                              ),
                            ),
                            const SizedBox(height: 11),
                            Wrap(
                              spacing: AppSpacing.sm,
                              runSpacing: AppSpacing.sm,
                              children: [
                                for (final city in widget.recentCities)
                                  GestureDetector(
                                    onTap: () => _select(city),
                                    child: KoraTag(
                                      label: city.name,
                                      variant: KoraTagVariant.outline,
                                    ),
                                  ),
                              ],
                            ),
                          ],
                          const SizedBox(height: AppSpacing.xl),
                        ],
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.menu-list` : la liste blanche à coins arrondis, une ligne par arrêt.
class _CityList extends StatelessWidget {
  const _CityList({
    required this.cities,
    required this.selected,
    required this.onSelect,
  });

  final List<City> cities;
  final City? selected;
  final ValueChanged<City> onSelect;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          for (final city in cities)
            _CityTile(
              city: city,
              isSelected: city == selected,
              // `.menu-item:last-child { border: 0 }`
              showDivider: city != cities.last,
              onTap: () => onSelect(city),
            ),
        ],
      ),
    );
  }
}

/// `.menu-item` : pastille d'icône, nom de la ville, type d'arrêt, chevron.
class _CityTile extends StatelessWidget {
  const _CityTile({
    required this.city,
    required this.isSelected,
    required this.showDivider,
    required this.onTap,
  });

  final City city;
  final bool isSelected;
  final bool showDivider;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return InkWell(
      onTap: onTap,
      child: Container(
        constraints: const BoxConstraints(minHeight: 58),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          border: showDivider
              ? Border(bottom: BorderSide(color: koraColors.line))
              : null,
        ),
        child: Row(
          children: [
            // `.menu-icon` : carré arrondi sur fond `mist`. La ville déjà
            // choisie prend le doré, comme le premier arrêt « recommandé »
            // de la maquette.
            Container(
              width: 36,
              height: 36,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: isSelected ? AppColors.gold : koraColors.background,
                borderRadius: BorderRadius.circular(AppRadius.base),
              ),
              // `.menu-icon { color: var(--kora) }`
              child: Icon(
                isSelected ? Icons.my_location_rounded : Icons.circle_outlined,
                size: 17,
                color: isSelected ? AppColors.kora : context.koraTitleColor,
              ),
            ),
            const SizedBox(width: AppSpacing.sm + 2),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  // `.menu-item strong { color: var(--kora); font-size: 12px }`
                  Text(
                    city.label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  // Toujours « Arrêt KORA » : la liste ne montre que des
                  // villes, et c'est le serveur qui choisit l'arrêt.
                  Text(
                    context.l10n.cityPickerStop,
                    style: TextStyle(color: koraColors.textMuted, fontSize: 9),
                  ),
                ],
              ),
            ),
            Icon(
              Icons.chevron_right_rounded,
              size: 20,
              color: koraColors.textMuted,
            ),
          ],
        ),
      ),
    );
  }
}

/// Aucune ville ne correspond à la saisie.
class _NoCityFound extends StatelessWidget {
  const _NoCityFound({required this.query});

  final String query;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.search_off_rounded,
              size: 34,
              color: koraColors.textMuted,
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              context.l10n.cityPickerNoResult(query),
              textAlign: TextAlign.center,
              style: AppTextStyles.body(koraColors.textMuted),
            ),
          ],
        ),
      ),
    );
  }
}
