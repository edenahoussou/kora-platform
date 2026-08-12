import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

/// Écran de connexion.
///
/// **Placeholder** : la feature `auth` (formulaire, appel API, écriture
/// du token dans `FlutterSecureStorage`) n'est pas encore écrite — voir
/// GUIDE.md §13. En attendant, cette page existe pour que le guard de
/// navigation ([AuthGuard], voir `core/router/app_router.dart`) ait une
/// destination valide vers laquelle rediriger un utilisateur non connecté.
///
/// À remplacer en suivant le tutoriel GUIDE.md §9 ("Créer une nouvelle
/// feature"), en gardant la même signature de classe (`LoginPage`) pour
/// que `app_router.dart` continue de fonctionner sans modification.
class LoginPage extends StatelessWidget {
  const LoginPage({super.key});

  @override
  Widget build(BuildContext context) {
    return const PlaceholderPage(
      title: 'Connexion',
      message: 'Le formulaire de connexion arrivera ici.',
    );
  }
}
