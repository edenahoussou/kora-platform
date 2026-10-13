import 'package:flutter/material.dart';

import '../di/injector.dart';
import 'app_update.dart';

/// Demande à Google Play, une fois par lancement, s'il y a une mise à
/// jour à installer.
///
/// Ce widget ne dessine rien et n'affiche jamais de message : toute
/// l'interface vient de Play, qui sait déjà le faire dans la langue du
/// téléphone. Il se contente de poser la question après la première
/// image, pour ne pas retarder l'affichage.
class UpdateGate extends StatefulWidget {
  const UpdateGate({required this.child, super.key});

  final Widget child;

  @override
  State<UpdateGate> createState() => _UpdateGateState();
}

class _UpdateGateState extends State<UpdateGate> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _check());
  }

  Future<void> _check() async {
    try {
      await getIt<AppUpdate>().run();
    } on Exception {
      // Rien de ce qui touche à la mise à jour ne doit empêcher
      // d'utiliser l'application.
    }
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
