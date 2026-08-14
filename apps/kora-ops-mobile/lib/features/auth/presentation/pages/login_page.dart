import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/widgets/app_button.dart';
import '../../../../core/widgets/app_text_field.dart';
import '../../../../injection/injection.dart';
import '../cubit/auth_cubit.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final TextEditingController _usernameController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();

  @override
  void dispose() {
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Connexion agent')),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            // Layout adaptatif : formulaire plein-largeur sur téléphone,
            // colonne resserrée sur tablette/desktop.
            constraints: BoxConstraints(
              maxWidth: Responsive.value<double>(context, mobile: double.infinity, tablet: 480),
            ),
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: BlocConsumer<AuthCubit, AuthState>(
                listener: (BuildContext context, AuthState state) {
                  if (state case AuthFailure(:final String message)) {
                    getIt.dialogService.showError(context, message: message);
                  }
                  if (state is AuthAuthenticated) {
                    context.go('/home');
                  }
                },
                builder: (BuildContext context, AuthState state) {
                  final bool isLoading = state is AuthLoading;
                  return Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      AppTextField(label: 'Identifiant', controller: _usernameController),
                      const SizedBox(height: AppSpacing.md),
                      AppTextField(
                        label: 'Mot de passe',
                        controller: _passwordController,
                        obscureText: true,
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      AppButton(
                        label: 'Se connecter',
                        isLoading: isLoading,
                        onPressed: () => context.read<AuthCubit>().login(
                              username: _usernameController.text,
                              password: _passwordController.text,
                            ),
                      ),
                    ],
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
