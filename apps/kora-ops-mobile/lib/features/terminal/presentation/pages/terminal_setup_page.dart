import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../cubit/terminal_cubit.dart';

/// Écran affiché après la connexion tant qu'aucun terminal P501 n'est lié
/// à cet appareil (voir `AuthGuard`, MEZ-91). Aucune route de retour :
/// tant que la liaison n'est pas faite, aucun écran métier n'est
/// utilisable (le scan, le manifeste, la sync en dépendent tous) — seule
/// échappatoire, se déconnecter pour reprendre sur un autre compte.
class TerminalSetupPage extends StatefulWidget {
  const TerminalSetupPage({super.key});

  @override
  State<TerminalSetupPage> createState() => _TerminalSetupPageState();
}

class _TerminalSetupPageState extends State<TerminalSetupPage> {
  final TextEditingController _serialController = TextEditingController();
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  @override
  void dispose() {
    _serialController.dispose();
    super.dispose();
  }

  String? _validateSerial(String? value) {
    final String serial = value?.trim() ?? '';
    return serial.isEmpty ? 'Numéro de série requis.' : null;
  }

  void _submit() {
    if (!_formKey.currentState!.validate()) return;
    context.read<TerminalCubit>().link(_serialController.text.trim());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: Responsive.value<double>(context,
                  mobile: double.infinity, tablet: 480),
            ),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: BlocBuilder<TerminalCubit, TerminalState>(
                builder: (context, state) {
                  final bool isLinking = state is TerminalLinking;

                  return Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: <Widget>[
                        Text('Terminal P501',
                            style: Theme.of(context).textTheme.headlineSmall),
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          'Saisissez le numéro de série du terminal P501 '
                          'utilisé pour cette tournée — il figure au dos '
                          'de l’appareil.',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        Text('Numéro de série',
                            style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: AppSpacing.sm),
                        TextFormField(
                          controller: _serialController,
                          textCapitalization: TextCapitalization.characters,
                          autofillHints: const <String>[],
                          decoration:
                              const InputDecoration(hintText: 'P501-000123'),
                          validator: _validateSerial,
                          enabled: !isLinking,
                          onFieldSubmitted: (_) => _submit(),
                        ),
                        if (state case TerminalUnknown(:final message))
                          _ErrorBanner(message),
                        if (state case TerminalNotUsable(:final message))
                          _ErrorBanner(message),
                        if (state case TerminalError(:final message))
                          _ErrorBanner(message),
                        const SizedBox(height: AppSpacing.md),
                        KoraButton(
                          label: 'Lier ce terminal',
                          isLoading: isLinking,
                          onPressed: _submit,
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        KoraButton(
                          label: 'Se déconnecter',
                          variant: KoraButtonVariant.ghost,
                          onPressed: isLinking
                              ? null
                              : () => context.read<AuthCubit>().logout(),
                        ),
                      ],
                    ),
                  );
                },
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner(this.message);

  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.sm),
      child: Text(
        message,
        style: Theme.of(context)
            .textTheme
            .bodySmall
            ?.copyWith(color: Theme.of(context).colorScheme.error),
      ),
    );
  }
}
