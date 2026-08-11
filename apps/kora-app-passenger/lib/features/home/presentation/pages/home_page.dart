import 'package:flutter/material.dart';

/// Onglet "Accueil" de la bottom nav.
///
/// **Contenu volontairement laissé au compteur Flutter par défaut**
/// (celui généré par `flutter create`), à la demande explicite du client
/// — le temps que le vrai écran d'accueil KORA (résumé des trajets, accès
/// rapide Bus/Ride, etc.) soit conçu. Remplace le contenu de ce fichier
/// quand tu seras prêt, en gardant la même signature de classe
/// (`HomePage`) pour que `core/router/app_router.dart` continue de
/// fonctionner sans modification.
class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  int _counter = 0;

  void _incrementCounter() {
    setState(() {
      _counter++;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
        title: const Text('Accueil'),
      ),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Text('You have pushed the button this many times:'),
            Text(
              '$_counter',
              style: Theme.of(context).textTheme.headlineMedium,
            ),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _incrementCounter,
        tooltip: 'Increment',
        child: const Icon(Icons.add),
      ),
    );
  }
}
