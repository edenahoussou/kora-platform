import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/screen_header.dart';
import '../bloc/auth_flow_bloc.dart';
import '../widgets/identifier_input.dart';

/// Écran "PIN oublié" — envoie un code de vérification à l'identifiant
/// (email ou téléphone) saisi, première étape du parcours `pin_reset`
/// (identifiant → OTP → nouveau PIN). Route : `/login/forgot-password`.
class ForgotPasswordPage extends StatefulWidget {
  const ForgotPasswordPage({super.key});

  @override
  State<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends State<ForgotPasswordPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final IdentifierInputController _controller = IdentifierInputController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _submit(BuildContext context) async {
    if (!_formKey.currentState!.validate()) return;

    final AuthFlowBloc bloc = context.read<AuthFlowBloc>();
    bloc.add(AuthFlowPinResetRequested(_controller.resolved));

    // AuthFlowPinResetRequested est asynchrone côté bloc (appel réseau) ;
    // on attend le prochain état stable avant de décider où naviguer.
    final AuthFlowState next = await bloc.stream
        .firstWhere((AuthFlowState state) => !state.isSubmitting);

    if (!context.mounted) return;

    if (next.hasError) {
      await KoraDialogs.error(
        context,
        title: 'Échec de l’envoi',
        description: next.errorMessage,
      );
      return;
    }

    await context.push(AppRoutes.loginOtp);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const ScreenHeader(eyebrow: 'Connexion', title: 'PIN oublié'),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: Responsive.value<double>(context,
                  mobile: double.infinity, tablet: 480),
            ),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Text(
                      "Saisissez l'email ou le numéro de téléphone associé "
                      'à votre compte KORA. Nous vous enverrons un code '
                      'pour choisir un nouveau PIN.',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    IdentifierInput(controller: _controller),
                    const SizedBox(height: AppSpacing.lg),
                    BlocBuilder<AuthFlowBloc, AuthFlowState>(
                      builder: (BuildContext context, AuthFlowState state) =>
                          KoraButton(
                        label: 'Envoyer le code',
                        isLoading: state.isSubmitting,
                        onPressed: () => _submit(context),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
