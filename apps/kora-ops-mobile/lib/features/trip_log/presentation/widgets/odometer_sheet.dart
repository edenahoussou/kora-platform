import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../domain/entities/trip_log.dart';
import '../trip_log_formatters.dart';

/// Saisie du kilométrage compteur (départ ou arrivée) dans un bottom sheet,
/// même présentation que `showDelaySheet`. Retourne `null` si abandonné
/// (« Plus tard »).
///
/// [minKm] : kilométrage de départ déjà saisi (l'arrivée ne peut pas être
/// inférieure). [hintKm] : dernier relevé connu du bus, pour repère.
Future<int?> showOdometerSheet(
  BuildContext context, {
  required OdometerKind kind,
  int? initialKm,
  int? minKm,
  int? hintKm,
  String? busRegistration,
  String cancelLabel = 'Annuler',
}) {
  return showModalBottomSheet<int>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (BuildContext sheetContext) => _OdometerSheet(
      kind: kind,
      initialKm: initialKm,
      minKm: minKm,
      hintKm: hintKm,
      busRegistration: busRegistration,
      cancelLabel: cancelLabel,
    ),
  );
}

class _OdometerSheet extends StatefulWidget {
  const _OdometerSheet({
    required this.kind,
    required this.cancelLabel,
    this.initialKm,
    this.minKm,
    this.hintKm,
    this.busRegistration,
  });

  final OdometerKind kind;
  final int? initialKm;
  final int? minKm;
  final int? hintKm;
  final String? busRegistration;
  final String cancelLabel;

  @override
  State<_OdometerSheet> createState() => _OdometerSheetState();
}

class _OdometerSheetState extends State<_OdometerSheet> {
  late final TextEditingController _controller = TextEditingController(
    text: widget.initialKm?.toString() ?? '',
  );

  int? get _value => int.tryParse(_controller.text.trim());

  String? get _error {
    final int? value = _value;
    if (_controller.text.trim().isEmpty) return null;
    if (value == null) return 'Nombre entier uniquement.';
    final int? min = widget.minKm;
    if (widget.kind == OdometerKind.end && min != null && value < min) {
      return 'Inférieur au départ (${formatKm(min)}).';
    }
    return null;
  }

  bool get _isValid => _value != null && _error == null;

  @override
  void initState() {
    super.initState();
    _controller.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _submit() {
    if (!_isValid) return;
    Navigator.of(context).pop(_value);
  }

  @override
  Widget build(BuildContext context) {
    final double keyboard = MediaQuery.viewInsetsOf(context).bottom;
    final KoraColors colors = context.koraColors;
    final int? value = _value;
    final int? min = widget.minKm;
    final String title = widget.kind == OdometerKind.start
        ? 'Kilométrage au départ'
        : 'Kilométrage à l\'arrivée';

    return Padding(
      padding: EdgeInsets.fromLTRB(
          AppSpacing.md, 0, AppSpacing.md, keyboard + AppSpacing.md),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 4),
            Text(
              'Relevez le compteur du bus'
              '${widget.busRegistration != null ? ' ${widget.busRegistration}' : ''}'
              ' et saisissez la valeur affichée.',
              style: TextStyle(color: colors.textMuted, fontSize: 12),
            ),
            const SizedBox(height: AppSpacing.md),
            TextField(
              controller: _controller,
              autofocus: true,
              keyboardType: TextInputType.number,
              textInputAction: TextInputAction.done,
              onSubmitted: (_) => _submit(),
              inputFormatters: <TextInputFormatter>[
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(7),
              ],
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700),
              decoration: InputDecoration(
                labelText: 'Compteur (km)',
                suffixText: 'km',
                errorText: _error,
                helperText: widget.hintKm != null
                    ? 'Dernier relevé connu : ${formatKm(widget.hintKm!)}'
                    : null,
              ),
            ),
            if (widget.kind == OdometerKind.end &&
                min != null &&
                value != null &&
                value >= min) ...<Widget>[
              const SizedBox(height: AppSpacing.sm),
              Text(
                'Distance parcourue : ${formatKm(value - min)}',
                style: TextStyle(
                  color: colors.textPrimary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
            const SizedBox(height: AppSpacing.md),
            KoraButton(
              label: 'Enregistrer',
              icon: Icons.speed,
              onPressed: _isValid ? _submit : null,
            ),
            const SizedBox(height: AppSpacing.sm),
            KoraButton(
              label: widget.cancelLabel,
              variant: KoraButtonVariant.ghost,
              onPressed: () => Navigator.of(context).pop(),
            ),
          ],
        ),
      ),
    );
  }
}
