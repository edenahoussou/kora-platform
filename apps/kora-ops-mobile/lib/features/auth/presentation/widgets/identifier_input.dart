import 'package:flutter/material.dart';
import 'package:phone_form_field/phone_form_field.dart';

/// Email ou numéro de téléphone : les deux façons de s'identifier auprès
/// de l'API (voir migration backend du 25/09/2026, colonne `identifier`
/// de `auth_otp_challenges`).
enum IdentifierKind { email, phone }

/// Porte l'état du champ identifiant (bascule email/téléphone + les deux
/// valeurs saisies) indépendamment du widget qui l'affiche, pour que
/// `LoginIdentifierPage` et `ForgotPasswordPage` partagent exactement la
/// même logique de saisie/validation sans dupliquer le widget.
class IdentifierInputController {
  IdentifierInputController({IdentifierKind initialKind = IdentifierKind.email})
      : kind = ValueNotifier<IdentifierKind>(initialKind);

  final ValueNotifier<IdentifierKind> kind;
  final TextEditingController emailController = TextEditingController();

  /// Bénin (+229) par défaut — marché principal de KORA EXPRESS. Mis à
  /// jour à chaque frappe via [PhoneFormField.onChanged] ; sert aussi de
  /// valeur de reprise quand l'utilisateur bascule vers l'email puis
  /// revient sur téléphone (le champ est recréé, pas juste masqué).
  PhoneNumber phoneNumber = PhoneNumber.parse('+229');

  void dispose() {
    kind.dispose();
    emailController.dispose();
  }

  /// L'identifiant normalisé prêt à être envoyé à l'API : email en
  /// minuscules et sans espaces, ou téléphone en E.164 strict (sans
  /// espaces) — voir `AuthIdentifierResolver::isValidPhone` côté backend,
  /// qui exige uniquement un `+` suivi de chiffres.
  String get resolved {
    if (kind.value == IdentifierKind.email) {
      return emailController.text.trim().toLowerCase();
    }
    return '+${phoneNumber.countryCode}${phoneNumber.nsn}';
  }
}

String? _validateEmail(String? value) {
  final String email = value?.trim() ?? '';
  if (email.isEmpty) return 'Adresse email requise.';
  final bool isValid = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email);
  return isValid ? null : 'Adresse email invalide.';
}

/// Validateur maison plutôt que `PhoneValidator.compose/required/valid` :
/// leur signature a changé plusieurs fois d'une version à l'autre du
/// package (certaines exigent un `BuildContext` positionnel). `isValid()`
/// de `phone_numbers_parser`, lui, est stable depuis longtemps.
String? _validatePhone(PhoneNumber? number) {
  if (number == null || number.nsn.isEmpty) {
    return 'Numéro de téléphone requis.';
  }
  if (!number.isValid()) {
    return 'Numéro de téléphone invalide.';
  }
  return null;
}

/// Le champ à afficher dans un `Form` : bascule Email / Téléphone, avec la
/// saisie adaptée à chacun. Le `Form` parent se valide normalement
/// (`formKey.currentState!.validate()`) — les deux modes sont des
/// `FormField` standard ; seul celui affiché à l'instant est enregistré
/// dans le `Form`, donc seul celui-là est validé.
class IdentifierInput extends StatelessWidget {
  const IdentifierInput({
    super.key,
    required this.controller,
    this.autofocus = false,
  });

  final IdentifierInputController controller;
  final bool autofocus;

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<IdentifierKind>(
      valueListenable: controller.kind,
      builder: (BuildContext context, IdentifierKind kind, _) {
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            SegmentedButton<IdentifierKind>(
              segments: const <ButtonSegment<IdentifierKind>>[
                ButtonSegment<IdentifierKind>(
                  value: IdentifierKind.email,
                  label: Text('Email'),
                  icon: Icon(Icons.email_outlined),
                ),
                ButtonSegment<IdentifierKind>(
                  value: IdentifierKind.phone,
                  label: Text('Téléphone'),
                  icon: Icon(Icons.phone_outlined),
                ),
              ],
              selected: <IdentifierKind>{kind},
              onSelectionChanged: (Set<IdentifierKind> selection) =>
                  controller.kind.value = selection.first,
            ),
            const SizedBox(height: 16),
            if (kind == IdentifierKind.email)
              TextFormField(
                controller: controller.emailController,
                keyboardType: TextInputType.emailAddress,
                autofillHints: const <String>[AutofillHints.email],
                autofocus: autofocus,
                decoration: const InputDecoration(
                    hintText: 'nom.prenom@kora-express.com'),
                validator: _validateEmail,
              )
            else
              PhoneFormField(
                initialValue: controller.phoneNumber,
                onChanged: (PhoneNumber number) =>
                    controller.phoneNumber = number,
                autofocus: autofocus,
                autofillHints: const <String>[AutofillHints.telephoneNumber],
                countrySelectorNavigator:
                    const CountrySelectorNavigator.bottomSheet(),
                validator: _validatePhone,
                decoration: const InputDecoration(hintText: '61 23 45 67'),
              ),
          ],
        );
      },
    );
  }
}
