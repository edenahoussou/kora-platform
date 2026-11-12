import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:uuid/uuid.dart';

import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/app_text_styles.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/errors/failures.dart';
import '../../../../core/result/result.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../injection/injection.dart';
import '../../../synchro/presentation/cubit/sync_cubit.dart';
import '../../domain/entities/trip_log.dart';
import '../../domain/repositories/trip_log_repository.dart';
import '../trip_log_formatters.dart';

/// Arguments de [TripExpenseFormPage] (route `AppRoutes.tripExpenseForm`).
class TripExpenseFormArgs {
  const TripExpenseFormArgs({
    required this.departureUuid,
    required this.kind,
    this.suggestedOdometerKm,
    this.busRegistration,
  });

  final String departureUuid;

  /// `fuel` ouvre le formulaire carburant ; `toll`/`borderTax` le
  /// formulaire péage / taxe frontière (bascule possible entre les deux).
  final TripExpenseKind kind;
  final int? suggestedOdometerKm;
  final String? busRegistration;
}

/// Pays des postes frontières les plus fréquents sur le réseau.
const List<({String code, String label})> _borderCountries =
    <({String code, String label})>[
  (code: 'BJ', label: 'Bénin'),
  (code: 'TG', label: 'Togo'),
  (code: 'NG', label: 'Nigeria'),
  (code: 'GH', label: 'Ghana'),
  (code: 'BF', label: 'Burkina Faso'),
  (code: 'NE', label: 'Niger'),
  (code: 'CI', label: 'Côte d\'Ivoire'),
];

/// Déclaration d'une dépense de voyage par le chauffeur ou le convoyeur :
///
/// - **Carburant** : litres, prix au litre (total calculé), station, photo
///   du ticket (obligatoire), compteur au moment du plein ;
/// - **Péage / taxe frontière** : montant, poste, pays (taxe), photo du
///   reçu (facultative).
///
/// La dépense part « en attente de validation » au bureau ; hors ligne,
/// elle est mise en file et envoyée au retour du réseau.
class TripExpenseFormPage extends StatefulWidget {
  const TripExpenseFormPage({required this.args, super.key});

  final TripExpenseFormArgs args;

  @override
  State<TripExpenseFormPage> createState() => _TripExpenseFormPageState();
}

class _TripExpenseFormPageState extends State<TripExpenseFormPage> {
  late TripExpenseKind _kind = widget.args.kind;
  TripPaymentChannel _channel = TripPaymentChannel.cash;
  String? _countryCode;
  String? _photoPath;
  bool _submitting = false;
  bool _showErrors = false;

  final TextEditingController _liters = TextEditingController();
  final TextEditingController _unitPrice = TextEditingController();
  final TextEditingController _amount = TextEditingController();
  final TextEditingController _station = TextEditingController();
  final TextEditingController _checkpoint = TextEditingController();
  late final TextEditingController _odometer = TextEditingController(
    text: widget.args.suggestedOdometerKm?.toString() ?? '',
  );
  final TextEditingController _note = TextEditingController();

  bool get _isFuel => _kind == TripExpenseKind.fuel;

  double? get _litersValue =>
      double.tryParse(_liters.text.trim().replaceAll(',', '.'));
  int? get _unitPriceValue => int.tryParse(_unitPrice.text.trim());
  int? get _amountValue => int.tryParse(_amount.text.trim());
  int? get _odometerValue => int.tryParse(_odometer.text.trim());

  int? get _fuelTotal {
    final double? liters = _litersValue;
    final int? price = _unitPriceValue;
    if (liters == null || price == null) return null;
    return (liters * price).round();
  }

  @override
  void initState() {
    super.initState();
    for (final TextEditingController c in <TextEditingController>[
      _liters,
      _unitPrice,
      _amount,
      _station,
      _checkpoint,
    ]) {
      c.addListener(_refresh);
    }
  }

  void _refresh() => setState(() {});

  @override
  void dispose() {
    for (final TextEditingController c in <TextEditingController>[
      _liters,
      _unitPrice,
      _amount,
      _station,
      _checkpoint,
      _odometer,
      _note,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  /// Erreurs de saisie, champ par champ (affichées après une tentative
  /// d'envoi).
  Map<String, String> get _errors {
    final Map<String, String> errors = <String, String>{};
    if (_isFuel) {
      final double? liters = _litersValue;
      if (liters == null || liters <= 0) {
        errors['liters'] = 'Quantité en litres requise.';
      }
      final int? price = _unitPriceValue;
      if (price == null || price <= 0) {
        errors['unit_price'] = 'Prix au litre requis.';
      }
      if (_station.text.trim().length < 2) {
        errors['station'] = 'Nom de la station requis.';
      }
      if (_photoPath == null) {
        errors['photo'] = 'La photo du ticket est obligatoire.';
      }
    } else {
      final int? amount = _amountValue;
      if (amount == null || amount <= 0) {
        errors['amount'] = 'Montant payé requis.';
      }
      if (_checkpoint.text.trim().length < 2) {
        errors['checkpoint'] = _kind == TripExpenseKind.borderTax
            ? 'Poste frontière requis.'
            : 'Poste de péage requis.';
      }
    }
    return errors;
  }

  String? _errorOf(String field) => _showErrors ? _errors[field] : null;

  Future<void> _pickPhoto() async {
    final ImageSource? source = await showModalBottomSheet<ImageSource>(
      context: context,
      showDragHandle: true,
      builder: (BuildContext sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            ListTile(
              leading: const Icon(Icons.photo_camera_rounded),
              title: const Text('Prendre une photo'),
              onTap: () => Navigator.of(sheetContext).pop(ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_rounded),
              title: const Text('Choisir dans la galerie'),
              onTap: () => Navigator.of(sheetContext).pop(ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;

    try {
      final XFile? file = await ImagePicker().pickImage(
        source: source,
        imageQuality: 70,
        maxWidth: 1600,
        maxHeight: 1600,
      );
      if (file != null && mounted) setState(() => _photoPath = file.path);
    } on PlatformException catch (error) {
      if (!mounted) return;
      await KoraDialogs.error(
        context,
        title: 'Photo impossible',
        description: error.code == 'camera_access_denied'
            ? 'Autorisez l\'appareil photo dans les réglages du téléphone.'
            : (error.message ?? 'Impossible d\'ouvrir l\'appareil photo.'),
      );
    }
  }

  Future<void> _submit() async {
    if (_errors.isNotEmpty) {
      setState(() => _showErrors = true);
      return;
    }

    setState(() => _submitting = true);
    final TripLogRepository repository = getIt<TripLogRepository>();
    final String clientUuid = const Uuid().v4();

    String? photoPath;
    final String? picked = _photoPath;
    if (picked != null) {
      try {
        photoPath = await repository.persistPhoto(picked, clientUuid);
      } on Object {
        photoPath = picked;
      }
    }

    final TripExpenseDraft draft = TripExpenseDraft(
      clientUuid: clientUuid,
      departureUuid: widget.args.departureUuid,
      kind: _kind,
      occurredAt: DateTime.now(),
      amount: _isFuel ? null : _amountValue,
      liters: _isFuel ? _litersValue : null,
      unitPrice: _isFuel ? _unitPriceValue : null,
      stationLabel: _isFuel ? _station.text.trim() : null,
      checkpointLabel: _isFuel ? null : _checkpoint.text.trim(),
      countryCode: _kind == TripExpenseKind.borderTax ? _countryCode : null,
      odometerKm: _odometerValue,
      paymentChannel: _channel,
      note: _note.text.trim().isEmpty ? null : _note.text.trim(),
      photoPath: photoPath,
    );

    final Result<TripExpense> result = await repository.submitExpense(draft);
    if (!mounted) return;
    setState(() => _submitting = false);

    await result.when<Future<void>>(
      onSuccess: (TripExpense expense) async {
        if (expense.pendingSync) {
          unawaited(getIt<SyncCubit>().refreshPendingCount());
        }
        await KoraDialogs.success(
          context,
          title: '${expense.kind.label} déclaré',
          description: expense.pendingSync
              ? 'Enregistré hors ligne — sera envoyé au bureau au retour '
                  'du réseau.'
              : '${formatFcfa(expense.amount)} — envoyé au bureau pour '
                  'validation.',
        );
        if (mounted) context.pop(true);
      },
      onFailure: (Failure failure) => KoraDialogs.error(
        context,
        title: 'Dépense non enregistrée',
        description: failure.message,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final KoraColors colors = context.koraColors;

    return Scaffold(
      backgroundColor: colors.background,
      appBar: ScreenHeader(
        eyebrow: widget.args.busRegistration ?? 'Carnet de bord',
        title: _isFuel ? 'Plein de carburant' : 'Péage / taxe frontière',
      ),
      body: SafeArea(
        child: Column(
          children: <Widget>[
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.md),
                children: <Widget>[
                  if (!_isFuel) ...<Widget>[
                    SegmentedButton<TripExpenseKind>(
                      segments: const <ButtonSegment<TripExpenseKind>>[
                        ButtonSegment<TripExpenseKind>(
                          value: TripExpenseKind.toll,
                          label: Text('Péage'),
                          icon: Icon(Icons.toll_rounded),
                        ),
                        ButtonSegment<TripExpenseKind>(
                          value: TripExpenseKind.borderTax,
                          label: Text('Taxe frontière'),
                          icon: Icon(Icons.flag_rounded),
                        ),
                      ],
                      selected: <TripExpenseKind>{_kind},
                      onSelectionChanged: (Set<TripExpenseKind> value) =>
                          setState(() => _kind = value.first),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                  if (_isFuel) ..._fuelFields(colors) else ..._tollFields(),
                  const SizedBox(height: AppSpacing.md),
                  TextField(
                    controller: _odometer,
                    keyboardType: TextInputType.number,
                    inputFormatters: <TextInputFormatter>[
                      FilteringTextInputFormatter.digitsOnly,
                      LengthLimitingTextInputFormatter(7),
                    ],
                    decoration: const InputDecoration(
                      labelText: 'Compteur du bus (km) — facultatif',
                      suffixText: 'km',
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  Text('Payé en',
                      style: AppTextStyles.titleMedium(colors.textPrimary)),
                  const SizedBox(height: AppSpacing.sm),
                  Wrap(
                    spacing: AppSpacing.sm,
                    runSpacing: AppSpacing.sm,
                    children: TripPaymentChannel.values
                        .map((TripPaymentChannel c) => ChoiceChip(
                              label: Text(c.label),
                              selected: _channel == c,
                              onSelected: (_) => setState(() => _channel = c),
                              selectedColor:
                                  AppColors.gold.withValues(alpha: 0.25),
                            ))
                        .toList(),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  _PhotoField(
                    path: _photoPath,
                    mandatory: _isFuel,
                    error: _errorOf('photo'),
                    onPick: _pickPhoto,
                    onRemove: () => setState(() => _photoPath = null),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  TextField(
                    controller: _note,
                    minLines: 1,
                    maxLines: 3,
                    maxLength: 500,
                    textCapitalization: TextCapitalization.sentences,
                    decoration: const InputDecoration(
                      labelText: 'Commentaire — facultatif',
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.md, 0, AppSpacing.md, AppSpacing.md),
              child: KoraButton(
                label: 'Envoyer au bureau',
                icon: Icons.send_rounded,
                isLoading: _submitting,
                onPressed: _submit,
              ),
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _fuelFields(KoraColors colors) {
    final int? total = _fuelTotal;

    return <Widget>[
      Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Expanded(
            child: TextField(
              controller: _liters,
              autofocus: true,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              inputFormatters: <TextInputFormatter>[
                FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]')),
                LengthLimitingTextInputFormatter(8),
              ],
              decoration: InputDecoration(
                labelText: 'Litres',
                suffixText: 'L',
                errorText: _errorOf('liters'),
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: TextField(
              controller: _unitPrice,
              keyboardType: TextInputType.number,
              inputFormatters: <TextInputFormatter>[
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(6),
              ],
              decoration: InputDecoration(
                labelText: 'Prix au litre',
                suffixText: 'FCFA',
                errorText: _errorOf('unit_price'),
              ),
            ),
          ),
        ],
      ),
      const SizedBox(height: AppSpacing.sm),
      Container(
        padding: const EdgeInsets.all(AppSpacing.md),
        decoration: BoxDecoration(
          color: AppColors.gold.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        child: Row(
          children: <Widget>[
            Text('Total', style: AppTextStyles.body(colors.textPrimary)),
            const Spacer(),
            Text(
              total == null ? '—' : formatFcfa(total),
              style: AppTextStyles.titleMedium(colors.textPrimary),
            ),
          ],
        ),
      ),
      const SizedBox(height: AppSpacing.md),
      TextField(
        controller: _station,
        textCapitalization: TextCapitalization.words,
        decoration: InputDecoration(
          labelText: 'Station',
          hintText: 'Ex. Total Godomey, Oryx Sèmè…',
          errorText: _errorOf('station'),
        ),
      ),
    ];
  }

  List<Widget> _tollFields() {
    final bool border = _kind == TripExpenseKind.borderTax;

    return <Widget>[
      TextField(
        controller: _amount,
        autofocus: true,
        keyboardType: TextInputType.number,
        inputFormatters: <TextInputFormatter>[
          FilteringTextInputFormatter.digitsOnly,
          LengthLimitingTextInputFormatter(8),
        ],
        decoration: InputDecoration(
          labelText: 'Montant payé',
          suffixText: 'FCFA',
          errorText: _errorOf('amount'),
        ),
      ),
      const SizedBox(height: AppSpacing.md),
      TextField(
        controller: _checkpoint,
        textCapitalization: TextCapitalization.words,
        decoration: InputDecoration(
          labelText: border ? 'Poste frontière' : 'Poste de péage',
          hintText: border ? 'Ex. Hillacondji, Sèmè-Kraké…' : 'Ex. Ouèdo…',
          errorText: _errorOf('checkpoint'),
        ),
      ),
      if (border) ...<Widget>[
        const SizedBox(height: AppSpacing.md),
        Text('Pays du poste',
            style: AppTextStyles.titleMedium(context.koraColors.textPrimary)),
        const SizedBox(height: AppSpacing.sm),
        Wrap(
          spacing: AppSpacing.sm,
          runSpacing: AppSpacing.sm,
          children: _borderCountries
              .map((({String code, String label}) country) => ChoiceChip(
                    label: Text(country.label),
                    selected: _countryCode == country.code,
                    onSelected: (bool selected) => setState(
                        () => _countryCode = selected ? country.code : null),
                    selectedColor: AppColors.gold.withValues(alpha: 0.25),
                  ))
              .toList(),
        ),
      ],
    ];
  }
}

class _PhotoField extends StatelessWidget {
  const _PhotoField({
    required this.path,
    required this.mandatory,
    required this.onPick,
    required this.onRemove,
    this.error,
  });

  final String? path;
  final bool mandatory;
  final String? error;
  final VoidCallback onPick;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final KoraColors colors = context.koraColors;
    final String? current = path;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          mandatory ? 'Photo du ticket' : 'Photo du reçu — facultative',
          style: AppTextStyles.titleMedium(colors.textPrimary),
        ),
        const SizedBox(height: AppSpacing.sm),
        if (current == null)
          InkWell(
            onTap: onPick,
            borderRadius: BorderRadius.circular(AppRadius.lg),
            child: Container(
              height: 120,
              width: double.infinity,
              decoration: BoxDecoration(
                color: colors.surface,
                borderRadius: BorderRadius.circular(AppRadius.lg),
                border: Border.all(
                  color: error != null ? AppColors.danger : colors.line,
                  width: error != null ? 1.5 : 1,
                ),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: <Widget>[
                  Icon(Icons.add_a_photo_rounded,
                      size: 30, color: colors.textMuted),
                  const SizedBox(height: 6),
                  Text('Photographier le ticket',
                      style: AppTextStyles.body(colors.textMuted)),
                ],
              ),
            ),
          )
        else
          Stack(
            children: <Widget>[
              ClipRRect(
                borderRadius: BorderRadius.circular(AppRadius.lg),
                child: Image.file(
                  File(current),
                  height: 180,
                  width: double.infinity,
                  fit: BoxFit.cover,
                ),
              ),
              Positioned(
                top: AppSpacing.sm,
                right: AppSpacing.sm,
                child: Row(
                  children: <Widget>[
                    _RoundAction(icon: Icons.refresh_rounded, onTap: onPick),
                    const SizedBox(width: AppSpacing.xs),
                    _RoundAction(icon: Icons.close_rounded, onTap: onRemove),
                  ],
                ),
              ),
            ],
          ),
        if (error != null)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(error!,
                style: const TextStyle(color: AppColors.danger, fontSize: 12)),
          ),
      ],
    );
  }
}

class _RoundAction extends StatelessWidget {
  const _RoundAction({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.black.withValues(alpha: 0.55),
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: Icon(icon, size: 18, color: Colors.white),
        ),
      ),
    );
  }
}
