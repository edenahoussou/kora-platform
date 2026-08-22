import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../auth/domain/repositories/auth_repository.dart';
import '../../../auth/presentation/widgets/pin_pad.dart';

/// Les trois temps du changement de PIN.
enum _Step { current, next, confirm }

/// Modification du PIN, depuis l'écran Sécurité.
///
/// L'API demande l'ancien code et le nouveau confirmé
/// (`POST auth/pin/change`, règle `confirmed` de Laravel). L'écran suit
/// donc le même pavé que l'inscription, trois fois de suite, plutôt que
/// d'aligner trois champs sur la même page.
class ChangePinPage extends StatefulWidget {
  const ChangePinPage({super.key, this.onDone});

  final VoidCallback? onDone;

  @override
  State<ChangePinPage> createState() => _ChangePinPageState();
}

class _ChangePinPageState extends State<ChangePinPage> {
  static const int _pinLength = 4;

  _Step _step = _Step.current;
  String _pin = '';
  String _currentPin = '';
  String _newPin = '';

  bool _saving = false;
  String? _error;

  void _press(String key) {
    setState(() {
      _error = null;

      switch (key) {
        case PinKeypad.backspace:
          if (_pin.isNotEmpty) _pin = _pin.substring(0, _pin.length - 1);
        case PinKeypad.validate:
          _submit();
        default:
          if (_pin.length < _pinLength) _pin += key;
      }
    });
  }

  Future<void> _submit() async {
    final l10n = context.l10n;

    if (_pin.length < _pinLength) {
      setState(() => _error = l10n.changePinIncomplete);
      return;
    }

    switch (_step) {
      case _Step.current:
        setState(() {
          _currentPin = _pin;
          _pin = '';
          _step = _Step.next;
        });
      case _Step.next:
        // Le serveur refuse un nouveau PIN identique à l'ancien
        // (`Rule::notIn`) : autant le dire avant l'aller-retour.
        if (_pin == _currentPin) {
          setState(() {
            _pin = '';
            _error = l10n.changePinSame;
          });
          return;
        }

        setState(() {
          _newPin = _pin;
          _pin = '';
          _step = _Step.confirm;
        });
      case _Step.confirm:
        if (_pin != _newPin) {
          // Comme à l'inscription, on repart du choix : corriger la
          // seule confirmation validerait un code non voulu.
          setState(() {
            _pin = '';
            _newPin = '';
            _step = _Step.next;
            _error = l10n.createPinMismatch;
          });
          return;
        }

        await _save();
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);

    final result = await getIt<AuthRepository>().changePin(
      currentPin: _currentPin,
      newPin: _newPin,
    );
    if (!mounted) return;

    setState(() => _saving = false);

    result.fold(
      (failure) => setState(() {
        // Ancien PIN refusé : on recommence du début, il n'y a rien à
        // corriger dans le nouveau.
        _step = _Step.current;
        _pin = '';
        _currentPin = '';
        _newPin = '';
        _error = failure.message;
      }),
      (_) {
        KoraToaster.show(context, context.l10n.changePinDone);
        widget.onDone?.call();
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;

    final (title, lead) = switch (_step) {
      _Step.current => (l10n.changePinCurrent, l10n.changePinCurrentLead),
      _Step.next => (l10n.changePinNew, l10n.changePinNewLead),
      _Step.confirm => (l10n.changePinConfirm, l10n.changePinConfirmLead),
    };

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(eyebrow: l10n.changePinEyebrow, title: title),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    Text(lead, style: AppTextStyles.lead(koraColors.textMuted)),
                    PinDots(filled: _pin.length, total: _pinLength),
                    if (_error != null) ...[
                      Center(
                        child: Text(
                          _error!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: AppColors.danger,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                    PinKeypad(onKey: _press),
                    const SizedBox(height: 18),
                    KoraButton(
                      label: l10n.actionContinue,
                      isLoading: _saving,
                      onPressed: _saving ? null : _submit,
                    ),
                    const SizedBox(height: AppSpacing.lg),
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
