import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../app/theme/app_spacing.dart';
import '../../app/theme/kora_colors.dart';
import 'kora_button.dart';

/// Saisie d'un retard (minutes + motif) dans un bottom sheet — remplace
/// l'ancien dialogue modal `KoraDialogs.promptDelay`. Retourne `null` si
/// abandonné. « 0 » minute est valide (annule un retard enregistré).
Future<({int minutes, String reason})?> showDelaySheet(
  BuildContext context, {
  required String title,
  String? description,
  int initialMinutes = 0,
  String confirmLabel = 'Enregistrer',
  String cancelLabel = 'Annuler',
}) {
  return showModalBottomSheet<({int minutes, String reason})>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (BuildContext sheetContext) => _DelaySheet(
      title: title,
      description: description,
      initialMinutes: initialMinutes,
      confirmLabel: confirmLabel,
      cancelLabel: cancelLabel,
    ),
  );
}

class _DelaySheet extends StatefulWidget {
  const _DelaySheet({
    required this.title,
    required this.initialMinutes,
    required this.confirmLabel,
    required this.cancelLabel,
    this.description,
  });

  final String title;
  final String? description;
  final int initialMinutes;
  final String confirmLabel;
  final String cancelLabel;

  @override
  State<_DelaySheet> createState() => _DelaySheetState();
}

class _DelaySheetState extends State<_DelaySheet> {
  // Contrôleurs portés par le State : disposés uniquement quand le sheet
  // est réellement démonté (pas en course avec l'animation de fermeture).
  late final TextEditingController _minutes =
      TextEditingController(text: widget.initialMinutes.toString());
  final TextEditingController _reason = TextEditingController();

  bool get _isValid {
    final int? minutes = int.tryParse(_minutes.text.trim());
    return minutes != null && minutes >= 0 && _reason.text.trim().isNotEmpty;
  }

  @override
  void initState() {
    super.initState();
    _minutes.addListener(_refresh);
    _reason.addListener(_refresh);
  }

  void _refresh() => setState(() {});

  @override
  void dispose() {
    _minutes.dispose();
    _reason.dispose();
    super.dispose();
  }

  void _submit() {
    if (!_isValid) return;
    Navigator.of(context).pop((
      minutes: int.parse(_minutes.text.trim()),
      reason: _reason.text.trim(),
    ));
  }

  @override
  Widget build(BuildContext context) {
    final double keyboard = MediaQuery.viewInsetsOf(context).bottom;

    return Padding(
      padding: EdgeInsets.fromLTRB(
          AppSpacing.md, 0, AppSpacing.md, keyboard + AppSpacing.md),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(widget.title, style: Theme.of(context).textTheme.titleMedium),
            if (widget.description != null) ...<Widget>[
              const SizedBox(height: 4),
              Text(
                widget.description!,
                style: TextStyle(
                    color: context.koraColors.textMuted, fontSize: 12),
              ),
            ],
            const SizedBox(height: AppSpacing.md),
            TextField(
              controller: _minutes,
              autofocus: true,
              keyboardType: TextInputType.number,
              inputFormatters: <TextInputFormatter>[
                FilteringTextInputFormatter.digitsOnly,
              ],
              decoration: const InputDecoration(labelText: 'Retard (minutes)'),
            ),
            const SizedBox(height: AppSpacing.sm),
            TextField(
              controller: _reason,
              minLines: 2,
              maxLines: 4,
              maxLength: 255,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                labelText: 'Motif du retard',
                hintText: 'Ex. embouteillage, panne, passagers en retard…',
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            KoraButton(
              label: widget.confirmLabel,
              icon: Icons.schedule,
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
