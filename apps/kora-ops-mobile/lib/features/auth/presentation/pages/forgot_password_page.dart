import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/screen_header.dart';
import '../cubit/login_cubit.dart';

/// Écran "Mot de passe oublié" — envoie (simule) un lien de
/// réinitialisation à l'email saisi. Route : `/login/forgot-password`.
class ForgotPasswordPage extends StatefulWidget {
  const ForgotPasswordPage({super.key});

  @override
  State<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends State<ForgotPasswordPage> {
  final TextEditingController _emailController = TextEditingController();
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  String? _validateEmail(String? value) {
    final String email = value?.trim() ?? '';
    if (email.isEmpty) return 'Adresse email requise.';
    final bool isValid = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email);
    return isValid ? null : 'Adresse email invalide.';
  }

  Future<void> _submit(BuildContext context) async {
    if (!_formKey.currentState!.validate()) return;
    final LoginCubit loginCubit = context.read<LoginCubit>();
    final bool success =
        await loginCubit.submitForgotPassword(_emailController.text.trim());
    if (!context.mounted) return;

    if (success) {
      await KoraDialogs.success(
        context,
        title: 'Email envoyé',
        description:
            'Si un compte existe pour cet email, un lien de réinitialisation vient '
            'd\'être envoyé.',
      );
      if (context.mounted) context.pop();
    } else {
      final String message =
          loginCubit.state.errorMessage ?? 'Erreur inattendue.';
      await KoraDialogs.error(context,
          title: 'Échec de l\'envoi', description: message);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const ScreenHeader(
          eyebrow: 'Connexion', title: 'Mot de passe oublié'),
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
                      'Saisissez l\'adresse email associée à votre compte KORA. Nous vous '
                      'enverrons un lien pour réinitialiser votre mot de passe.',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Text('Adresse email',
                        style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: AppSpacing.sm),
                    TextFormField(
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      autofillHints: const <String>[AutofillHints.email],
                      decoration: const InputDecoration(
                          hintText: 'nom.prenom@kora-express.com'),
                      validator: _validateEmail,
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    BlocBuilder<LoginCubit, LoginState>(
                      builder: (BuildContext context, LoginState state) =>
                          KoraButton(
                        label: 'Envoyer le lien',
                        isLoading: state.status == LoginStatus.submitting,
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
