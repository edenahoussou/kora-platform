import 'package:flutter/material.dart';

/// Wrapper commun à toutes les stories : centre le contenu dans un
/// `Scaffold` avec un padding standard et autorise le scroll — utile
/// pour les tokens et les composants métier qui dépassent la hauteur
/// visible sur les petits viewports.
class StoryScaffold extends StatelessWidget {
  const StoryScaffold({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(24),
  });

  final Widget child;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(padding: padding, child: child),
      ),
    );
  }
}
