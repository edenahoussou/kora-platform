import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/screen_header.dart';
import '../bloc/auth_flow_bloc.dart';
import '../widgets/identifier_input.dart';

/// Écran "Se connecter" — première ouverture de l'app sur cet appareil,
/// ou lien "pas moi" depuis l'écran PIN.
///
/// Le contrat réel de l'API est identifiant (email ou téléphone) + PIN.
/// Cet écran ne fait aucun appel réseau — il mémorise juste l'identifiant
/// et passe à l'écran PIN, qui est celui qui appelle réellement `login`.
/// Sur un appareil déjà reconnu, cet écran n'est jamais affiché — l'app
/// démarre directement sur le PIN (voir `AuthGuard`).
///
/// Anciennement `LoginEmailPage` (email uniquement) — voir
/// `login_email_page_old.dart`, conservé pour référence.
class LoginIdentifierPage extends StatefulWidget {
  const LoginIdentifierPage({super.key});

  @override
  State<LoginIdentifierPage> createState() => _LoginIdentifierPageState();
}

class _LoginIdentifierPageState extends State<LoginIdentifierPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final IdentifierInputController _controller = IdentifierInputController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _submit(BuildContext context) {
    if (!_formKey.currentState!.validate()) return;
    context
        .read<AuthFlowBloc>()
        .add(AuthFlowIdentifierEntered(_controller.resolved));
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
                      'Nouvel appareil détecté. Saisissez votre email ou '
                      'votre numéro de téléphone KORA pour continuer.',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    IdentifierInput(controller: _controller),
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
