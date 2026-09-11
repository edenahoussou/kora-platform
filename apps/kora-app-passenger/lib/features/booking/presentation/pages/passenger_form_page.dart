import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/phone_number.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../auth/domain/entities/auth_user.dart';
import '../../../auth/domain/entities/phone_country.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../profil/presentation/pages/birth_date_picker_page.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/passenger.dart';
import '../../domain/entities/seat.dart';
import '../bloc/seat_hold_bloc.dart';
import '../widgets/booking_progress.dart';
import '../widgets/hold_countdown.dart';
import '../widgets/state_view.dart';
import 'summary_page.dart';

/// Données passées à [PassengerFormPage] par `go_router` (`extra`).
class PassengerFormArgs {
  const PassengerFormArgs({
    required this.departure,
    required this.seats,
    this.returnDeparture,
    this.returnSeats,
  });

  final Departure departure;

  /// Les sièges choisis à l'écran précédent, un par voyageur.
  final List<Seat> seats;

  /// Le retour d'un aller-retour, avec ses sièges. Transmis au
  /// récapitulatif : sans lui, il redemanderait un devis d'aller simple et
  /// annoncerait la moitié du prix.
  final Departure? returnDeparture;
  final List<Seat>? returnSeats;
}

/// Identité des voyageurs — écrans `passenger-info` et
/// `international-info` de la maquette : un formulaire par passager,
/// rattaché à son siège.
///
/// **Le formulaire se choisit tout seul.** La maquette en propose deux et
/// les place sur deux écrans, avec un lien pour passer de l'un à l'autre ;
/// ici c'est le trajet qui tranche. Un voyage qui reste au Bénin s'en
/// tient à l'identité simple ; dès qu'il franchit une frontière, les
/// champs du manifeste apparaissent et deviennent obligatoires. Demander
/// au voyageur de choisir lui-même son formulaire, c'est lui demander de
/// connaître une règle douanière que l'app connaît déjà.
///
/// Voir [Departure.isInternational] : la règle porte sur le **tronçon**
/// retenu, pas sur la ligne.
class PassengerFormPage extends StatefulWidget {
  const PassengerFormPage({
    super.key,
    required this.departure,
    this.seats = const [],
    this.returnDeparture,
    this.returnSeats,
  });

  /// `null` si l'écran est ouvert sans données (lien profond).
  final Departure? departure;

  final List<Seat> seats;

  /// Voir [PassengerFormArgs.returnDeparture].
  final Departure? returnDeparture;
  final List<Seat>? returnSeats;

  @override
  State<PassengerFormPage> createState() => _PassengerFormPageState();
}

class _PassengerFormPageState extends State<PassengerFormPage> {
  bool get _isInternational => widget.departure?.isInternational ?? false;

  /// Le premier bloc part des informations du compte : c'est le voyageur
  /// le plus probable, et les retaper alors qu'elles sont déjà connues
  /// n'apporte rien. Les suivants restent vides — ce sont d'autres
  /// personnes, que le titulaire renseigne lui-même.
  late final List<_PassengerFields> _fields = [
    for (var i = 0; i < widget.seats.length; i++)
      _PassengerFields(
        index: i,
        international: _isInternational,
        holder: i == 0 ? context.read<AuthBloc>().state.session?.user : null,
      ),
  ];

  /// Erreurs de saisie, calculées à la validation seulement : signaler un
  /// champ vide avant que l'utilisateur ait eu l'occasion de le remplir
  /// n'aide personne.
  var _showErrors = false;

  /// Le titulaire certifie l'exactitude de ce qu'il a saisi.
  ///
  /// Une seule pour la réservation, quel que soit le nombre de voyageurs :
  /// c'est la personne qui remplit le formulaire qui s'engage, pas chacun
  /// de ceux qu'elle inscrit. La déclaration de vaccination, elle, est
  /// bien par voyageur.
  ///
  /// Demandée à la revue Kora du 29 août 2026 : c'est elle qui remplace
  /// l'obligation de pièce d'identité comme condition de poursuite.
  var _accuracyConfirmed = false;

  @override
  void dispose() {
    for (final fields in _fields) {
      fields.dispose();
    }
    super.dispose();
  }

  void _submit() {
    setState(() => _showErrors = true);

    final l10n = context.l10n;

    final missing = _missing(l10n);
    if (missing.isNotEmpty) {
      // Le toast de la maquette, et non le `SnackBar` de Material : ce
      // dernier barre le bas de l'écran, donc le bouton qu'on vient
      // d'actionner, et ne ressemble à rien d'autre dans l'app.
      //
      // Le message ne porte pas seul l'information : les champs fautifs
      // sont déjà soulignés de rouge sous les yeux du voyageur. Il dit
      // lesquels, pour ceux que le défilement cache.
      KoraToaster.show(
        context,
        l10n.passengersIncompleteDetail(missing.join(' · ')),
      );
      return;
    }

    // La confirmation en dernier, et non en premier : réclamer une
    // certification d'exactitude alors qu'un nom manque encore
    // demanderait de certifier un formulaire incomplet. Une fois la
    // saisie tenable, c'est elle seule qui retient le voyageur — la revue
    // Kora n'a voulu qu'un seul verrou à ce stade.
    if (!_accuracyConfirmed) {
      KoraToaster.show(context, l10n.passengersAccuracyRequired);
      return;
    }

    final passengers = _collect();

    // Les identités partent au serveur avant le récapitulatif : c'est lui
    // qui les rattache à la réservation retenue, et qui rend le `status`
    // et l'`expires_at` à jour. Avancer sans attendre afficherait un
    // récapitulatif que le serveur ne connaît pas encore.
    //
    // Sans blocage en cours — un lien profond, un retour après
    // expiration — il n'y a aucune réservation à renseigner : on laisse
    // passer plutôt que de bloquer sur un appel qui échouerait.
    final bloc = context.read<SeatHoldBloc>();
    if (bloc.state.hold == null) {
      _openSummary(passengers);
      return;
    }

    bloc.add(SeatHoldPassengersSubmitted(passengers));
  }

  /// Les champs à reprendre, voyageur par voyageur, en clair.
  ///
  /// Un « Complétez les champs signalés » ne dit pas *lequel* : sur le
  /// formulaire international, le champ Naissance tombe juste sous le bloc
  /// document, et le message générique se lisait comme si la pièce
  /// d'identité bloquait encore — alors qu'elle est facultative depuis la
  /// revue Kora. Nommer le champ lève l'ambiguïté.
  ///
  /// Le rang du voyageur ne s'y ajoute que s'il y en a plusieurs :
  /// « Passager 1 : Prénom » devant un formulaire unique fait chercher
  /// un passager 2 qui n'existe pas.
  List<String> _missing(AppLocalization l10n) {
    final summaries = <String>[];
    final alone = _fields.length == 1;

    for (final fields in _fields) {
      final errors = fields.errorsFor(l10n);
      if (errors.isEmpty) continue;

      final labels = errors.keys
          .map((key) => _fieldLabel(l10n, key))
          .join(', ');

      summaries.add(
        alone
            ? labels
            : l10n.passengersIncompletePassenger(fields.index + 1, labels),
      );
    }

    return summaries;
  }

  /// Les champs saisis, devenus voyageurs.
  List<Passenger> _collect() => [
    for (var i = 0; i < _fields.length; i++)
      _fields[i].toPassenger(widget.seats[i]),
  ];

  void _openSummary(List<Passenger> passengers) {
    context.push(
      AppRouter.summaryPath,
      extra: SummaryArgs(
        departure: widget.departure!,
        passengers: passengers,
        returnDeparture: widget.returnDeparture,
        returnSeats: widget.returnSeats,
      ),
    );
  }

  /// Le siège du voyageur [index] : le sien à l'aller, et celui du retour
  /// quand il y en a un.
  ///
  /// Les deux voyages se paient ensemble ; n'en montrer qu'un laisserait
  /// croire que le retour n'a pas de place attribuée.
  String _seatLabel(BuildContext context, int index) {
    final outbound = widget.seats[index].label;
    final returnSeats = widget.returnSeats;

    if (returnSeats == null || index >= returnSeats.length) return outbound;

    return context.l10n.summarySeatsRound(outbound, returnSeats[index].label);
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;
    final departure = widget.departure;

    if (departure == null || widget.seats.isEmpty) {
      return const Scaffold(
        body: SafeArea(
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: 18),
            child: _MissingSelection(),
          ),
        ),
      );
    }

    final count = widget.seats.length;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: _isInternational
                    ? _lineLabel(l10n, departure)
                    : (count == 1 ? '1 passager' : '$count passagers'),
                title: _isInternational
                    ? l10n.passengersTitleInternational
                    : l10n.passengersTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    BookingProgress(step: 4, label: l10n.passengersStep),
                    const SizedBox(height: 11),
                    const HoldCountdown(margin: EdgeInsets.only(bottom: 11)),
                    for (final fields in _fields) ...[
                      _PassengerCard(
                        fields: fields,
                        seat: widget.seats[fields.index],
                        seatLabel: _seatLabel(context, fields.index),
                        showErrors: _showErrors,
                        international: _isInternational,
                        onChanged: () => setState(() {}),
                      ),
                      const SizedBox(height: 10),
                    ],
                    KoraCard(
                      child: _CheckRow(
                        value: _accuracyConfirmed,
                        label: l10n.passengersAccuracy,
                        errorText: _showErrors && !_accuracyConfirmed
                            ? l10n.passengersAccuracyRequired
                            : null,
                        onChanged: (value) =>
                            setState(() => _accuracyConfirmed = value),
                      ),
                    ),
                    const SizedBox(height: 10),
                    KoraCard(
                      variant: KoraCardVariant.mist,
                      child: Text(
                        _isInternational
                            ? l10n.passengersNoticeInternational
                            : l10n.passengersNotice,
                        style: AppTextStyles.body(koraColors.textMuted),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: BlocConsumer<SeatHoldBloc, SeatHoldState>(
                  listenWhen: (previous, current) =>
                      previous.passengersStatus != current.passengersStatus,
                  listener: (context, hold) {
                    if (hold.passengersStatus == PassengersStatus.saved) {
                      _openSummary(_collect());
                      return;
                    }

                    final failure = hold.failure;
                    if (hold.passengersStatus == PassengersStatus.failure &&
                        failure != null) {
                      KoraToaster.show(context, failure.message);
                    }
                  },
                  builder: (context, hold) => KoraButton(
                    label: l10n.passengersSubmit,
                    isLoading: hold.passengersStatus == PassengersStatus.saving,
                    onPressed: _submit,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.passenger-form-card` : l'en-tête « Passager n · Siège X » suivie des
/// champs d'identité.
class _PassengerCard extends StatelessWidget {
  const _PassengerCard({
    required this.fields,
    required this.seat,
    required this.seatLabel,
    required this.showErrors,
    required this.international,
    required this.onChanged,
  });

  final _PassengerFields fields;
  final Seat seat;
  final bool showErrors;

  /// Le voyage franchit une frontiere : la piece d'identite remplace le
  /// courriel facultatif.
  final bool international;

  final VoidCallback onChanged;

  /// Le siège affiché, déjà composé : un seul numéro sur un aller simple,
  /// les deux (« 5A · 2C ») sur un aller-retour.
  final String seatLabel;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;
    final errors = showErrors
        ? fields.errorsFor(l10n)
        : const <String, String>{};

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                context.l10n.passengersBlock(fields.index + 1),
                style: AppTextStyles.eyebrow(koraColors.textMuted),
              ),
              _SeatBadge(label: seatLabel),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          KoraInput(
            label: l10n.passengersFirstName,
            controller: fields.firstName,
            errorText: errors['firstName'],
            onChanged: (_) => onChanged(),
          ),
          const SizedBox(height: AppSpacing.md),
          KoraInput(
            label: l10n.passengersLastName,
            controller: fields.lastName,
            errorText: errors['lastName'],
            onChanged: (_) => onChanged(),
          ),
          const SizedBox(height: AppSpacing.md),
          _PhoneField(
            label: l10n.passengersPhone,
            country: fields.country,
            controller: fields.phone,
            errorText: errors['phone'],
            onCountry: (value) {
              // Le `01` béninois n'a plus de sens sous un autre indicatif
              // — voir [PhoneNumberInput.rebase], partagé avec les écrans
              // permissions et Mobile Money.
              fields.phone.text = PhoneNumberInput.rebase(
                fields.phone.text,
                from: fields.country,
                to: value,
              );
              fields.country = value;
              onChanged();
            },
            onChanged: onChanged,
          ),
          const SizedBox(height: AppSpacing.md),
          if (!international)
            KoraInput(
              label: l10n.passengersEmail,
              controller: fields.email,
              keyboardType: TextInputType.emailAddress,
              errorText: errors['email'],
              onChanged: (_) => onChanged(),
            )
          else ...[
            _DocumentTypeField(fields: fields, onChanged: onChanged),
            const SizedBox(height: AppSpacing.md),
            KoraInput(
              label: l10n.passengersDocumentNumberOptional,
              controller: fields.documentNumber,
              // Aucun `errorText` : le numéro est facultatif depuis la
              // revue Kora, et plus rien ne peut le mettre en faute.
              onChanged: (_) => onChanged(),
            ),
            const SizedBox(height: AppSpacing.md),
            // `.grid-2` de la maquette : nationalité et naissance côte à
            // côte, deux champs courts qui tiennent sur une ligne.
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: _NationalityField(
                    fields: fields,
                    onChanged: onChanged,
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: _BirthDateField(
                    fields: fields,
                    errorText: errors['birthDate'],
                    onChanged: onChanged,
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: AppSpacing.md),
          // Posée pour tout le monde, intérieur comme international : la
          // revue Kora la veut « indépendante », et elle ne suit donc pas
          // la bascule qui gouverne les champs du manifeste.
          _CheckRow(
            value: fields.yellowFeverVaccinated,
            label: l10n.passengersYellowFever,
            onChanged: (value) {
              fields.yellowFeverVaccinated = value;
              onChanged();
            },
          ),
        ],
      ),
    );
  }
}

/// Une case à cocher et son libellé, sur toute la largeur.
///
/// Le rail [KoraToggle] du design system dirait « réglage » ; ces deux
/// cases-ci sont des déclarations que le voyageur fait sur lui-même, et
/// la case carrée est la forme que la revue Kora a demandée.
class _CheckRow extends StatelessWidget {
  const _CheckRow({
    required this.value,
    required this.label,
    required this.onChanged,
    this.errorText,
  });

  final bool value;
  final String label;
  final ValueChanged<bool> onChanged;

  /// Affiché sous la ligne, en rouge, quand la case est due et non cochée.
  final String? errorText;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final error = errorText;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Toute la ligne est cliquable : viser une case de 18 points au
        // pouce, dans un bus, ne marche pas.
        InkWell(
          onTap: () => onChanged(!value),
          borderRadius: BorderRadius.circular(10),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  height: 24,
                  width: 24,
                  child: Checkbox(
                    value: value,
                    onChanged: (next) => onChanged(next ?? false),
                    activeColor: AppColors.gold,
                    checkColor: AppColors.kora,
                    side: BorderSide(
                      color: error == null ? koraColors.line : AppColors.danger,
                      width: 1.5,
                    ),
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 3),
                    child: Text(
                      label,
                      style: AppTextStyles.body(koraColors.textPrimary),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.only(left: 32, top: 2),
            child: Text(error, style: AppTextStyles.caption(AppColors.danger)),
          ),
      ],
    );
  }
}

/// `<select>` de la maquette : passeport ou carte d'identité.
class _DocumentTypeField extends StatelessWidget {
  const _DocumentTypeField({required this.fields, required this.onChanged});

  final _PassengerFields fields;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    return _LabelledField(
      label: context.l10n.passengersDocumentTypeOptional,
      child: DropdownButtonFormField<TravelDocumentType?>(
        initialValue: fields.documentType,
        isExpanded: true,
        // Le `null` est une réponse valable, pas un abandon de saisie :
        // c'est ce qui permet de revenir sur un choix fait par erreur.
        onChanged: (value) {
          fields.documentType = value;
          onChanged();
        },
        items: [
          DropdownMenuItem(
            child: Text(
              context.l10n.passengersDocumentNone,
              style: AppTextStyles.body(context.koraColors.textMuted),
            ),
          ),
          for (final type in TravelDocumentType.values)
            DropdownMenuItem(
              value: type,
              child: Text(
                _documentLabel(context.l10n, type),
                style: AppTextStyles.body(context.koraColors.textPrimary),
              ),
            ),
        ],
      ),
    );
  }
}

/// La nationalité déclarée, parmi les pays que dessert le réseau.
class _NationalityField extends StatelessWidget {
  const _NationalityField({required this.fields, required this.onChanged});

  final _PassengerFields fields;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    return _LabelledField(
      label: context.l10n.passengersNationality,
      child: DropdownButtonFormField<String>(
        initialValue: fields.nationality,
        isExpanded: true,
        onChanged: (value) {
          if (value == null) return;
          fields.nationality = value;
          onChanged();
        },
        items: [
          for (final entry in _nationalities.entries)
            DropdownMenuItem(
              value: entry.key,
              child: Text(
                entry.value,
                style: AppTextStyles.body(context.koraColors.textPrimary),
              ),
            ),
        ],
      ),
    );
  }
}

/// La date de naissance, choisie au calendrier KORA plutôt que saisie.
///
/// La maquette pose un champ texte (`18/04/1994`), mais une date tapée à
/// la main se saisit mal et se valide encore plus mal. On réutilise le
/// calendrier du profil : c'est déjà celui du choix de trajet, et deux
/// calendriers différents dans la même app se remarquent.
class _BirthDateField extends StatelessWidget {
  const _BirthDateField({
    required this.fields,
    required this.errorText,
    required this.onChanged,
  });

  final _PassengerFields fields;
  final String? errorText;
  final VoidCallback onChanged;

  Future<void> _pick(BuildContext context) async {
    final picked = await context.push<DateTime>(
      AppRouter.birthDatePath,
      // Le même calendrier que le profil, mais annoncé pour ce qu'il est
      // ici : on renseigne un voyageur, pas son propre compte.
      extra: BirthDateArgs(
        selected: fields.birthDate,
        eyebrow: context.l10n.passengersBlock(fields.index + 1),
      ),
    );
    if (picked == null) return;

    fields.birthDate = picked;
    onChanged();
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final date = fields.birthDate;

    return _LabelledField(
      label: context.l10n.passengersBirthDateOptional,
      child: InkWell(
        onTap: () => _pick(context),
        borderRadius: BorderRadius.circular(AppRadius.input),
        child: InputDecorator(
          decoration: InputDecoration(errorText: errorText),
          child: Text(
            date == null
                ? context.l10n.passengersBirthDateChoose
                : KoraDateFormats.dayMonthYear.format(date),
            style: AppTextStyles.body(
              date == null ? koraColors.textMuted : koraColors.textPrimary,
            ),
          ),
        ),
      ),
    );
  }
}

/// Un intitulé au-dessus d'un champ qui n'en porte pas lui-même — les
/// `DropdownButtonFormField` et le sélecteur de date, là où [KoraInput]
/// gère le sien.
class _LabelledField extends StatelessWidget {
  const _LabelledField({required this.label, required this.child});

  final String label;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(
            color: context.koraTitleColor,
            fontSize: 13,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: AppSpacing.sm),
        child,
      ],
    );
  }
}

/// `Ligne L1 · Données obligatoires`, comme la maquette.
///
/// À défaut de code commercial — l'API ne le renvoie pas toujours — on
/// nomme le trajet plutôt que d'afficher l'UUID de la ligne, que personne
/// ne sait lire.
String _lineLabel(AppLocalization l10n, Departure departure) {
  final code = departure.line.code;
  final route = code == null
      ? '${departure.line.origin.name} → ${departure.line.destination.name}'
      : l10n.passengersLine(code);

  return '$route · ${l10n.passengersEyebrowInternational}';
}

/// Le nom affichable d'un champ, depuis la clé que porte son erreur.
///
/// Les clés viennent de [_PassengerFields.errorsFor] ; le `default` rend
/// la clé brute plutôt que de faire sauter l'app si un champ s'ajoute sans
/// passer par ici.
String _fieldLabel(AppLocalization l10n, String key) => switch (key) {
  'firstName' => l10n.passengersFirstName,
  'lastName' => l10n.passengersLastName,
  'phone' => l10n.passengersPhone,
  'email' => l10n.passengersEmail,
  'birthDate' => l10n.passengersBirthDate,
  _ => key,
};

String _documentLabel(AppLocalization l10n, TravelDocumentType type) =>
    switch (type) {
      TravelDocumentType.passport => l10n.passengersDocPassport,
      TravelDocumentType.nationalId => l10n.passengersDocNationalId,
      TravelDocumentType.biometricCard => l10n.passengersDocBiometric,
      TravelDocumentType.consularCard => l10n.passengersDocConsular,
    };

/// Les nationalités proposées, dans l'ordre de la maquette.
///
/// La liste suit les pays que traverse le réseau (`countries_crossed` de
/// l'API) : proposer les cent quatre-vingt-quinze autres ferait défiler
/// pour rien.
const Map<String, String> _nationalities = {
  'BJ': 'Béninoise',
  'TG': 'Togolaise',
  'GH': 'Ghanéenne',
  'CI': 'Ivoirienne',
};

/// Le siège attribué, en pastille dorée dans l'en-tête du bloc.
///
/// Valeurs reprises telles quelles de la maquette : `.passenger-form-head
/// strong { padding: 5px 7px; border-radius: 8px; background: var(--gold);
/// color: var(--kora); font-size: 8px }`.
///
/// Ce n'est **pas** un `KoraTag` : celui-ci est une pilule (`.tag {
/// border-radius: 999px }`) écrite en capitales, alors que le design
/// demande ici un petit rectangle arrondi et la casse d'origine.
class _SeatBadge extends StatelessWidget {
  const _SeatBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 5),
      decoration: BoxDecoration(
        color: AppColors.gold,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        context.l10n.passengersSeat(label),
        style: const TextStyle(
          color: AppColors.kora,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

/// Les champs d'un passager et leurs règles de validation.
class _PassengerFields {
  /// [holder] est le titulaire du compte, ou `null` pour les voyageurs
  /// suivants : c'est lui qui pré-remplit le premier bloc.
  _PassengerFields({
    required this.index,
    required this.international,
    AuthUser? holder,
  }) : firstName = TextEditingController(text: _firstNameOf(holder)),
       lastName = TextEditingController(text: _lastNameOf(holder)),
       email = TextEditingController(text: holder?.email ?? ''),
       documentNumber = TextEditingController(),
       phone = TextEditingController(text: _nationalPart(holder?.phone)),
       country = PhoneCountry.fromCode(holder?.countryCode);

  final int index;

  /// Le voyage franchit une frontière : quatre champs de plus, tous
  /// obligatoires.
  final bool international;

  final TextEditingController firstName;
  final TextEditingController lastName;
  final TextEditingController phone;
  final TextEditingController email;
  final TextEditingController documentNumber;

  /// Aucune pièce présélectionnée.
  ///
  /// Le champ est facultatif depuis la revue Kora du 29 août 2026 :
  /// pré-cocher « passeport » ferait porter au billet une pièce que
  /// personne n'a déclarée, et le voyageur qui n'en a pas ne pourrait pas
  /// revenir en arrière.
  TravelDocumentType? documentType;

  /// Déclaration de vaccination contre la fièvre jaune, propre à ce
  /// voyageur.
  bool yellowFeverVaccinated = false;

  /// Pré-remplie au Bénin, d'où partent les lignes.
  String nationality = 'BJ';

  /// L'indicatif du numéro, choisi à part.
  ///
  /// Sans lui, la règle du Bénin — dix chiffres commençant par `01` —
  /// s'appliquait à tout le monde, et un numéro togolais valide était
  /// refusé.
  PhoneCountry country;

  DateTime? birthDate;

  /// Un message par champ fautif, vide si la saisie est complète.
  ///
  /// Prend la traduction en paramètre : ces champs vivent hors de l'arbre
  /// de widgets et n'ont pas de `BuildContext` à interroger.
  ///
  /// Le téléphone est exigé parce que c'est par lui que passent les avis
  /// de retard le jour du départ ; l'e-mail reste facultatif mais doit
  /// ressembler à une adresse s'il est renseigné.
  Map<String, String> errorsFor(AppLocalization l10n) {
    final errors = <String, String>{};

    if (firstName.text.trim().isEmpty) {
      errors['firstName'] = l10n.passengersFirstNameRequired;
    }
    if (lastName.text.trim().isEmpty) {
      errors['lastName'] = l10n.passengersLastNameRequired;
    }

    // La règle vient du pays choisi, jamais d'un seuil unique : le Bénin
    // attend dix chiffres ouverts par `01` depuis 2024, le Togo huit sans
    // préfixe imposé.
    final digits = phone.text.replaceAll(RegExp(r'\D'), '');
    if (digits.isEmpty) {
      errors['phone'] = l10n.passengersPhoneRequired;
    } else if (country.prefix.isNotEmpty &&
        !digits.startsWith(country.prefix)) {
      errors['phone'] = l10n.passengersPhonePrefix(
        country.name,
        country.prefix,
      );
    } else if (digits.length != country.digits) {
      errors['phone'] = l10n.passengersPhoneLength(country.digits);
    }

    final mail = email.text.trim();
    if (mail.isNotEmpty &&
        !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(mail)) {
      errors['email'] = l10n.passengersEmailInvalid;
    }

    if (!international) return errors;

    // Sur une liaison internationale, rien de plus n'est exigé : ni la
    // pièce d'identité — facultative depuis la revue Kora du 29 août 2026,
    // pour le voyageur dont le document est expiré, perdu ou en cours de
    // renouvellement — ni la date de naissance, qui la rejoint.
    //
    // Le manifeste de frontière la réclame toujours, mais c'est au comptoir
    // de la compléter : bloquer l'achat sur un champ que le voyageur ne
    // sait pas toujours renseigner de tête coûtait des réservations
    // qu'aucune règle ne refusait.
    return errors;
  }

  Passenger toPassenger(Seat seat) {
    final mail = email.text.trim();

    return Passenger(
      firstName: firstName.text.trim(),
      lastName: lastName.text.trim(),
      // Recomposé avec son indicatif : le serveur reçoit un numéro
      // joignable, pas la partie nationale seule.
      phone: '${country.code} ${phone.text.trim()}',
      seat: seat,
      email: mail.isEmpty ? null : mail,
      // Rien de tout ça sur une liaison intérieure : y laisser des valeurs
      // par défaut ferait porter au billet une identité que personne n'a
      // saisie.
      documentType: international ? documentType : null,
      // Une chaîne vide n'est pas un numéro : le serveur la prendrait pour
      // une pièce déclarée sans référence.
      documentNumber: international && documentNumber.text.trim().isNotEmpty
          ? documentNumber.text.trim()
          : null,
      nationality: international ? nationality : null,
      birthDate: international ? birthDate : null,
      // Posée pour chaque voyageur, y compris sur une liaison intérieure :
      // la case est indépendante du reste du formulaire.
      yellowFeverVaccinated: yellowFeverVaccinated,
    );
  }

  void dispose() {
    firstName.dispose();
    lastName.dispose();
    phone.dispose();
    email.dispose();
    documentNumber.dispose();
  }
}

class _MissingSelection extends StatelessWidget {
  const _MissingSelection();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        ScreenHeader(
          eyebrow: l10n.departuresEyebrow,
          title: l10n.summaryMissingEyebrow,
        ),
        StateView(
          icon: Icons.help_outline_rounded,
          title: l10n.passengersMissingTitle,
          message: l10n.passengersMissingText,
          primaryLabel: l10n.passengersMissingPrimary,
          onPrimary: () => Navigator.of(context).pop(),
        ),
      ],
    );
  }
}

/// Indicatif et numéro, côte à côte — la même paire que l'écran des
/// permissions.
class _PhoneField extends StatelessWidget {
  const _PhoneField({
    required this.label,
    required this.country,
    required this.controller,
    required this.errorText,
    required this.onCountry,
    required this.onChanged,
  });

  final String label;
  final PhoneCountry country;
  final TextEditingController controller;
  final String? errorText;
  final ValueChanged<PhoneCountry> onCountry;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    return _LabelledField(
      label: label,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 124,
            child: DropdownButtonFormField<PhoneCountry>(
              initialValue: country,
              isExpanded: true,
              onChanged: (value) => onCountry(value ?? PhoneCountry.benin),
              items: [
                for (final item in PhoneCountry.all)
                  DropdownMenuItem(
                    value: item,
                    child: Text(
                      '${item.flag}  ${item.code}',
                      style: AppTextStyles.body(context.koraColors.textPrimary),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: KoraInput(
              hint: country.example,
              controller: controller,
              keyboardType: TextInputType.phone,
              errorText: errorText,
              onChanged: (_) => onChanged(),
            ),
          ),
        ],
      ),
    );
  }
}

/// Le compte ne garde qu'un nom entier : on le coupe au premier espace,
/// comme le fait déjà l'écran « Mes informations ».
String _firstNameOf(AuthUser? holder) {
  final parts = (holder?.name ?? '').trim().split(RegExp(r'\s+'));
  return parts.isEmpty ? '' : parts.first;
}

String _lastNameOf(AuthUser? holder) {
  final parts = (holder?.name ?? '').trim().split(RegExp(r'\s+'));
  return parts.length > 1 ? parts.sublist(1).join(' ') : '';
}

/// Sépare un numéro enregistré de son indicatif.
///
/// Le compte garde le numéro complet ; le formulaire, lui, montre les deux
/// séparément pour qu'on puisse changer de pays.
String _nationalPart(String? stored) {
  final raw = (stored ?? '').trim();
  if (raw.isEmpty) return '';

  for (final country in PhoneCountry.all) {
    if (raw.startsWith(country.code)) {
      return raw.substring(country.code.length).trim();
    }
  }
  return raw;
}
