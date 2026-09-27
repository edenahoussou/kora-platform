import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/screen_header.dart';
import '../bloc/auth_flow_bloc.dart';

/// Écran "Se connecter" — première ouverture de l'app sur cet appareil,
/// ou lien "pas moi" depuis l'écran PIN.
///
/// Contrairement à la version précédente (mock), il n'y a plus de mot de
/// passe : le contrat réel de l'API est email + PIN. Cet écran ne fait
/// aucun appel réseau — il mémorise juste l'adresse et passe à l'écran
/// PIN, qui est celui qui appelle réellement `login`. Sur un appareil déjà
/// reconnu, cet écran n'est jamais affiché — l'app démarre directement sur
/// le PIN (voir `AuthGuard`).
class LoginEmailPage extends StatefulWidget {
  const LoginEmailPage({super.key});

  @override
  State<LoginEmailPage> createState() => _LoginEmailPageState();
}

class _LoginEmailPageState extends State<LoginEmailPage> {
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

  void _submit(BuildContext context) {
    if (!_formKey.currentState!.validate()) return;
    context
        .read<AuthFlowBloc>()
        .add(AuthFlowEmailEntered(_emailController.text.trim()));
    context.push(AppRoutes.loginPin);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const ScreenHeader(eyebrow: 'Connexion', title: 'Se connecter'),
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
                      'Nouvel appareil détecté. Saisissez votre adresse email '
                      'KORA pour continuer.',
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
                      onFieldSubmitted: (_) => _submit(context),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    KoraButton(
                      label: 'Continuer',
                      onPressed: () => _submit(context),
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
