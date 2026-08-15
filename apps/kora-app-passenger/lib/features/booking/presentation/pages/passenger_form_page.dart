import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/app_router.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/passenger.dart';
import '../../domain/entities/seat.dart';
import '../widgets/booking_progress.dart';
import '../widgets/state_view.dart';
import 'summary_page.dart';

/// Données passées à [PassengerFormPage] par `go_router` (`extra`).
class PassengerFormArgs {
  const PassengerFormArgs({required this.departure, required this.seats});

  final Departure departure;

  /// Les sièges choisis à l'écran précédent, un par voyageur.
  final List<Seat> seats;
}

/// Identité des voyageurs — écran `passenger-info` de la maquette : un
/// formulaire par passager, rattaché à son siège.
///
/// **Formulaire national uniquement.** La maquette propose aussi une
/// variante internationale (pièce d'identité, nationalité, date de
/// naissance) sur un écran distinct, destinée au manifeste de frontière :
/// c'est un autre jeu de champs et d'obligations, à écrire quand ce
/// parcours sera au programme.
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
  late final List<_PassengerFields> _fields = [
    for (var i = 0; i < widget.seats.length; i++) _PassengerFields(index: i),
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
                eyebrow: count == 1 ? '1 passager' : '$count passagers',
                title: 'Qui voyage ?',
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    const BookingProgress(step: 4, label: 'Vos informations'),
                    const SizedBox(height: 11),
                    for (final fields in _fields) ...[
                      _PassengerCard(
                        fields: fields,
                        seat: widget.seats[fields.index],
                        showErrors: _showErrors,
                        onChanged: () => setState(() {}),
                      ),
                      const SizedBox(height: 10),
                    ],
                    KoraCard(
                      variant: KoraCardVariant.mist,
                      child: Text(
                        'Ces informations seront associées uniquement à ce '
                        'billet.',
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
    required this.onChanged,
  });

  final _PassengerFields fields;
  final Seat seat;
  final bool showErrors;
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
          KoraInput(
            label: 'E-mail (optionnel)',
            controller: fields.email,
            keyboardType: TextInputType.emailAddress,
            errorText: errors['email'],
            onChanged: (_) => onChanged(),
          ),
        ],
      ),
    );
  }
}

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
  _PassengerFields({required this.index})
    : firstName = TextEditingController(text: index == 0 ? 'Aïcha' : ''),
      lastName = TextEditingController(text: index == 0 ? 'HOUNKPÈ' : ''),
      phone = TextEditingController(
        text: index == 0 ? '+229 01 97 42 18 60' : '',
      ),
      email = TextEditingController(
        text: index == 0 ? 'aicha@example.com' : '',
      );

  final int index;
  final TextEditingController firstName;
  final TextEditingController lastName;
  final TextEditingController phone;
  final TextEditingController email;

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
    );
  }

  void dispose() {
    firstName.dispose();
    lastName.dispose();
    phone.dispose();
    email.dispose();
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
