import 'package:flutter/material.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/helpers/responsive.dart';

/// Clavier numérique de saisie du PIN — touches blanches arrondies sur fond
/// bleu marine, fidèle à la maquette "Votre PIN KORA". Touche 10 = effacer,
/// touche 12 = valider (case échéant).
class NumericKeypad extends StatelessWidget {
  const NumericKeypad({
    required this.onDigitPressed,
    required this.onBackspacePressed,
    this.onConfirmPressed,
    super.key,
  });

  final ValueChanged<String> onDigitPressed;
  final VoidCallback onBackspacePressed;
  final VoidCallback? onConfirmPressed;

  static const List<List<_KeypadKey>> _rows = <List<_KeypadKey>>[
    <_KeypadKey>[_KeypadKey.digit('1'), _KeypadKey.digit('2'), _KeypadKey.digit('3')],
    <_KeypadKey>[_KeypadKey.digit('4'), _KeypadKey.digit('5'), _KeypadKey.digit('6')],
    <_KeypadKey>[_KeypadKey.digit('7'), _KeypadKey.digit('8'), _KeypadKey.digit('9')],
    <_KeypadKey>[_KeypadKey.backspace(), _KeypadKey.digit('0'), _KeypadKey.confirm()],
  ];

  @override
  Widget build(BuildContext context) {
    final double keySize = Responsive.value<double>(context, mobile: 72, tablet: 88);

    return Column(
      children: _rows
          .map(
            (List<_KeypadKey> row) => Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.md),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: row.map((_KeypadKey key) => _buildKey(context, key, keySize)).toList(),
              ),
            ),
          )
          .toList(),
    );
  }

  Widget _buildKey(BuildContext context, _KeypadKey key, double size) {
    final Widget content = switch (key.type) {
      _KeypadKeyType.digit => Text(
          key.value!,
          style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: AppColors.primary),
        ),
      _KeypadKeyType.backspace => const Icon(Icons.backspace_outlined, color: AppColors.primary),
      _KeypadKeyType.confirm => const Icon(Icons.check, color: AppColors.primary),
    };

    return SizedBox(
      width: size,
      height: size * 0.72,
      child: Material(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: InkWell(
          borderRadius: BorderRadius.circular(AppRadius.md),
          onTap: () => switch (key.type) {
            _KeypadKeyType.digit => onDigitPressed(key.value!),
            _KeypadKeyType.backspace => onBackspacePressed(),
            _KeypadKeyType.confirm => onConfirmPressed?.call(),
          },
          child: Center(child: content),
        ),
      ),
    );
  }
}

enum _KeypadKeyType { digit, backspace, confirm }

class _KeypadKey {
  const _KeypadKey.digit(this.value) : type = _KeypadKeyType.digit;
  const _KeypadKey.backspace() : type = _KeypadKeyType.backspace, value = null;
  const _KeypadKey.confirm() : type = _KeypadKeyType.confirm, value = null;

  final _KeypadKeyType type;
  final String? value;
}
