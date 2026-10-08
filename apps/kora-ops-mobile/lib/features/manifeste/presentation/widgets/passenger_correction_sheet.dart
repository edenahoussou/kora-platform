import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/result/result.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../domain/entities/passenger_correction.dart';
import '../cubit/manifeste_cubit.dart';

/// Libellés des types de pièce (valeurs de `PassengerDocumentType` côté
/// backend). Un passeport = `passport` + numéro de pièce.
const Map<String, String> kDocumentTypeLabels = <String, String>{
  'passport': 'Passeport',
  'national_id': 'Carte d\'identité nationale',
  'biometric_card': 'Carte biométrique',
  'consular_card': 'Carte consulaire',
  'cip': 'CIP',
};

/// Pays proposés pour la nationalité (code ISO 3166-1 alpha-2). Un code
/// déjà présent sur le passager mais absent de cette liste y est ajouté à
/// l'affichage, jamais perdu.
const Map<String, String> kCountryLabels = <String, String>{
  'BJ': 'Bénin',
  'TG': 'Togo',
  'NG': 'Nigeria',
  'GH': 'Ghana',
  'BF': 'Burkina Faso',
  'NE': 'Niger',
  'CI': 'Côte d\'Ivoire',
  'SN': 'Sénégal',
  'ML': 'Mali',
  'GN': 'Guinée',
  'CM': 'Cameroun',
  'GA': 'Gabon',
  'CG': 'Congo',
  'CD': 'RD Congo',
  'TD': 'Tchad',
  'MR': 'Mauritanie',
  'GM': 'Gambie',
  'SL': 'Sierra Leone',
  'LR': 'Libéria',
  'CV': 'Cap-Vert',
  'GW': 'Guinée-Bissau',
  'MA': 'Maroc',
  'DZ': 'Algérie',
  'TN': 'Tunisie',
  'FR': 'France',
  'BE': 'Belgique',
  'CH': 'Suisse',
  'CA': 'Canada',
  'US': 'États-Unis',
  'LB': 'Liban',
  'CN': 'Chine',
  'IN': 'Inde',
};

final RegExp _phonePattern = RegExp(r'^\+?\d{7,15}$');
final RegExp _emailPattern = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

/// Ouvre le formulaire de correction des informations d'un passager.
/// Retourne l'issue (`saved` / `pendingSync`) ou `null` si abandonné.
Future<PassengerCorrectionOutcome?> showPassengerCorrectionSheet(
  BuildContext context, {
  required Passenger passenger,
  required bool showBorderFields,
}) {
  final ManifesteCubit cubit = context.read<ManifesteCubit>();
  return showModalBottomSheet<PassengerCorrectionOutcome>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (BuildContext sheetContext) => PassengerCorrectionSheet(
      cubit: cubit,
      passenger: passenger,
      showBorderFields: showBorderFields,
    ),
  );
}

class PassengerCorrectionSheet extends StatefulWidget {
  const PassengerCorrectionSheet({
    required this.cubit,
    required this.passenger,
    required this.showBorderFields,
    super.key,
  });

  final ManifesteCubit cubit;
  final Passenger passenger;

  /// Champs frontière (pièce / passeport, nationalité, naissance, fièvre
  /// jaune) : uniquement sur une ligne internationale.
  final bool showBorderFields;

  @override
  State<PassengerCorrectionSheet> createState() =>
      _PassengerCorrectionSheetState();
}

class _PassengerCorrectionSheetState extends State<PassengerCorrectionSheet> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  late final TextEditingController _firstName;
  late final TextEditingController _lastName;
  late final TextEditingController _phone;
  late final TextEditingController _email;
  late final TextEditingController _documentNumber;
  late final TextEditingController _birthDate;
  late final TextEditingController _reason;

  String? _documentType;
  String? _nationality;
  bool? _yellowFever;

  bool _submitting = false;
  String? _error;

  Passenger get _p => widget.passenger;

  @override
  void initState() {
    super.initState();
    final (String first, String last) = _p.editableName;
    _firstName = TextEditingController(text: first);
    _lastName = TextEditingController(text: last);
    _phone = TextEditingController(text: _p.telephone ?? '');
    _email = TextEditingController(text: _p.email ?? '');
    _documentNumber = TextEditingController(text: _p.documentNumero ?? '');
    _birthDate = TextEditingController(text: _p.dateNaissance ?? '');
    _reason = TextEditingController();
    _documentType = _p.documentType;
    _nationality = _p.nationalite;
    _yellowFever = _p.fievreJauneVaccine;
  }

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _phone.dispose();
    _email.dispose();
    _documentNumber.dispose();
    _birthDate.dispose();
    _reason.dispose();
    super.dispose();
  }

  String? _blankToNull(String value) {
    final String trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }

  /// Delta entre ce que le passager avait et ce qui est saisi. Seuls les
  /// champs modifiés partent au serveur (PATCH partiel).
  PassengerCorrection _buildCorrection() {
    final Map<String, Object?> changes = <String, Object?>{};

    final (String first, String last) = _p.editableName;
    if (_firstName.text.trim() != first.trim()) {
      changes[PassengerCorrectionKeys.firstName] = _firstName.text.trim();
    }
    if (_lastName.text.trim() != last.trim()) {
      changes[PassengerCorrectionKeys.lastName] = _lastName.text.trim();
    }

    final String? phone =
        _blankToNull(_phone.text.replaceAll(RegExp(r'[\s.-]'), ''));
    if (phone != _blankToNull(_p.telephone ?? '')) {
      changes[PassengerCorrectionKeys.phone] = phone;
    }
    final String? email = _blankToNull(_email.text);
    if (email != _blankToNull(_p.email ?? '')) {
      changes[PassengerCorrectionKeys.email] = email;
    }

    if (widget.showBorderFields) {
      final String? number = _blankToNull(_documentNumber.text);
      final bool typeChanged = _documentType != _p.documentType;
      final bool numberChanged =
          number != _blankToNull(_p.documentNumero ?? '');
      if (typeChanged || numberChanged) {
        // Le serveur exige type et numéro ensemble : on envoie les deux.
        changes[PassengerCorrectionKeys.documentType] = _documentType;
        changes[PassengerCorrectionKeys.documentNumber] = number;
      }
      if (_nationality != _p.nationalite) {
        changes[PassengerCorrectionKeys.nationality] = _nationality;
      }
      final String? birth = _blankToNull(_birthDate.text);
      if (birth != _blankToNull(_p.dateNaissance ?? '')) {
        changes[PassengerCorrectionKeys.birthDate] = birth;
      }
      if (_yellowFever != _p.fievreJauneVaccine) {
        changes[PassengerCorrectionKeys.yellowFever] = _yellowFever;
      }
    }
    return PassengerCorrection(changes);
  }

  Future<void> _pickBirthDate() async {
    final DateTime now = DateTime.now();
    final DateTime initial = DateTime.tryParse(_birthDate.text) ??
        DateTime(now.year - 30, now.month, now.day);
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: initial.isAfter(now) ? now : initial,
      firstDate: DateTime(1900),
      lastDate: now,
    );
    if (picked == null) return;
    setState(() {
      _birthDate.text = '${picked.year.toString().padLeft(4, '0')}-'
          '${picked.month.toString().padLeft(2, '0')}-'
          '${picked.day.toString().padLeft(2, '0')}';
    });
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;

    final PassengerCorrection correction = _buildCorrection();
    if (correction.isEmpty) {
      setState(() => _error = 'Aucune modification à enregistrer.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    final Result<PassengerCorrectionOutcome> result =
        await widget.cubit.correctPassenger(
      ticketUuid: _p.id,
      correction: correction,
      reason: _blankToNull(_reason.text),
    );
    if (!mounted) return;

    result.when(
      onSuccess: (PassengerCorrectionOutcome outcome) =>
          Navigator.of(context).pop(outcome),
      onFailure: (failure) => setState(() {
        _submitting = false;
        _error = failure.message;
      }),
    );
  }

  @override
  Widget build(BuildContext context) {
    final double keyboard = MediaQuery.viewInsetsOf(context).bottom;

    final Map<String, String> countries = <String, String>{
      ...kCountryLabels,
      if (_nationality != null && !kCountryLabels.containsKey(_nationality))
        _nationality!: _nationality!,
    };
    final Map<String, String> documentTypes = <String, String>{
      ...kDocumentTypeLabels,
      if (_documentType != null &&
          !kDocumentTypeLabels.containsKey(_documentType))
        _documentType!: _documentType!,
    };

    return Padding(
      padding: EdgeInsets.fromLTRB(
          AppSpacing.md, 0, AppSpacing.md, keyboard + AppSpacing.md),
      child: Form(
        key: _formKey,
        child: ListView(
          shrinkWrap: true,
          children: <Widget>[
            Text('Corriger les informations',
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 4),
            Text(
              'Siège ${_p.siege} · billet ${_p.ticketId.toUpperCase()}. '
              'Corrigez uniquement ce qui a été mal saisi.',
              style:
                  TextStyle(color: context.koraColors.textMuted, fontSize: 12),
            ),
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              controller: _firstName,
              textCapitalization: TextCapitalization.words,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(labelText: 'Prénom'),
              validator: (String? v) => (v ?? '').trim().isEmpty
                  ? 'Le prénom est obligatoire.'
                  : null,
            ),
            const SizedBox(height: AppSpacing.sm),
            TextFormField(
              controller: _lastName,
              textCapitalization: TextCapitalization.words,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(labelText: 'Nom'),
              validator: (String? v) =>
                  (v ?? '').trim().isEmpty ? 'Le nom est obligatoire.' : null,
            ),
            const SizedBox(height: AppSpacing.sm),
            TextFormField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(
                labelText: 'Téléphone',
                hintText: '+22997000000',
              ),
              inputFormatters: <TextInputFormatter>[
                FilteringTextInputFormatter.allow(RegExp(r'[0-9+\s.-]')),
              ],
              validator: (String? v) {
                final String value =
                    (v ?? '').replaceAll(RegExp(r'[\s.-]'), '');
                if (value.isEmpty) return null;
                return _phonePattern.hasMatch(value)
                    ? null
                    : 'Numéro invalide (ex : +22997000000).';
              },
            ),
            const SizedBox(height: AppSpacing.sm),
            TextFormField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              decoration:
                  const InputDecoration(labelText: 'Email (facultatif)'),
              validator: (String? v) {
                final String value = (v ?? '').trim();
                if (value.isEmpty) return null;
                return _emailPattern.hasMatch(value)
                    ? null
                    : 'Adresse email invalide.';
              },
            ),
            if (widget.showBorderFields) ...<Widget>[
              const SizedBox(height: AppSpacing.md),
              Text('Contrôle frontière',
                  style: Theme.of(context).textTheme.titleSmall),
              const SizedBox(height: AppSpacing.sm),
              DropdownButtonFormField<String?>(
                initialValue: _documentType,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'Type de pièce'),
                items: <DropdownMenuItem<String?>>[
                  const DropdownMenuItem<String?>(
                      value: null, child: Text('Non renseigné')),
                  ...documentTypes.entries.map(
                    (MapEntry<String, String> e) => DropdownMenuItem<String?>(
                        value: e.key, child: Text(e.value)),
                  ),
                ],
                onChanged: (String? v) => setState(() => _documentType = v),
              ),
              const SizedBox(height: AppSpacing.sm),
              TextFormField(
                controller: _documentNumber,
                textCapitalization: TextCapitalization.characters,
                textInputAction: TextInputAction.next,
                decoration: InputDecoration(
                  labelText: _documentType == 'passport'
                      ? 'Numéro de passeport'
                      : 'Numéro de la pièce',
                ),
                validator: (String? v) {
                  final bool hasNumber = (v ?? '').trim().isNotEmpty;
                  if (_documentType != null && !hasNumber) {
                    return 'Saisissez le numéro de la pièce.';
                  }
                  if (_documentType == null && hasNumber) {
                    return 'Choisissez le type de pièce.';
                  }
                  return null;
                },
              ),
              const SizedBox(height: AppSpacing.sm),
              DropdownButtonFormField<String?>(
                initialValue: _nationality,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'Nationalité'),
                items: <DropdownMenuItem<String?>>[
                  const DropdownMenuItem<String?>(
                      value: null, child: Text('Non renseignée')),
                  ...countries.entries.map(
                    (MapEntry<String, String> e) => DropdownMenuItem<String?>(
                        value: e.key, child: Text('${e.value} (${e.key})')),
                  ),
                ],
                onChanged: (String? v) => setState(() => _nationality = v),
              ),
              const SizedBox(height: AppSpacing.sm),
              TextFormField(
                controller: _birthDate,
                readOnly: true,
                onTap: _pickBirthDate,
                decoration: InputDecoration(
                  labelText: 'Date de naissance',
                  hintText: 'AAAA-MM-JJ',
                  suffixIcon: _birthDate.text.isEmpty
                      ? const Icon(Icons.calendar_today_outlined)
                      : IconButton(
                          icon: const Icon(Icons.clear),
                          onPressed: () => setState(_birthDate.clear),
                        ),
                ),
              ),
              const SizedBox(height: AppSpacing.sm),
              DropdownButtonFormField<bool?>(
                initialValue: _yellowFever,
                isExpanded: true,
                decoration:
                    const InputDecoration(labelText: 'Vaccin fièvre jaune'),
                items: const <DropdownMenuItem<bool?>>[
                  DropdownMenuItem<bool?>(
                      value: null, child: Text('Non renseigné')),
                  DropdownMenuItem<bool?>(value: true, child: Text('Vacciné')),
                  DropdownMenuItem<bool?>(
                      value: false, child: Text('Non vacciné')),
                ],
                onChanged: (bool? v) => setState(() => _yellowFever = v),
              ),
            ],
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              controller: _reason,
              maxLength: 500,
              minLines: 1,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Motif (facultatif)',
                hintText: 'Ex. faute de frappe constatée à la montée',
              ),
            ),
            if (_error != null) ...<Widget>[
              const SizedBox(height: AppSpacing.sm),
              Text(_error!, style: const TextStyle(color: Colors.red)),
            ],
            const SizedBox(height: AppSpacing.md),
            KoraButton(
              label: 'Enregistrer la correction',
              icon: Icons.check,
              isLoading: _submitting,
              onPressed: _submitting ? null : _submit,
            ),
            const SizedBox(height: AppSpacing.sm),
            KoraButton(
              label: 'Annuler',
              variant: KoraButtonVariant.ghost,
              onPressed: _submitting ? null : () => Navigator.of(context).pop(),
            ),
          ],
        ),
      ),
    );
  }
}
