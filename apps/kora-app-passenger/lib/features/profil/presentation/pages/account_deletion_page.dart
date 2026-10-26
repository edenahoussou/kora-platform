import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../auth/domain/repositories/auth_repository.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../auth/presentation/widgets/pin_pad.dart';

/// Les deux temps de la demande : comprendre, puis confirmer.
enum _Step { explain, pin }

/// Demande de suppression du compte, depuis l'écran Sécurité.
///
/// Apple et Google exigent que la suppression puisse commencer dans
/// l'app. `DELETE auth/me` enregistre une demande que l'équipe KORA
/// traite sous 30 jours ; le PIN y est re-vérifié, parce qu'un téléphone
/// laissé déverrouillé ne doit pas suffire à effacer un compte.
///
/// Le serveur ne ferme pas la session : une fois la demande partie,
/// l'écran déconnecte lui-même le voyageur.
class AccountDeletionPage extends StatefulWidget {
  const AccountDeletionPage({super.key});

  @override
  State<AccountDeletionPage> createState() => _AccountDeletionPageState();
}

class _AccountDeletionPageState extends State<AccountDeletionPage> {
  static const int _pinLength = 4;

  /// La limite du serveur (`max:500`) : au-delà, il refuserait tout.
  static const int _reasonMaxLength = 500;

  final _reason = TextEditingController();

  _Step _step = _Step.explain;
  String _pin = '';
  bool _sending = false;
  String? _error;

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  void _press(String key) {
    setState(() {
      _error = null;

      switch (key) {
        case PinKeypad.backspace:
          if (_pin.isNotEmpty) _pin = _pin.substring(0, _pin.length - 1);
        case PinKeypad.validate:
          _send();
        default:
          if (_pin.length < _pinLength) _pin += key;
      }
    });
  }

  Future<void> _send() async {
    final l10n = context.l10n;

    if (_pin.length < _pinLength) {
      setState(() => _error = l10n.changePinIncomplete);
      return;
    }

    setState(() => _sending = true);

    final result = await getIt<AuthRepository>().requestAccountDeletion(
      pin: _pin,
      reason: _reason.text,
    );
    if (!mounted) return;

    result.fold(
      (failure) => setState(() {
        // PIN refusé, réseau absent : rien n'est parti, on recommence la
        // saisie sans perdre le motif.
        _sending = false;
        _pin = '';
        _error = failure.message;
      }),
      (_) {
        KoraToaster.show(context, l10n.accountDeletionDone);
        context.read<AuthBloc>().add(const AuthLogoutRequested());
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.accountDeletionEyebrow,
                title: _step == _Step.explain
                    ? l10n.accountDeletionTitle
                    : l10n.accountDeletionPinTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: _step == _Step.explain
                      ? _explain(context)
                      : _confirm(context),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  List<Widget> _explain(BuildContext context) {
    final l10n = context.l10n;

    return [
      Text(
        l10n.accountDeletionLead,
        style: AppTextStyles.lead(context.koraColors.textMuted),
      ),
      const SizedBox(height: AppSpacing.lg),
      KoraInput(
        label: l10n.accountDeletionReason,
        hint: l10n.accountDeletionReasonHint,
        controller: _reason,
        inputFormatters: [LengthLimitingTextInputFormatter(_reasonMaxLength)],
      ),
      const SizedBox(height: AppSpacing.lg),
      KoraButton(
        label: l10n.actionContinue,
        variant: KoraButtonVariant.danger,
        onPressed: () => setState(() => _step = _Step.pin),
      ),
      const SizedBox(height: AppSpacing.lg),
    ];
  }

  List<Widget> _confirm(BuildContext context) {
    final l10n = context.l10n;
    final error = _error;

    return [
      Text(
        l10n.accountDeletionPinLead,
        style: AppTextStyles.lead(context.koraColors.textMuted),
      ),
      PinDots(filled: _pin.length, total: _pinLength),
      if (error != null) ...[
        Center(
          child: Text(
            error,
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
        label: l10n.accountDeletionSubmit,
        variant: KoraButtonVariant.danger,
        isLoading: _sending,
        onPressed: _sending ? null : _send,
      ),
      const SizedBox(height: AppSpacing.lg),
    ];
  }
}
