import 'package:flutter/material.dart';

import 'brand_mini.dart';
import 'screen_header.dart';

/// Un onglet réduit à son en-tête, pour une fonctionnalité pas encore
/// écrite.
///
/// **Ce qui existe déjà n'est pas supprimé** : `ColisPage` et `WalletPage`
/// gardent leurs écrans complets, dessinés d'après la maquette, et
/// reprendront leur place dans le routeur le jour où la feature aura une
/// source de données. En attendant, montrer une interface qui ne répond à
/// rien — un suivi d'envoi qui ne suit rien, un solde inventé — promet ce
/// que l'app ne tient pas.
///
/// L'en-tête, lui, reste : il dit où l'on est. Sans lui, l'onglet
/// ressemblait à un écran qui n'a pas fini de charger.
class ComingSoonPage extends StatelessWidget {
  const ComingSoonPage({super.key, required this.eyebrow, required this.title});

  final String eyebrow;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Même en-tête que l'écran qu'il remplace : la marque à
              // gauche, le surtitre et le titre de l'onglet. Sans action
              // à droite, en revanche — les boutons de ces deux écrans
              // n'ouvrent rien qui existe.
              ScreenHeader(
                showBack: false,
                leading: const BrandMini(),
                eyebrow: eyebrow,
                title: title,
              ),
              const Expanded(child: SizedBox.expand()),
            ],
          ),
        ),
      ),
    );
  }
}
