import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/domain/entities/city.dart';

/// Lequel des deux champs de trajet cet écran renseigne.
enum CityField { origin, destination }

/// Ce qu'on compose en choisissant la ville : un voyage, ou l'envoi d'un
/// colis. Le même écran sert les deux, mais pas avec les mêmes mots — on
/// ne demande pas « d'où partez-vous ? » à qui dépose un carton.
enum CityPickerPurpose {
  trip,
  parcel;

  /// Les libellés se lisent dans les fichiers de langue : ils ne peuvent
  /// donc pas être portés par l'enum, dont les valeurs sont constantes.
  String eyebrowOf(AppLocalization l10n) => switch (this) {
    CityPickerPurpose.trip => l10n.cityPickerEyebrow,
    CityPickerPurpose.parcel => l10n.cityPickerParcelEyebrow,
  };

  String titleOf(AppLocalization l10n, CityField field) => switch ((
    this,
    field,
  )) {
    (CityPickerPurpose.trip, CityField.origin) => l10n.cityPickerOriginTitle,
    (CityPickerPurpose.trip, CityField.destination) =>
      l10n.cityPickerDestinationTitle,
    (CityPickerPurpose.parcel, CityField.origin) =>
      l10n.cityPickerParcelOriginTitle,
    (CityPickerPurpose.parcel, CityField.destination) =>
      l10n.cityPickerParcelDestinationTitle,
  };

  /// Sous-titre de chaque ligne : un arrêt pour le bus, un guichet pour
  /// le colis — c'est ce qu'on y trouve qui change, pas la ville.
  String stopOf(AppLocalization l10n) => switch (this) {
    CityPickerPurpose.trip => l10n.cityPickerStop,
    CityPickerPurpose.parcel => l10n.cityPickerParcelStop,
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
    this.purpose = CityPickerPurpose.trip,
  });

  final List<City> cities;
  final List<City> recentCities;
  final City? selected;

  /// Ce que l'appelant compose. Le trajet reste la valeur par défaut :
  /// l'accueil ouvrait cet écran bien avant que le colis existe.
  final CityPickerPurpose purpose;
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
    this.purpose = CityPickerPurpose.trip,
  });

  final CityField field;
  final List<City> cities;
  final List<City> recentCities;
  final CityPickerPurpose purpose;

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

  /// Retient la ville, et demande l'arrêt quand il y en a plusieurs.
  ///
  /// Le second choix ne s'impose pas : « Tous les arrêts » garde la
  /// recherche par ville, celle qui trouve les départs des deux gares à
  /// la fois. C'est le repli qui évite de renvoyer « Aucun voyage ce
  /// jour-là » à qui aurait choisi la mauvaise.
  Future<void> _select(City city) async {
    if (widget.purpose != CityPickerPurpose.trip || city.stops.length < 2) {
      Navigator.of(context).pop(city);
      return;
    }

    final chosen = await showModalBottomSheet<City>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheet) => _StopSheet(city: city),
    );

    if (chosen == null || !mounted) return;
    Navigator.of(context).pop(chosen);
  }

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
                eyebrow: widget.purpose.eyebrowOf(context.l10n),
                title: widget.purpose.titleOf(context.l10n, widget.field),
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
                            purpose: widget.purpose,
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
    required this.purpose,
    required this.onSelect,
  });

  final List<City> cities;
  final City? selected;
  final CityPickerPurpose purpose;
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
              purpose: purpose,
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
    required this.purpose,
    required this.showDivider,
    required this.onTap,
  });

  final City city;
  final bool isSelected;
  final CityPickerPurpose purpose;
  final bool showDivider;
  final VoidCallback onTap;

  /// Les arrêts de la ville, ou la mention générique à défaut.
  ///
  /// Le guichet colis garde la sienne : les points d'arrêt bus ne sont
  /// pas des guichets, et les annoncer comme tels enverrait le client
  /// déposer son colis à la mauvaise porte.
  String _stopsLabel(BuildContext context) {
    final l10n = context.l10n;
    if (purpose != CityPickerPurpose.trip || city.stops.isEmpty) {
      return purpose.stopOf(l10n);
    }

    return city.stops.map((stop) => stop.name).join(' · ');
  }

  /// La ligne des arrêts : une épingle bleue devant chaque nom.
  ///
  /// Le point médian d'avant se perdait dans le gris de la ligne, et on
  /// ne voyait pas où un arrêt finissait et où le suivant commençait.
  /// L'épingle sépare **et** annonce ce qu'on lit : un lieu.
  ///
  /// Deux lignes au maximum : les couper à la première n'en montrerait
  /// qu'un, et annoncer trois arrêts pour n'en nommer qu'un laisserait
  /// le voyageur deviner les autres.
  Widget _stopsLine(BuildContext context, KoraColors koraColors) {
    final style = TextStyle(color: koraColors.textMuted, fontSize: 9);

    if (purpose != CityPickerPurpose.trip || city.stops.isEmpty) {
      return Text(_stopsLabel(context), maxLines: 2, style: style);
    }

    return Text.rich(
      TextSpan(
        children: [
          for (final stop in city.stops) ...[
            WidgetSpan(
              alignment: PlaceholderAlignment.middle,
              child: Padding(
                padding: const EdgeInsets.only(right: 2),
                child: Icon(
                  Icons.place_rounded,
                  size: 11,
                  color: AppColors.digital,
                ),
              ),
            ),
            TextSpan(text: stop.name),
            if (stop != city.stops.last) const TextSpan(text: '   '),
          ],
        ],
      ),
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
      style: style,
    );
  }

  /// Le nombre d'arrêts, annoncé seulement quand il y en a plusieurs.
  ///
  /// Une ville peut porter deux ou trois points d'embarquement — Cotonou
  /// en a plusieurs — et le voyageur doit le savoir **avant** de choisir,
  /// pas une fois devant la mauvaise gare. Un arrêt unique n'a rien à
  /// annoncer : son nom suffit, et « 1 arrêt KORA » ne ferait
  /// qu'allonger la ligne.
  String? _countLabel(BuildContext context) {
    if (purpose != CityPickerPurpose.trip || city.stops.length < 2) {
      return null;
    }

    return context.l10n.cityPickerStopsCount(city.stops.length);
  }

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
                  // La liste ne montre que des villes, mais elle dit
                  // lesquels de ses arrêts KORA s'y trouvent : « Cotonou »
                  // ne dit pas d'où part le bus, « Gare de Dantokpa » si.
                  // Faute de noms — une ville mockée, une recherche
                  // récente reconstruite — on retombe sur ce qu'on y
                  // trouve : un arrêt, ou un guichet colis.
                  if (_countLabel(context) case final count?)
                    Text(
                      count,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  _stopsLine(context, koraColors),
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
/// Le second choix : quel arrêt, dans une ville qui en porte plusieurs.
///
/// Il s'ouvre **après** la ville, et jamais avant : une liste qui
/// mélangerait villes et arrêts ferait choisir « Cotonou » ou « Arrêt
/// Cotonou » sans qu'on sache ce qui les distingue.
class _StopSheet extends StatelessWidget {
  const _StopSheet({required this.city});

  final City city;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return SafeArea(
      top: false,
      child: Container(
        margin: const EdgeInsets.all(11),
        padding: const EdgeInsets.all(AppSpacing.md),
        decoration: BoxDecoration(
          color: koraColors.surface,
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              l10n.cityPickerStopTitle(city.name),
              style: AppTextStyles.heading2(context.koraTitleColor),
            ),
            const SizedBox(height: 4),
            Text(
              l10n.cityPickerStopLead,
              style: AppTextStyles.body(koraColors.textMuted),
            ),
            const SizedBox(height: AppSpacing.md),
            // « Tous les arrêts » en tête : c'est le choix qui trouve le
            // plus de départs, et celui que le serveur sait traiter seul.
            _StopRow(
              label: l10n.cityPickerStopAll,
              lead: l10n.cityPickerStopAllLead,
              icon: Icons.travel_explore_rounded,
              onTap: () => Navigator.of(context).pop(city),
            ),
            for (final stop in city.stops)
              _StopRow(
                label: stop.name,
                icon: Icons.place_rounded,
                onTap: () => Navigator.of(context).pop(city.atStop(stop)),
              ),
            const SizedBox(height: AppSpacing.sm),
          ],
        ),
      ),
    );
  }
}

/// Une ligne de la feuille des arrêts.
class _StopRow extends StatelessWidget {
  const _StopRow({
    required this.label,
    required this.icon,
    required this.onTap,
    this.lead,
  });

  final String label;
  final String? lead;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.base),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 11),
        child: Row(
          children: [
            Container(
              width: 34,
              height: 34,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: koraColors.background,
                borderRadius: BorderRadius.circular(AppRadius.base),
              ),
              child: Icon(icon, size: 17, color: context.koraTitleColor),
            ),
            const SizedBox(width: AppSpacing.sm + 2),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    label,
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  if (lead case final text?) ...[
                    const SizedBox(height: 2),
                    Text(
                      text,
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 10,
                      ),
                    ),
                  ],
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
