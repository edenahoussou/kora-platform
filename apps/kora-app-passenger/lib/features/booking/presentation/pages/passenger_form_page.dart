import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/passenger.dart';
import '../../domain/entities/seat.dart';
import '../widgets/booking_progress.dart';
import '../widgets/hold_countdown.dart';
import '../widgets/state_view.dart';
import 'summary_page.dart';

/// Données passées à [PassengerFormPage] par `go_router` (`extra`).
class PassengerFormArgs {
  const PassengerFormArgs({required this.departure, required this.seats});

  final Departure departure;

  /// Les sièges choisis à l'écran précédent, un par voyageur.
  final List<Seat> seats;
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
  });

  /// `null` si l'écran est ouvert sans données (lien profond).
  final Departure? departure;

  final List<Seat> seats;

  @override
  State<PassengerFormPage> createState() => _PassengerFormPageState();
}

class _PassengerFormPageState extends State<PassengerFormPage> {
  bool get _isInternational => widget.departure?.isInternational ?? false;

  late final List<_PassengerFields> _fields = [
    for (var i = 0; i < widget.seats.length; i++)
      _PassengerFields(index: i, international: _isInternational),
  ];

  /// Erreurs de saisie, calculées à la validation seulement : signaler un
  /// champ vide avant que l'utilisateur ait eu l'occasion de le remplir
  /// n'aide personne.
  var _showErrors = false;

  @override
  void dispose() {
    for (final fields in _fields) {
      fields.dispose();
    }
    super.dispose();
  }

  void _submit() {
    setState(() => _showErrors = true);

    final invalid = _fields.any((fields) => fields.errors.isNotEmpty);
    if (invalid) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text('Complétez les champs signalés pour continuer.'),
          ),
        );
      return;
    }

    final passengers = [
      for (var i = 0; i < _fields.length; i++)
        _fields[i].toPassenger(widget.seats[i]),
    ];

    context.push(
      AppRouter.summaryPath,
      extra: SummaryArgs(departure: widget.departure!, passengers: passengers),
    );
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
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
                    ? _lineLabel(departure)
                    : (count == 1 ? '1 passager' : '$count passagers'),
                title: _isInternational
                    ? 'Identités internationales'
                    : 'Qui voyage ?',
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    const BookingProgress(step: 4, label: 'Vos informations'),
                    const SizedBox(height: 11),
                    const HoldCountdown(margin: EdgeInsets.only(bottom: 11)),
                    for (final fields in _fields) ...[
                      _PassengerCard(
                        fields: fields,
                        seat: widget.seats[fields.index],
                        showErrors: _showErrors,
                        international: _isInternational,
                        onChanged: () => setState(() {}),
                      ),
                      const SizedBox(height: 10),
                    ],
                    KoraCard(
                      variant: KoraCardVariant.mist,
                      child: Text(
                        _isInternational
                            ? 'Ces informations servent au manifeste de '
                                  'frontière.'
                            : 'Ces informations seront associées uniquement '
                                  'à ce billet.',
                        style: AppTextStyles.body(koraColors.textMuted),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: KoraButton(
                  label: 'Enregistrer et continuer',
                  onPressed: _submit,
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

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final errors = showErrors ? fields.errors : const <String, String>{};

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Passager ${fields.index + 1}',
                style: AppTextStyles.eyebrow(koraColors.textMuted),
              ),
              _SeatBadge(label: seat.label),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          KoraInput(
            label: 'Prénom',
            controller: fields.firstName,
            errorText: errors['firstName'],
            onChanged: (_) => onChanged(),
          ),
          const SizedBox(height: AppSpacing.md),
          KoraInput(
            label: 'Nom',
            controller: fields.lastName,
            errorText: errors['lastName'],
            onChanged: (_) => onChanged(),
          ),
          const SizedBox(height: AppSpacing.md),
          KoraInput(
            label: 'Téléphone',
            controller: fields.phone,
            keyboardType: TextInputType.phone,
            errorText: errors['phone'],
            onChanged: (_) => onChanged(),
          ),
          const SizedBox(height: AppSpacing.md),
          if (!international)
            KoraInput(
              label: 'E-mail (optionnel)',
              controller: fields.email,
              keyboardType: TextInputType.emailAddress,
              errorText: errors['email'],
              onChanged: (_) => onChanged(),
            )
          else ...[
            _DocumentTypeField(fields: fields, onChanged: onChanged),
            const SizedBox(height: AppSpacing.md),
            KoraInput(
              label: 'Numéro du document',
              controller: fields.documentNumber,
              errorText: errors['documentNumber'],
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
        ],
      ),
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
      label: 'Type de document',
      child: DropdownButtonFormField<TravelDocumentType>(
        initialValue: fields.documentType,
        isExpanded: true,
        onChanged: (value) {
          if (value == null) return;
          fields.documentType = value;
          onChanged();
        },
        items: [
          for (final type in TravelDocumentType.values)
            DropdownMenuItem(
              value: type,
              child: Text(
                _documentLabel(type),
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
      label: 'Nationalité',
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
      extra: fields.birthDate,
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
      label: 'Naissance',
      child: InkWell(
        onTap: () => _pick(context),
        borderRadius: BorderRadius.circular(AppRadius.input),
        child: InputDecorator(
          decoration: InputDecoration(errorText: errorText),
          child: Text(
            date == null
                ? 'Choisir'
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
String _lineLabel(Departure departure) {
  final code = departure.line.code;
  final route = code == null
      ? '${departure.line.origin.name} → ${departure.line.destination.name}'
      : 'Ligne $code';

  return '$route · Données obligatoires';
}

String _documentLabel(TravelDocumentType type) => switch (type) {
  TravelDocumentType.passport => 'Passeport',
  TravelDocumentType.idCard => 'Carte d’identité',
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
        'Siège $label',
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
  _PassengerFields({required this.index, required this.international})
    : firstName = TextEditingController(text: index == 0 ? 'Aïcha' : ''),
      lastName = TextEditingController(text: index == 0 ? 'HOUNKPÈ' : ''),
      phone = TextEditingController(
        text: index == 0 ? '+229 01 97 42 18 60' : '',
      ),
      email = TextEditingController(
        text: index == 0 ? 'aicha@example.com' : '',
      ),
      documentNumber = TextEditingController();

  final int index;

  /// Le voyage franchit une frontière : quatre champs de plus, tous
  /// obligatoires.
  final bool international;

  final TextEditingController firstName;
  final TextEditingController lastName;
  final TextEditingController phone;
  final TextEditingController email;
  final TextEditingController documentNumber;

  /// Le passeport d'abord : c'est la pièce qu'attendent les postes
  /// frontière de la sous-région, la carte d'identité n'étant acceptée
  /// qu'entre certains pays.
  TravelDocumentType documentType = TravelDocumentType.passport;

  /// Pré-remplie au Bénin, d'où partent les lignes.
  String nationality = 'BJ';

  DateTime? birthDate;

  /// Un message par champ fautif, vide si la saisie est complète.
  ///
  /// Le téléphone est exigé parce que c'est par lui que passent les avis
  /// de retard le jour du départ ; l'e-mail reste facultatif mais doit
  /// ressembler à une adresse s'il est renseigné.
  Map<String, String> get errors {
    final errors = <String, String>{};

    if (firstName.text.trim().isEmpty) errors['firstName'] = 'Prénom requis';
    if (lastName.text.trim().isEmpty) errors['lastName'] = 'Nom requis';

    final digits = phone.text.replaceAll(RegExp(r'\D'), '');
    if (digits.isEmpty) {
      errors['phone'] = 'Téléphone requis';
    } else if (digits.length < 8) {
      errors['phone'] = 'Numéro trop court';
    }

    final mail = email.text.trim();
    if (mail.isNotEmpty &&
        !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(mail)) {
      errors['email'] = 'Adresse e-mail invalide';
    }

    if (!international) return errors;

    // Au-delà de la frontière, ces champs ne sont plus des agréments :
    // sans eux le voyageur est refoulé au poste, et le manifeste que KORA
    // dépose serait incomplet.
    if (documentNumber.text.trim().isEmpty) {
      errors['documentNumber'] = 'Numéro requis';
    }
    if (birthDate == null) errors['birthDate'] = 'Date requise';

    return errors;
  }

  Passenger toPassenger(Seat seat) {
    final mail = email.text.trim();

    return Passenger(
      firstName: firstName.text.trim(),
      lastName: lastName.text.trim(),
      phone: phone.text.trim(),
      seat: seat,
      email: mail.isEmpty ? null : mail,
      // Rien de tout ça sur une liaison intérieure : y laisser des valeurs
      // par défaut ferait porter au billet une identité que personne n'a
      // saisie.
      documentType: international ? documentType : null,
      documentNumber: international ? documentNumber.text.trim() : null,
      nationality: international ? nationality : null,
      birthDate: international ? birthDate : null,
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
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        const ScreenHeader(eyebrow: 'KORA BUS', title: 'Réservation perdue'),
        StateView(
          icon: Icons.help_outline_rounded,
          title: 'Aucun siège sélectionné',
          message:
              'Reprenez le choix des sièges pour renseigner les '
              'voyageurs.',
          primaryLabel: 'Retour au plan des sièges',
          onPrimary: () => Navigator.of(context).pop(),
        ),
      ],
    );
  }
}
