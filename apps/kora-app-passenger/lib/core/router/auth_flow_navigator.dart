import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/bloc/auth_bloc.dart';
import '../../features/auth/presentation/bloc/auth_flow_bloc.dart';
import 'app_router.dart';

/// Traduit l'étape du parcours d'authentification en navigation.
///
/// Les écrans se contentent d'envoyer des événements ; c'est ici, en un
/// seul endroit, qu'on décide où ils mènent. Sans ça, chaque écran
/// devrait connaître le suivant — or l'écran de code peut mener au choix
/// d'un PIN, à l'accueil ou nulle part, selon le motif de l'OTP.
class AuthFlowNavigator extends StatelessWidget {
  const AuthFlowNavigator({super.key, required this.child});

  final Widget child;

  /// L'écran actuellement affiché, tel que le routeur le connaît.
  ///
  /// Le **dernier** écran de la pile, et non l'adresse courante : un
  /// écran ouvert par `push` ne change pas l'adresse, qui resterait
  /// celle du formulaire alors que le code est déjà à l'écran.
  static String _location(BuildContext context) => GoRouter.of(
    context,
  ).routerDelegate.currentConfiguration.last.matchedLocation;

  @override
  Widget build(BuildContext context) {
    return BlocListener<AuthFlowBloc, AuthFlowState>(
      listenWhen: (previous, current) =>
          previous.step != current.step ||
          // **Un second code demandé depuis le début du parcours.**
          // L'étape ne change pas — elle vaut déjà \`code\` — et la
          // navigation ne partait donc jamais : revenu en arrière sans
          // saisir le premier code, le voyageur retapait un autre numéro,
          // touchait « Recevoir mon code » et restait sur le formulaire,
          // le code parti sans écran pour le saisir. C'est le défi qui
          // dit qu'il y a bien un nouvel écran à ouvrir.
          (current.step == AuthFlowStep.code &&
              previous.challenge?.uuid != current.challenge?.uuid),
      listener: (context, flow) {
        switch (flow.step) {
          case AuthFlowStep.identifier:
            // Retour au début : après un PIN réinitialisé, on se
            // reconnecte avec le nouveau code.
            context.go(AppRouter.loginPath, extra: flow.identifier);
          case AuthFlowStep.newDevice:
            context.push(AppRouter.newDevicePath);
          case AuthFlowStep.code:
            // Déjà sur l'écran du code : « Je n'ai pas reçu le code » y
            // redemande un défi, et l'empiler une seconde fois mettrait
            // deux écrans identiques l'un sur l'autre.
            if (_location(context) == AppRouter.otpPath) return;

            context.push(AppRouter.otpPath);
          case AuthFlowStep.pin:
            context.push(AppRouter.createPinPath);
          case AuthFlowStep.done:
            final session = flow.session;
            if (session == null) return;

            // La session ouverte passe au bloc qui la porte pour toute
            // l'app ; le garde de navigation s'appuie sur lui.
            context.read<AuthBloc>().add(AuthSessionOpened(session));

            // Une inscription se termine par les autorisations, où le
            // nouveau venu donne son nom et son téléphone. Une connexion
            // — même sur un appareil inconnu — va droit à l'accueil : le
            // profil est déjà rempli.
            //
            // On lit `justRegistered`, pas `purpose` : ce dernier vaut
            // `registration` par défaut et une connexion ordinaire ne le
            // change jamais, ce qui renvoyait tout le monde aux
            // autorisations à chaque fois.
            context.go(
              flow.justRegistered ? AppRouter.permissionsPath : '/home',
            );
        }
      },
      child: child,
    );
  }
}
